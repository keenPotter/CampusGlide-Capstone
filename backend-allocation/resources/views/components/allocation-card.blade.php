@props(['allocation', 'isAdmin' => false])
<div class="bg-white rounded-2xl shadow-sm p-3 space-y-1">
    <div class="flex justify-between items-start">
        <div>
            <p class="font-semibold text-sm text-gray-800">{{ $allocation['trip']['destination'] ?? '—' }}</p>
            <p class="text-xs text-gray-400">{{ $allocation['trip']['purpose'] ?? '' }}</p>
            <p class="text-xs text-gray-400">Requested by: {{ $allocation['vehicle_request']['requester'] ?? '—' }}</p>
        </div>
        <x-status-badge :status="$allocation['status'] ?? 'scheduled'" />
    </div>
    <p class="text-xs text-gray-500">
        📅 {{ $allocation['trip']['trip_date'] ?? '—' }} · 🕒 {{ $allocation['trip']['departure_time'] ?? '—' }} → {{ $allocation['trip']['estimated_return_time'] ?? '—' }}
    </p>
    <div class="flex flex-col gap-0.5 text-xs text-gray-600 bg-gray-50 rounded-lg p-2">
        <span>🚐 {{ $allocation['vehicle']['vehicle_model'] ?? '—' }} ({{ $allocation['vehicle']['plate_number'] ?? '—' }})</span>
        <span>🧑‍✈️ {{ $allocation['driver']['name'] ?? '—' }} — Lic# {{ $allocation['driver']['license_number'] ?? '—' }}</span>
    </div>
    @if(!empty($allocation['notes']))
        <p class="text-xs text-gray-400 italic">Note: {{ $allocation['notes'] }}</p>
    @endif
    @if($isAdmin)
    <div class="flex gap-2 pt-1">
        <x-primary-button variant="warning" onclick="editAllocation({{ json_encode($allocation) }})">Reassign</x-primary-button>
        <x-primary-button variant="danger" onclick="cancelAllocation({{ $allocation['id'] ?? 0 }})">Cancel</x-primary-button>
    </div>
    @endif
</div>
