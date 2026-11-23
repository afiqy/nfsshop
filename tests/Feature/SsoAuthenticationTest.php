<?php

namespace Tests\Feature;

use App\Models\SocialIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SsoAuthenticationTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->artisan('migrate:fresh');
        RefreshDatabaseState::$migrated = false;
    }

    public function test_new_sso_identity_creates_and_authenticates_a_client_account(): void
    {
        $this->enableGoogle();
        $this->mockGoogleUser(SocialiteUser::fake([
            'id' => 'google-123',
            'name' => 'New Customer',
            'email' => 'new-customer@example.test',
            'email_verified' => true,
        ]));

        $this->withSession(['sso_intent' => 'login', 'sso_provider' => 'google'])
            ->get(route('sso.callback', 'google'))
            ->assertRedirect(route('client.portal'));

        $user = User::query()->where('email', 'new-customer@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(User::ROLE_CLIENT, $user->role);
        $this->assertNull($user->tenant_id);
        $this->assertTrue((bool) $user->email_verified);
        $this->assertSame('google-123', $user->socialIdentities()->sole()->provider_user_id);
        $this->assertNotNull(session('device_session_id'));
    }

    public function test_existing_email_is_never_automatically_linked_to_an_sso_identity(): void
    {
        $this->enableGoogle();
        $existing = User::factory()->create(['email' => 'existing@example.test']);
        $this->mockGoogleUser(SocialiteUser::fake([
            'id' => 'unlinked-google-id',
            'email' => 'existing@example.test',
        ]));

        $this->withSession(['sso_intent' => 'login', 'sso_provider' => 'google'])
            ->get(route('sso.callback', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('sso');

        $this->assertGuest();
        $this->assertSame(0, SocialIdentity::query()->count());
        $this->assertSame($existing->id, User::query()->where('email', 'existing@example.test')->sole()->id);
    }

    public function test_existing_sso_identity_logs_in_by_provider_id_not_email(): void
    {
        $this->enableMicrosoft();
        $user = User::factory()->create(['email' => 'customer@example.test']);
        $user->socialIdentities()->create(['provider' => 'microsoft', 'provider_user_id' => 'ms-123']);
        $this->mockMicrosoftUser(SocialiteUser::fake([
            'id' => 'ms-123',
            'email' => null,
        ]));

        $this->withSession(['sso_intent' => 'login', 'sso_provider' => 'microsoft'])
            ->get(route('sso.callback', 'microsoft'))
            ->assertRedirect(route('client.portal'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::query()->count());
    }

    public function test_provider_state_failure_does_not_authenticate_or_create_accounts(): void
    {
        $this->enableGoogle();
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->withSession(['sso_intent' => 'login', 'sso_provider' => 'google'])
            ->get(route('sso.callback', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('sso');
        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_invalid_provider_identity_data_does_not_create_accounts(): void
    {
        $this->enableGoogle();
        $this->mockGoogleUser(SocialiteUser::fake([
            'id' => 'google-no-email',
            'email' => 'not-an-email',
        ]));
        $this->withSession(['sso_intent' => 'login', 'sso_provider' => 'google'])
            ->get(route('sso.callback', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('sso');
        $this->assertSame(0, User::query()->count());
    }

    public function test_sso_does_not_authenticate_locked_or_suspended_accounts(): void
    {
        $this->enableGoogle();
        $suspended = User::factory()->create(['status' => User::STATUS_SUSPENDED]);
        $locked = User::factory()->create(['locked_until' => now()->addMinute()]);
        $suspended->socialIdentities()->create(['provider' => 'google', 'provider_user_id' => 'suspended-google']);
        $locked->socialIdentities()->create(['provider' => 'google', 'provider_user_id' => 'locked-google']);
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->twice()->andReturn(
            SocialiteUser::fake(['id' => 'suspended-google', 'email' => $suspended->email]),
            SocialiteUser::fake(['id' => 'locked-google', 'email' => $locked->email]),
        );
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        foreach (['suspended-google', 'locked-google'] as $_) {
            $this->withSession(['sso_intent' => 'login', 'sso_provider' => 'google'])
                ->get(route('sso.callback', 'google'))
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('sso');
            $this->assertGuest();
        }

        $this->assertNull(session('device_session_id'));
    }

    public function test_linking_requires_password_and_rejects_an_identity_owned_by_another_user(): void
    {
        $this->enableGoogle();
        $user = User::factory()->create(['password' => 'correct-password']);
        $other = User::factory()->create();
        $other->socialIdentities()->create(['provider' => 'google', 'provider_user_id' => 'already-linked']);
        $driver = Mockery::mock();
        $driver->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.test'));
        $driver->shouldReceive('user')->once()->andReturn(SocialiteUser::fake([
            'id' => 'already-linked',
            'email' => 'other@example.test',
        ]));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->actingAs($user)->post(route('profile.identities.link', 'google'), [
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrors('current_password');
        $this->post(route('profile.identities.link', 'google'), [
            'current_password' => 'correct-password',
        ])->assertRedirect('https://accounts.google.test');
        $this->get(route('sso.callback', 'google'))->assertRedirect(route('profile.index'))->assertSessionHasErrors('provider');
        $this->assertSame(0, $user->fresh()->socialIdentities()->count());
        $this->assertSame($other->id, SocialIdentity::query()->where('provider_user_id', 'already-linked')->sole()->user_id);
    }

    public function test_link_callback_is_rejected_if_account_credentials_changed_during_provider_authentication(): void
    {
        $this->enableGoogle();
        $user = User::factory()->create(['password' => 'correct-password']);
        $driver = Mockery::mock();
        $driver->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.test'));
        $driver->shouldReceive('user')->once()->andReturn(SocialiteUser::fake([
            'id' => 'google-after-password-change',
            'email' => 'new@example.test',
        ]));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->actingAs($user)->post(route('profile.identities.link', 'google'), [
            'current_password' => 'correct-password',
        ])->assertRedirect('https://accounts.google.test');
        $user->forceFill(['credential_version' => 1])->save();

        $this->get(route('sso.callback', 'google'))
            ->assertRedirect(route('profile.index'))
            ->assertSessionHasErrors('sso');
        $this->assertSame(0, $user->socialIdentities()->count());
    }

    public function test_linked_provider_can_be_removed_only_by_its_account_owner_with_password(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $identity = $user->socialIdentities()->create([
            'provider' => 'google',
            'provider_user_id' => 'google-to-remove',
        ]);
        $other = User::factory()->create();

        $this->actingAs($other)->delete(route('profile.identities.unlink', $identity->id), [
            'current_password' => 'correct-password',
        ])->assertNotFound();
        $this->actingAs($user)->delete(route('profile.identities.unlink', $identity->id), [
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrors('current_password');
        $this->delete(route('profile.identities.unlink', $identity->id), [
            'current_password' => 'correct-password',
        ])->assertRedirect();

        $this->assertDatabaseMissing('social_identities', ['id' => $identity->id]);
    }

    public function test_sso_buttons_are_only_shown_for_configured_providers(): void
    {
        $this->get(route('login'))->assertDontSee('Continue with Google')->assertDontSee('Continue with Microsoft');
        $this->enableGoogle();
        $this->get(route('login'))->assertSee('Continue with Google')->assertDontSee('Continue with Microsoft');
    }

    public function test_google_redirect_uses_the_sso_state_and_required_scopes(): void
    {
        $this->enableGoogle();
        $driver = Mockery::mock();
        $driver->shouldReceive('scopes')->once()->with(['openid', 'email', 'profile'])->andReturnSelf();
        $driver->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.test'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get(route('sso.redirect', 'google'))
            ->assertRedirect('https://accounts.google.test')
            ->assertSessionHas('sso_intent', 'login')
            ->assertSessionHas('sso_provider', 'google');
    }

    public function test_microsoft_socialite_provider_is_registered(): void
    {
        $this->enableMicrosoft();

        $this->assertInstanceOf(\SocialiteProviders\Microsoft\Provider::class, Socialite::driver('microsoft'));
    }

    private function enableGoogle(): void
    {
        config([
            'services.google.client_id' => 'google-client',
            'services.google.client_secret' => 'google-secret',
        ]);
    }

    private function enableMicrosoft(): void
    {
        config([
            'services.microsoft.client_id' => 'microsoft-client',
            'services.microsoft.client_secret' => 'microsoft-secret',
        ]);
    }

    private function mockGoogleUser(SocialiteUser $user): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }

    private function mockMicrosoftUser(SocialiteUser $user): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($user);
        Socialite::shouldReceive('driver')->with('microsoft')->andReturn($driver);
    }
}
