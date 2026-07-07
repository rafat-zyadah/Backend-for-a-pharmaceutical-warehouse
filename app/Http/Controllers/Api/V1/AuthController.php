<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ClientPlatform;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Support\Audit\StateTransitionLogger;
use App\Support\Users\SupervisorContactService;
use App\Support\Users\SupervisorPasswordRecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly StateTransitionLogger $stateTransitionLogger,
        private readonly SupervisorContactService $supervisorContactService,
        private readonly SupervisorPasswordRecoveryService $supervisorPasswordRecoveryService,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $platform = ClientPlatform::fromHeader($request->header('X-Client-Platform'));

        if ($platform === null) {
            throw ValidationException::withMessages([
                'platform' => ['Missing or invalid X-Client-Platform header (web, desktop, mobile).'],
            ]);
        }

        $user = User::query()
            ->where('username', $validated['login'])
            ->orWhere('phone', $validated['login'])
            ->first();

        if ($user === null || $user->password !== $validated['password']) {
            throw ValidationException::withMessages([
                'login' => ['Invalid credentials.'],
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'login' => ['Account is not active.'],
            ]);
        }

        if ($platform !== $user->role->allowedPlatform()) {
            throw ValidationException::withMessages([
                'login' => ['This account is not allowed on this platform.'],
            ]);
        }

        $token = $user->createToken(
            name: 'api-'.$platform->value,
            abilities: [$user->role->value],
        )->plainTextToken;

        $this->stateTransitionLogger->log(
            entityType: 'user',
            entityId: $user->id,
            event: 'login',
            fromState: null,
            toState: 'authenticated',
            actorId: $user->id,
            actorRole: $user->role->value,
            request: $request,
        );

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'role' => $user->role->value,
                'platform' => $platform->value,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user !== null) {
            $request->user()->currentAccessToken()?->delete();

            $this->stateTransitionLogger->log(
                entityType: 'user',
                entityId: $user->id,
                event: 'logout',
                fromState: 'authenticated',
                toState: null,
                actorId: $user->id,
                actorRole: $user->role->value,
                request: $request,
            );
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $platform = ClientPlatform::fromHeader($request->header('X-Client-Platform'));

        if (! in_array($platform, [ClientPlatform::Mobile, ClientPlatform::Desktop], true)) {
            throw ValidationException::withMessages([
                'platform' => ['Password recovery contact is available on mobile and desktop apps only.'],
            ]);
        }

        $supervisor = $this->supervisorContactService->resolvePrimary();

        if ($supervisor === null) {
            return response()->json([
                'message' => 'Supervisor contact is not available. Please try again later.',
                'supervisor' => null,
            ], 503);
        }

        return response()->json([
            'message' => 'للاستعلام عن كلمة المرور أو إعادة تعيينها، يرجى التواصل مع المشرف مباشرةً.',
            'supervisor' => [
                'name' => $supervisor->name,
                'phone' => $supervisor->phone,
            ],
        ]);
    }

    public function recoverSupervisorPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
        ]);

        $platform = ClientPlatform::fromHeader($request->header('X-Client-Platform'));

        if ($platform !== ClientPlatform::Web) {
            throw ValidationException::withMessages([
                'platform' => ['Supervisor password recovery is available on the web app only.'],
            ]);
        }

        $supervisor = User::query()
            ->role(UserRole::Supervisor)
            ->where(function ($query) use ($validated): void {
                $query->where('username', $validated['login'])
                    ->orWhere('phone', $validated['login'])
                    ->orWhere('email', $validated['login']);
            })
            ->first();

        if ($supervisor === null || $supervisor->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'login' => ['No active supervisor account matches the provided identifier.'],
            ]);
        }

        $channels = $this->supervisorPasswordRecoveryService->send($supervisor);

        $this->stateTransitionLogger->log(
            entityType: 'user',
            entityId: $supervisor->id,
            event: 'recover_supervisor_password',
            fromState: null,
            toState: null,
            actorId: $supervisor->id,
            actorRole: $supervisor->role->value,
            request: $request,
            reason: 'Supervisor requested password recovery (UC-512).',
            metadata: ['channels' => $channels],
        );

        return response()->json([
            'message' => 'Your password has been sent to your registered contact channel(s).',
            'channels' => $channels,
        ]);
    }
}
