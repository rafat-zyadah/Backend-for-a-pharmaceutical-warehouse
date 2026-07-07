<?php

namespace App\Http\Middleware;

use App\Enums\ClientPlatform;
use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformMatchesRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $platform = ClientPlatform::fromHeader($request->header('X-Client-Platform'));

        if ($platform === null) {
            return response()->json([
                'message' => 'Missing or invalid X-Client-Platform header (web, desktop, mobile).',
            ], 422);
        }

        /** @var UserRole $role */
        $role = $user->role;

        if ($platform !== $role->allowedPlatform()) {
            return response()->json([
                'message' => 'This account is not allowed on this platform.',
            ], 403);
        }

        return $next($request);
    }
}
