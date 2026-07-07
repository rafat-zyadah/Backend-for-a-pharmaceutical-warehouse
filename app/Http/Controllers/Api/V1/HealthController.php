<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'pharmacy-warehouse-api',
            'version' => 'v1',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
