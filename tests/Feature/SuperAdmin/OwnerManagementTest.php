<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Database\Models\Tenant;
use Tests\TestCase;

class OwnerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_owner(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'email' => 'super@example.com',
        ]);

        $payload = [
            'name' => 'John Doe',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'workshop_name' => 'Doe Mechanics',
            'tenant_id' => 'doe-mechanics',
        ];

        $response = $this->actingAs($superAdmin)
            ->post(route('super-admin.owners.store'), $payload);

        $response->assertRedirect(route('super-admin.owners.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'owner@example.com',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'tenant_id' => 'doe-mechanics',
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => 'doe-mechanics',
        ]);
    }

    public function test_suspended_owner_cannot_login(): void
    {
        $owner = User::factory()->create([
            'email' => 'blocked@example.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_SUSPENDED,
        ]);

        $response = $this->post(route('login'), [
            'email' => 'blocked@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
