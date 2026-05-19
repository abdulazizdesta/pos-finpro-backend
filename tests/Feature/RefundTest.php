<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\TransactionItem;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RefundTest extends TestCase
{
    private function baseSetup(): array
    {
        $business = $this->createBusiness('Fashion', 'FSH');
        $outlet = $this->createOutlet($business->id, 'Outlet Pusat', 'FSH-01');
        $owner = $this->createUser(UserRole::OWNER, $business->id);
        $cashier = $this->createUser(UserRole::CASHIER, $business->id, $outlet->id);
        $category = Category::create(['business_id' => $business->id, 'name' => 'Pakaian']);

        $product = Product::create([
            'business_id' => $business->id,
            'category_id' => $category->id,
            'name' => 'Kaos Polos',
            'sku' => 'KPS-001',
            'price' => 89000,
            'is_active' => true,
        ]);

        $stock = Stock::create([
            'product_id' => $product->id,
            'variant_id' => 0,
            'outlet_id' => $outlet->id,
            'quantity' => 100,
            'min_threshold' => 5,
        ]);

        $shift = Shift::create([
            'user_id' => $cashier->id,
            'outlet_id' => $outlet->id,
            'opened_at' => now(),
            'opening_cash' => 500000,
            'status' => 'open',
        ]);

        return compact('business', 'outlet', 'owner', 'cashier', 'product', 'stock', 'shift');
    }

    private function createPaidTransaction(array $setup): array
    {
        $response = $this->actingAs($setup['cashier'])
            ->postJson('/api/v1/transactions', [
                'shift_id' => $setup['shift']->id,
                'items' => [['product_id' => $setup['product']->id, 'quantity' => 3]],
                'discount_codes' => [],
                'payment_method' => 'cash',
            ])
            ->assertCreated();

        $transaction = Transaction::find($response->json('data.id'));
        $item = TransactionItem::where('transaction_id', $transaction->id)->first();

        return compact('transaction', 'item');
    }

    // ─── Happy Path ──────────────────────────────────────────────────────────

    #[Test]
    public function refund_partial_item_returns_201()
    {
        $setup = $this->baseSetup();
        ['transaction' => $trx, 'item' => $item] = $this->createPaidTransaction($setup);

        $this->actingAs($setup['cashier'])
            ->postJson("/api/v1/transactions/{$trx->id}/refund", [
                'items' => [['transaction_item_id' => $item->id, 'quantity' => 1]],
                'reason' => 'Barang rusak',
            ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'amount', 'items', 'transaction']]);
    }

    #[Test]
    public function refund_increments_stock_back()
    {
        $setup = $this->baseSetup();
        ['transaction' => $trx, 'item' => $item] = $this->createPaidTransaction($setup);

        // Stok berkurang 3 setelah transaksi
        $this->assertDatabaseHas('stocks', ['id' => $setup['stock']->id, 'quantity' => 97]);

        $this->actingAs($setup['cashier'])
            ->postJson("/api/v1/transactions/{$trx->id}/refund", [
                'items' => [['transaction_item_id' => $item->id, 'quantity' => 1]],
                'reason' => 'Barang rusak',
            ])
            ->assertCreated();

        // Stok kembali +1
        $this->assertDatabaseHas('stocks', ['id' => $setup['stock']->id, 'quantity' => 98]);
    }

    #[Test]
    public function refund_updates_refunded_quantity()
    {
        $setup = $this->baseSetup();
        ['transaction' => $trx, 'item' => $item] = $this->createPaidTransaction($setup);

        $this->actingAs($setup['cashier'])
            ->postJson("/api/v1/transactions/{$trx->id}/refund", [
                'items' => [['transaction_item_id' => $item->id, 'quantity' => 2]],
                'reason' => 'Barang rusak',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('transaction_items', [
            'id' => $item->id,
            'refunded_quantity' => 2,
        ]);
    }

    #[Test]
    public function refund_changes_transaction_status_to_refunded()
    {
        $setup = $this->baseSetup();
        ['transaction' => $trx, 'item' => $item] = $this->createPaidTransaction($setup);

        $this->actingAs($setup['cashier'])
            ->postJson("/api/v1/transactions/{$trx->id}/refund", [
                'items' => [['transaction_item_id' => $item->id, 'quantity' => 3]],
                'reason' => 'Semua rusak',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('transactions', [
            'id' => $trx->id,
            'payment_status' => 'refunded',
        ]);
    }

    #[Test]
    public function refund_records_stock_mutation_type_refund()
    {
        $setup = $this->baseSetup();
        ['transaction' => $trx, 'item' => $item] = $this->createPaidTransaction($setup);

        $this->actingAs($setup['cashier'])
            ->postJson("/api/v1/transactions/{$trx->id}/refund", [
                'items' => [['transaction_item_id' => $item->id, 'quantity' => 1]],
                'reason' => 'Test',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('stock_mutations', [
            'type' => 'refund',
            'quantity_change' => 1,
        ]);
    }

    // ─── Validation Errors ───────────────────────────────────────────────────

    #[Test]
    public function refund_pending_transaction_returns_422()
    {
        $setup = $this->baseSetup();

        $response = $this->actingAs($setup['cashier'])
            ->postJson('/api/v1/transactions', [
                'shift_id' => $setup['shift']->id,
                'items' => [['product_id' => $setup['product']->id, 'quantity' => 1]],
                'payment_method' => 'qris',
            ])
            ->assertCreated();

        $trx = Transaction::find($response->json('data.id'));
        $item = TransactionItem::where('transaction_id', $trx->id)->first();

        $this->actingAs($setup['cashier'])
            ->postJson("/api/v1/transactions/{$trx->id}/refund", [
                'items' => [['transaction_item_id' => $item->id, 'quantity' => 1]],
                'reason' => 'Test',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function refund_quantity_exceeds_refundable_returns_422()
    {
        $setup = $this->baseSetup();
        ['transaction' => $trx, 'item' => $item] = $this->createPaidTransaction($setup);

        $this->actingAs($setup['cashier'])
            ->postJson("/api/v1/transactions/{$trx->id}/refund", [
                'items' => [['transaction_item_id' => $item->id, 'quantity' => 99]],
                'reason' => 'Test',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function refund_item_not_belong_to_transaction_returns_422()
    {
        $setup = $this->baseSetup();
        ['transaction' => $trx] = $this->createPaidTransaction($setup);

        $this->actingAs($setup['cashier'])
            ->postJson("/api/v1/transactions/{$trx->id}/refund", [
                'items' => [['transaction_item_id' => 9999, 'quantity' => 1]],
                'reason' => 'Test',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function refund_double_refund_returns_422()
    {
        $setup = $this->baseSetup();
        ['transaction' => $trx, 'item' => $item] = $this->createPaidTransaction($setup);

        // Refund pertama — full
        $this->actingAs($setup['cashier'])
            ->postJson("/api/v1/transactions/{$trx->id}/refund", [
                'items' => [['transaction_item_id' => $item->id, 'quantity' => 3]],
                'reason' => 'Rusak',
            ])
            ->assertCreated();

        // Refund kedua — sudah refunded
        $this->actingAs($setup['cashier'])
            ->postJson("/api/v1/transactions/{$trx->id}/refund", [
                'items' => [['transaction_item_id' => $item->id, 'quantity' => 1]],
                'reason' => 'Test',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function refund_other_business_transaction_returns_403()
    {
        $setup = $this->baseSetup();
        ['transaction' => $trx, 'item' => $item] = $this->createPaidTransaction($setup);

        $other = $this->createBusiness('Other', 'OTH');
        $otherOwner = $this->createUser(UserRole::OWNER, $other->id);

        $this->actingAs($otherOwner)
            ->postJson("/api/v1/transactions/{$trx->id}/refund", [
                'items' => [['transaction_item_id' => $item->id, 'quantity' => 1]],
                'reason' => 'Hack',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function refund_after_24_hours_returns_422()
    {
        $setup = $this->baseSetup();
        ['transaction' => $trx, 'item' => $item] = $this->createPaidTransaction($setup);

        // Manipulasi created_at jadi 25 jam yang lalu
        $trx->timestamps = false;
        Transaction::withoutTimestamps(function () use ($trx) {
            $trx->created_at = now()->subHours(25);
            $trx->save();
        });

        $this->actingAs($setup['cashier'])
            ->postJson("/api/v1/transactions/{$trx->id}/refund", [
                'items' => [['transaction_item_id' => $item->id, 'quantity' => 1]],
                'reason' => 'Test',
            ])
            ->assertStatus(422);
    }
}