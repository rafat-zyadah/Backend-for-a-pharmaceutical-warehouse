<?php

namespace Tests\Feature\Api;

use App\Enums\AssignmentMode;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Region;
use App\Models\SubRegion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithApiUsers;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use InteractsWithApiUsers;
    use RefreshDatabase;

    /** @return array{invoicer: User, rep: User, pharmacy: Pharmacy, product: Product} */
    private function seedOrderContext(): array
    {
        $invoicer = $this->createEmployeeViaApi(UserRole::Invoicer, [
            'username' => 'invoicer_orders',
            'phone' => '0509200001',
        ]);

        $rep = $this->createEmployeeViaApi(UserRole::Rep, [
            'username' => 'rep_orders',
            'phone' => '0509200002',
        ]);

        $region = Region::query()->create(['name' => 'Order Region', 'status' => 'active']);
        $subRegion = SubRegion::query()->create([
            'region_id' => $region->id,
            'name' => 'Order Sub',
            'status' => 'active',
        ]);

        $pharmacy = Pharmacy::query()->create([
            'name' => 'Order Pharmacy',
            'phone' => '07792000001',
            'region_id' => $region->id,
            'sub_region_id' => $subRegion->id,
            'status' => 'active',
            'current_balance' => 0,
            'created_by' => $invoicer->id,
        ]);

        $company = Company::query()->create([
            'name' => 'Order Co',
            'status' => 'active',
            'created_by' => $invoicer->id,
        ]);

        $product = Product::query()->create([
            'company_id' => $company->id,
            'name' => 'Order Product',
            'quantity' => 500,
            'price' => 1000,
            'purchase_date' => '2026-01-01',
            'production_date' => '2025-06-01',
            'expiry_date' => '2027-12-01',
            'rep_visible' => true,
            'status' => 'active',
            'created_by' => $invoicer->id,
        ]);

        $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/distribution/reps/{$rep->id}/regions", [
                'region_id' => $region->id,
                'mode' => AssignmentMode::Add->value,
            ])
            ->assertCreated();

        return compact('invoicer', 'rep', 'pharmacy', 'product');
    }

    public function test_rep_can_submit_order_and_invoicer_can_approve(): void
    {
        ['invoicer' => $invoicer, 'rep' => $rep, 'pharmacy' => $pharmacy, 'product' => $product] = $this->seedOrderContext();

        $submitResponse = $this->actingAsApiUser($rep)
            ->postJson('/api/v1/orders', [
                'pharmacy_id' => $pharmacy->id,
                'rep_notes' => 'Urgent delivery',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 10,
                        'offer_source' => 'no_offer',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('order.status', 'pending_review');

        $orderId = $submitResponse->json('order.id');

        $this->actingAsApiUser($invoicer)
            ->getJson('/api/v1/orders/dashboard')
            ->assertOk()
            ->assertJsonPath('summary.pending_review', 1);

        $this->actingAsApiUser($invoicer)
            ->patchJson("/api/v1/orders/{$orderId}", [
                'invoicer_notes' => 'Adjusted for stock',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 8,
                        'discount' => 0,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('order.status', 'modified');

        $approveResponse = $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/orders/{$orderId}/approve-shipment")
            ->assertOk()
            ->assertJsonPath('order.status', 'approved');

        $this->assertDatabaseHas('invoices', [
            'id' => $approveResponse->json('invoice.id'),
            'order_id' => $orderId,
        ]);

        $product->refresh();
        $pharmacy->refresh();

        $this->assertSame(492, $product->quantity);
        $this->assertSame('8000.00', $pharmacy->current_balance);
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_invoicer_can_reject_order_with_reason(): void
    {
        ['invoicer' => $invoicer, 'rep' => $rep, 'pharmacy' => $pharmacy, 'product' => $product] = $this->seedOrderContext();

        $orderId = $this->actingAsApiUser($rep)
            ->postJson('/api/v1/orders', [
                'pharmacy_id' => $pharmacy->id,
                'items' => [['product_id' => $product->id, 'quantity' => 5]],
            ])
            ->json('order.id');

        $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/orders/{$orderId}/reject", ['reason' => 'Out of policy'])
            ->assertOk()
            ->assertJsonPath('order.status', 'rejected');
    }

    public function test_rep_can_cancel_pending_order(): void
    {
        ['rep' => $rep, 'pharmacy' => $pharmacy, 'product' => $product] = $this->seedOrderContext();

        $orderId = $this->actingAsApiUser($rep)
            ->postJson('/api/v1/orders', [
                'pharmacy_id' => $pharmacy->id,
                'items' => [['product_id' => $product->id, 'quantity' => 3]],
            ])
            ->json('order.id');

        $this->actingAsApiUser($rep)
            ->postJson("/api/v1/orders/{$orderId}/cancel-by-rep")
            ->assertOk()
            ->assertJsonPath('order.status', 'cancelled_by_rep');
    }

    public function test_invoicer_can_cancel_pending_order_with_reason(): void
    {
        ['invoicer' => $invoicer, 'rep' => $rep, 'pharmacy' => $pharmacy, 'product' => $product] = $this->seedOrderContext();

        $orderId = $this->actingAsApiUser($rep)
            ->postJson('/api/v1/orders', [
                'pharmacy_id' => $pharmacy->id,
                'items' => [['product_id' => $product->id, 'quantity' => 3]],
            ])
            ->json('order.id');

        $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/orders/{$orderId}/cancel", ['reason' => 'Duplicate request'])
            ->assertOk()
            ->assertJsonPath('order.status', 'cancelled_by_invoicer');
    }

    public function test_approve_fails_when_stock_insufficient(): void
    {
        ['invoicer' => $invoicer, 'rep' => $rep, 'pharmacy' => $pharmacy, 'product' => $product] = $this->seedOrderContext();

        $product->update(['quantity' => 5]);

        $orderId = $this->actingAsApiUser($rep)
            ->postJson('/api/v1/orders', [
                'pharmacy_id' => $pharmacy->id,
                'items' => [['product_id' => $product->id, 'quantity' => 20]],
            ])
            ->json('order.id');

        $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/orders/{$orderId}/approve-shipment")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);

        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(0, Order::query()->where('status', 'approved')->count());
    }

    public function test_rep_cannot_submit_without_pharmacy_assignment(): void
    {
        $invoicer = $this->createEmployeeViaApi(UserRole::Invoicer, [
            'username' => 'invoicer_no_assign',
            'phone' => '0509200101',
        ]);

        $rep = $this->createEmployeeViaApi(UserRole::Rep, [
            'username' => 'rep_no_assign',
            'phone' => '0509200102',
        ]);

        $region = Region::query()->create(['name' => 'Unassigned Region', 'status' => 'active']);
        $subRegion = SubRegion::query()->create([
            'region_id' => $region->id,
            'name' => 'Unassigned Sub',
            'status' => 'active',
        ]);

        $pharmacy = Pharmacy::query()->create([
            'name' => 'Unassigned Pharmacy',
            'phone' => '07792001001',
            'region_id' => $region->id,
            'sub_region_id' => $subRegion->id,
            'status' => 'active',
            'current_balance' => 0,
            'created_by' => $invoicer->id,
        ]);

        $company = Company::query()->create([
            'name' => 'Co',
            'status' => 'active',
            'created_by' => $invoicer->id,
        ]);

        $product = Product::query()->create([
            'company_id' => $company->id,
            'name' => 'P1',
            'quantity' => 100,
            'price' => 100,
            'purchase_date' => '2026-01-01',
            'production_date' => '2025-06-01',
            'expiry_date' => '2027-12-01',
            'rep_visible' => true,
            'status' => 'active',
            'created_by' => $invoicer->id,
        ]);

        $this->actingAsApiUser($rep)
            ->postJson('/api/v1/orders', [
                'pharmacy_id' => $pharmacy->id,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pharmacy_id']);
    }

    public function test_supervisor_can_view_orders_but_not_manage(): void
    {
        ['rep' => $rep, 'pharmacy' => $pharmacy, 'product' => $product] = $this->seedOrderContext();

        $this->actingAsApiUser($rep)
            ->postJson('/api/v1/orders', [
                'pharmacy_id' => $pharmacy->id,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
            ])
            ->assertCreated();

        $supervisor = $this->supervisorUser();

        $this->actingAsApiUser($supervisor)
            ->getJson('/api/v1/orders')
            ->assertOk();

        $this->actingAsApiUser($supervisor)
            ->getJson('/api/v1/orders/dashboard')
            ->assertForbidden();
    }
}
