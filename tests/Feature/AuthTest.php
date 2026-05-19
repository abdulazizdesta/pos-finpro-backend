<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Business;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    // ─── Register ────────────────────────────────────────────────────────────

    #[Test]
    public function register_valid_returns_201_with_token()
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name'         => 'Toko Maju',
            'owner_name'            => 'John Doe',
            'email'                 => 'john@toko.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['token', 'name', 'role', 'business']]);
    }

    #[Test]
    public function register_generates_business_code_automatically()
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name'         => 'Toko Maju',
            'owner_name'            => 'John Doe',
            'email'                 => 'john@toko.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $business = Business::first();
        $this->assertNotNull($business->code);
        $this->assertMatchesRegularExpression('/^[A-Z]+-\d{4}$/', $business->code);
    }

    #[Test]
    public function register_creates_owner_role()
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name'         => 'Toko Maju',
            'owner_name'            => 'John Doe',
            'email'                 => 'john@toko.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()
          ->assertJsonPath('data.role', 'owner');
    }

    #[Test]
    public function register_duplicate_email_returns_422()
    {
        $business = $this->createBusiness();
        $this->createUser(UserRole::OWNER, $business->id, null, ['email' => 'john@toko.com']);

        $this->postJson('/api/v1/auth/register', [
            'business_name'         => 'Toko Lain',
            'owner_name'            => 'Jane Doe',
            'email'                 => 'john@toko.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function register_password_mismatch_returns_422()
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name'         => 'Toko Maju',
            'owner_name'            => 'John Doe',
            'email'                 => 'john@toko.com',
            'password'              => 'password123',
            'password_confirmation' => 'wrongpassword',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function register_missing_fields_returns_422()
    {
        $this->postJson('/api/v1/auth/register', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['business_name', 'owner_name', 'email', 'password']);
    }

    // ─── Login ───────────────────────────────────────────────────────────────

    #[Test]
    public function login_valid_returns_200_with_token()
    {
        $business = $this->createBusiness();
        $this->createUser(UserRole::OWNER, $business->id, null, ['email' => 'owner@test.com']);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'owner@test.com',
            'password' => 'password123',
        ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'name', 'role']]);
    }

    #[Test]
    public function login_wrong_password_returns_401()
    {
        $business = $this->createBusiness();
        $this->createUser(UserRole::OWNER, $business->id, null, ['email' => 'owner@test.com']);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'owner@test.com',
            'password' => 'wrongpassword',
        ])->assertStatus(401);
    }

    #[Test]
    public function login_inactive_user_returns_403()
    {
        $business = $this->createBusiness();
        $this->createUser(UserRole::OWNER, $business->id, null, [
            'email'     => 'owner@test.com',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'owner@test.com',
            'password' => 'password123',
        ])->assertStatus(403);
    }

    #[Test]
    public function login_non_existent_email_returns_401()
    {
        $this->postJson('/api/v1/auth/login', [
            'email'    => 'notexist@test.com',
            'password' => 'password123',
        ])->assertStatus(401);
    }

    // ─── Login PIN ───────────────────────────────────────────────────────────

    #[Test]
    public function login_pin_valid_cashier_returns_200()
    {
        $business = $this->createBusiness();
        $outlet   = $this->createOutlet($business->id);
        $cashier  = $this->createUser(UserRole::CASHIER, $business->id, $outlet->id);

        $this->postJson('/api/v1/auth/login/pin', [
            'email' => $cashier->email,
            'pin'   => '123456',
        ])->assertOk()
          ->assertJsonStructure(['data' => ['token', 'name', 'role']]);
    }

    #[Test]
    public function login_pin_wrong_pin_returns_401()
    {
        $business = $this->createBusiness();
        $outlet   = $this->createOutlet($business->id);
        $cashier  = $this->createUser(UserRole::CASHIER, $business->id, $outlet->id);

        $this->postJson('/api/v1/auth/login/pin', [
            'email' => $cashier->email,
            'pin'   => '999999',
        ])->assertStatus(401);
    }

    // ─── Me ──────────────────────────────────────────────────────────────────

    #[Test]
    public function me_returns_authenticated_user_data()
    {
        $business = $this->createBusiness();
        $owner    = $this->createUser(UserRole::OWNER, $business->id);

        $this->actingAs($owner)
             ->getJson('/api/v1/auth/me')
             ->assertOk()
             ->assertJsonStructure(['data' => ['name', 'email', 'role', 'business']]);
    }

    #[Test]
    public function me_unauthenticated_returns_401()
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    // ─── Logout ──────────────────────────────────────────────────────────────

    #[Test]
    public function logout_returns_200()
    {
        $business = $this->createBusiness();
        $owner    = $this->createUser(UserRole::OWNER, $business->id);

        $this->actingAs($owner)
             ->postJson('/api/v1/auth/logout')
             ->assertOk();
    }
}
