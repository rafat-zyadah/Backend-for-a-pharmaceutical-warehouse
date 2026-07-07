<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PharmacyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckPharmacyDuplicateRequest;
use App\Http\Requests\Api\V1\StorePharmacyRequest;
use App\Http\Requests\Api\V1\SuspendPharmacyRequest;
use App\Http\Requests\Api\V1\UpdatePharmacyRequest;
use App\Http\Resources\Api\V1\PharmacyResource;
use App\Models\Pharmacy;
use App\Support\Audit\StateTransitionLogger;
use App\Support\MasterData\PharmacyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PharmacyController extends Controller
{
    public function __construct(
        private readonly PharmacyService $pharmacyService,
        private readonly StateTransitionLogger $stateTransitionLogger,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Pharmacy::query()
            ->with(['region', 'subRegion'])
            ->orderBy('name');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('region_id')) {
            $query->where('region_id', $request->string('region_id')->toString());
        }

        if ($request->filled('sub_region_id')) {
            $query->where('sub_region_id', $request->string('sub_region_id')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        return PharmacyResource::collection(
            $query->paginate($request->integer('per_page', 15)),
        );
    }

    public function duplicateCheck(CheckPharmacyDuplicateRequest $request): JsonResponse
    {
        $result = $this->pharmacyService->findDuplicates($request->validated());

        return response()->json([
            'confirmed' => PharmacyResource::collection($result['confirmed']),
            'possible' => PharmacyResource::collection($result['possible']),
        ]);
    }

    public function store(StorePharmacyRequest $request): JsonResponse
    {
        $pharmacy = $this->pharmacyService->create($request->validated(), $request->user());

        $this->stateTransitionLogger->log(
            entityType: 'pharmacy',
            entityId: $pharmacy->id,
            event: 'create',
            fromState: null,
            toState: $pharmacy->status->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'Pharmacy created.',
            'pharmacy' => new PharmacyResource($pharmacy->load(['region', 'subRegion'])),
        ], 201);
    }

    public function show(Pharmacy $pharmacy): JsonResponse
    {
        return response()->json(
            new PharmacyResource($pharmacy->load(['region', 'subRegion'])),
        );
    }

    public function update(UpdatePharmacyRequest $request, Pharmacy $pharmacy): JsonResponse
    {
        $previousStatus = $pharmacy->status->value;

        $pharmacy = $this->pharmacyService->update($pharmacy, $request->validated());

        $this->stateTransitionLogger->log(
            entityType: 'pharmacy',
            entityId: $pharmacy->id,
            event: 'update',
            fromState: $previousStatus,
            toState: $pharmacy->status->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'Pharmacy updated.',
            'pharmacy' => new PharmacyResource($pharmacy),
        ]);
    }

    public function suspend(SuspendPharmacyRequest $request, Pharmacy $pharmacy): JsonResponse
    {
        $pharmacy = $this->pharmacyService->suspend($pharmacy, $request->input('reason'));

        $this->stateTransitionLogger->log(
            entityType: 'pharmacy',
            entityId: $pharmacy->id,
            event: 'suspend',
            fromState: PharmacyStatus::Active->value,
            toState: PharmacyStatus::Suspended->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
            reason: $request->input('reason'),
        );

        return response()->json([
            'message' => 'Pharmacy suspended.',
            'pharmacy' => new PharmacyResource($pharmacy),
        ]);
    }

    public function activate(Request $request, Pharmacy $pharmacy): JsonResponse
    {
        $pharmacy = $this->pharmacyService->activate($pharmacy);

        $this->stateTransitionLogger->log(
            entityType: 'pharmacy',
            entityId: $pharmacy->id,
            event: 'activate',
            fromState: PharmacyStatus::Suspended->value,
            toState: PharmacyStatus::Active->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'Pharmacy activated.',
            'pharmacy' => new PharmacyResource($pharmacy),
        ]);
    }

    public function destroy(Request $request, Pharmacy $pharmacy): JsonResponse
    {
        $previousStatus = $pharmacy->status->value;

        $pharmacy = $this->pharmacyService->archive($pharmacy);

        $this->stateTransitionLogger->log(
            entityType: 'pharmacy',
            entityId: $pharmacy->id,
            event: 'archive',
            fromState: $previousStatus,
            toState: PharmacyStatus::Archived->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'Pharmacy archived.',
        ]);
    }
}
