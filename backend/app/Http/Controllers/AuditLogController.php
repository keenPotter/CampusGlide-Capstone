<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'user_email' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = AuditLog::query()
            ->with('user:id,first_name,last_name,email');

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['user_email'])) {
            $query->whereHas('user', function ($userQuery) use ($filters) {
                $userQuery->where('email', 'like', '%' . $filters['user_email'] . '%');
            });
        }

        $page = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20)
            ->appends($request->query());

        return AuditLogResource::collection($page);
    }
}
