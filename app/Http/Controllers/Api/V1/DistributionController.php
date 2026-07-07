<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignPharmaciesRequest;
use App\Http\Requests\Api\V1\AssignRegionRequest;
use App\Http\Requests\Api\V1\RemoveRegionAssignmentRequest;
use App\Http\Requests\Api\V1\TransferRegionRequest;
use App\Http\Resources\Api\V1\PharmacyResource;
use App\Http\Resources\Api\V1\RepDistributionResource;
use App\Http\Resources\Api\V1\RepPharmacyAssignmentResource;
use App\Http\Resources\Api\V1\RepRegionAssignmentResource;
use App\Models\Pharmacy;
use App\Models\Region;
use App\Models\RepPharmacyAssignment;
use App\Models\User;
use App\Support\Audit\StateTransitionLogger;
use App\Support\Distribution\DistributionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DistributionController extends Controller
{
    public function __construct(
        private readonly DistributionService $distributionService,
        private readonly StateTransitionLogger $stateTransitionLogger,
    ) {}

    public function dashboard(): JsonResponse
    {
        return response()->json([
            'summary' => $this->distributionService->dashboardStats(),
        ]);
    }

    public function reps(Request $request): AnonymousResourceCollection
    {
        $query = User::query()
            ->role(UserRole::Rep)
            ->withCount(['activeRegionAssignments', 'activePharmacyAssignments'])
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $reps = $query->paginate($request->integer('per_page', 15));

        $sharedPharmacyIds = RepPharmacyAssignment::query()
            ->where('status', \App\Enums\AssignmentStatus::Active)
            ->select('pharmacy_id')
            ->groupBy('pharmacy_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('pharmacy_id');

        $reps->getCollection()->transform(function (User $rep) use ($sharedPharmacyIds): User {
            $rep->shared_pharmacies_count = RepPharmacyAssignment::query()
                ->where('rep_id', $rep->id)
                ->where('status', \App\Enums\AssignmentStatus::Active)
                ->whereIn('pharmacy_id', $sharedPharmacyIds)
                ->count();

            return $rep;
        });

        return RepDistributionResource::collection($reps);
    }

    public function showRep(User $user): JsonResponse
    {
        if ($user->role !== UserRole::Rep) {
            abort(404);
        }

        $user->load([
            'activeRegionAssignments.region',
            'activePharmacyAssignments.pharmacy.region',
            'activePharmacyAssignments.pharmacy.subRegion',
        ]);

        $user->loadCount(['activeRegionAssignments', 'activePharmacyAssignments']);

        $user->activePharmacyAssignments->each(function (RepPharmacyAssignment $assignment): void {
            $assignment->is_shared = $this->distributionService->isPharmacyShared($assignment->pharmacy);
        });

        $sharedCount = $user->activePharmacyAssignments
            ->filter(fn (RepPharmacyAssignment $a) => (bool) $a->is_shared)
            ->count();

        $user->shared_pharmacies_count = $sharedCount;

        return response()->json(new RepDistributionResource($user));
    }

    public function assignRegion(AssignRegionRequest $request, User $user): JsonResponse
    {
        $region = Region::query()->findOrFail($request->input('region_id'));

        $result = $this->distributionService->assignRegion(
            $user,
            $region,
            $request->validated(),
            $request->user(),
        );

        $this->stateTransitionLogger->log(
            entityType: 'rep_region_assignment',
            entityId: $result['region']->id,
            event: 'assign_region',
            fromState: null,
            toState: $result['region']->status->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
            metadata: [
                'rep_id' => $user->id,
                'region_id' => $region->id,
                'mode' => $request->input('mode', 'add'),
                'pharmacies_assigned' => $result['pharmacies_assigned'],
            ],
        );

        return response()->json([
            'message' => 'Region assigned.',
            'region_assignment' => new RepRegionAssignmentResource($result['region']->load('region')),
            'pharmacies_assigned' => $result['pharmacies_assigned'],
        ], 201);
    }

    public function removeRegion(RemoveRegionAssignmentRequest $request, User $user, Region $region): JsonResponse
    {
        $result = $this->distributionService->removeRegion(
            $user,
            $region,
            $request->input('reason'),
            $request->user(),
        );

        $this->stateTransitionLogger->log(
            entityType: 'rep_region_assignment',
            entityId: $region->id,
            event: 'remove_region',
            fromState: 'active',
            toState: 'ended',
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
            reason: $request->input('reason'),
            metadata: [
                'rep_id' => $user->id,
                'region_id' => $region->id,
                'pharmacies_ended' => $result['pharmacies_ended'],
                'pharmacies_now_unassigned' => $result['pharmacies_now_unassigned'],
            ],
        );

        return response()->json([
            'message' => 'Region removed from rep.',
            'result' => $result,
        ]);
    }

    public function assignPharmacies(AssignPharmaciesRequest $request, User $user): JsonResponse
    {
        $assignments = $this->distributionService->assignPharmacies(
            $user,
            $request->validated(),
            $request->user(),
        );

        foreach ($assignments as $assignment) {
            $this->stateTransitionLogger->log(
                entityType: 'rep_pharmacy_assignment',
                entityId: $assignment->id,
                event: 'assign_pharmacy',
                fromState: null,
                toState: $assignment->status->value,
                actorId: $request->user()->id,
                actorRole: $request->user()->role->value,
                request: $request,
                metadata: [
                    'rep_id' => $user->id,
                    'pharmacy_id' => $assignment->pharmacy_id,
                    'mode' => $request->input('mode', 'add'),
                ],
            );
        }

        $assignmentIds = $assignments->pluck('id');

        $loaded = RepPharmacyAssignment::query()
            ->whereIn('id', $assignmentIds)
            ->with('pharmacy.region', 'pharmacy.subRegion')
            ->get();

        return response()->json([
            'message' => 'Pharmacies assigned.',
            'assignments' => RepPharmacyAssignmentResource::collection($loaded),
        ], 201);
    }

    public function removePharmacy(Request $request, User $user, Pharmacy $pharmacy): JsonResponse
    {
        $assignment = $this->distributionService->removePharmacy($user, $pharmacy, $request->user());

        $this->stateTransitionLogger->log(
            entityType: 'rep_pharmacy_assignment',
            entityId: $assignment->id,
            event: 'remove_pharmacy',
            fromState: 'active',
            toState: 'ended',
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
            metadata: ['rep_id' => $user->id, 'pharmacy_id' => $pharmacy->id],
        );

        return response()->json([
            'message' => 'Pharmacy removed from rep.',
            'assignment' => new RepPharmacyAssignmentResource($assignment),
        ]);
    }

    public function setPrimary(Request $request, User $user, Pharmacy $pharmacy): JsonResponse
    {
        $assignment = $this->distributionService->setPrimaryRep($pharmacy, $user, $request->user());

        $this->stateTransitionLogger->log(
            entityType: 'rep_pharmacy_assignment',
            entityId: $assignment->id,
            event: 'set_primary',
            fromState: null,
            toState: 'active',
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
            metadata: ['rep_id' => $user->id, 'pharmacy_id' => $pharmacy->id],
        );

        return response()->json([
            'message' => 'Primary rep updated.',
            'assignment' => new RepPharmacyAssignmentResource($assignment),
        ]);
    }

    public function transferRegion(TransferRegionRequest $request): JsonResponse
    {
        $result = $this->distributionService->transferRegion($request->validated(), $request->user());

        $this->stateTransitionLogger->log(
            entityType: 'rep_region_assignment',
            entityId: $result['region']->id,
            event: 'transfer_region',
            fromState: null,
            toState: $result['region']->status->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
            reason: $request->input('reason'),
            metadata: $request->only(['from_rep_id', 'to_rep_id', 'region_id', 'effective_date']),
        );

        return response()->json([
            'message' => 'Region transferred.',
            'region_assignment' => new RepRegionAssignmentResource($result['region']->load('region')),
            'pharmacies_assigned' => $result['pharmacies_assigned'],
        ]);
    }

    public function unassignedPharmacies(): JsonResponse
    {
        return response()->json([
            'pharmacies' => PharmacyResource::collection(
                $this->distributionService->unassignedPharmacies(),
            ),
        ]);
    }

    public function sharedPharmacies(): JsonResponse
    {
        $pharmacies = $this->distributionService->sharedPharmacies();

        return response()->json([
            'pharmacies' => $pharmacies->map(fn (Pharmacy $pharmacy) => [
                'pharmacy' => new PharmacyResource($pharmacy),
                'reps' => $pharmacy->activeRepAssignments->map(fn (RepPharmacyAssignment $a) => [
                    'id' => $a->rep?->id,
                    'name' => $a->rep?->name,
                    'is_primary' => $a->is_primary,
                    'start_date' => $a->start_date?->toDateString(),
                ]),
            ]),
        ]);
    }

    public function pharmacyReps(Pharmacy $pharmacy): JsonResponse
    {
        $assignments = RepPharmacyAssignment::query()
            ->with('rep')
            ->where('pharmacy_id', $pharmacy->id)
            ->where('status', \App\Enums\AssignmentStatus::Active)
            ->orderByDesc('is_primary')
            ->get();

        return response()->json([
            'pharmacy' => new PharmacyResource($pharmacy->load(['region', 'subRegion'])),
            'is_shared' => $assignments->count() > 1,
            'assignments' => RepPharmacyAssignmentResource::collection($assignments),
        ]);
    }
}
