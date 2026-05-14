<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMutation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockTest extends TestCase
{
    private function baseSetup(): array
    {
        $business = $this->createBusiness('Fashion', 'FSH');
        $outlet   = $this->createOutlet($business->id, 'Outlet Pusat', 'FSH-01');
        $owner    = $this->createUser(UserRole::OWNER, $business->id);
        $admin    = $this->createUser(UserRole::ADMIN, $business->id, $outlet->id);
        $category = Category::create(['business_id' => $business->id, 'name' => 'Beauty']);
        $product  = Product::create([
            'business_id' => $business->id,
            'category_id' => $category->id,
            'name'        => 'Kopi Susu',
            'sku'         => 'KOPI-001',
            'price'       => 25000,
            'is_active'   => true,
        ]);

        return compact('business', 'outlet', 'owner', 'admin', 'category', 'product');
    }

    private function createStock(int $productId, int $outletId, int $quantity = 50, int $minThreshold = 5): Stock
    {
        return Stock::create([
            'product_id'    => $productId,
            'variant_id'    => 0,
            'outlet_id'     => $outletId,
            'quantity'      => $quantity,
            'min_threshold' => $minThreshold,
        ]);
    }

    // ─── GET All ─────────────────────────────────────────────────────────────

    #[Test]
    public function get_all_stocks_returns_only_own_business()
    {
        ['owner' => $owner, 'product' => $product, 'outlet' => $outlet] = $this->baseSetup();
        $this->createStock($product->id, $outlet->id);

        // Buat stok bisnis lain
        $other        = $this->createBusiness('Other', 'OTH');
        $otherOutlet  = $this->createOutlet($other->id, 'Other Outlet', 'OTH-01');
        $otherProduct = Product::create(['business_id' => $other->id, 'name' => 'Other', 'sku' => 'OTH-001', 'price' => 5000]);
        $this->createStock($otherProduct->id, $otherOutlet->id);

        $this->actingAs($owner)
             ->getJson('/api/v1/stocks')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function get_all_stocks_filter_by_outlet()
    {
        ['owner' => $owner, 'product' => $product, 'outlet' => $outlet, 'business' => $business] = $this->baseSetup();
        $outlet2 = $this->createOutlet($business->id, 'Outlet Selatan', 'FSH-02');

        $product2 = Product::create(['business_id' => $business->id, 'name' => 'Teh', 'sku' => 'TEH-001', 'price' => 10000]);

        $this->createStock($product->id, $outlet->id);
        $this->createStock($product2->id, $outlet2->id);

        $this->actingAs($owner)
             ->getJson("/api/v1/stocks?outlet_id={$outlet->id}")
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function get_all_stocks_filter_by_product()
    {
        ['owner' => $owner, 'product' => $product, 'outlet' => $outlet, 'business' => $business] = $this->baseSetup();

        $product2 = Product::create(['business_id' => $business->id, 'name' => 'Teh', 'sku' => 'TEH-001', 'price' => 10000]);

        $this->createStock($product->id, $outlet->id);
        $this->createStock($product2->id, $outlet->id);

        $this->actingAs($owner)
             ->getJson("/api/v1/stocks?product_id={$product->id}")
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    // ─── GET Detail ──────────────────────────────────────────────────────────

    #[Test]
    public function get_stock_detail_returns_mutations_and_low_stock_flag()
    {
        ['owner' => $owner, 'product' => $product, 'outlet' => $outlet] = $this->baseSetup();
        $stock = $this->createStock($product->id, $outlet->id, 3, 5); // qty < min_threshold

        $this->actingAs($owner)
             ->getJson("/api/v1/stocks/{$stock->id}")
             ->assertOk()
             ->assertJsonPath('data.low_stock', true)
             ->assertJsonStructure(['data' => ['mutations']]);
    }

    #[Test]
    public function get_stock_detail_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();

        $other       = $this->createBusiness('Other', 'OTH');
        $otherOutlet = $this->createOutlet($other->id, 'Other Outlet', 'OTH-01');
        $otherProduct = Product::create(['business_id' => $other->id, 'name' => 'Other', 'sku' => 'OTH-001', 'price' => 5000]);
        $otherStock  = $this->createStock($otherProduct->id, $otherOutlet->id);

        $this->actingAs($owner)
             ->getJson("/api/v1/stocks/{$otherStock->id}")
             ->assertForbidden();
    }

    // ─── POST Create ─────────────────────────────────────────────────────────

    #[Test]
    public function create_stock_valid_returns_201_with_initial_mutation()
    {
        ['owner' => $owner, 'product' => $product, 'outlet' => $outlet] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/stocks', [
                 'product_id'    => $product->id,
                 'outlet_id'     => $outlet->id,
                 'quantity'      => 50,
                 'min_threshold' => 10,
             ])
             ->assertCreated()
             ->assertJsonPath('success', true);

        // Cek mutasi awal tercatat
        $this->assertDatabaseHas('stock_mutations', [
            'type'            => 'restock',
            'quantity_before' => 0,
            'quantity_after'  => 50,
            'notes'           => 'initial_stock',
        ]);
    }

    #[Test]
    public function create_stock_duplicate_returns_422()
    {
        ['owner' => $owner, 'product' => $product, 'outlet' => $outlet] = $this->baseSetup();
        $this->createStock($product->id, $outlet->id);

        $this->actingAs($owner)
             ->postJson('/api/v1/stocks', [
                 'product_id' => $product->id,
                 'outlet_id'  => $outlet->id,
                 'quantity'   => 10,
             ])
             ->assertStatus(422);
    }

    #[Test]
    public function create_stock_outlet_other_business_returns_403()
    {
        ['owner' => $owner, 'product' => $product] = $this->baseSetup();

        $other       = $this->createBusiness('Other', 'OTH');
        $otherOutlet = $this->createOutlet($other->id, 'Other Outlet', 'OTH-01');

        $this->actingAs($owner)
             ->postJson('/api/v1/stocks', [
                 'product_id' => $product->id,
                 'outlet_id'  => $otherOutlet->id,
                 'quantity'   => 10,
             ])
             ->assertForbidden();
    }

    // ─── PUT Restock ─────────────────────────────────────────────────────────

    #[Test]
    public function restock_increases_quantity_and_records_mutation()
    {
        ['owner' => $owner, 'product' => $product, 'outlet' => $outlet] = $this->baseSetup();
        $stock = $this->createStock($product->id, $outlet->id, 50);

        $this->actingAs($owner)
             ->putJson("/api/v1/stocks/{$stock->id}/restock", [
                 'quantity' => 30,
                 'notes'    => 'Restock dari supplier',
             ])
             ->assertOk()
             ->assertJsonPath('data.quantity', 80);

        $this->assertDatabaseHas('stock_mutations', [
            'stock_id'        => $stock->id,
            'type'            => 'restock',
            'quantity_change' => 30,
            'quantity_before' => 50,
            'quantity_after'  => 80,
        ]);
    }

    #[Test]
    public function restock_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();

        $other        = $this->createBusiness('Other', 'OTH');
        $otherOutlet  = $this->createOutlet($other->id, 'Other Outlet', 'OTH-01');
        $otherProduct = Product::create(['business_id' => $other->id, 'name' => 'Other', 'sku' => 'OTH-001', 'price' => 5000]);
        $otherStock   = $this->createStock($otherProduct->id, $otherOutlet->id);

        $this->actingAs($owner)
             ->putJson("/api/v1/stocks/{$otherStock->id}/restock", ['quantity' => 10])
             ->assertForbidden();
    }

    // ─── PUT Adjust ──────────────────────────────────────────────────────────

    #[Test]
    public function adjust_positive_increases_quantity()
    {
        ['owner' => $owner, 'product' => $product, 'outlet' => $outlet] = $this->baseSetup();
        $stock = $this->createStock($product->id, $outlet->id, 50);

        $this->actingAs($owner)
             ->putJson("/api/v1/stocks/{$stock->id}/adjust", [
                 'quantity_change' => 5,
                 'notes'           => 'Koreksi lebih',
             ])
             ->assertOk()
             ->assertJsonPath('data.quantity', 55);
    }

    #[Test]
    public function adjust_negative_decreases_quantity()
    {
        ['owner' => $owner, 'product' => $product, 'outlet' => $outlet] = $this->baseSetup();
        $stock = $this->createStock($product->id, $outlet->id, 50);

        $this->actingAs($owner)
             ->putJson("/api/v1/stocks/{$stock->id}/adjust", [
                 'quantity_change' => -10,
                 'notes'           => 'Produk rusak',
             ])
             ->assertOk()
             ->assertJsonPath('data.quantity', 40);

        $this->assertDatabaseHas('stock_mutations', [
            'type'            => 'adjustment',
            'quantity_change' => -10,
            'quantity_before' => 50,
            'quantity_after'  => 40,
        ]);
    }

    #[Test]
    public function adjust_would_go_negative_returns_422()
    {
        ['owner' => $owner, 'product' => $product, 'outlet' => $outlet] = $this->baseSetup();
        $stock = $this->createStock($product->id, $outlet->id, 5);

        $this->actingAs($owner)
             ->putJson("/api/v1/stocks/{$stock->id}/adjust", [
                 'quantity_change' => -10,
                 'notes'           => 'Kurangi lebih dari stok',
             ])
             ->assertStatus(422);
    }

    #[Test]
    public function adjust_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();

        $other        = $this->createBusiness('Other', 'OTH');
        $otherOutlet  = $this->createOutlet($other->id, 'Other Outlet', 'OTH-01');
        $otherProduct = Product::create(['business_id' => $other->id, 'name' => 'Other', 'sku' => 'OTH-001', 'price' => 5000]);
        $otherStock   = $this->createStock($otherProduct->id, $otherOutlet->id);

        $this->actingAs($owner)
             ->putJson("/api/v1/stocks/{$otherStock->id}/adjust", [
                 'quantity_change' => -5,
                 'notes'           => 'Unauthorized',
             ])
             ->assertForbidden();
    }

    // ─── GET Mutations ───────────────────────────────────────────────────────

    #[Test]
    public function get_mutations_returns_own_business_only()
    {
        ['owner' => $owner, 'product' => $product, 'outlet' => $outlet] = $this->baseSetup();
        $stock = $this->createStock($product->id, $outlet->id, 50);

        StockMutation::create([
            'stock_id'        => $stock->id,
            'type'            => 'restock',
            'quantity_change' => 50,
            'quantity_before' => 0,
            'quantity_after'  => 50,
            'user_id'         => $owner->id,
            'notes'           => 'Test',
        ]);

        $this->actingAs($owner)
             ->getJson('/api/v1/stock-mutations')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function get_mutations_filter_by_type()
    {
        ['owner' => $owner, 'product' => $product, 'outlet' => $outlet] = $this->baseSetup();
        $stock = $this->createStock($product->id, $outlet->id, 50);

        StockMutation::create(['stock_id' => $stock->id, 'type' => 'restock',    'quantity_change' => 50,  'quantity_before' => 0,  'quantity_after' => 50,  'user_id' => $owner->id, 'notes' => 'Restock']);
        StockMutation::create(['stock_id' => $stock->id, 'type' => 'adjustment', 'quantity_change' => -5, 'quantity_before' => 50, 'quantity_after' => 45, 'user_id' => $owner->id, 'notes' => 'Adjust']);

        $this->actingAs($owner)
             ->getJson('/api/v1/stock-mutations?type=restock')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function get_mutations_filter_by_date_range()
    {
        ['owner' => $owner, 'product' => $product, 'outlet' => $outlet] = $this->baseSetup();
        $stock = $this->createStock($product->id, $outlet->id, 50);

        StockMutation::create(['stock_id' => $stock->id, 'type' => 'restock', 'quantity_change' => 50, 'quantity_before' => 0, 'quantity_after' => 50, 'user_id' => $owner->id, 'notes' => 'Test']);

        $today     = now()->format('Y-m-d');
        $tomorrow  = now()->addDay()->format('Y-m-d');

        $this->actingAs($owner)
             ->getJson("/api/v1/stock-mutations?date_from={$today}&date_to={$tomorrow}")
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }
}