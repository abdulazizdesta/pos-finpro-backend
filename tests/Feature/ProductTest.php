<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductTest extends TestCase
{
    private function baseSetup(): array
    {
        $business = $this->createBusiness('Fashion', 'FSH');
        $outlet   = $this->createOutlet($business->id);
        $owner    = $this->createUser(UserRole::OWNER, $business->id);
        $category = Category::create(['business_id' => $business->id, 'name' => 'Beauty']);
        $product  = Product::create([
            'business_id' => $business->id,
            'category_id' => $category->id,
            'name'        => 'Kopi Susu',
            'sku'         => 'KOPI-001',
            'price'       => 25000,
            'cost_price'  => 12000,
            'is_active'   => true,
        ]);

        return compact('business', 'outlet', 'owner', 'category', 'product');
    }

    // ─── GET All ─────────────────────────────────────────────────────────────

    /** @test TC-PRD-01 */
    #[Test]
    public function get_all_products_returns_only_own_business()
    {
        ['owner' => $owner] = $this->baseSetup();

        $other = $this->createBusiness('Other', 'OTH');
        Product::create(['business_id' => $other->id, 'name' => 'Other Product', 'sku' => 'OTH-001', 'price' => 10000]);

        $this->actingAs($owner)
             ->getJson('/api/v1/products')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    /** @test TC-PRD-02 */
    #[Test]
    public function get_all_products_with_search_filter()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        Product::create(['business_id' => $business->id, 'name' => 'Teh Manis', 'sku' => 'TEHM-001', 'price' => 10000]);

        $this->actingAs($owner)
             ->getJson('/api/v1/products?search=kopi')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    /** @test TC-PRD-03 */
    #[Test]
    public function get_all_products_with_category_filter()
    {
        ['owner' => $owner, 'business' => $business, 'category' => $category] = $this->baseSetup();
        $other = Category::create(['business_id' => $business->id, 'name' => 'Food']);
        Product::create(['business_id' => $business->id, 'category_id' => $other->id, 'name' => 'Roti', 'sku' => 'ROTI-001', 'price' => 5000]);

        $this->actingAs($owner)
             ->getJson("/api/v1/products?category_id={$category->id}")
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    /** @test TC-PRD-04 */
    #[Test]
    public function get_all_products_filter_inactive()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        Product::create(['business_id' => $business->id, 'name' => 'Nonaktif', 'sku' => 'NAKT-001', 'price' => 5000, 'is_active' => false]);

        $this->actingAs($owner)
             ->getJson('/api/v1/products?is_active=0')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    /** @test TC-PRD-05 */
    #[Test]
    public function get_all_products_superadmin_sees_all_businesses()
    {
        ['product' => $product] = $this->baseSetup();

        $superadmin = $this->createUser(UserRole::SUPERADMIN);
        $other      = $this->createBusiness('Other', 'OTH');
        Product::create(['business_id' => $other->id, 'name' => 'Other', 'sku' => 'OTH-001', 'price' => 5000]);

        $this->actingAs($superadmin)
             ->getJson('/api/v1/products')
             ->assertOk()
             ->assertJsonPath('meta.total', 2);
    }

    // ─── GET by ID ───────────────────────────────────────────────────────────

    /** @test TC-PRD-06 */
    #[Test]
    public function get_product_by_id_own_business_returns_200()
    {
        ['owner' => $owner, 'product' => $product] = $this->baseSetup();

        $this->actingAs($owner)
             ->getJson("/api/v1/products/{$product->id}")
             ->assertOk()
             ->assertJsonPath('data.id', $product->id);
    }

    /** @test TC-PRD-07 */
    #[Test]
    public function get_product_by_id_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();

        $other        = $this->createBusiness('Other', 'OTH');
        $otherProduct = Product::create(['business_id' => $other->id, 'name' => 'Other', 'sku' => 'OTH-001', 'price' => 5000]);

        $this->actingAs($owner)
             ->getJson("/api/v1/products/{$otherProduct->id}")
             ->assertForbidden();
    }

    // ─── POST Create ─────────────────────────────────────────────────────────

    /** @test TC-PRD-08 */
    #[Test]
    public function create_product_without_image_returns_201_with_auto_sku()
    {
        Storage::fake('public');
        ['owner' => $owner, 'category' => $category] = $this->baseSetup();

        $response = $this->actingAs($owner)
             ->postJson('/api/v1/products', [
                 'name'        => 'Matcha Latte',
                 'price'       => 28000,
                 'cost_price'  => 14000,
                 'category_id' => $category->id,
                 'is_active'   => true,
             ])
             ->assertCreated()
             ->assertJsonPath('success', true);

        $this->assertNotNull($response->json('data.sku'));
    }

    /** @test TC-PRD-09 */
    #[Test]
    public function create_product_with_image_stores_file()
    {
        Storage::fake('public');
        ['owner' => $owner] = $this->baseSetup();

        $file = UploadedFile::fake()->image('product.jpg');

        $this->actingAs($owner)
             ->post('/api/v1/products', [
                 'name'  => 'Product With Image',
                 'price' => 15000,
                 'image' => $file,
             ])
             ->assertCreated()
             ->assertJsonPath('success', true);
    }

    /** @test TC-PRD-10 */
    #[Test]
    public function create_product_without_price_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/products', ['name' => 'No Price'])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['price']);
    }

    /** @test TC-PRD-11 */
    #[Test]
    public function create_product_with_duplicate_sku_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/products', ['name' => 'Duplicate SKU', 'price' => 10000, 'sku' => 'KOPI-001'])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['sku']);
    }

    // ─── PUT Update ──────────────────────────────────────────────────────────

    /** @test TC-PRD-13 */
    #[Test]
    public function update_product_without_image_keeps_old_image_url()
    {
        Storage::fake('public');
        ['owner' => $owner, 'product' => $product] = $this->baseSetup();

        $this->actingAs($owner)
             ->putJson("/api/v1/products/{$product->id}", ['name' => 'Updated Name', 'price' => 30000])
             ->assertOk()
             ->assertJsonPath('data.name', 'Updated Name');
    }

    /** @test TC-PRD-14 */
    #[Test]
    public function update_product_with_new_image_replaces_file()
    {
        Storage::fake('public');
        ['owner' => $owner, 'product' => $product] = $this->baseSetup();

        $file = UploadedFile::fake()->image('new.jpg');

        $this->actingAs($owner)
             ->post("/api/v1/products/{$product->id}", array_merge(
                 ['_method' => 'PUT'],
                 ['image' => $file]
             ))
             ->assertOk();
    }

    /** @test TC-PRD-15 */
    #[Test]
    public function update_product_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();

        $other        = $this->createBusiness('Other', 'OTH');
        $otherProduct = Product::create(['business_id' => $other->id, 'name' => 'Other', 'sku' => 'OTH-001', 'price' => 5000]);

        $this->actingAs($owner)
             ->putJson("/api/v1/products/{$otherProduct->id}", ['name' => 'Hacked'])
             ->assertForbidden();
    }

    // ─── DELETE ──────────────────────────────────────────────────────────────

    /** @test TC-PRD-16 */
    #[Test]
    public function delete_product_soft_deletes_and_sets_deleted_by()
    {
        ['owner' => $owner, 'product' => $product] = $this->baseSetup();

        $this->actingAs($owner)
             ->deleteJson("/api/v1/products/{$product->id}")
             ->assertOk();

        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $this->assertDatabaseHas('products', [
            'id'         => $product->id,
            'deleted_by' => $owner->id,
        ]);
    }

    /** @test TC-PRD-17 */
    #[Test]
    public function delete_product_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();

        $other        = $this->createBusiness('Other', 'OTH');
        $otherProduct = Product::create(['business_id' => $other->id, 'name' => 'Other', 'sku' => 'OTH-001', 'price' => 5000]);

        $this->actingAs($owner)
             ->deleteJson("/api/v1/products/{$otherProduct->id}")
             ->assertForbidden();
    }

    /** @test TC-PRD-18 */
    #[Test]
    public function force_delete_by_superadmin_removes_record_permanently()
    {
        Storage::fake('public');
        ['product' => $product] = $this->baseSetup();

        $superadmin = $this->createUser(UserRole::SUPERADMIN);

        // Soft delete dulu
        $product->delete();

        $this->actingAs($superadmin)
             ->deleteJson("/api/v1/products/{$product->id}/force")
             ->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    /** @test TC-PRD-19 */
    #[Test]
    public function force_delete_by_non_superadmin_returns_403()
    {
        ['owner' => $owner, 'product' => $product] = $this->baseSetup();

        $this->actingAs($owner)
             ->deleteJson("/api/v1/products/{$product->id}/force")
             ->assertForbidden();
    }
}