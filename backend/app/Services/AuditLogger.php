<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuditLogger
{
    public function logAction(
        Request $request,
        string $action,
        ?string $targetTable,
        int|string|null $targetId,
        ?User $actor = null,
    ): void {
        $userId = $actor?->getKey() ?? $request->user()?->getAuthIdentifier();

        if ($userId === null) {
            throw new LogicException('An authenticated user is required for audit logging.');
        }

        DB::table('audit_logs')->insert([
            'user_id' => $userId,
            'action' => $action,
            'target_table' => $targetTable,
            'target_id' => $targetId === null ? null : (string) $targetId,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
