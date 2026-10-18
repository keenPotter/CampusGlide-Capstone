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
    public function index()
    {
        return UserResource::collection(
            User::orderByRaw('deletion_requested_at IS NOT NULL')
                ->orderBy('last_name')->orderBy('first_name')->get()
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
        if ($user->role === 'administrator' && $data['role'] !== 'administrator') {
            return response()->json(['message' => 'Administrator accounts must keep the Administrator role.'], 422);
        }
        if (array_key_exists('password', $data) && blank($data['password'])) unset($data['password']);
        if (!empty($data['password'])) $data['password'] = Hash::make($data['password']);
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

        $user->deletion_requested_at = now();
        $user->is_active = false;
        $user->save();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Account scheduled for deletion in 30 days. It is disabled immediately and can be restored during the waiting period.',
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
