<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Region;
use App\Models\SubRegion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithApiUsers;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use InteractsWithApiUsers;
    use RefreshDatabase;

    private function invoicerUser(): User
    {
        return $this->createEmployeeViaApi(UserRole::Invoicer, [
            'username' => 'invoicer_master',
            'phone' => '0509000001',
        ]);
    }

    private function repUser(): User
    {
        return $this->createEmployeeViaApi(UserRole::Rep, [
            'username' => 'rep_master',
            'phone' => '0509000002',
        ]);
    }

    /** @return array{region: Region, sub_region: SubRegion} */
    private function createRegionWithSubRegion(User $invoicer): array
    {
        $regionResponse = $this->actingAsApiUser($invoicer)
            ->postJson('/api/v1/regions', ['name' => 'Baghdad'])
            ->assertCreated();

        $region = Region::query()->findOrFail($regionResponse->json('region.id'));

        $subResponse = $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/regions/{$region->id}/sub-regions", ['name' => 'Karkh'])
            ->assertCreated();

        $subRegion = SubRegion::query()->findOrFail($subResponse->json('sub_region.id'));

        return ['region' => $region, 'sub_region' => $subRegion];
    }

    public function test_invoicer_can_manage_regions_companies_products_and_pharmacies(): void
    {
        $invoicer = $this->invoicerUser();
        ['region' => $region, 'sub_region' => $subRegion] = $this->createRegionWithSubRegion($invoicer);

        $companyResponse = $this->actingAsApiUser($invoicer)
            ->postJson('/api/v1/companies', [
                'name' => 'Pharma Co',
                'location' => 'Baghdad',
            ])
            ->assertCreated();

        $companyId = $companyResponse->json('company.id');

        $productPayload = [
            'company_id' => $companyId,
            'name' => 'Augmentin',
            'scientific_name' => 'Amoxicillin',
            'quantity' => 500,
            'price' => 15000,
            'purchase_date' => '2026-01-01',
            'production_date' => '2025-06-01',
            'expiry_date' => '2027-01-01',
            'base_offer' => [
                'required_qty' => 10,
                'bonus_qty' => 2,
            ],
        ];

        $this->actingAsApiUser($invoicer)
            ->postJson('/api/v1/products', $productPayload)
            ->assertCreated()
            ->assertJsonPath('product.name', 'Augmentin')
            ->assertJsonPath('product.quantity', 500);

        $this->actingAsApiUser($invoicer)
            ->postJson('/api/v1/products', array_merge($productPayload, ['quantity' => 200]))
            ->assertOk()
            ->assertJsonPath('merged', true)
            ->assertJsonPath('product.quantity', 700);

        $this->assertSame(1, Product::query()->count());

        $this->actingAsApiUser($invoicer)
            ->postJson('/api/v1/pharmacies', [
                'name' => 'Al-Shifa Pharmacy',
                'phone' => '07701234567',
                'region_id' => $region->id,
                'sub_region_id' => $subRegion->id,
                'address' => 'Main Street 1',
            ])
            ->assertCreated()
            ->assertJsonPath('pharmacy.status', 'active');
    }

    public function test_product_merge_creates_separate_row_for_different_expiry(): void
    {
        $invoicer = $this->invoicerUser();

        $company = Company::query()->create([
            'name' => 'Test Co',
            'status' => 'active',
            'created_by' => $invoicer->id,
        ]);

        $base = [
            'company_id' => $company->id,
            'name' => 'Augmentin',
            'quantity' => 100,
            'price' => 1000,
            'purchase_date' => '2026-01-01',
            'production_date' => '2025-06-01',
        ];

        $this->actingAsApiUser($invoicer)
            ->postJson('/api/v1/products', array_merge($base, ['expiry_date' => '2027-01-01']))
            ->assertCreated();

        $this->actingAsApiUser($invoicer)
            ->postJson('/api/v1/products', array_merge($base, ['expiry_date' => '2027-06-01']))
            ->assertCreated()
            ->assertJsonPath('merged', false);

        $this->assertSame(2, Product::query()->count());
    }

    public function test_rep_cannot_see_product_quantity(): void
    {
        $invoicer = $this->invoicerUser();
        $rep = $this->repUser();

        $company = Company::query()->create([
            'name' => 'Rep Co',
            'status' => 'active',
            'created_by' => $invoicer->id,
        ]);

        $createResponse = $this->actingAsApiUser($invoicer)
            ->postJson('/api/v1/products', [
                'company_id' => $company->id,
                'name' => 'Panadol',
                'quantity' => 250,
                'price' => 500,
                'purchase_date' => '2026-01-01',
                'production_date' => '2025-06-01',
                'expiry_date' => '2027-01-01',
                'rep_visible' => true,
            ])
            ->assertCreated();

        $productId = $createResponse->json('product.id');

        $this->actingAsApiUser($rep)
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonMissingPath('data.0.quantity');

        $this->actingAsApiUser($rep)
            ->getJson("/api/v1/products/{$productId}")
            ->assertOk()
            ->assertJsonMissingPath('quantity')
            ->assertJsonPath('availability', 'available');
    }

    public function test_pharmacy_duplicate_is_blocked(): void
    {
        $invoicer = $this->invoicerUser();
        ['region' => $region, 'sub_region' => $subRegion] = $this->createRegionWithSubRegion($invoicer);

        $payload = [
            'name' => 'Duplicate Pharmacy',
            'phone' => '07709998888',
            'region_id' => $region->id,
            'sub_region_id' => $subRegion->id,
            'address' => 'Same Address',
        ];

        $this->actingAsApiUser($invoicer)
            ->postJson('/api/v1/pharmacies', $payload)
            ->assertCreated();

        $this->actingAsApiUser($invoicer)
            ->postJson('/api/v1/pharmacies/duplicate-check', $payload)
            ->assertOk()
            ->assertJsonCount(1, 'confirmed');

        $this->actingAsApiUser($invoicer)
            ->postJson('/api/v1/pharmacies', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_pharmacy_suspend_requires_reason(): void
    {
        $invoicer = $this->invoicerUser();
        ['region' => $region, 'sub_region' => $subRegion] = $this->createRegionWithSubRegion($invoicer);

        $pharmacy = Pharmacy::query()->create([
            'name' => 'Suspend Me',
            'phone' => '07701112222',
            'region_id' => $region->id,
            'sub_region_id' => $subRegion->id,
            'status' => 'active',
            'current_balance' => 0,
            'created_by' => $invoicer->id,
        ]);

        $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/pharmacies/{$pharmacy->id}/suspend", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/pharmacies/{$pharmacy->id}/suspend", ['reason' => 'Debt dispute'])
            ->assertOk()
            ->assertJsonPath('pharmacy.status', 'suspended');
    }

    public function test_supervisor_can_view_but_not_manage_master_data(): void
    {
        $supervisor = $this->supervisorUser();

        $this->actingAsApiUser($supervisor)
            ->getJson('/api/v1/regions')
            ->assertOk();

        $this->actingAsApiUser($supervisor)
            ->postJson('/api/v1/regions', ['name' => 'Denied Region'])
            ->assertForbidden();

        $this->actingAsApiUser($supervisor)
            ->getJson('/api/v1/companies')
            ->assertOk();

        $this->actingAsApiUser($supervisor)
            ->postJson('/api/v1/companies', ['name' => 'Denied Co'])
            ->assertForbidden();
    }

    public function test_inactive_region_blocks_pharmacy_creation(): void
    {
        $invoicer = $this->invoicerUser();
        ['region' => $region, 'sub_region' => $subRegion] = $this->createRegionWithSubRegion($invoicer);

        $this->actingAsApiUser($invoicer)
            ->patchJson("/api/v1/regions/{$region->id}", ['status' => 'inactive'])
            ->assertOk();

        $this->actingAsApiUser($invoicer)
            ->postJson('/api/v1/pharmacies', [
                'name' => 'Blocked Pharmacy',
                'phone' => '07703334444',
                'region_id' => $region->id,
                'sub_region_id' => $subRegion->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['region_id']);
    }

    public function test_product_update_rejects_direct_quantity_change(): void
    {
        $invoicer = $this->invoicerUser();

        $company = Company::query()->create([
            'name' => 'Qty Co',
            'status' => 'active',
            'created_by' => $invoicer->id,
        ]);

        $product = Product::query()->create([
            'company_id' => $company->id,
            'name' => 'Item',
            'quantity' => 50,
            'price' => 100,
            'purchase_date' => '2026-01-01',
            'production_date' => '2025-06-01',
            'expiry_date' => '2027-01-01',
            'status' => 'active',
            'created_by' => $invoicer->id,
        ]);

        $this->actingAsApiUser($invoicer)
            ->patchJson("/api/v1/products/{$product->id}", ['quantity' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }
}
