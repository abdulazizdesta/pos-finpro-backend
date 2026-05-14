<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    // ─── Setup ───────────────────────────────────────────────────────────────

    private function baseSetup(): array
    {
        $business = $this->createBusiness('Fashion', 'FSH');
        $outlet   = $this->createOutlet($business->id);
        $owner    = $this->createUser(UserRole::OWNER, $business->id);
        $category = Category::create(['business_id' => $business->id, 'name' => 'Beauty']);

        return compact('business', 'outlet', 'owner', 'category');
    }

    // ─── GET All ─────────────────────────────────────────────────────────────

    /** @test TC-CAT-01 */
    #[Test]
    public function get_all_categories_returns_only_own_business()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();

        // Buat kategori bisnis lain
        $other = $this->createBusiness('Other', 'OTH');
        Category::create(['business_id' => $other->id, 'name' => 'Other Category']);

        $this->actingAs($owner)
             ->getJson('/api/v1/categories')
             ->assertOk()
             ->assertJsonPath('success', true)
             ->assertJsonCount(1, 'data'); // hanya 1 kategori milik sendiri
    }

    /** @test TC-CAT-02 */
    #[Test]
    public function get_all_categories_with_search_filter()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        Category::create(['business_id' => $business->id, 'name' => 'Frozen Food']);

        $this->actingAs($owner)
             ->getJson('/api/v1/categories?search=beauty')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    /** @test TC-CAT-03 */
    #[Test]
    public function get_all_categories_without_token_returns_401()
    {
        $this->getJson('/api/v1/categories')->assertUnauthorized();
    }

    // ─── GET by ID ───────────────────────────────────────────────────────────

    /** @test TC-CAT-04 */
    #[Test]
    public function get_category_by_id_own_business_returns_200()
    {
        ['owner' => $owner, 'category' => $category] = $this->baseSetup();

        $this->actingAs($owner)
             ->getJson("/api/v1/categories/{$category->id}")
             ->assertOk()
             ->assertJsonPath('data.id', $category->id);
    }

    /** @test TC-CAT-05 */
    #[Test]
    public function get_category_by_id_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();

        $other         = $this->createBusiness('Other', 'OTH');
        $otherCategory = Category::create(['business_id' => $other->id, 'name' => 'Other']);

        $this->actingAs($owner)
             ->getJson("/api/v1/categories/{$otherCategory->id}")
             ->assertForbidden();
    }

    // ─── POST Create ─────────────────────────────────────────────────────────

    /** @test TC-CAT-06 */
    #[Test]
    public function create_category_with_valid_data_returns_201()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/categories', ['name' => 'Minuman Segar'])
             ->assertCreated()
             ->assertJsonPath('success', true)
             ->assertJsonPath('data.name', 'Minuman Segar');
    }

    /** @test TC-CAT-07 */
    #[Test]
    public function create_category_without_name_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/categories', [])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['name']);
    }

    /** @test TC-CAT-08 */
    #[Test]
    public function create_category_with_name_exceeding_limit_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/categories', ['name' => str_repeat('A', 101)])
             ->assertUnprocessable()
             ->assertJsonValidationErrors(['name']);
    }

    // ─── PUT Update ──────────────────────────────────────────────────────────

    /** @test TC-CAT-09 */
    #[Test]
    public function update_category_own_business_returns_200()
    {
        ['owner' => $owner, 'category' => $category] = $this->baseSetup();

        $this->actingAs($owner)
             ->putJson("/api/v1/categories/{$category->id}", ['name' => 'Updated Name'])
             ->assertOk()
             ->assertJsonPath('data.name', 'Updated Name');
    }

    /** @test TC-CAT-10 */
    #[Test]
    public function update_category_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();

        $other         = $this->createBusiness('Other', 'OTH');
        $otherCategory = Category::create(['business_id' => $other->id, 'name' => 'Other']);

        $this->actingAs($owner)
             ->putJson("/api/v1/categories/{$otherCategory->id}", ['name' => 'Hacked'])
             ->assertForbidden();
    }

    // ─── DELETE ──────────────────────────────────────────────────────────────

    /** @test TC-CAT-11 */
    #[Test]
    public function delete_category_own_business_returns_200()
    {
        ['owner' => $owner, 'category' => $category] = $this->baseSetup();

        $this->actingAs($owner)
             ->deleteJson("/api/v1/categories/{$category->id}")
             ->assertOk();

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    /** @test TC-CAT-12 */
    #[Test]
    public function delete_category_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();

        $other         = $this->createBusiness('Other', 'OTH');
        $otherCategory = Category::create(['business_id' => $other->id, 'name' => 'Other']);

        $this->actingAs($owner)
             ->deleteJson("/api/v1/categories/{$otherCategory->id}")
             ->assertForbidden();
    }
}