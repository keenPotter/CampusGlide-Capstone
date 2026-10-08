<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AllocationResource;
use App\Models\Allocation;
use App\Models\TripRequestShare;
use App\Models\User;
use App\Services\AllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TripRequestShareController extends Controller
{
    private const WITH = [
        'allocation.trip.vehicleRequest.requester',
        'allocation.vehicle',
        'allocation.driver',
        'allocation.allocatedBy',
        'sender',
    ];

    public function __construct()
    {
        Allocation::registerSharedRelations();
    }

    public function admins(Request $request): JsonResponse
    {
        $this->assertAdmin($request);

        $admins = User::query()
            ->whereIn('role', AllocationService::ADMIN_ROLES)
            ->where('id', '!=', $request->user()->id)
            ->orderBy('email')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => self::userName($user),
                'email' => $user->email,
            ])
            ->values();

        return response()->json(['data' => $admins]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertAdmin($request);

        $shares = TripRequestShare::query()
            ->with(self::WITH)
            ->where('recipient_id', $request->user()->id)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (TripRequestShare $share) => $this->shape($share, $request))
            ->values();

        return response()->json(['data' => $shares]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertAdmin($request);

        $data = $request->validate([
            'allocation_id' => ['required', 'integer', 'exists:allocations,id'],
            'recipient_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->whereIn('role', AllocationService::ADMIN_ROLES)
                ),
            ],
        ]);

        abort_if((int) $data['recipient_id'] === (int) $request->user()->id, 422, 'Choose another admin.');

        $allocation = Allocation::with('trip')->findOrFail($data['allocation_id']);
        abort_unless($allocation->trip && $allocation->trip->trip_status !== 'cancelled', 422, 'Cancelled trip requests cannot be sent.');

        $share = TripRequestShare::create([
            'allocation_id' => $allocation->id,
            'sender_id' => $request->user()->id,
            'recipient_id' => $data['recipient_id'],
        ])->load(self::WITH);

        return response()->json(['data' => $this->shape($share, $request)], 201);
    }

    private function assertAdmin(Request $request): void
    {
        abort_unless(AllocationService::isAdmin($request->user()), 403, 'Admin only.');
    }

    private function shape(TripRequestShare $share, Request $request): array
    {
        return [
            'id' => $share->id,
            'created_at' => $share->created_at,
            'sender' => [
                'id' => $share->sender->id,
                'name' => self::userName($share->sender),
                'email' => $share->sender->email,
            ],
            'recipient' => [
                'id' => $share->recipient->id,
                'name' => self::userName($share->recipient),
                'email' => $share->recipient->email,
            ],
            'allocation' => (new AllocationResource($share->allocation))->resolve($request),
        ];
    }

    private static function userName(User $user): string
    {
        $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        return $name !== '' ? $name : ($user->name ?? $user->email);
    }
}
