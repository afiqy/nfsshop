<?php

namespace Tests\Feature;

use App\Mail\ConfirmEmailChange;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->artisan('migrate:fresh');
        RefreshDatabaseState::$migrated = false;
    }

    public function test_profile_edit_saves_identity_preferences_and_private_avatar(): void
    {
        Storage::fake('profile_avatars');
        $user = User::factory()->create(['name' => 'Original Name', 'phone' => null])->fresh();
        $other = User::factory()->create()->fresh();
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('Original');
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/aN8AAAAASUVORK5CYII=');
        $data = ['first_name' => 'New', 'last_name' => 'Name', 'phone' => '12345', 'preferred_language' => 'ms',
            'timezone' => 'Asia/Kuala_Lumpur', 'preferred_contact_method' => 'whatsapp', 'bio' => 'My bio',
            'notification_preferences' => [
                UserProfile::EMAIL_APPOINTMENTS => '0',
                UserProfile::EMAIL_INVOICES => '1',
                UserProfile::EMAIL_PAYMENT_REMINDERS => '0',
            ],
            'avatar' => UploadedFile::fake()->createWithContent('avatar.png', $image)];
        $this->put(route('profile.update'), $data)->assertRedirect(route('profile.index'))->assertSessionHasNoErrors();
        $profile = UserProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('New Name', $user->fresh()->name);
        $this->assertSame('12345', $user->fresh()->phone);
        $this->assertSame('ms', $profile->preferred_language);
        $this->assertSame('Asia/Kuala_Lumpur', $profile->timezone);
        $this->assertSame('whatsapp', $profile->preferred_contact_method);
        $this->assertFalse($profile->allowsEmail(UserProfile::EMAIL_APPOINTMENTS));
        $this->assertTrue($profile->allowsEmail(UserProfile::EMAIL_INVOICES));
        $this->assertFalse($profile->allowsEmail(UserProfile::EMAIL_PAYMENT_REMINDERS));
        Storage::disk('profile_avatars')->assertExists($profile->avatar);
        $oldPath = $profile->avatar;
        $this->get(route('profile.index'))->assertOk()->assertSee('New')->assertSee(route('profile.avatar'))
            ->assertSee('lang="ms"', false)->assertSee('Profil Pengguna')->assertSee('Pemberitahuan e-mel');
        $this->get(route('profile.avatar'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($other)->get(route('profile.avatar'))->assertNotFound();
        $this->actingAs($user->fresh())->put(route('profile.update'), array_replace($data, [
            'avatar' => UploadedFile::fake()->createWithContent('other.png', $image),
        ]))->assertSessionHasNoErrors();
        Storage::disk('profile_avatars')->assertMissing($oldPath);
        $newPath = $profile->fresh()->avatar;
        unset($data['avatar']);
        $this->put(route('profile.update'), $data + ['remove_avatar' => 1])->assertSessionHasNoErrors();
        Storage::disk('profile_avatars')->assertMissing($newPath);
        $this->get(route('profile.avatar'))->assertNotFound();
        $legacy = 'avatars/'.str_repeat('a', 40).'.png';
        Storage::disk('profile_avatars')->put('public/'.$legacy, $image);
        $profile->fresh()->update(['avatar' => $legacy]);
        $this->get(route('profile.avatar'))->assertOk();
        $this->put(route('profile.update'), $data + ['remove_avatar' => 1])->assertSessionHasNoErrors();
        Storage::disk('profile_avatars')->assertMissing('public/'.$legacy);
    }

    public function test_workshop_admin_can_upload_replace_and_remove_a_private_workshop_logo(): void
    {
        Storage::fake('workshop_assets');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $workshop = Tenant::create([
            'id' => 'profile-logo-'.Str::lower(Str::random(12)),
            'account_owner_id' => $admin->id,
            'workshop_name' => 'Logo Workshop',
        ]);
        $admin->update(['tenant_id' => $workshop->id]);
        $data = [
            'first_name' => 'Workshop',
            'last_name' => 'Owner',
            'preferred_language' => 'en',
            'timezone' => 'UTC',
        ];
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/aN8AAAAASUVORK5CYII=');

        $this->actingAs($admin)->put(route('profile.update'), $data + [
            'workshop_logo' => UploadedFile::fake()->createWithContent('logo.png', $image),
        ])->assertRedirect(route('profile.index'))->assertSessionHasNoErrors();
        $path = $workshop->fresh()->workshop_logo;
        Storage::disk('workshop_assets')->assertExists($path);
        $this->assertSame('Logo Workshop', $workshop->fresh()->workshop_name);
        $this->get(route('profile.workshop-logo'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs(User::factory()->create())->get(route('profile.workshop-logo'))->assertNotFound();

        $this->actingAs($admin)->put(route('profile.update'), $data + [
            'workshop_logo' => UploadedFile::fake()->createWithContent('replacement.png', $image),
        ])->assertSessionHasNoErrors();
        Storage::disk('workshop_assets')->assertMissing($path);
        $replacement = $workshop->fresh()->workshop_logo;
        Storage::disk('workshop_assets')->assertExists($replacement);

        $this->put(route('profile.update'), $data + ['remove_workshop_logo' => 1])->assertSessionHasNoErrors();
        Storage::disk('workshop_assets')->assertMissing($replacement);
        $this->get(route('profile.workshop-logo'))->assertNotFound();
        $this->assertSame('Logo Workshop', $workshop->fresh()->workshop_name);
    }

    public function test_language_and_timezone_preferences_apply_to_profile_ui_and_device_activity(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id,
            'preferred_language' => 'ms',
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);
        UserSession::create([
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test browser',
            'device_name' => 'Test device',
            'last_activity' => '2026-01-15 12:00:00',
            'managed' => true,
            'credential_version' => $user->credential_version,
        ]);

        $this->actingAs($user)->get(route('devices.index'))
            ->assertOk()
            ->assertSee('Sesi peranti')
            ->assertSee('lang="ms"', false)
            ->assertSee('15 Jan 2026 20:00')
            ->assertSee('Asia/Kuala_Lumpur');
    }

    public function test_profile_rejects_invalid_values_without_changing_user(): void
    {
        $user = User::factory()->create(['name' => 'Original'])->fresh();
        $this->actingAs($user)->put(route('profile.update'), [
            'first_name' => '', 'last_name' => '', 'phone' => str_repeat('1', 256),
            'preferred_language' => 'xx', 'timezone' => 'Not/A_Timezone',
            'preferred_contact_method' => 'telegram',
            'avatar' => UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        ])->assertSessionHasErrors(['first_name', 'phone', 'preferred_language', 'timezone', 'preferred_contact_method', 'avatar']);
        $this->assertSame('Original', $user->fresh()->name);
    }

    public function test_email_change_requires_password_and_verified_single_use_link(): void
    {
        Mail::fake();
        config(['app.url' => 'https://workshop.example.test']);
        $user = User::factory()->create(['email' => 'old@example.test', 'password' => Hash::make('password123')])->fresh();
        $other = User::factory()->create()->fresh();
        $oldReset = Password::broker()->createToken($user);
        $this->actingAs($user)->post(route('profile.email.request'), ['new_email' => 'NEW@example.test', 'current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');
        Mail::assertNothingSent();
        $this->post(route('profile.email.request'), ['new_email' => 'NEW@example.test', 'current_password' => 'password123'])
            ->assertSessionHasNoErrors();
        $mail = Mail::sent(ConfirmEmailChange::class)->sole();
        $this->assertTrue($mail->hasTo('new@example.test'));
        $this->assertStringStartsWith('https://workshop.example.test/', $mail->confirmationUrl);
        $token = basename(parse_url($mail->confirmationUrl, PHP_URL_PATH));
        $pending = DB::table('pending_email_changes')->where('user_id', $user->id)->first();
        $this->assertNotSame($token, $pending->token_hash);
        $this->assertSame('old@example.test', $user->fresh()->email);
        $this->actingAs($other)->post(route('profile.email.confirm.submit'), ['token' => $token])->assertSessionHasErrors('token');
        $this->actingAs($user)->get($mail->confirmationUrl)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->post(route('profile.email.confirm.submit'), ['token' => $token])->assertRedirect(route('profile.index'))->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertSame('new@example.test', $user->email);
        $this->assertEquals(1, $user->email_verified);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse(Password::broker()->tokenExists($user, $oldReset));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'old@example.test']);
        $this->post(route('profile.email.confirm.submit'), ['token' => $token])->assertSessionHasErrors('token');
    }

    public function test_expired_replaced_or_security_changed_email_links_are_rejected(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => Hash::make('password123')])->fresh();
        $this->actingAs($user);
        $payload = ['new_email' => 'first@example.test', 'current_password' => 'password123'];
        $this->post(route('profile.email.request'), $payload)->assertSessionHasNoErrors();
        $first = basename(parse_url(Mail::sent(ConfirmEmailChange::class)->last()->confirmationUrl, PHP_URL_PATH));
        $payload['new_email'] = 'second@example.test';
        $this->post(route('profile.email.request'), $payload)->assertSessionHasNoErrors();
        $second = basename(parse_url(Mail::sent(ConfirmEmailChange::class)->last()->confirmationUrl, PHP_URL_PATH));
        $this->post(route('profile.email.confirm.submit'), ['token' => $first])->assertSessionHasErrors('token');
        $this->travel(61)->minutes();
        $this->withSession(['last_activity' => now()])->post(route('profile.email.confirm.submit'), ['token' => $second])->assertSessionHasErrors('token');
        $this->travelBack();
        $this->withSession(['last_activity' => now()])->post(route('profile.email.request'), $payload)->assertSessionHasNoErrors();
        $third = basename(parse_url(Mail::sent(ConfirmEmailChange::class)->last()->confirmationUrl, PHP_URL_PATH));
        User::factory()->create(['email' => 'second@example.test']);
        $this->post(route('profile.email.confirm.submit'), ['token' => $third])->assertSessionHasErrors('token');
        $payload['new_email'] = 'third@example.test';
        $this->post(route('profile.email.request'), $payload)->assertSessionHasNoErrors();
        $fourth = basename(parse_url(Mail::sent(ConfirmEmailChange::class)->last()->confirmationUrl, PHP_URL_PATH));
        DB::table('pending_email_changes')->where('user_id', $user->id)->update(['credential_version' => 999]);
        $this->post(route('profile.email.confirm.submit'), ['token' => $fourth])->assertSessionHasErrors('token');
        $this->assertNotSame('second@example.test', $user->fresh()->email);
    }

    public function test_email_delivery_failure_is_reported_without_creating_a_pending_change(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')])->fresh();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('private transport details'));
        Log::shouldReceive('error')->once()->with('Email change verification delivery failed.', ['exception_class' => \RuntimeException::class]);
        $this->actingAs($user)->post(route('profile.email.request'), ['new_email' => 'new@example.test', 'current_password' => 'password123'])
            ->assertSessionHasErrors('new_email');
        $this->assertDatabaseMissing('pending_email_changes', ['user_id' => $user->id]);
    }
}
