<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Support\Audit\StateTransitionLogger;
use App\Support\MasterData\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly StateTransitionLogger $stateTransitionLogger,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()
            ->with(['company', 'baseOffer'])
            ->orderBy('name');

        if ($request->user()->role === UserRole::Rep) {
            $query->where('rep_visible', true);
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->string('company_id')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        } else {
            $query->where('status', ProductStatus::Active);
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('scientific_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('availability')) {
            $threshold = (int) config('pharmacy.low_stock_threshold', 100);
            match ($request->string('availability')->toString()) {
                'out_of_stock' => $query->where('quantity', 0),
                'low_stock' => $query->where('quantity', '>', 0)->where('quantity', '<', $threshold),
                'available' => $query->where('quantity', '>=', $threshold),
                default => null,
            };
        }

        if ($request->boolean('rep_visible')) {
            $query->where('rep_visible', true);
        }

        return ProductResource::collection(
            $query->paginate($request->integer('per_page', 15)),
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $existing = Product::query()
            ->where('company_id', $request->input('company_id'))
            ->where('name', $request->input('name'))
            ->whereDate('expiry_date', $request->input('expiry_date'))
            ->where('status', ProductStatus::Active)
            ->first();

        $product = $this->productService->createOrMerge($request->validated(), $request->user());

        $this->stateTransitionLogger->log(
            entityType: 'product',
            entityId: $product->id,
            event: $existing !== null ? 'merge_quantity' : 'create',
            fromState: $existing !== null ? (string) ($existing->quantity - (int) $request->input('quantity')) : null,
            toState: (string) $product->quantity,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
            metadata: ['merged' => $existing !== null],
        );

        return response()->json([
            'message' => $existing !== null ? 'Product quantity merged.' : 'Product created.',
            'merged' => $existing !== null,
            'product' => new ProductResource($product),
        ], $existing !== null ? 200 : 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        if ($request->user()->role === UserRole::Rep && ! $product->rep_visible) {
            abort(404);
        }

        return response()->json(
            new ProductResource($product->load(['company', 'baseOffer'])),
        );
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->productService->update($product, $request->validated());

        $this->stateTransitionLogger->log(
            entityType: 'product',
            entityId: $product->id,
            event: 'update',
            fromState: null,
            toState: $product->status->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'Product updated.',
            'product' => new ProductResource($product),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $previousStatus = $product->status->value;

        $product = $this->productService->archive($product);

        $this->stateTransitionLogger->log(
            entityType: 'product',
            entityId: $product->id,
            event: 'archive',
            fromState: $previousStatus,
            toState: ProductStatus::Archived->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'Product archived.',
        ]);
    }
}
