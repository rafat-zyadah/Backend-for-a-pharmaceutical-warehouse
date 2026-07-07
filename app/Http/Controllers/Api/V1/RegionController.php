<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRegionRequest;
use App\Http\Requests\Api\V1\StoreSubRegionRequest;
use App\Http\Requests\Api\V1\UpdateRegionRequest;
use App\Http\Requests\Api\V1\UpdateSubRegionRequest;
use App\Http\Resources\Api\V1\RegionResource;
use App\Http\Resources\Api\V1\SubRegionResource;
use App\Models\Region;
use App\Models\SubRegion;
use App\Support\Audit\StateTransitionLogger;
use App\Support\Geography\RegionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RegionController extends Controller
{
    public function __construct(
        private readonly RegionService $regionService,
        private readonly StateTransitionLogger $stateTransitionLogger,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Region::query()->with('subRegions')->orderBy('name');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where('name', 'like', "%{$search}%");
        }

        return RegionResource::collection(
            $query->paginate($request->integer('per_page', 50)),
        );
    }

    public function store(StoreRegionRequest $request): JsonResponse
    {
        $region = $this->regionService->createRegion($request->validated());

        $this->stateTransitionLogger->log(
            entityType: 'region',
            entityId: $region->id,
            event: 'create',
            fromState: null,
            toState: $region->status->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'Region created.',
            'region' => new RegionResource($region->load('subRegions')),
        ], 201);
    }

    public function show(Region $region): JsonResponse
    {
        return response()->json(
            new RegionResource($region->load('subRegions')),
        );
    }

    public function update(UpdateRegionRequest $request, Region $region): JsonResponse
    {
        $previousStatus = $region->status->value;

        $region = $this->regionService->updateRegion($region, $request->validated());

        $this->stateTransitionLogger->log(
            entityType: 'region',
            entityId: $region->id,
            event: 'update',
            fromState: $previousStatus,
            toState: $region->status->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'Region updated.',
            'region' => new RegionResource($region->load('subRegions')),
        ]);
    }

    public function storeSubRegion(StoreSubRegionRequest $request, Region $region): JsonResponse
    {
        $subRegion = $this->regionService->createSubRegion($region, $request->validated());

        $this->stateTransitionLogger->log(
            entityType: 'sub_region',
            entityId: $subRegion->id,
            event: 'create',
            fromState: null,
            toState: $subRegion->status->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
            metadata: ['region_id' => $region->id],
        );

        return response()->json([
            'message' => 'Sub-region created.',
            'sub_region' => new SubRegionResource($subRegion),
        ], 201);
    }

    public function updateSubRegion(UpdateSubRegionRequest $request, SubRegion $subRegion): JsonResponse
    {
        $previousStatus = $subRegion->status->value;

        $subRegion = $this->regionService->updateSubRegion($subRegion, $request->validated());

        $this->stateTransitionLogger->log(
            entityType: 'sub_region',
            entityId: $subRegion->id,
            event: 'update',
            fromState: $previousStatus,
            toState: $subRegion->status->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'Sub-region updated.',
            'sub_region' => new SubRegionResource($subRegion),
        ]);
    }
}
