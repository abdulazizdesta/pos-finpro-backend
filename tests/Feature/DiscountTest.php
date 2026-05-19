<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Discount;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscountTest extends TestCase
{
    private function baseSetup(): array
    {
        $business = $this->createBusiness('Fashion', 'FSH');
        $owner    = $this->createUser(UserRole::OWNER, $business->id);
        $admin    = $this->createUser(UserRole::ADMIN, $business->id);
        return compact('business', 'owner', 'admin');
    }

    private function createDiscount(int $businessId, array $overrides = []): Discount
    {
        return Discount::create(array_merge([
            'business_id'  => $businessId,
            'code'         => 'HEMAT10',
            'name'         => 'Diskon 10%',
            'type'         => 'percentage',
            'value'        => 10,
            'min_purchase' => 0,
            'used_count'   => 0,
            'is_active'    => true,
        ], $overrides));
    }

    // ─── POST Create ─────────────────────────────────────────────────────────

    #[Test]
    public function create_discount_percentage_valid_returns_201()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/discounts', [
                 'code'  => 'HEMAT10',
                 'type'  => 'percentage',
                 'value' => 10,
             ])
             ->assertCreated()
             ->assertJsonPath('data.code', 'HEMAT10')
             ->assertJsonPath('data.type', 'percentage');
    }

    #[Test]
    public function create_discount_fixed_valid_returns_201()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/discounts', [
                 'code'  => 'FLAT20K',
                 'type'  => 'fixed',
                 'value' => 20000,
             ])
             ->assertCreated()
             ->assertJsonPath('data.type', 'fixed');
    }

    #[Test]
    public function create_discount_code_auto_uppercase()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/discounts', ['code' => 'hemat10', 'type' => 'fixed', 'value' => 10000])
             ->assertCreated()
             ->assertJsonPath('data.code', 'HEMAT10');
    }

    #[Test]
    public function create_discount_duplicate_code_returns_422()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $this->createDiscount($business->id, ['code' => 'HEMAT10']);

        $this->actingAs($owner)
             ->postJson('/api/v1/discounts', ['code' => 'HEMAT10', 'type' => 'fixed', 'value' => 10000])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function create_discount_invalid_type_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/discounts', ['code' => 'TEST', 'type' => 'invalid', 'value' => 10])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['type']);
    }

    #[Test]
    public function create_discount_admin_role_returns_403()
    {
        ['admin' => $admin] = $this->baseSetup();

        $this->actingAs($admin)
             ->postJson('/api/v1/discounts', ['code' => 'TEST', 'type' => 'fixed', 'value' => 10000])
             ->assertForbidden();
    }

    #[Test]
    public function create_discount_valid_until_before_valid_from_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/discounts', [
                 'code'        => 'TEST',
                 'type'        => 'fixed',
                 'value'       => 10000,
                 'valid_from'  => '2026-06-01',
                 'valid_until' => '2026-05-01',
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['valid_until']);
    }

    // ─── GET All ─────────────────────────────────────────────────────────────

    #[Test]
    public function get_all_discounts_returns_own_business_only()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $this->createDiscount($business->id);

        $other = $this->createBusiness('Other', 'OTH');
        $this->createDiscount($other->id, ['code' => 'OTHER10']);

        $this->actingAs($owner)
             ->getJson('/api/v1/discounts')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function get_all_discounts_filter_by_type()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $this->createDiscount($business->id, ['code' => 'PCT', 'type' => 'percentage']);
        $this->createDiscount($business->id, ['code' => 'FIX', 'type' => 'fixed', 'value' => 20000]);

        $this->actingAs($owner)
             ->getJson('/api/v1/discounts?type=fixed')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    // ─── GET Detail ──────────────────────────────────────────────────────────

    #[Test]
    public function get_discount_detail_returns_correct_data()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $discount = $this->createDiscount($business->id);

        $this->actingAs($owner)
             ->getJson("/api/v1/discounts/{$discount->id}")
             ->assertOk()
             ->assertJsonPath('data.id', $discount->id);
    }

    #[Test]
    public function get_discount_detail_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();
        $other           = $this->createBusiness('Other', 'OTH');
        $otherDiscount   = $this->createDiscount($other->id, ['code' => 'OTHER']);

        $this->actingAs($owner)
             ->getJson("/api/v1/discounts/{$otherDiscount->id}")
             ->assertForbidden();
    }

    // ─── PUT Update ──────────────────────────────────────────────────────────

    #[Test]
    public function update_discount_valid_returns_200()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $discount = $this->createDiscount($business->id);

        $this->actingAs($owner)
             ->putJson("/api/v1/discounts/{$discount->id}", ['value' => 20])
             ->assertOk()
             ->assertJsonPath('data.value', 20);
    }

    #[Test]
    public function update_discount_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();
        $other         = $this->createBusiness('Other', 'OTH');
        $otherDiscount = $this->createDiscount($other->id, ['code' => 'OTHER']);

        $this->actingAs($owner)
             ->putJson("/api/v1/discounts/{$otherDiscount->id}", ['value' => 5])
             ->assertForbidden();
    }

    // ─── DELETE ──────────────────────────────────────────────────────────────

    #[Test]
    public function delete_discount_unused_soft_deletes()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $discount = $this->createDiscount($business->id, ['used_count' => 0]);

        $this->actingAs($owner)
             ->deleteJson("/api/v1/discounts/{$discount->id}")
             ->assertOk();

        $this->assertSoftDeleted('discounts', ['id' => $discount->id]);
    }

    #[Test]
    public function delete_discount_already_used_returns_422()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $discount = $this->createDiscount($business->id, ['used_count' => 3]);

        $this->actingAs($owner)
             ->deleteJson("/api/v1/discounts/{$discount->id}")
             ->assertStatus(422);
    }

    #[Test]
    public function delete_discount_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();
        $other         = $this->createBusiness('Other', 'OTH');
        $otherDiscount = $this->createDiscount($other->id, ['code' => 'OTHER']);

        $this->actingAs($owner)
             ->deleteJson("/api/v1/discounts/{$otherDiscount->id}")
             ->assertForbidden();
    }
}
