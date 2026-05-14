<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Shift;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    private function baseSetup(): array
    {
        $business = $this->createBusiness('Fashion', 'FSH');
        $outlet   = $this->createOutlet($business->id, 'Outlet Pusat', 'FSH-01');
        $owner    = $this->createUser(UserRole::OWNER, $business->id);
        $admin    = $this->createUser(UserRole::ADMIN, $business->id, $outlet->id);
        $cashier  = $this->createUser(UserRole::CASHIER, $business->id, $outlet->id);

        return compact('business', 'outlet', 'owner', 'admin', 'cashier');
    }

    private function createShift(int $userId, int $outletId, string $status = 'open'): Shift
    {
        return Shift::create([
            'user_id'      => $userId,
            'outlet_id'    => $outletId,
            'opened_at'    => now(),
            'opening_cash' => 500000,
            'status'       => $status,
        ]);
    }

    // ─── POST Open ───────────────────────────────────────────────────────────

    #[Test]
    public function open_shift_valid_returns_201()
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/shifts', [
                 'outlet_id'    => $outlet->id,
                 'opening_cash' => 500000,
             ])
             ->assertCreated()
             ->assertJsonPath('success', true)
             ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('shifts', [
            'outlet_id' => $outlet->id,
            'status'    => 'open',
        ]);
    }

    #[Test]
    public function open_shift_cashier_can_open()
    {
        ['cashier' => $cashier, 'outlet' => $outlet] = $this->baseSetup();

        $this->actingAs($cashier)
             ->postJson('/api/v1/shifts', [
                 'outlet_id'    => $outlet->id,
                 'opening_cash' => 200000,
             ])
             ->assertCreated();
    }

    #[Test]
    public function open_shift_duplicate_returns_422()
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->baseSetup();
        $this->createShift($owner->id, $outlet->id);

        $this->actingAs($owner)
             ->postJson('/api/v1/shifts', [
                 'outlet_id'    => $outlet->id,
                 'opening_cash' => 500000,
             ])
             ->assertStatus(422);
    }

    #[Test]
    public function open_shift_other_business_outlet_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();

        $other       = $this->createBusiness('Other', 'OTH');
        $otherOutlet = $this->createOutlet($other->id, 'Other Outlet', 'OTH-01');

        $this->actingAs($owner)
             ->postJson('/api/v1/shifts', [
                 'outlet_id'    => $otherOutlet->id,
                 'opening_cash' => 500000,
             ])
             ->assertForbidden();
    }

    #[Test]
    public function open_shift_missing_fields_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/shifts', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['outlet_id', 'opening_cash']);
    }

    // ─── PUT Close ───────────────────────────────────────────────────────────

    #[Test]
    public function close_shift_valid_returns_200()
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->baseSetup();
        $shift = $this->createShift($owner->id, $outlet->id);

        $this->actingAs($owner)
             ->putJson("/api/v1/shifts/{$shift->id}/close", [
                 'closing_cash' => 750000,
             ])
             ->assertOk()
             ->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('shifts', [
            'id'           => $shift->id,
            'status'       => 'closed',
            'closing_cash' => 750000,
        ]);
    }

    #[Test]
    public function close_shift_already_closed_returns_422()
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->baseSetup();
        $shift = $this->createShift($owner->id, $outlet->id, 'closed');

        $this->actingAs($owner)
             ->putJson("/api/v1/shifts/{$shift->id}/close", [
                 'closing_cash' => 750000,
             ])
             ->assertStatus(422);
    }

    #[Test]
    public function close_shift_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();

        $other       = $this->createBusiness('Other', 'OTH');
        $otherOutlet = $this->createOutlet($other->id, 'Other Outlet', 'OTH-01');
        $otherUser   = $this->createUser(UserRole::OWNER, $other->id);
        $otherShift  = $this->createShift($otherUser->id, $otherOutlet->id);

        $this->actingAs($owner)
             ->putJson("/api/v1/shifts/{$otherShift->id}/close", [
                 'closing_cash' => 750000,
             ])
             ->assertForbidden();
    }

    // ─── GET Active ──────────────────────────────────────────────────────────

    #[Test]
    public function get_active_shift_returns_open_shift()
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->baseSetup();
        $shift = $this->createShift($owner->id, $outlet->id);

        $this->actingAs($owner)
             ->getJson("/api/v1/shifts/active?outlet_id={$outlet->id}")
             ->assertOk()
             ->assertJsonPath('data.id', $shift->id)
             ->assertJsonPath('data.status', 'open');
    }

    #[Test]
    public function get_active_shift_no_open_shift_returns_404()
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->baseSetup();
        $this->createShift($owner->id, $outlet->id, 'closed');

        $this->actingAs($owner)
             ->getJson("/api/v1/shifts/active?outlet_id={$outlet->id}")
             ->assertNotFound();
    }

    #[Test]
    public function get_active_shift_missing_outlet_id_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->getJson('/api/v1/shifts/active')
             ->assertStatus(422);
    }

    // ─── GET All ─────────────────────────────────────────────────────────────

    #[Test]
    public function get_all_shifts_returns_only_own_business()
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->baseSetup();
        $this->createShift($owner->id, $outlet->id);

        $other       = $this->createBusiness('Other', 'OTH');
        $otherOutlet = $this->createOutlet($other->id, 'Other Outlet', 'OTH-01');
        $otherUser   = $this->createUser(UserRole::OWNER, $other->id);
        $this->createShift($otherUser->id, $otherOutlet->id);

        $this->actingAs($owner)
             ->getJson('/api/v1/shifts')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function get_all_shifts_filter_by_status()
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->baseSetup();
        $this->createShift($owner->id, $outlet->id, 'open');
        $this->createShift($owner->id, $outlet->id, 'closed');

        // Update closed_at untuk shift ke-2 dulu biar tidak konflik
        Shift::where('status', 'closed')->update(['closed_at' => now()]);

        $this->actingAs($owner)
             ->getJson('/api/v1/shifts?status=open')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    // ─── GET Detail ──────────────────────────────────────────────────────────

    #[Test]
    public function get_shift_detail_returns_correct_data()
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->baseSetup();
        $shift = $this->createShift($owner->id, $outlet->id);

        $this->actingAs($owner)
             ->getJson("/api/v1/shifts/{$shift->id}")
             ->assertOk()
             ->assertJsonPath('data.id', $shift->id)
             ->assertJsonStructure(['data' => ['id', 'status', 'opening_cash', 'user', 'outlet']]);
    }

    #[Test]
    public function get_shift_detail_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();

        $other       = $this->createBusiness('Other', 'OTH');
        $otherOutlet = $this->createOutlet($other->id, 'Other Outlet', 'OTH-01');
        $otherUser   = $this->createUser(UserRole::OWNER, $other->id);
        $otherShift  = $this->createShift($otherUser->id, $otherOutlet->id);

        $this->actingAs($owner)
             ->getJson("/api/v1/shifts/{$otherShift->id}")
             ->assertForbidden();
    }
}