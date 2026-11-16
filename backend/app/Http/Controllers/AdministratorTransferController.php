<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransferAdministratorRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdministratorTransferController extends Controller
{
    public function store(TransferAdministratorRequest $request)
    {
        $replacementData = $request->validated();
        $outgoingAdministrator = $request->user();

        return DB::transaction(function () use ($replacementData, $outgoingAdministrator) {
            $activeAdministrators = User::query()
                ->activeAdministrators()
                ->lockForUpdate()
                ->get();

            $outgoingAdministratorIsActive = $activeAdministrators->contains(
                fn (User $administrator) => $administrator->is($outgoingAdministrator)
            );

            if (
                $activeAdministrators->count() !== User::MAX_ACTIVE_ADMINISTRATORS
                || ! $outgoingAdministratorIsActive
            ) {
                return response()->json([
                    'message' => 'Administrator replacement requires exactly two active administrators, including your account.',
                ], 409);
            }

            $replacement = User::create([
                ...$replacementData,
                'role' => User::ROLE_ADMINISTRATOR,
                'is_active' => true,
            ]);

            $outgoingAdministrator->is_active = false;
            $outgoingAdministrator->save();
            $outgoingAdministrator->tokens()->delete();

            return (new UserResource($replacement))
                ->additional(['message' => 'Administrator transfer completed. Sign in with the replacement account.'])
                ->response()
                ->setStatusCode(201);
        });
    }
}
