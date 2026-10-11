<?php

namespace App\Http\Controllers;

use App\Models\VehicleRequest;
use Carbon\Carbon;

class VehicleRequestPrintController extends Controller
{
    // Officers whose names are printed on the form.
    // Change the names here if the officers change.
    private const CHIEF_MOTORPOOL = 'TONY O. BASATAN';
    private const UNIT_HEAD_GSU = 'JAYSON R. PIMENTEL';

    public function show(VehicleRequest $vehicleRequest)
    {
        // Only approved requests can be printed.
        if ($vehicleRequest->status !== 'approved') {
            abort(422, 'Only approved requests can be printed.');
        }

        $vehicleRequest->loadMissing('requester');
        $requester = $vehicleRequest->requester;

        $start = $vehicleRequest->trip_date ? Carbon::parse($vehicleRequest->trip_date) : null;
        $end = $vehicleRequest->trip_end_date ? Carbon::parse($vehicleRequest->trip_end_date) : $start;

        $days = $vehicleRequest->travel_days;
        if (!$days && $start && $end) {
            $days = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
        }

        $travelDate = '';
        if ($start) {
            $travelDate = ($end && !$end->isSameDay($start))
                ? $start->format('F j, Y') . ' to ' . $end->format('F j, Y')
                : $start->format('F j, Y');
        }

        $requestedAt = $vehicleRequest->created_at;

        $official = $requester
            ? strtoupper(trim($requester->first_name . ' ' . $requester->last_name))
            : '';

        return response()->view('print.vehicle-request', [
            'logo' => $this->logo(),
            'requestNo' => $vehicleRequest->id,
            'official' => $official,
            'position' => $requester->position ?? '',
            'destination' => $vehicleRequest->destination,
            'purpose' => $vehicleRequest->purpose,
            'passengers' => $vehicleRequest->passengers,
            'travelDate' => $travelDate,
            'days' => $days ?? '',
            'tripType' => $vehicleRequest->trip_type,
            'departure' => $this->time($vehicleRequest->departure_time),
            'dateRequested' => $requestedAt ? $requestedAt->format('F j, Y') : '',
            'timeRequested' => $requestedAt ? $requestedAt->format('g:i A') : '',
            'chiefMotorpool' => self::CHIEF_MOTORPOOL,
            'unitHeadGsu' => self::UNIT_HEAD_GSU,
        ]);
    }

    // Turns "06:00:00" into "6:00 AM".
    private function time(?string $value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('g:i A');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    // The logo is embedded in the page so it always prints.
    private function logo(): ?string
    {
        $path = public_path('images/nvsu-logo.png');

        if (!file_exists($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }
}