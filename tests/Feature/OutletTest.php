<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Outlet;
use App\Models\Shift;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OutletTest extends TestCase
{
    private function baseSetup(): array
    {
        $business = $this->createBusiness('Fashion', 'FSH');
        $owner    = $this->createUser(UserRole::OWNER, $business->id);
        $admin    = $this->createUser(UserRole::ADMIN, $business->id);
        return compact('business', 'owner', 'admin');
    }

    // ─── POST Create ─────────────────────────────────────────────────────────

    #[Test]
    public function create_outlet_valid_returns_201()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/outlets', [
                 'name' => 'Outlet Selatan',
                 'code' => 'FSH02',
             ])
             ->assertCreated()
             ->assertJsonPath('data.name', 'Outlet Selatan')
             ->assertJsonPath('data.code', 'FSH02');

        $this->assertDatabaseHas('outlets', [
            'business_id' => $business->id,
            'code'        => 'FSH02',
        ]);
    }

    #[Test]
    public function create_outlet_code_auto_uppercase()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/outlets', ['name' => 'Outlet', 'code' => 'fsh02'])
             ->assertCreated()
             ->assertJsonPath('data.code', 'FSH02');
    }

    #[Test]
    public function create_outlet_duplicate_code_returns_422()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $this->createOutlet($business->id, 'Outlet 1', 'FSH01');

        $this->actingAs($owner)
             ->postJson('/api/v1/outlets', ['name' => 'Outlet 2', 'code' => 'FSH01'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function create_outlet_admin_role_returns_403()
    {
        ['admin' => $admin] = $this->baseSetup();

        $this->actingAs($admin)
             ->postJson('/api/v1/outlets', ['name' => 'Outlet', 'code' => 'ADM01'])
             ->assertForbidden();
    }

    #[Test]
    public function create_outlet_missing_fields_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/outlets', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['name', 'code']);
    }

    // ─── GET All ─────────────────────────────────────────────────────────────

    #[Test]
    public function get_all_outlets_returns_own_business_only()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $this->createOutlet($business->id, 'Outlet 1', 'FSH01');

        $other       = $this->createBusiness('Other', 'OTH');
        $otherOwner  = $this->createUser(UserRole::OWNER, $other->id);
        $this->createOutlet($other->id, 'Other Outlet', 'OTH01');

        $this->actingAs($owner)
             ->getJson('/api/v1/outlets')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    // ─── GET Detail ──────────────────────────────────────────────────────────

    #[Test]
    public function get_outlet_detail_returns_correct_data()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $outlet = $this->createOutlet($business->id, 'Outlet Pusat', 'FSH01');

        $this->actingAs($owner)
             ->getJson("/api/v1/outlets/{$outlet->id}")
             ->assertOk()
             ->assertJsonPath('data.id', $outlet->id);
    }

    #[Test]
    public function get_outlet_detail_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();
        $other       = $this->createBusiness('Other', 'OTH');
        $otherOutlet = $this->createOutlet($other->id, 'Other Outlet', 'OTH01');

        $this->actingAs($owner)
             ->getJson("/api/v1/outlets/{$otherOutlet->id}")
             ->assertForbidden();
    }

    // ─── PUT Update ──────────────────────────────────────────────────────────

    #[Test]
    public function update_outlet_valid_returns_200()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $outlet = $this->createOutlet($business->id, 'Outlet Lama', 'FSH01');

        $this->actingAs($owner)
             ->putJson("/api/v1/outlets/{$outlet->id}", ['name' => 'Outlet Baru'])
             ->assertOk()
             ->assertJsonPath('data.name', 'Outlet Baru');
    }

    #[Test]
    public function update_outlet_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();
        $other       = $this->createBusiness('Other', 'OTH');
        $otherOutlet = $this->createOutlet($other->id, 'Other', 'OTH01');

        $this->actingAs($owner)
             ->putJson("/api/v1/outlets/{$otherOutlet->id}", ['name' => 'Hack'])
             ->assertForbidden();
    }

    // ─── DELETE ──────────────────────────────────────────────────────────────

    #[Test]
    public function delete_outlet_valid_soft_deletes()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $outlet = $this->createOutlet($business->id, 'Outlet', 'FSH01');

        $this->actingAs($owner)
             ->deleteJson("/api/v1/outlets/{$outlet->id}")
             ->assertOk();

        $this->assertSoftDeleted('outlets', ['id' => $outlet->id]);
    }

    #[Test]
    public function delete_outlet_with_active_shift_returns_422()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $outlet = $this->createOutlet($business->id, 'Outlet', 'FSH01');

        Shift::create([
            'user_id'      => $owner->id,
            'outlet_id'    => $outlet->id,
            'opened_at'    => now(),
            'opening_cash' => 0,
            'status'       => 'open',
        ]);

        $this->actingAs($owner)
             ->deleteJson("/api/v1/outlets/{$outlet->id}")
             ->assertStatus(422);
    }
}
