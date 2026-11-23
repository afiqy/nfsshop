<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\PasswordCredentials;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class UserController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $profile = $this->profileFor($user);
        $workshop = $this->managedWorkshop($user);
        $identities = $user->socialIdentities()->orderBy('provider')->get();
        $ssoProviders = SsoController::configuredProviders();

        return view('profile.index', compact('user', 'profile', 'workshop', 'identities', 'ssoProviders'));
    }

    public function editProfile()
    {
        $user = auth()->user();
        $profile = $this->profileFor($user);
        $workshop = $this->managedWorkshop($user);

        return view('profile.edit', compact('user', 'profile', 'workshop'));
    }

    public function updateProfile(Request $request)
    {
        $workshop = $this->managedWorkshop($request->user());
        $logoChangeRequested = $request->hasFile('workshop_logo') || $request->boolean('remove_workshop_logo');
        abort_unless(! $logoChangeRequested || $workshop, 403);
        abort_unless(! $request->has('notification_preferences')
            || $request->user()->role === User::ROLE_CLIENT, 403);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:255'],
            'preferred_language' => ['required', Rule::in(['en', 'ms'])],
            'timezone' => ['required', 'timezone'],
            'preferred_contact_method' => ['nullable', Rule::in(['email', 'sms', 'whatsapp'])],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'notification_preferences' => ['sometimes', 'array'],
            'notification_preferences.appointment_emails' => ['required_with:notification_preferences', 'boolean'],
            'notification_preferences.invoice_emails' => ['required_with:notification_preferences', 'boolean'],
            'notification_preferences.payment_reminder_emails' => ['required_with:notification_preferences', 'boolean'],
            'workshop_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_workshop_logo' => ['nullable', 'boolean'],
            'bio' => ['nullable', 'string', 'max:2000'],
        ]);
        $newAvatar = null;
        $newLogo = null;
        try {
            if ($request->hasFile('avatar')) {
                $newAvatar = $request->file('avatar')->store('profile-avatars', 'profile_avatars');
                if ($newAvatar === false) {
                    Log::error('Profile avatar upload failed.', ['user_id' => $request->user()->id]);
                    throw ValidationException::withMessages(['avatar' => 'Avatar upload failed. Please try again.']);
                }
            }
            if ($request->hasFile('workshop_logo') && $workshop) {
                $directory = 'workshop-logos/'.hash('sha256', (string) $workshop->getKey());
                $newLogo = $request->file('workshop_logo')->store($directory, 'workshop_assets');
                if ($newLogo === false) {
                    Log::error('Workshop logo upload failed.', ['tenant_id' => $workshop->getKey()]);
                    throw ValidationException::withMessages(['workshop_logo' => 'Workshop logo upload failed. Please try again.']);
                }
            }
            $oldFiles = DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($request, $data, $newAvatar, $newLogo, $workshop) {
                $user = User::on(config('tenancy.database.central_connection'))->lockForUpdate()->findOrFail($request->user()->id);
                $profile = $this->profileFor($user);
                $oldAvatar = $profile->avatar;
                $oldLogo = null;
                $user->update([
                    'name' => trim($data['first_name'].' '.($data['last_name'] ?? '')),
                    'phone' => $data['phone'] ?? null,
                ]);
                $preferences = $profile->notification_preferences ?? [];
                foreach (UserProfile::EMAIL_PREFERENCES as $preference) {
                    if (isset($data['notification_preferences'][$preference])) {
                        $preferences[$preference] = filter_var(
                            $data['notification_preferences'][$preference],
                            FILTER_VALIDATE_BOOLEAN
                        );
                    }
                }
                $profile->fill([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'] ?? '',
                    'preferred_language' => $data['preferred_language'],
                    'timezone' => $data['timezone'],
                    'preferred_contact_method' => $data['preferred_contact_method'] ?? null,
                    'bio' => $data['bio'] ?? null,
                    'avatar' => $newAvatar ?? ($request->boolean('remove_avatar') ? null : $oldAvatar),
                    'notification_preferences' => $preferences ?: null,
                ])->save();

                if ($workshop && ($newLogo || $request->boolean('remove_workshop_logo'))) {
                    $tenant = Tenant::on(config('tenancy.database.central_connection'))
                        ->lockForUpdate()->findOrFail($workshop->getKey());
                    $oldLogo = $tenant->workshop_logo;
                    $tenant->workshop_logo = $newLogo;
                    $tenant->save();
                }

                return ['avatar' => $oldAvatar, 'logo' => $oldLogo];
            });
        } catch (Throwable $exception) {
            if ($newAvatar) {
                $this->deleteAvatar($newAvatar);
            }
            if ($newLogo) {
                $this->deleteWorkshopLogo($newLogo, $workshop);
            }
            throw $exception;
        }
        if (($newAvatar || $request->boolean('remove_avatar')) && $oldFiles['avatar']) {
            $this->deleteAvatar($oldFiles['avatar']);
        }
        if (($newLogo || $request->boolean('remove_workshop_logo')) && $oldFiles['logo']) {
            $this->deleteWorkshopLogo($oldFiles['logo'], $workshop);
        }

        return redirect()->route('profile.index')->with('success', 'Profile updated.');
    }

    public function avatar(Request $request): BinaryFileResponse
    {
        $path = $this->profileFor($request->user())->avatar;
        abort_unless($path && preg_match('/\A(profile-avatars|avatars)\/[a-zA-Z0-9]{40}\.(jpg|jpeg|png|webp)\z/i', $path), 404);
        $disk = Storage::disk('profile_avatars');
        $path = str_starts_with($path, 'avatars/') ? 'public/'.$path : $path;
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function workshopLogo(Request $request): BinaryFileResponse
    {
        $workshop = $this->managedWorkshop($request->user());
        abort_unless($workshop, 404);
        $path = $workshop->workshop_logo;
        abort_unless($this->isWorkshopLogoPath($path, $workshop), 404);
        $disk = Storage::disk('workshop_assets');
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function profileFor(User $user): UserProfile
    {
        $name = explode(' ', $user->name, 2);

        $profile = UserProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['first_name' => $name[0], 'last_name' => $name[1] ?? '', 'preferred_language' => 'en', 'timezone' => 'UTC']
        );
        if (! $profile->first_name) {
            $profile->first_name = $name[0];
            $profile->last_name = $name[1] ?? '';
        }

        return $profile;
    }

    private function deleteAvatar(string $path): void
    {
        if (! preg_match('/\A(profile-avatars|avatars)\/[a-zA-Z0-9]{40}\.(jpg|jpeg|png|webp)\z/i', $path)) {
            return;
        }
        $disk = Storage::disk('profile_avatars');
        $path = str_starts_with($path, 'avatars/') ? 'public/'.$path : $path;
        if (! $disk->delete($path)) {
            Log::warning('Old profile avatar could not be removed.', ['path' => $path]);
        }
    }

    private function managedWorkshop(?User $user): ?Tenant
    {
        if (! $user || $user->role !== User::ROLE_ADMIN || ! $user->tenant_id
            || session()->has('impersonator_id')) {
            return null;
        }

        return Tenant::on(config('tenancy.database.central_connection'))->find($user->tenant_id);
    }

    private function isWorkshopLogoPath(mixed $path, Tenant $workshop): bool
    {
        $directory = preg_quote(hash('sha256', (string) $workshop->getKey()), '/');

        return is_string($path)
            && preg_match('/\Aworkshop-logos\/'.$directory.'\/[a-zA-Z0-9]{40}\.(jpg|jpeg|png|webp)\z/i', $path) === 1;
    }

    private function deleteWorkshopLogo(string $path, ?Tenant $workshop): void
    {
        if (! $workshop || ! $this->isWorkshopLogoPath($path, $workshop)) {
            return;
        }
        if (! Storage::disk('workshop_assets')->delete($path)) {
            Log::warning('Workshop logo could not be removed.', ['tenant_id' => $workshop->getKey()]);
        }
    }

    public function updatePassword(Request $request, PasswordCredentials $credentials)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'confirmed', ...PasswordPolicy::rules()],
        ]);

        $user = DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($request, $data, $credentials) {
            $user = User::on(config('tenancy.database.central_connection'))->lockForUpdate()->findOrFail($request->user()->id);
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Current password is incorrect.']);
            }
            $credentials->updateLocked($user, $data['new_password']);

            return $user;
        });
        Auth::setUser($user);
        $request->session()->put('credential_version', $user->credential_version);
        \App\Models\UserSession::query()->whereKey($request->session()->get('device_session_id'))
            ->where('user_id', $user->id)->update(['credential_version' => $user->credential_version]);

        return back()->with('success', 'Password updated.');
    }
}
