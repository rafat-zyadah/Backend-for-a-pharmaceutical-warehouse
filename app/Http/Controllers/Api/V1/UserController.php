<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Support\Audit\StateTransitionLogger;
use App\Support\Users\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly StateTransitionLogger $stateTransitionLogger,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $reps = User::query()->role(UserRole::Rep);

        return response()->json([
            'summary' => [
                'reps_total' => (clone $reps)->count(),
                'reps_active' => (clone $reps)->status(UserStatus::Active)->count(),
                'reps_suspended' => (clone $reps)->status(UserStatus::Suspended)->count(),
                'invoicers_total' => User::query()->role(UserRole::Invoicer)->count(),
                'supervisors_total' => User::query()->role(UserRole::Supervisor)->count(),
                'users_total' => User::query()->count(),
                'monthly_target' => null,
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->orderBy('name');

        if ($request->filled('role')) {
            $query->role(UserRole::from($request->string('role')->toString()));
        }

        if ($request->filled('status')) {
            $query->status(UserStatus::from($request->string('status')->toString()));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return UserResource::collection(
            $query->paginate($request->integer('per_page', 15)),
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        $this->stateTransitionLogger->log(
            entityType: 'user',
            entityId: $user->id,
            event: 'create',
            fromState: null,
            toState: $user->status->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
            metadata: ['role' => $user->role->value],
        );

        return response()->json([
            'message' => 'User created.',
            'user' => new UserResource($user),
        ], 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $includePassword = $user->passwordVisibleTo($request->user());

        if ($includePassword) {
            $this->stateTransitionLogger->log(
                entityType: 'user',
                entityId: $user->id,
                event: 'view_password',
                fromState: null,
                toState: null,
                actorId: $request->user()->id,
                actorRole: $request->user()->role->value,
                request: $request,
                reason: 'Supervisor viewed employee password (UC-511).',
            );
        }

        return response()->json(
            (new UserResource($user))->withPassword($includePassword),
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $previousStatus = $user->status->value;

        $passwordChanged = $request->filled('password');

        $user = $this->userService->update($user, $request->validated());

        $this->stateTransitionLogger->log(
            entityType: 'user',
            entityId: $user->id,
            event: 'update',
            fromState: $previousStatus,
            toState: $user->status->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        $includePassword = $user->passwordVisibleTo($request->user());

        if ($includePassword && $request->filled('password')) {
            $this->stateTransitionLogger->log(
                entityType: 'user',
                entityId: $user->id,
                event: 'reset_password_by_supervisor',
                fromState: null,
                toState: null,
                actorId: $request->user()->id,
                actorRole: $request->user()->role->value,
                request: $request,
                reason: 'Supervisor reset employee password.',
            );
        }

        $response = [
            'message' => $passwordChanged
                ? 'User updated. The user must sign in again on all devices.'
                : 'User updated.',
            'user' => (new UserResource($user))->withPassword($includePassword),
        ];

        if ($passwordChanged) {
            $response['requires_relogin'] = true;
        }

        return response()->json($response);
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        $this->authorize('suspend', $user);

        $user = $this->userService->suspend($user);

        $this->stateTransitionLogger->log(
            entityType: 'user',
            entityId: $user->id,
            event: 'suspend',
            fromState: UserStatus::Active->value,
            toState: UserStatus::Suspended->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'User suspended.',
            'user' => new UserResource($user),
        ]);
    }

    public function restore(Request $request, User $user): JsonResponse
    {
        $this->authorize('restore', $user);

        $user = $this->userService->restore($user);

        $this->stateTransitionLogger->log(
            entityType: 'user',
            entityId: $user->id,
            event: 'restore_from_suspend',
            fromState: UserStatus::Suspended->value,
            toState: UserStatus::Active->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        return response()->json([
            'message' => 'User restored.',
            'user' => new UserResource($user),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->stateTransitionLogger->log(
            entityType: 'user',
            entityId: $user->id,
            event: 'soft_delete',
            fromState: $user->status->value,
            toState: UserStatus::Deleted->value,
            actorId: $request->user()->id,
            actorRole: $request->user()->role->value,
            request: $request,
        );

        $this->userService->delete($user);

        return response()->json([
            'message' => 'User deleted.',
        ]);
    }
}
