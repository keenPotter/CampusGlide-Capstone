<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'role' => ['nullable', Rule::in(['administrator', 'faculty', 'staff'])],
            'deletion_status' => ['nullable', Rule::in(['pending'])],
        ]);

        $query = User::query();

        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (($filters['deletion_status'] ?? null) === 'pending') {
            $query->whereNotNull('deletion_requested_at');
        }

        if ($request->filled('search')) {
            $terms = preg_split('/\s+/', trim($request->query('search')));
            $query->where(function ($searchQuery) use ($terms) {
                foreach ($terms as $term) {
                    $searchQuery->where(function ($termQuery) use ($term) {
                        $pattern = "%{$term}%";
                        $roleTerm = str_contains('user', strtolower($term)) ? 'faculty' : $term;
                        $termQuery
                            ->where('first_name', 'like', $pattern)
                            ->orWhere('last_name', 'like', $pattern)
                            ->orWhere('email', 'like', $pattern)
                            ->orWhere('role', 'like', "%{$roleTerm}%")
                            ->orWhere('position', 'like', $pattern)
                            ->orWhere('phone_number', 'like', $pattern);
                    });
                }
            });
        }

        return UserResource::collection(
            $query->orderByRaw('deletion_requested_at IS NOT NULL')
                ->orderBy('last_name')->orderBy('first_name')
                ->paginate(10)
                ->appends($request->query())
        );
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'position' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(['administrator', 'faculty', 'staff'])],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        // Admin accounts may be edited, but never converted to another role.
        if ($user->role === User::ROLE_ADMINISTRATOR && $data['role'] !== User::ROLE_ADMINISTRATOR) {
            return response()->json(['message' => 'Administrator accounts must keep the Administrator role.'], 422);
        }
        if (array_key_exists('password', $data) && blank($data['password'])) unset($data['password']);
        if (!empty($data['password'])) $data['password'] = Hash::make($data['password']);

        $isAdministratorPromotion = $user->is_active
            && $user->role !== User::ROLE_ADMINISTRATOR
            && $data['role'] === User::ROLE_ADMINISTRATOR;

        if ($isAdministratorPromotion) {
            return DB::transaction(function () use ($user, $data) {
                $activeAdministrators = User::query()
                    ->activeAdministrators()
                    ->lockForUpdate()
                    ->get();

                if ($activeAdministrators->count() >= User::MAX_ACTIVE_ADMINISTRATORS) {
                    return response()->json([
                        'message' => 'Maximum limit of 2 administrators reached.',
                    ], 400);
                }

                $user->update($data);

                return new UserResource($user->fresh());
            });
        }

        $user->update($data);

        return new UserResource($user->fresh());
    }

    public function requestDeletion(Request $request, User $user)
    {
        if ($user->role === 'administrator') {
            return response()->json(['message' => 'Administrator accounts cannot be deleted.'], 422);
        }
        if ($user->deletion_requested_at) {
            return response()->json(['message' => 'This account is already pending deletion.'], 422);
        }
        if ((int) $request->user()->id === (int) $user->id) {
            return response()->json(['message' => 'You cannot schedule your own account for deletion.'], 422);
        }

        DB::transaction(function () use ($user) {
            $user->deletion_requested_at = now();
            $user->is_active = false;
            $user->save();
            $user->tokens()->delete();
        });

        return response()->json([
            'message' => 'Account deactivated. Personal details are scheduled to be anonymized after 30 days; the account record and historical references will remain. The account can be restored during this period.',
            'user' => new UserResource($user),
        ]);
    }

    public function cancelDeletion(Request $request, User $user)
    {
        if (!$user->deletion_requested_at) {
            return response()->json(['message' => 'This account is not pending deletion.'], 422);
        }
        if ($user->deletion_requested_at->copy()->addDays(30)->lte(now())) {
            return response()->json(['message' => 'The 30-day deletion period has expired.'], 422);
        }
        $user->deletion_requested_at = null;
        $user->is_active = true;
        $user->save();

        return new UserResource($user);
    }
}
