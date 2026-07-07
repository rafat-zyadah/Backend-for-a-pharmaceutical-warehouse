<?php

namespace App\Support\MasterData;

use App\Enums\ProductBaseOfferStatus;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductBaseOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        private readonly CompanyService $companyService,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function createOrMerge(array $data, User $actor): Product
    {
        $company = \App\Models\Company::query()->findOrFail($data['company_id']);
        $this->companyService->assertActive($company);

        return DB::transaction(function () use ($data, $actor, $company): Product {
            $existing = Product::query()
                ->where('company_id', $company->id)
                ->where('name', $data['name'])
                ->whereDate('expiry_date', $data['expiry_date'])
                ->where('status', ProductStatus::Active)
                ->first();

            if ($existing !== null) {
                $existing->increment('quantity', (int) $data['quantity']);
                $product = $existing->refresh();
            } else {
                $product = Product::query()->create([
                    'company_id' => $company->id,
                    'name' => $data['name'],
                    'scientific_name' => $data['scientific_name'] ?? null,
                    'quantity' => (int) $data['quantity'],
                    'price' => $data['price'],
                    'purchase_date' => $data['purchase_date'],
                    'production_date' => $data['production_date'],
                    'expiry_date' => $data['expiry_date'],
                    'rep_visible' => $data['rep_visible'] ?? true,
                    'status' => ProductStatus::Active,
                    'created_by' => $actor->id,
                ]);
            }

            if (isset($data['base_offer'])) {
                $this->syncBaseOffer($product, $data['base_offer']);
            }

            return $product->load(['company', 'baseOffer']);
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(Product $product, array $data): Product
    {
        if (isset($data['quantity'])) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be changed via documented stock movements, not direct edit.'],
            ]);
        }

        $product->fill([
            'name' => $data['name'] ?? $product->name,
            'scientific_name' => $data['scientific_name'] ?? $product->scientific_name,
            'price' => $data['price'] ?? $product->price,
            'purchase_date' => $data['purchase_date'] ?? $product->purchase_date,
            'production_date' => $data['production_date'] ?? $product->production_date,
            'expiry_date' => $data['expiry_date'] ?? $product->expiry_date,
            'rep_visible' => $data['rep_visible'] ?? $product->rep_visible,
        ])->save();

        if (array_key_exists('base_offer', $data)) {
            $this->syncBaseOffer($product, $data['base_offer']);
        }

        return $product->refresh()->load(['company', 'baseOffer']);
    }

    public function archive(Product $product): Product
    {
        $product->update(['status' => ProductStatus::Archived]);
        $product->delete();

        return $product->refresh();
    }

    /** @param  array<string, mixed>|null  $offer */
    private function syncBaseOffer(Product $product, ?array $offer): void
    {
        if ($offer === null) {
            $product->baseOffer()?->delete();

            return;
        }

        ProductBaseOffer::query()->updateOrCreate(
            ['product_id' => $product->id],
            [
                'required_qty' => (int) $offer['required_qty'],
                'bonus_qty' => (int) $offer['bonus_qty'],
                'status' => ProductBaseOfferStatus::from($offer['status'] ?? ProductBaseOfferStatus::Active->value),
            ],
        );
    }
}
