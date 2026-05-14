<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\BulkImportProductJob;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductBulkTest extends TestCase
{
    private function baseSetup(): array
    {
        $business = $this->createBusiness('Fashion', 'FSH');
        $outlet   = $this->createOutlet($business->id);
        $owner    = $this->createUser(UserRole::OWNER, $business->id);
        $category = Category::create(['business_id' => $business->id, 'name' => 'Beauty']);

        $products = collect(range(1, 3))->map(fn($i) => Product::create([
            'business_id' => $business->id,
            'name'        => "Product {$i}",
            'sku'         => "PRD-00{$i}",
            'price'       => 10000 * $i,
        ]));

        return compact('business', 'outlet', 'owner', 'category', 'products');
    }

    private function makeCsv(string $content): UploadedFile
    {
        $path = sys_get_temp_dir() . '/test_import.csv';
        file_put_contents($path, $content);
        return new UploadedFile($path, 'test_import.csv', 'text/csv', null, true);
    }

    // ─── Bulk Delete ─────────────────────────────────────────────────────────

    /** @test TC-BLK-01 */
    #[Test]
    public function bulk_delete_valid_ids_soft_deletes_products()
    {
        ['owner' => $owner, 'products' => $products] = $this->baseSetup();

        $ids = $products->pluck('id')->toArray();

        $this->actingAs($owner)
             ->deleteJson('/api/v1/products/bulk', ['ids' => $ids])
             ->assertOk()
             ->assertJsonPath('success', true);

        foreach ($ids as $id) {
            $this->assertSoftDeleted('products', ['id' => $id]);
        }
    }

    /** @test TC-BLK-02 */
    #[Test]
    public function bulk_delete_nonexistent_ids_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->deleteJson('/api/v1/products/bulk', ['ids' => [999, 998]])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['ids.0', 'ids.1']);
    }

    /** @test TC-BLK-03 */
    #[Test]
    public function bulk_delete_empty_array_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->deleteJson('/api/v1/products/bulk', ['ids' => []])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['ids']);
    }

    /** @test TC-BLK-04 */
    #[Test]
    public function bulk_delete_without_ids_field_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->deleteJson('/api/v1/products/bulk', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['ids']);
    }

    // ─── Bulk Import ─────────────────────────────────────────────────────────

    /** @test TC-BLK-05 */
    #[Test]
    public function bulk_import_valid_csv_dispatches_job()
    {
        Queue::fake();
        Storage::fake('local');
        ['owner' => $owner, 'category' => $category] = $this->baseSetup();

        $csv = $this->makeCsv(
            "name,sku,price,cost_price,category_id,description,has_variants,is_active\n" .
            "Hand Cream,HNCR-001,45000,20000,{$category->id},Deskripsi,false,true\n"
        );

        $this->actingAs($owner)
             ->post('/api/v1/products/bulk-import', ['file' => $csv])
             ->assertOk()
             ->assertJsonPath('data.message', 'Import on processing.');

        Queue::assertPushed(BulkImportProductJob::class);
    }

    /** @test TC-BLK-06 */
    #[Test]
    public function bulk_import_csv_with_column_mismatch_logs_failed_rows()
    {
        Storage::fake('local');
        ['owner' => $owner] = $this->baseSetup();

        // QUEUE_CONNECTION=sync di phpunit.xml → job langsung jalan
        $csv = $this->makeCsv(
            "name,sku,price,cost_price,category_id,description,has_variants,is_active\n" .
            "Valid Product,VLDP-001,10000,5000,,Desc,false,true\n" .
            "Bad Row,missing_columns\n" // kolom tidak lengkap
        );

        $this->actingAs($owner)
             ->post('/api/v1/products/bulk-import', ['file' => $csv])
             ->assertOk();

        // Cek hanya 1 produk yang masuk DB
        $this->assertDatabaseCount('products', 4); // 3 dari setup + 1 valid
    }

    /** @test TC-BLK-07 */
    #[Test]
    public function bulk_import_csv_missing_required_fields_skips_rows()
    {
        Storage::fake('local');
        ['owner' => $owner] = $this->baseSetup();

        $csv = $this->makeCsv(
            "name,sku,price,cost_price,category_id,description,has_variants,is_active\n" .
            "Valid Product,VLDP-002,10000,5000,,,false,true\n" .
            ",NONAME-001,5000,,,,false,true\n" // tanpa name
        );

        $this->actingAs($owner)
             ->post('/api/v1/products/bulk-import', ['file' => $csv])
             ->assertOk();

        $this->assertDatabaseCount('products', 4); // 3 setup + 1 valid
    }

    /** @test TC-BLK-08 */
    #[Test]
    public function bulk_import_csv_with_wrong_category_skips_rows()
    {
        Storage::fake('local');
        ['owner' => $owner] = $this->baseSetup();

        $csv = $this->makeCsv(
            "name,sku,price,cost_price,category_id,description,has_variants,is_active\n" .
            "Valid Product,VLDP-003,10000,5000,,,false,true\n" .
            "Wrong Cat,WCAT-001,10000,5000,999,,false,true\n" // category_id tidak ada
        );

        $this->actingAs($owner)
             ->post('/api/v1/products/bulk-import', ['file' => $csv])
             ->assertOk();

        $this->assertDatabaseCount('products', 4); // 3 setup + 1 valid
    }

    /** @test TC-BLK-09 */
    #[Test]
    public function bulk_import_non_csv_file_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $file = UploadedFile::fake()->image('photo.jpg');

        $this->actingAs($owner)
             ->post('/api/v1/products/bulk-import', ['file' => $file])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['file']);
    }

    /** @test TC-BLK-10 */
    #[Test]
    public function bulk_import_without_file_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/products/bulk-import', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['file']);
    }
}