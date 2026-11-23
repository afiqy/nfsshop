<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->artisan('migrate:fresh');
        RefreshDatabaseState::$migrated = false;
    }

    public function test_recovery_is_generic_single_use_and_revokes_old_web_sessions(): void
    {
        Notification::fake();
        config(['app.url' => 'https://workshop.example.test']);
        $user = User::factory()->create(['email' => 'recovery@example.test', 'password' => Hash::make('old-password'), 'remember_token' => 'old-token']);
        $this->get(route('login'))->assertOk()->assertSee(route('password.request'));
        $this->get(route('password.request'))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('Cache-Control', 'no-store, private');
        $known = $this->post(route('password.email'), ['email' => 'RECOVERY@example.test'])->assertSessionHasNoErrors();
        $message = $known->getSession()->get('success');
        $this->post(route('password.email'), ['email' => 'missing@example.test'])->assertSessionHas('success', $message);
        $this->post(route('password.email'), ['email' => 'recovery@example.test'])->assertSessionHas('success', $message);
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        $notification = Notification::sent($user, ResetPassword::class)->first();
        $this->assertStringStartsWith('https://workshop.example.test/reset-password/', $notification->toMail($user)->actionUrl);
        $this->assertNotSame($notification->token, DB::table('password_reset_tokens')->value('token'));
        $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $payload = ['email' => $user->email, 'token' => $notification->token, 'password' => 'new-password', 'password_confirmation' => 'new-password'];
        $this->post(route('password.update'), array_replace($payload, ['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->assertTrue(Password::broker()->tokenExists($user, $notification->token));
        $this->post(route('password.update'), $payload)->assertRedirect(route('login'));
        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertNotSame('old-token', $user->remember_token);
        $this->post(route('password.update'), $payload)->assertSessionHasErrors('email');
        $this->actingAs($user)->withSession(['credential_version' => 0])->get(route('profile.index'))->assertRedirect(route('login'));
    }

    public function test_expired_or_invalid_token_cannot_change_password_and_suspended_account_stays_suspended(): void
    {
        $user = User::factory()->create(['email' => 'blocked@example.test', 'status' => User::STATUS_SUSPENDED, 'password' => Hash::make('old-password')]);
        $token = Password::broker()->createToken($user);
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'new-password', 'password_confirmation' => 'new-password'];
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()->subMinutes(61)]);
        $this->post(route('password.update'), $payload)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
        $payload['token'] = Password::broker()->createToken($user);
        $this->post(route('password.update'), $payload)->assertRedirect(route('login'));
        $this->assertSame(User::STATUS_SUSPENDED, $user->fresh()->status);
        $this->post(route('login.post'), ['email' => $user->email, 'password' => 'new-password'])->assertSessionHasErrors('email');
    }

    public function test_password_policy_and_current_password_apply_to_profile_and_registration(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')])->fresh();
        $token = Password::broker()->createToken($user);
        $this->actingAs($user)->withSession(['credential_version' => 0])
            ->patch(route('profile.password.update'), ['current_password' => 'wrong', 'new_password' => 'new-password', 'new_password_confirmation' => 'new-password'])
            ->assertSessionHasErrors('current_password');
        $this->patch(route('profile.password.update'), ['current_password' => 'old-password', 'new_password' => '1234567', 'new_password_confirmation' => '1234567'])
            ->assertSessionHasErrors('new_password');
        $tooLong = str_repeat('é', 37);
        $this->patch(route('profile.password.update'), ['current_password' => 'old-password', 'new_password' => $tooLong, 'new_password_confirmation' => $tooLong])
            ->assertSessionHasErrors('new_password');
        $this->patch(route('profile.password.update'), ['current_password' => 'old-password', 'new_password' => ' new-pass ', 'new_password_confirmation' => ' new-pass '])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(' new-pass ', $user->fresh()->password));
        $this->assertFalse(Password::broker()->tokenExists($user, $token));
        $this->assertSame(1, session('credential_version'));
        $this->get(route('profile.index'))->assertOk();
        auth()->logout();
        $this->post(route('register.account'), ['name' => 'Test', 'email' => 'new@example.test', 'phone' => '123', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
    }

    public function test_new_account_cannot_inherit_an_old_recovery_token(): void
    {
        $old = User::factory()->create(['email' => 'reused@example.test']);
        $token = Password::broker()->createToken($old);
        $old->delete();
        $new = User::factory()->create(['email' => 'reused@example.test']);
        $this->assertFalse(Password::broker()->tokenExists($new, $token));
    }

    public function test_temporary_password_requires_change_and_existing_sessions_are_revoked(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN, 'password' => Hash::make('old-password')])->fresh();
        $token = Password::broker()->createToken($owner);
        $response = $this->actingAs($super)->withSession(['credential_version' => 0])->post(route('super-admin.owners.reset-password', $owner->id));
        $message = $response->getSession()->get('success');
        $temporaryPassword = substr($message, strlen('Temporary password generated: '));
        $owner->refresh();
        $this->assertTrue($owner->force_password_reset);
        $this->assertFalse(Password::broker()->tokenExists($owner, $token));
        $this->assertTrue(Hash::check($temporaryPassword, $owner->password));
        $this->actingAs($owner)->withSession(['credential_version' => 0])->get(route('profile.index'))->assertRedirect(route('login'));
        $this->actingAs($owner)->withSession(['credential_version' => 1])->get(route('dashboard'))->assertRedirect(route('profile.index'));
        $this->get(route('profile.index'))->assertOk()->assertSee('Change your temporary password');
        $this->patch(route('profile.password.update'), ['current_password' => $temporaryPassword, 'new_password' => 'new-password', 'new_password_confirmation' => 'new-password'])->assertSessionHasNoErrors();
        $this->assertFalse($owner->fresh()->force_password_reset);
    }

    public function test_mail_failure_does_not_disclose_account_and_is_logged(): void
    {
        $user = User::factory()->create(['email' => 'delivery@example.test']);
        $this->mock(\Illuminate\Contracts\Notifications\Dispatcher::class, function ($mock) {
            $mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('transport details'));
        });
        \Illuminate\Support\Facades\Log::shouldReceive('error')->once()->withArgs(
            fn ($message, $context) => $message === 'Password recovery email failed.' && $context === ['exception_class' => \RuntimeException::class]
        );
        $known = $this->post(route('password.email'), ['email' => $user->email]);
        $message = $known->getSession()->get('success');
        $this->post(route('password.email'), ['email' => 'missing@example.test'])->assertSessionHas('success', $message);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_impersonation_session_is_revoked_if_impersonator_changes_password(): void
    {
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN])->fresh();
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN])->fresh();
        $this->actingAs($owner)->withSession([
            'credential_version' => 0,
            'impersonator_id' => $super->id,
            'impersonator_credential_version' => 0,
        ])->get(route('profile.index'))->assertOk();
        $super->forceFill(['credential_version' => 1])->save();
        $this->get(route('profile.index'))->assertRedirect(route('login'));
    }
}
