<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use App\Models\User;
use App\Services\PasswordCredentials;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
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

        return view('profile.index', compact('user', 'profile'));
    }

    public function editProfile()
    {
        $user = auth()->user();
        $profile = $this->profileFor($user);

        return view('profile.edit', compact('user', 'profile'));
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:255'],
            'preferred_language' => ['required', Rule::in(['en', 'ms'])],
            'timezone' => ['required', 'timezone'],
            'preferred_contact_method' => ['nullable', Rule::in(['email', 'sms', 'whatsapp'])],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'bio' => ['nullable', 'string', 'max:2000'],
        ]);
        $newAvatar = null;
        if ($request->hasFile('avatar')) {
            $newAvatar = $request->file('avatar')->store('profile-avatars', 'profile_avatars');
            if ($newAvatar === false) {
                Log::error('Profile avatar upload failed.', ['user_id' => $request->user()->id]);
                throw ValidationException::withMessages(['avatar' => 'Avatar upload failed. Please try again.']);
            }
        }
        try {
            $oldAvatar = DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($request, $data, $newAvatar) {
                $user = User::on(config('tenancy.database.central_connection'))->lockForUpdate()->findOrFail($request->user()->id);
                $profile = $this->profileFor($user);
                $oldAvatar = $profile->avatar;
                $user->update([
                    'name' => trim($data['first_name'].' '.($data['last_name'] ?? '')),
                    'phone' => $data['phone'] ?? null,
                ]);
                $profile->fill([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'] ?? '',
                    'preferred_language' => $data['preferred_language'],
                    'timezone' => $data['timezone'],
                    'preferred_contact_method' => $data['preferred_contact_method'] ?? null,
                    'bio' => $data['bio'] ?? null,
                    'avatar' => $newAvatar ?? ($request->boolean('remove_avatar') ? null : $oldAvatar),
                ])->save();

                return $oldAvatar;
            });
        } catch (Throwable $exception) {
            if ($newAvatar) {
                $this->deleteAvatar($newAvatar);
            }
            throw $exception;
        }
        if (($newAvatar || $request->boolean('remove_avatar')) && $oldAvatar) {
            $this->deleteAvatar($oldAvatar);
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