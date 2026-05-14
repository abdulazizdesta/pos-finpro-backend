#!/bin/bash

# Script untuk membuat file test Shift dan Transaction
# Jalankan dari root project Laravel: bash make_test_files.sh

set -e

echo "🧪 Membuat file test..."

cat > tests/Feature/ShiftTest.php << 'EOF'
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

    #[Test]
    public function open_shift_valid_returns_201()
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/shifts', [
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
             ->postJson('/api/shifts', [
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
             ->postJson('/api/shifts', [
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
             ->postJson('/api/shifts', [
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
             ->postJson('/api/shifts', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['outlet_id', 'opening_cash']);
    }

    #[Test]
    public function close_shift_valid_returns_200()
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->baseSetup();
        $shift = $this->createShift($owner->id, $outlet->id);

        $this->actingAs($owner)
             ->putJson("/api/shifts/{$shift->id}/close", [
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
             ->putJson("/api/shifts/{$shift->id}/close", [
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
             ->putJson("/api/shifts/{$otherShift->id}/close", [
                 'closing_cash' => 750000,
             ])
             ->assertForbidden();
    }

    #[Test]
    public function get_active_shift_returns_open_shift()
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->baseSetup();
        $shift = $this->createShift($owner->id, $outlet->id);

        $this->actingAs($owner)
             ->getJson("/api/shifts/active?outlet_id={$outlet->id}")
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
             ->getJson("/api/shifts/active?outlet_id={$outlet->id}")
             ->assertNotFound();
    }

    #[Test]
    public function get_active_shift_missing_outlet_id_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->getJson('/api/shifts/active')
             ->assertStatus(422);
    }

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
             ->getJson('/api/shifts')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function get_shift_detail_returns_correct_data()
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->baseSetup();
        $shift = $this->createShift($owner->id, $outlet->id);

        $this->actingAs($owner)
             ->getJson("/api/shifts/{$shift->id}")
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
             ->getJson("/api/shifts/{$otherShift->id}")
             ->assertForbidden();
    }
}
EOF
echo "✅ tests/Feature/ShiftTest.php"

cat > tests/Feature/TransactionTest.php << 'EOF'
<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Stock;
use App\Models\TaxSetting;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    private function baseSetup(): array
    {
        $business = $this->createBusiness('Fashion', 'FSH');
        $outlet   = $this->createOutlet($business->id, 'Outlet Pusat', 'FSH-01');
        $owner    = $this->createUser(UserRole::OWNER, $business->id);
        $cashier  = $this->createUser(UserRole::CASHIER, $business->id, $outlet->id);
        $category = Category::create(['business_id' => $business->id, 'name' => 'Pakaian']);

        $product1 = Product::create([
            'business_id' => $business->id,
            'category_id' => $category->id,
            'name'        => 'Kaos Polos',
            'sku'         => 'KPS-001',
            'price'       => 89000,
            'is_active'   => true,
        ]);

        $product2 = Product::create([
            'business_id' => $business->id,
            'category_id' => $category->id,
            'name'        => 'Celana Chino',
            'sku'         => 'CCH-001',
            'price'       => 225000,
            'is_active'   => true,
        ]);

        $stock1 = Stock::create([
            'product_id'    => $product1->id,
            'variant_id'    => 0,
            'outlet_id'     => $outlet->id,
            'quantity'      => 100,
            'min_threshold' => 5,
        ]);

        $stock2 = Stock::create([
            'product_id'    => $product2->id,
            'variant_id'    => 0,
            'outlet_id'     => $outlet->id,
            'quantity'      => 50,
            'min_threshold' => 5,
        ]);

        $shift = Shift::create([
            'user_id'      => $cashier->id,
            'outlet_id'    => $outlet->id,
            'opened_at'    => now(),
            'opening_cash' => 500000,
            'status'       => 'open',
        ]);

        $tax = TaxSetting::create([
            'business_id' => $business->id,
            'name'        => 'PPN',
            'rate'        => 11.00,
            'is_active'   => true,
        ]);

        return compact(
            'business', 'outlet', 'owner', 'cashier',
            'product1', 'product2', 'stock1', 'stock2',
            'shift', 'tax'
        );
    }

    #[Test]
    public function create_transaction_cash_returns_201_and_status_paid()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1] = $this->baseSetup();

        $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 2]],
                 'discount_codes' => [],
                 'payment_method' => 'cash',
             ])
             ->assertCreated()
             ->assertJsonPath('data.payment_status', 'paid')
             ->assertJsonPath('data.payment_method', 'cash');
    }

    #[Test]
    public function create_transaction_qris_returns_201_and_status_pending()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1] = $this->baseSetup();

        $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'discount_codes' => [],
                 'payment_method' => 'qris',
             ])
             ->assertCreated()
             ->assertJsonPath('data.payment_status', 'pending');
    }

    #[Test]
    public function create_transaction_calculates_subtotal_correctly()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1, 'product2' => $p2] = $this->baseSetup();

        $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [
                     ['product_id' => $p1->id, 'quantity' => 2],
                     ['product_id' => $p2->id, 'quantity' => 1],
                 ],
                 'discount_codes' => [],
                 'payment_method' => 'cash',
             ])
             ->assertCreated()
             ->assertJsonPath('data.subtotal', 403000);
    }

    #[Test]
    public function create_transaction_applies_tax_correctly()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1] = $this->baseSetup();

        $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'discount_codes' => [],
                 'payment_method' => 'cash',
             ])
             ->assertCreated()
             ->assertJsonPath('data.tax_amount', 9790)
             ->assertJsonPath('data.total', 98790);
    }

    #[Test]
    public function create_transaction_deducts_stock_when_paid()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1, 'stock1' => $stock1] = $this->baseSetup();

        $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 3]],
                 'discount_codes' => [],
                 'payment_method' => 'cash',
             ])
             ->assertCreated();

        $this->assertDatabaseHas('stocks', ['id' => $stock1->id, 'quantity' => 97]);
        $this->assertDatabaseHas('stock_mutations', [
            'type'            => 'sale',
            'quantity_change' => -3,
            'quantity_before' => 100,
            'quantity_after'  => 97,
        ]);
    }

    #[Test]
    public function create_transaction_qris_does_not_deduct_stock()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1, 'stock1' => $stock1] = $this->baseSetup();

        $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 2]],
                 'discount_codes' => [],
                 'payment_method' => 'qris',
             ])
             ->assertCreated();

        $this->assertDatabaseHas('stocks', ['id' => $stock1->id, 'quantity' => 100]);
    }

    #[Test]
    public function create_transaction_generates_correct_transaction_code()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1] = $this->baseSetup();

        $response = $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'discount_codes' => [],
                 'payment_method' => 'cash',
             ])
             ->assertCreated();

        $code = $response->json('data.transaction_code');
        $date = now()->format('Ymd');

        $this->assertStringContainsString('FSH-01', $code);
        $this->assertStringContainsString($date, $code);
        $this->assertStringEndsWith('0001', $code);
    }

    #[Test]
    public function create_transaction_snapshots_product_name_and_price()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1] = $this->baseSetup();

        $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'discount_codes' => [],
                 'payment_method' => 'cash',
             ])
             ->assertCreated();

        $p1->update(['name' => 'Nama Baru', 'price' => 999999]);

        $this->assertDatabaseHas('transaction_items', [
            'product_name' => 'Kaos Polos',
            'unit_price'   => 89000,
        ]);
    }

    #[Test]
    public function create_transaction_with_percentage_discount()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1, 'business' => $business] = $this->baseSetup();

        Discount::create([
            'business_id'  => $business->id,
            'code'         => 'HEMAT10',
            'type'         => 'percentage',
            'value'        => 10,
            'min_purchase' => 0,
            'is_active'    => true,
        ]);

        $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'discount_codes' => ['HEMAT10'],
                 'payment_method' => 'cash',
             ])
             ->assertCreated()
             ->assertJsonPath('data.discount_amount', 8900);
    }

    #[Test]
    public function create_transaction_with_multiple_discounts_stackable()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1, 'business' => $business] = $this->baseSetup();

        Discount::create(['business_id' => $business->id, 'code' => 'DIS1', 'type' => 'percentage', 'value' => 10,   'min_purchase' => 0, 'is_active' => true]);
        Discount::create(['business_id' => $business->id, 'code' => 'DIS2', 'type' => 'fixed',      'value' => 5000, 'min_purchase' => 0, 'is_active' => true]);

        $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'discount_codes' => ['DIS1', 'DIS2'],
                 'payment_method' => 'cash',
             ])
             ->assertCreated()
             ->assertJsonPath('data.discount_amount', 13900);
    }

    #[Test]
    public function create_transaction_discount_increments_used_count()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1, 'business' => $business] = $this->baseSetup();

        $discount = Discount::create([
            'business_id'  => $business->id,
            'code'         => 'PROMO1',
            'type'         => 'fixed',
            'value'        => 10000,
            'min_purchase' => 0,
            'is_active'    => true,
            'used_count'   => 0,
        ]);

        $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'discount_codes' => ['PROMO1'],
                 'payment_method' => 'cash',
             ])
             ->assertCreated();

        $this->assertDatabaseHas('discounts', ['id' => $discount->id, 'used_count' => 1]);
    }

    #[Test]
    public function create_transaction_shift_closed_returns_422()
    {
        ['cashier' => $cashier, 'outlet' => $outlet, 'product1' => $p1] = $this->baseSetup();

        $closedShift = Shift::create([
            'user_id'      => $cashier->id,
            'outlet_id'    => $outlet->id,
            'opened_at'    => now()->subHour(),
            'closed_at'    => now(),
            'opening_cash' => 500000,
            'closing_cash' => 750000,
            'status'       => 'closed',
        ]);

        $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $closedShift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'payment_method' => 'cash',
             ])
             ->assertStatus(422);
    }

    #[Test]
    public function create_transaction_stock_insufficient_returns_422()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1, 'stock1' => $stock1] = $this->baseSetup();

        $stock1->update(['quantity' => 2]);

        $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 5]],
                 'payment_method' => 'cash',
             ])
             ->assertStatus(422);
    }

    #[Test]
    public function create_transaction_invalid_discount_code_returns_422()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1] = $this->baseSetup();

        $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'discount_codes' => ['KODEGHOIB'],
                 'payment_method' => 'cash',
             ])
             ->assertStatus(422);
    }

    #[Test]
    public function create_transaction_discount_max_uses_exceeded_returns_422()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1, 'business' => $business] = $this->baseSetup();

        Discount::create([
            'business_id'  => $business->id,
            'code'         => 'HABIS',
            'type'         => 'fixed',
            'value'        => 5000,
            'min_purchase' => 0,
            'is_active'    => true,
            'max_uses'     => 1,
            'used_count'   => 1,
        ]);

        $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'discount_codes' => ['HABIS'],
                 'payment_method' => 'cash',
             ])
             ->assertStatus(422);
    }

    #[Test]
    public function create_transaction_discount_min_purchase_not_met_returns_422()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1, 'business' => $business] = $this->baseSetup();

        Discount::create([
            'business_id'  => $business->id,
            'code'         => 'MIN500K',
            'type'         => 'fixed',
            'value'        => 50000,
            'min_purchase' => 500000,
            'is_active'    => true,
        ]);

        $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'discount_codes' => ['MIN500K'],
                 'payment_method' => 'cash',
             ])
             ->assertStatus(422);
    }

    #[Test]
    public function create_transaction_other_business_shift_returns_403()
    {
        ['cashier' => $cashier, 'product1' => $p1] = $this->baseSetup();

        $other       = $this->createBusiness('Other', 'OTH');
        $otherOutlet = $this->createOutlet($other->id, 'Other Outlet', 'OTH-01');
        $otherUser   = $this->createUser(UserRole::OWNER, $other->id);
        $otherShift  = Shift::create([
            'user_id'      => $otherUser->id,
            'outlet_id'    => $otherOutlet->id,
            'opened_at'    => now(),
            'opening_cash' => 0,
            'status'       => 'open',
        ]);

        $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $otherShift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'payment_method' => 'cash',
             ])
             ->assertForbidden();
    }

    #[Test]
    public function confirm_payment_pending_transaction_deducts_stock()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1, 'stock1' => $stock1] = $this->baseSetup();

        $response = $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 2]],
                 'discount_codes' => [],
                 'payment_method' => 'qris',
             ])
             ->assertCreated();

        $transactionId = $response->json('data.id');

        $this->assertDatabaseHas('stocks', ['id' => $stock1->id, 'quantity' => 100]);

        $this->actingAs($cashier)
             ->putJson("/api/transactions/{$transactionId}/confirm-payment", [
                 'payment_method' => 'qris',
             ])
             ->assertOk()
             ->assertJsonPath('data.payment_status', 'paid');

        $this->assertDatabaseHas('stocks', ['id' => $stock1->id, 'quantity' => 98]);
    }

    #[Test]
    public function confirm_payment_already_paid_returns_422()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1] = $this->baseSetup();

        $response = $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'payment_method' => 'cash',
             ])
             ->assertCreated();

        $this->actingAs($cashier)
             ->putJson("/api/transactions/{$response->json('data.id')}/confirm-payment", [
                 'payment_method' => 'cash',
             ])
             ->assertStatus(422);
    }

    #[Test]
    public function get_all_transactions_returns_own_business_only()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1] = $this->baseSetup();

        $this->actingAs($cashier)->postJson('/api/transactions', [
            'shift_id' => $shift->id,
            'items'    => [['product_id' => $p1->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ]);

        $other        = $this->createBusiness('Other', 'OTH');
        $otherOutlet  = $this->createOutlet($other->id, 'Other Outlet', 'OTH-01');
        $otherUser    = $this->createUser(UserRole::CASHIER, $other->id, $otherOutlet->id);
        $otherProduct = Product::create(['business_id' => $other->id, 'name' => 'X', 'sku' => 'X-001', 'price' => 10000, 'is_active' => true]);
        Stock::create(['product_id' => $otherProduct->id, 'variant_id' => 0, 'outlet_id' => $otherOutlet->id, 'quantity' => 50]);
        $otherShift = Shift::create(['user_id' => $otherUser->id, 'outlet_id' => $otherOutlet->id, 'opened_at' => now(), 'opening_cash' => 0, 'status' => 'open']);
        $this->actingAs($otherUser)->postJson('/api/transactions', [
            'shift_id' => $otherShift->id,
            'items'    => [['product_id' => $otherProduct->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ]);

        $this->actingAs($cashier)
             ->getJson('/api/transactions')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function get_all_transactions_filter_by_payment_status()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1, 'product2' => $p2] = $this->baseSetup();

        $this->actingAs($cashier)->postJson('/api/transactions', ['shift_id' => $shift->id, 'items' => [['product_id' => $p1->id, 'quantity' => 1]], 'payment_method' => 'cash']);
        $this->actingAs($cashier)->postJson('/api/transactions', ['shift_id' => $shift->id, 'items' => [['product_id' => $p2->id, 'quantity' => 1]], 'payment_method' => 'qris']);

        $this->actingAs($cashier)
             ->getJson('/api/transactions?payment_status=pending')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function get_transaction_detail_returns_full_structure()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1] = $this->baseSetup();

        $response = $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'payment_method' => 'cash',
             ])
             ->assertCreated();

        $this->actingAs($cashier)
             ->getJson("/api/transactions/{$response->json('data.id')}")
             ->assertOk()
             ->assertJsonStructure([
                 'data' => [
                     'id', 'transaction_code', 'subtotal',
                     'discount_amount', 'tax_amount', 'total',
                     'payment_method', 'payment_status',
                     'items', 'discounts', 'taxes', 'outlet', 'shift',
                 ],
             ]);
    }

    #[Test]
    public function get_transaction_detail_other_business_returns_403()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1] = $this->baseSetup();

        $response = $this->actingAs($cashier)
             ->postJson('/api/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'payment_method' => 'cash',
             ]);

        $other     = $this->createBusiness('Other', 'OTH');
        $otherUser = $this->createUser(UserRole::OWNER, $other->id);

        $this->actingAs($otherUser)
             ->getJson("/api/transactions/{$response->json('data.id')}")
             ->assertForbidden();
    }
}
EOF
echo "✅ tests/Feature/TransactionTest.php"

echo ""
echo "✅ Selesai! 2 file test berhasil dibuat."
echo "   Jalankan dengan: php artisan test --filter=ShiftTest"
echo "                    php artisan test --filter=TransactionTest"
echo "                    php artisan test (semua test)"