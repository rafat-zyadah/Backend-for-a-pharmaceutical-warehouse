<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Support\Users\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $passwordChanged = $request->filled('password');

        $user = $this->userService->updateProfile(
            user: $request->user(),
            data: $request->validated(),
        );

        $response = [
            'message' => $passwordChanged
                ? 'Profile updated. Please sign in again on all devices.'
                : 'Profile updated.',
            'user' => new UserResource($user),
        ];

        if ($passwordChanged) {
            $response['requires_relogin'] = true;
        }

        return response()->json($response);
    }

    public function show(Request $request): JsonResponse
    {
        $this->authorize('updateProfile', $request->user());

        return response()->json(new UserResource($request->user()));
    }
}
