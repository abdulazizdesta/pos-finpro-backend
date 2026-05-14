<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Stock;
use App\Models\TaxSetting;
use App\Models\Transaction;
use App\Models\TransactionItem;
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

    private function validPayload(int $shiftId, array $overrides = []): array
    {
        return array_merge([
            'shift_id'       => $shiftId,
            'items'          => [
                ['product_id' => 1, 'quantity' => 2],
            ],
            'discount_codes' => [],
            'payment_method' => 'cash',
            'notes'          => null,
        ], $overrides);
    }

    // ─── POST Create — Happy Path ─────────────────────────────────────────────

    #[Test]
    public function create_transaction_cash_returns_201_and_status_paid()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1] = $this->baseSetup();

        $this->actingAs($cashier)
             ->postJson('/api/v1/transactions', [
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
             ->postJson('/api/v1/transactions', [
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

        // subtotal: (89000 * 2) + (225000 * 1) = 403000
        $this->actingAs($cashier)
             ->postJson('/api/v1/transactions', [
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

        // subtotal: 89000 * 1 = 89000
        // taxable: 89000 (tidak ada diskon)
        // tax 11%: round(89000 * 11 / 100) = 9790
        // total: 89000 + 9790 = 98790
        $this->actingAs($cashier)
             ->postJson('/api/v1/transactions', [
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
             ->postJson('/api/v1/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 3]],
                 'discount_codes' => [],
                 'payment_method' => 'cash',
             ])
             ->assertCreated();

        $this->assertDatabaseHas('stocks', [
            'id'       => $stock1->id,
            'quantity' => 97, // 100 - 3
        ]);

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
             ->postJson('/api/v1/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 2]],
                 'discount_codes' => [],
                 'payment_method' => 'qris',
             ])
             ->assertCreated();

        // Stok tidak berkurang karena masih pending
        $this->assertDatabaseHas('stocks', [
            'id'       => $stock1->id,
            'quantity' => 100,
        ]);
    }

    #[Test]
    public function create_transaction_generates_correct_transaction_code()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1] = $this->baseSetup();

        $response = $this->actingAs($cashier)
             ->postJson('/api/v1/transactions', [
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
             ->postJson('/api/v1/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'discount_codes' => [],
                 'payment_method' => 'cash',
             ])
             ->assertCreated();

        // Ubah nama produk setelah transaksi
        $p1->update(['name' => 'Nama Baru', 'price' => 999999]);

        // Snapshot di transaction_items harus tetap nama & harga lama
        $this->assertDatabaseHas('transaction_items', [
            'product_name' => 'Kaos Polos',
            'unit_price'   => 89000,
        ]);
    }

    // ─── POST Create — Discount ───────────────────────────────────────────────

    #[Test]
    public function create_transaction_with_percentage_discount()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1, 'business' => $business] = $this->baseSetup();

        Discount::create([
            'business_id' => $business->id,
            'code'        => 'HEMAT10',
            'name'        => 'Diskon 10%',
            'type'        => 'percentage',
            'value'       => 10,
            'min_purchase'=> 0,
            'is_active'   => true,
        ]);

        // subtotal: 89000, diskon 10% = 8900, taxable = 80100, tax 11% = 8811, total = 88911
        $this->actingAs($cashier)
             ->postJson('/api/v1/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'discount_codes' => ['HEMAT10'],
                 'payment_method' => 'cash',
             ])
             ->assertCreated()
             ->assertJsonPath('data.discount_amount', 8900);
    }

    #[Test]
    public function create_transaction_with_fixed_discount()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1, 'business' => $business] = $this->baseSetup();

        Discount::create([
            'business_id' => $business->id,
            'code'        => 'FLAT20K',
            'name'        => 'Diskon 20 Ribu',
            'type'        => 'fixed',
            'value'       => 20000,
            'min_purchase'=> 0,
            'is_active'   => true,
        ]);

        $this->actingAs($cashier)
             ->postJson('/api/v1/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'discount_codes' => ['FLAT20K'],
                 'payment_method' => 'cash',
             ])
             ->assertCreated()
             ->assertJsonPath('data.discount_amount', 20000);
    }

    #[Test]
    public function create_transaction_with_multiple_discounts_stackable()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1, 'business' => $business] = $this->baseSetup();

        Discount::create(['business_id' => $business->id, 'code' => 'DIS1', 'type' => 'percentage', 'value' => 10, 'min_purchase' => 0, 'is_active' => true]);
        Discount::create(['business_id' => $business->id, 'code' => 'DIS2', 'type' => 'fixed',      'value' => 5000, 'min_purchase' => 0, 'is_active' => true]);

        // subtotal 89000, diskon1: 8900, diskon2: 5000 → total diskon 13900
        $this->actingAs($cashier)
             ->postJson('/api/v1/transactions', [
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
            'business_id' => $business->id,
            'code'        => 'PROMO1',
            'type'        => 'fixed',
            'value'       => 10000,
            'min_purchase'=> 0,
            'is_active'   => true,
            'used_count'  => 0,
        ]);

        $this->actingAs($cashier)
             ->postJson('/api/v1/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'discount_codes' => ['PROMO1'],
                 'payment_method' => 'cash',
             ])
             ->assertCreated();

        $this->assertDatabaseHas('discounts', [
            'id'         => $discount->id,
            'used_count' => 1,
        ]);
    }

    // ─── POST Create — Validation Errors ─────────────────────────────────────

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
             ->postJson('/api/v1/transactions', [
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
             ->postJson('/api/v1/transactions', [
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
             ->postJson('/api/v1/transactions', [
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
            'business_id' => $business->id,
            'code'        => 'HABIS',
            'type'        => 'fixed',
            'value'       => 5000,
            'min_purchase'=> 0,
            'is_active'   => true,
            'max_uses'    => 1,
            'used_count'  => 1, // sudah habis
        ]);

        $this->actingAs($cashier)
             ->postJson('/api/v1/transactions', [
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
            'business_id' => $business->id,
            'code'        => 'MIN500K',
            'type'        => 'fixed',
            'value'       => 50000,
            'min_purchase'=> 500000, // min 500rb, tapi produk cuma 89rb
            'is_active'   => true,
        ]);

        $this->actingAs($cashier)
             ->postJson('/api/v1/transactions', [
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
             ->postJson('/api/v1/transactions', [
                 'shift_id'       => $otherShift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'payment_method' => 'cash',
             ])
             ->assertForbidden();
    }

    // ─── PUT Confirm Payment ──────────────────────────────────────────────────

    #[Test]
    public function confirm_payment_pending_transaction_deducts_stock()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1, 'stock1' => $stock1] = $this->baseSetup();

        // Buat transaksi QRIS → pending
        $response = $this->actingAs($cashier)
             ->postJson('/api/v1/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 2]],
                 'discount_codes' => [],
                 'payment_method' => 'qris',
             ])
             ->assertCreated();

        $transactionId = $response->json('data.id');

        // Stok belum berkurang
        $this->assertDatabaseHas('stocks', ['id' => $stock1->id, 'quantity' => 100]);

        // Konfirmasi pembayaran
        $this->actingAs($cashier)
             ->putJson("/api/v1/transactions/{$transactionId}/confirm-payment", [
                 'payment_method' => 'qris',
             ])
             ->assertOk()
             ->assertJsonPath('data.payment_status', 'paid');

        // Stok sekarang berkurang
        $this->assertDatabaseHas('stocks', ['id' => $stock1->id, 'quantity' => 98]);
    }

    #[Test]
    public function confirm_payment_already_paid_returns_422()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1] = $this->baseSetup();

        $response = $this->actingAs($cashier)
             ->postJson('/api/v1/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'payment_method' => 'cash', // langsung paid
             ])
             ->assertCreated();

        $transactionId = $response->json('data.id');

        $this->actingAs($cashier)
             ->putJson("/api/v1/transactions/{$transactionId}/confirm-payment", [
                 'payment_method' => 'cash',
             ])
             ->assertStatus(422);
    }

    // ─── GET All ─────────────────────────────────────────────────────────────

    #[Test]
    public function get_all_transactions_returns_own_business_only()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1] = $this->baseSetup();

        $this->actingAs($cashier)
             ->postJson('/api/v1/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'payment_method' => 'cash',
             ]);

        // Buat transaksi bisnis lain
        $other       = $this->createBusiness('Other', 'OTH');
        $otherOutlet = $this->createOutlet($other->id, 'Other Outlet', 'OTH-01');
        $otherUser   = $this->createUser(UserRole::CASHIER, $other->id, $otherOutlet->id);
        $otherProduct = Product::create(['business_id' => $other->id, 'name' => 'X', 'sku' => 'X-001', 'price' => 10000, 'is_active' => true]);
        Stock::create(['product_id' => $otherProduct->id, 'variant_id' => 0, 'outlet_id' => $otherOutlet->id, 'quantity' => 50]);
        $otherShift = Shift::create(['user_id' => $otherUser->id, 'outlet_id' => $otherOutlet->id, 'opened_at' => now(), 'opening_cash' => 0, 'status' => 'open']);
        $this->actingAs($otherUser)
             ->postJson('/api/v1/transactions', [
                 'shift_id'       => $otherShift->id,
                 'items'          => [['product_id' => $otherProduct->id, 'quantity' => 1]],
                 'payment_method' => 'cash',
             ]);

        $this->actingAs($cashier)
             ->getJson('/api/v1/transactions')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function get_all_transactions_filter_by_payment_status()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1, 'product2' => $p2] = $this->baseSetup();

        $this->actingAs($cashier)->postJson('/api/v1/transactions', [
            'shift_id' => $shift->id, 'items' => [['product_id' => $p1->id, 'quantity' => 1]], 'payment_method' => 'cash',
        ]);
        $this->actingAs($cashier)->postJson('/api/v1/transactions', [
            'shift_id' => $shift->id, 'items' => [['product_id' => $p2->id, 'quantity' => 1]], 'payment_method' => 'qris',
        ]);

        $this->actingAs($cashier)
             ->getJson('/api/v1/transactions?payment_status=pending')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    // ─── GET Detail ──────────────────────────────────────────────────────────

    #[Test]
    public function get_transaction_detail_returns_full_structure()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1] = $this->baseSetup();

        $response = $this->actingAs($cashier)
             ->postJson('/api/v1/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'payment_method' => 'cash',
             ])
             ->assertCreated();

        $transactionId = $response->json('data.id');

        $this->actingAs($cashier)
             ->getJson("/api/v1/transactions/{$transactionId}")
             ->assertOk()
             ->assertJsonStructure([
                 'data' => [
                     'id', 'transaction_code', 'subtotal',
                     'discount_amount', 'tax_amount', 'total',
                     'payment_method', 'payment_status',
                     'items', 'discounts', 'taxes',
                     'outlet', 'shift',
                 ],
             ]);
    }

    #[Test]
    public function get_transaction_detail_other_business_returns_403()
    {
        ['cashier' => $cashier, 'shift' => $shift, 'product1' => $p1] = $this->baseSetup();

        $response = $this->actingAs($cashier)
             ->postJson('/api/v1/transactions', [
                 'shift_id'       => $shift->id,
                 'items'          => [['product_id' => $p1->id, 'quantity' => 1]],
                 'payment_method' => 'cash',
             ]);

        $transactionId = $response->json('data.id');

        $other     = $this->createBusiness('Other', 'OTH');
        $otherUser = $this->createUser(UserRole::OWNER, $other->id);

        $this->actingAs($otherUser)
             ->getJson("/api/v1/transactions/{$transactionId}")
             ->assertForbidden();
    }
}