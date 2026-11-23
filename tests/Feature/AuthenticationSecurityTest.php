<?php

namespace Tests\Feature;

use App\Models\AccountSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->artisan('migrate:fresh');
        RefreshDatabaseState::$migrated = false;
    }

    public function test_captcha_is_required_after_three_failures_and_checks_provider_and_hostname(): void
    {
        config(['services.recaptcha.site_key' => 'test-site', 'services.recaptcha.secret_key' => 'test-secret', 'app.url' => 'https://workshop.example.test']);
        $user = User::factory()->create(['email' => 'person@example.test', 'password' => Hash::make('correct-password')]);
        $payload = ['email' => $user->email, 'password' => 'wrong'];
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('login.post'), $payload)->assertSessionHasErrors('email');
        }
        $this->get(route('login'))->assertSee('g-recaptcha');
        $payload['password'] = 'correct-password';
        $this->post(route('login.post'), $payload)->assertSessionHasErrors('captcha');
        $this->assertGuest();
        Http::fakeSequence('google.com/recaptcha/api/siteverify')
            ->push(['success' => true, 'hostname' => 'attacker.example.test'])
            ->push(['success' => true, 'hostname' => 'workshop.example.test']);
        $payload['g-recaptcha-response'] = 'test-response';
        $this->post(route('login.post'), $payload)->assertSessionHasErrors('captcha');
        $this->post(route('login.post'), $payload)->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_captcha_fails_closed_when_missing_or_unavailable(): void
    {
        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret_key' => null]);
        $user = User::factory()->create(['email' => 'person@example.test', 'password' => Hash::make('correct-password')]);
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('login.post'), ['email' => $user->email, 'password' => 'wrong']);
        }
        $this->post(route('login.post'), ['email' => $user->email, 'password' => 'correct-password'])
            ->assertSessionHasErrors('captcha');
        $this->assertGuest();
        config(['services.recaptcha.site_key' => 'site', 'services.recaptcha.secret_key' => 'secret', 'app.url' => 'https://workshop.example.test']);
        Http::fake(['google.com/recaptcha/api/siteverify' => Http::response([], 503)]);
        $this->post(route('login.post'), ['email' => $user->email, 'password' => 'correct-password', 'g-recaptcha-response' => 'token'])
            ->assertSessionHasErrors('captcha');
        $this->assertGuest();
    }

    public function test_authenticator_enrollment_recovery_codes_and_privileged_actions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'password' => Hash::make('correct-password')])->fresh();
        $this->actingAs($admin)->get(route('authenticator.show'))->assertOk();
        $this->post(route('authenticator.start'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post(route('authenticator.start'), ['password' => 'correct-password'])->assertRedirect(route('authenticator.show'));
        $secret = Crypt::decryptString(session('mfa_setup.secret'));
        $otp = app(Google2FA::class)->getCurrentOtp($secret);
        $response = $this->post(route('authenticator.confirm'), ['mfa_code' => $otp])->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $codes = $response->viewData('codes');
        $this->assertCount(10, $codes);
        $admin->refresh();
        $this->assertNotSame($secret, $admin->getRawOriginal('mfa_secret'));
        $this->assertArrayNotHasKey('mfa_secret', $admin->toArray());
        session()->forget(['mfa_verified_user', 'mfa_verified_secret', 'mfa_verified_version', 'mfa_verified_until']);
        $this->post(route('super-admin.owners.store'), [])->assertSessionHasErrors('mfa');
        $this->post(route('authenticator.verify'), ['mfa_code' => $otp])->assertSessionHasErrors('mfa_code');
        $this->post(route('authenticator.verify'), ['mfa_code' => $codes[0]])->assertSessionHasNoErrors();
        $this->post(route('super-admin.owners.store'), [])->assertSessionHasErrors('name');
        $this->travel(5)->minutes();
        $this->post(route('super-admin.owners.store'), [])->assertSessionHasErrors('mfa');
        $this->post(route('authenticator.verify'), ['mfa_code' => $codes[0]])->assertSessionHasErrors('mfa_code');
        $this->delete(route('authenticator.disable'), ['password' => 'correct-password', 'mfa_code' => $codes[1]])->assertSessionHasNoErrors();
        $this->assertNull($admin->fresh()->mfa_secret);
        $this->post(route('super-admin.owners.store'), [])->assertSessionHasErrors('name');
    }

    public function test_authenticator_enrollment_requires_enterprise_or_system_admin(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_ACCOUNT_SUPER_ADMIN, 'password' => Hash::make('correct-password')])->fresh();
        $plan = SubscriptionPlan::firstOrCreate(['slug' => 'pro'], ['name' => 'Pro']);
        $subscription = AccountSubscription::create(['user_id' => $owner->id, 'subscription_plan_id' => $plan->id,
            'status' => AccountSubscription::STATUS_ACTIVE, 'current_period_ends_at' => now()->addDay()]);
        $this->actingAs($owner)->post(route('authenticator.start'), ['password' => 'correct-password'])->assertForbidden();
        $enterprise = SubscriptionPlan::firstOrCreate(['slug' => 'enterprise'], ['name' => 'Enterprise']);
        $subscription->update(['subscription_plan_id' => $enterprise->id]);
        $this->post(route('authenticator.start'), ['password' => 'correct-password'])->assertRedirect(route('authenticator.show'));
    }

    public function test_device_revocation_is_user_scoped_and_invalidates_the_device(): void
    {
        $user = User::factory()->create()->fresh();
        $other = User::factory()->create()->fresh();
        $this->actingAs($user)->get(route('devices.index'))->assertOk();
        $current = session('device_session_id');
        $remote = UserSession::create(['user_id' => $user->id, 'managed' => true, 'credential_version' => 0, 'last_activity' => now(), 'device_name' => 'Remote browser']);
        $foreign = UserSession::create(['user_id' => $other->id, 'managed' => true, 'credential_version' => 0, 'last_activity' => now(), 'device_name' => 'Foreign browser']);
        $this->get(route('devices.index'))->assertSee('Remote browser')->assertDontSee('Foreign browser');
        $this->delete(route('devices.revoke', $foreign->id))->assertNotFound();
        $oldToken = $user->remember_token;
        $this->delete(route('devices.revoke', $remote->id))->assertSessionHasNoErrors();
        $this->assertNotSame($oldToken, $user->fresh()->remember_token);
        $this->withSession(['device_session_id' => $remote->id])->get(route('profile.index'))->assertRedirect(route('login'));
        $this->actingAs($user->fresh())->withSession(['device_session_id' => $current])->delete(route('devices.revoke', $current))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
