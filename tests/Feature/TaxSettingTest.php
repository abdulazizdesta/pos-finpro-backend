<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\TaxSetting;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaxSettingTest extends TestCase
{
    private function baseSetup(): array
    {
        $business = $this->createBusiness('Fashion', 'FSH');
        $owner    = $this->createUser(UserRole::OWNER, $business->id);
        $admin    = $this->createUser(UserRole::ADMIN, $business->id);
        return compact('business', 'owner', 'admin');
    }

    private function createTax(int $businessId, string $name = 'PPN', float $rate = 11.0): TaxSetting
    {
        return TaxSetting::create([
            'business_id' => $businessId,
            'name'        => $name,
            'rate'        => $rate,
            'is_active'   => true,
        ]);
    }

    // ─── POST Create ─────────────────────────────────────────────────────────

    #[Test]
    public function create_tax_setting_valid_returns_201()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/tax-settings', ['name' => 'PPN', 'rate' => 11.0])
             ->assertCreated()
             ->assertJsonPath('data.name', 'PPN')
             ->assertJsonPath('data.rate', 11);

        $this->assertDatabaseHas('tax_settings', [
            'business_id' => $business->id,
            'name'        => 'PPN',
        ]);
    }

    #[Test]
    public function create_tax_setting_admin_role_returns_403()
    {
        ['admin' => $admin] = $this->baseSetup();

        $this->actingAs($admin)
             ->postJson('/api/v1/tax-settings', ['name' => 'PPN', 'rate' => 11.0])
             ->assertForbidden();
    }

    #[Test]
    public function create_tax_setting_rate_above_100_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/tax-settings', ['name' => 'PPN', 'rate' => 101])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['rate']);
    }

    #[Test]
    public function create_tax_setting_missing_fields_returns_422()
    {
        ['owner' => $owner] = $this->baseSetup();

        $this->actingAs($owner)
             ->postJson('/api/v1/tax-settings', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['name', 'rate']);
    }

    // ─── GET All ─────────────────────────────────────────────────────────────

    #[Test]
    public function get_all_tax_settings_returns_own_business_only()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $this->createTax($business->id);

        $other      = $this->createBusiness('Other', 'OTH');
        $otherOwner = $this->createUser(UserRole::OWNER, $other->id);
        $this->createTax($other->id, 'VAT', 10.0);

        $this->actingAs($owner)
             ->getJson('/api/v1/tax-settings')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function get_all_tax_settings_filter_by_active()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $this->createTax($business->id, 'PPN', 11.0);
        TaxSetting::create(['business_id' => $business->id, 'name' => 'Service', 'rate' => 5.0, 'is_active' => false]);

        $this->actingAs($owner)
             ->getJson('/api/v1/tax-settings?is_active=1')
             ->assertOk()
             ->assertJsonCount(1, 'data');
    }

    // ─── GET Detail ──────────────────────────────────────────────────────────

    #[Test]
    public function get_tax_setting_detail_returns_correct_data()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $tax = $this->createTax($business->id);

        $this->actingAs($owner)
             ->getJson("/api/v1/tax-settings/{$tax->id}")
             ->assertOk()
             ->assertJsonPath('data.id', $tax->id);
    }

    #[Test]
    public function get_tax_setting_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();
        $other    = $this->createBusiness('Other', 'OTH');
        $otherTax = $this->createTax($other->id);

        $this->actingAs($owner)
             ->getJson("/api/v1/tax-settings/{$otherTax->id}")
             ->assertForbidden();
    }

    // ─── PUT Update ──────────────────────────────────────────────────────────

    #[Test]
    public function update_tax_setting_valid_returns_200()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $tax = $this->createTax($business->id);

        $this->actingAs($owner)
             ->putJson("/api/v1/tax-settings/{$tax->id}", ['rate' => 12.0])
             ->assertOk()
             ->assertJsonPath('data.rate', 12);
    }

    #[Test]
    public function update_tax_setting_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();
        $other    = $this->createBusiness('Other', 'OTH');
        $otherTax = $this->createTax($other->id);

        $this->actingAs($owner)
             ->putJson("/api/v1/tax-settings/{$otherTax->id}", ['rate' => 5.0])
             ->assertForbidden();
    }

    // ─── DELETE ──────────────────────────────────────────────────────────────

    #[Test]
    public function delete_tax_setting_valid_soft_deletes()
    {
        ['owner' => $owner, 'business' => $business] = $this->baseSetup();
        $tax = $this->createTax($business->id);

        $this->actingAs($owner)
             ->deleteJson("/api/v1/tax-settings/{$tax->id}")
             ->assertOk();

        $this->assertSoftDeleted('tax_settings', ['id' => $tax->id]);
    }

    #[Test]
    public function delete_tax_setting_other_business_returns_403()
    {
        ['owner' => $owner] = $this->baseSetup();
        $other    = $this->createBusiness('Other', 'OTH');
        $otherTax = $this->createTax($other->id);

        $this->actingAs($owner)
             ->deleteJson("/api/v1/tax-settings/{$otherTax->id}")
             ->assertForbidden();
    }
}