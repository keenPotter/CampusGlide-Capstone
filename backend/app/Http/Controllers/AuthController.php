<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/register
     * Creates a user account. Administrators only (route middleware and RegisterRequest).
     */
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($data) {
            if ($data['role'] === User::ROLE_ADMINISTRATOR) {
                $activeAdministrators = User::query()
                    ->activeAdministrators()
                    ->lockForUpdate()
                    ->get();

                if ($activeAdministrators->count() >= User::MAX_ACTIVE_ADMINISTRATORS) {
                    return response()->json([
                        'message' => 'Maximum limit of 2 administrators reached.',
                    ], 400);
                }
            }

            // The User model hashes the password (see casts), so the plain value is passed here.
            $user = User::create($data);

            return (new UserResource($user))
                ->additional(['message' => 'User registered successfully'])
                ->response()
                ->setStatusCode(201);
        });
    }

    /**
     * POST /api/login
     */
    public function login(LoginRequest $request, AuditLogger $auditLogger)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['The provided email address is incorrect.'],
            ]);
        }

        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated.'],
            ]);
        }

        // The selected role is only a sign-in preference; it must match the role
        // already assigned to this account in the database.
        if ($user->role !== $request->role) {
            throw ValidationException::withMessages([
                'role' => ['The selected account type does not match this account.'],
            ]);
        }

        // Identify this browser tab/session so a repeat login replaces only
        // the token from this same session, not tokens on other devices.
        $clientSession = (string) $request->header('X-Client-Session', '');
        if ($clientSession === '' || ! preg_match('/^[a-f0-9-]{16,80}$/i', $clientSession)) {
            throw ValidationException::withMessages([
                'session' => ['The login session could not be identified. Please refresh the page and try again.'],
            ]);
        }

        return DB::transaction(function () use ($request, $user, $clientSession, $auditLogger) {
            // A repeat login replaces only this browser session's token.
            $tokenName = 'campusglide:' . $clientSession;
            $user->tokens()->where('name', $tokenName)->delete();
            $token = $user->createToken($tokenName)->plainTextToken;

            $auditLogger->logAction($request, 'LOGIN', 'users', $user->id, $user);

            return response()->json([
                'token' => $token,
                'user' => new UserResource($user),
            ]);
        });
    }

    /**
     * POST /api/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * GET /api/user
     */
    public function user(Request $request)
    {
        return new UserResource($request->user());
    }
}