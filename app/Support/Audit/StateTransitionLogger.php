<?php

namespace App\Support\Audit;

use App\Models\StateTransitionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StateTransitionLogger
{
    public function log(
        string $entityType,
        string $entityId,
        string $event,
        ?string $fromState,
        ?string $toState,
        ?string $actorId = null,
        ?string $actorRole = null,
        ?Request $request = null,
        ?string $reason = null,
        ?array $metadata = null,
        ?string $correlationId = null,
    ): StateTransitionLog {
        return StateTransitionLog::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'event' => $event,
            'from_state' => $fromState,
            'to_state' => $toState,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'occurred_at' => now(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'reason' => $reason,
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'metadata' => $metadata,
        ]);
    }
}
