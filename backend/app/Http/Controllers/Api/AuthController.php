<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * OPTIONAL — gamitin LANG kung wala pang /api/login ang team mo.
 * Kailangan: User model may `use HasApiTokens;` (Laravel Sanctum)
 * at nakapag-`php artisan install:api` na.
 * Ibinabalik: { token, role, user } — ito mismo ang binabasa ng frontend.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if (isset($user->is_active) && ! $user->is_active) {
            return response()->json(['message' => 'This account is inactive.'], 403);
        }

        return response()->json([
            'token' => $user->createToken('allocation-app')->plainTextToken,
            'role' => $user->role,
            'user' => [
                'id' => $user->id,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }
}
