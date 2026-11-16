<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(
        Request $request,
        Closure $next,
        string $action,
        string $targetTable,
    ): Response {
        return DB::transaction(function () use ($request, $next, $action, $targetTable) {
            $response = $next($request);

            if ($response->isSuccessful()) {
                $actor = $request->user();

                if (! $actor instanceof User) {
                    throw new \LogicException('An authenticated user is required for audit logging.');
                }

                $effectiveAction = $action === 'UPDATE_MAINTENANCE'
                    && $request->input('status') === 'completed'
                    ? 'COMPLETE_MAINTENANCE'
                    : $action;

                $this->auditLogger->logAction(
                    $request,
                    $effectiveAction,
                    $targetTable,
                    $this->targetId($request, $response, $targetTable, $actor),
                    $actor,
                );
            }

            return $response;
        });
    }

    private function targetId(
        Request $request,
        Response $response,
        string $targetTable,
        User $actor,
    ): int|string|null {
        $responseId = $this->responseId($response);

        if ($request->isMethod('POST') && $responseId !== null) {
            return $responseId;
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model && $parameter->getTable() === $targetTable) {
                return $parameter->getKey();
            }
        }

        return $responseId ?? ($targetTable === 'users' ? $actor->getKey() : null);
    }

    private function responseId(Response $response): int|string|null
    {
        if (! $response instanceof JsonResponse) {
            return null;
        }

        $payload = $response->getData(true);
        if (! is_array($payload)) {
            return null;
        }

        foreach (['data', 'user', 'record'] as $key) {
            $candidate = $payload[$key] ?? null;

            if (
                is_array($candidate)
                && isset($candidate['id'])
                && (is_int($candidate['id']) || is_string($candidate['id']))
            ) {
                return $candidate['id'];
            }
        }

        return isset($payload['id']) && (is_int($payload['id']) || is_string($payload['id']))
            ? $payload['id']
            : null;
    }
}
