<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\RescheduleRequest;
use App\Services\AllocationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Palit ng petsa ng trip.
 *  - Faculty (end user): request lang (POST allocations/{id}/reschedule-requests).
 *  - Admin: approve / cancel ang request, o direct na palitan ang petsa na may reason.
 */
class RescheduleRequestController extends Controller
{
    private const WITH = ['trip', 'requester', 'handler'];

    public function __construct(protected AllocationService $allocations)
    {
        Allocation::registerSharedRelations();
    }

    /** Admin: lahat. End user: sariling requests lang. ?status=pending */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = RescheduleRequest::with(self::WITH)->latest();

        if (! AllocationService::isAdmin($user)) {
            $query->whereHas('trip.vehicleRequest', fn ($r) => $r->where('requester_id', $user->id));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->limit(100)->get()->map(fn ($r) => $this->shape($r))->values()]);
    }

    /** Faculty: request for date change (hindi siya ang naglilipat). */
    public function store(Request $request, Allocation $allocation): JsonResponse
    {
        $user = $request->user();

        abort_if(AllocationService::isAdmin($user), 403, 'Admins change the date directly.');

        $allocation->loadMissing('trip.vehicleRequest');
        $trip = $allocation->trip;

        abort_unless(
            (int) $trip?->vehicleRequest?->requester_id === (int) $user->id,
            403,
            'You may only request a date change for your own trips.'
        );

        $data = $request->validate([
            'new_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($trip->trip_status !== 'scheduled') {
            return response()->json(['message' => "A {$trip->trip_status} trip can no longer be rescheduled."], 422);
        }

        if (RescheduleRequest::where('trip_id', $trip->id)->where('status', 'pending')->exists()) {
            return response()->json(['message' => 'This trip already has a pending date change request.'], 422);
        }

        $old = Carbon::parse($trip->trip_date)->toDateString();

        if ($data['new_date'] === $old) {
            return response()->json(['message' => 'The new date is the same as the current date.'], 422);
        }

        $rr = RescheduleRequest::create([
            'trip_id' => $trip->id,
            'requested_by' => $user->id,
            'old_date' => $old,
            'new_date' => $data['new_date'],
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        return response()->json(['data' => $this->shape($rr->load(self::WITH))], 201);
    }

    /** Admin: i-approve = ililipat ang trip sa hiniling na petsa (may conflict check). */
    public function approve(Request $request, RescheduleRequest $rescheduleRequest): JsonResponse
    {
        abort_unless(AllocationService::isAdmin($request->user()), 403, 'Admin only.');
        $this->assertPending($rescheduleRequest);

        $request->validate(['admin_reason' => ['nullable', 'string', 'max:500']]);

        $this->allocations->moveTrip($rescheduleRequest->trip, Carbon::parse($rescheduleRequest->new_date)->toDateString());

        $rescheduleRequest->update([
            'status' => 'approved',
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
            'admin_reason' => $request->input('admin_reason'),
        ]);

        return response()->json(['data' => $this->shape($rescheduleRequest->load(self::WITH))]);
    }

    /** Admin: i-delete ang request kapag kinansela (hindi gagalawin ang trip). */
    public function cancel(Request $request, RescheduleRequest $rescheduleRequest): JsonResponse
    {
        abort_unless(AllocationService::isAdmin($request->user()), 403, 'Admin only.');
        $this->assertPending($rescheduleRequest);

        $rescheduleRequest->delete();

        return response()->json(['message' => 'Request cancelled and deleted.']);
    }

    /** Admin: direct na palit ng petsa, kailangan ng reason. */
    public function reschedule(Request $request, Allocation $allocation): JsonResponse
    {
        abort_unless(AllocationService::isAdmin($request->user()), 403, 'Admin only.');

        $data = $request->validate([
            'new_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $trip = $allocation->trip;
        $old = $this->allocations->moveTrip($trip, $data['new_date']);

        // Hindi na kailangan ang pending faculty request dahil direkta nang pinalitan ng Admin ang petsa.
        RescheduleRequest::where('trip_id', $trip->id)->where('status', 'pending')->delete();

        $rr = RescheduleRequest::create([
            'trip_id' => $trip->id,
            'requested_by' => $request->user()->id,
            'old_date' => $old,
            'new_date' => $data['new_date'],
            'reason' => $data['reason'],
            'status' => 'approved',
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        return response()->json(['data' => $this->shape($rr->load(self::WITH))], 201);
    }

    private function assertPending(RescheduleRequest $rr): void
    {
        abort_unless($rr->status === 'pending', 422, "This request is already {$rr->status}.");
    }

    private function shape(RescheduleRequest $r): array
    {
        $trip = $r->trip;
        $name = fn ($u) => $u ? trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) : null;

        return [
            'id' => $r->id,
            'status' => $r->status,
            'old_date' => Carbon::parse($r->old_date)->toDateString(),
            'new_date' => Carbon::parse($r->new_date)->toDateString(),
            'reason' => $r->reason,
            'admin_reason' => $r->admin_reason,
            'requested_by' => $name($r->requester),
            'handled_by' => $name($r->handler),
            'handled_at' => $r->handled_at,
            'created_at' => $r->created_at,
            'allocation_id' => $trip ? Allocation::where('trip_id', $trip->id)->value('id') : null,
            'trip' => [
                'id' => $trip?->id,
                'destination' => $trip?->destination,
                'trip_date' => $trip?->trip_date ? Carbon::parse($trip->trip_date)->toDateString() : null,
                'departure_time' => $trip?->departure_time,
                'estimated_return_time' => $trip?->estimated_return_time,
            ],
        ];
    }
}
