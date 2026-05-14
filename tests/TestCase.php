<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Illuminate\Contracts\Auth\Authenticatable;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    protected function createBusiness(string $name = 'Test Business', string $code = 'TST'): Business
    {
        return Business::create(['name' => $name, 'code' => $code]);
    }

    protected function createOutlet(int $businessId, string $name = 'Test Outlet', string $code = 'O-TST'): Outlet
    {
        return Outlet::create(['business_id' => $businessId, 'name' => $name, 'code' => $code]);
    }

    protected function createUser(
        UserRole $role,
        ?int $businessId = null,
        ?int $outletId = null,
        array $overrides = []
    ): User {
        static $counter = 0;
        $counter++;

        return User::create(array_merge([
            'name' => "User {$counter}",
            'email' => "user{$counter}@test.com",
            'password' => Hash::make('password123'),
            'pin' => $role === UserRole::CASHIER ? Hash::make('123456') : null,
            'role' => $role,
            'is_active' => true,
            'business_id' => $businessId,
            'outlet_id' => $outletId,
        ], $overrides));
    }

    public function actingAs(Authenticatable $user, $guard = null): static
    {
        Sanctum::actingAs($user);
        return $this;
    }
}