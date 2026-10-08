<div id="modalOverlay" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-4 space-y-2 max-h-[90vh] overflow-y-auto">
        <h2 class="font-semibold text-sm text-gray-700" id="modalTitle">New Allocation</h2>
        <input type="hidden" id="f_id">

        <label class="text-xs text-gray-500">Approved Vehicle Request</label>
        <select id="f_vehicle_request_id" onchange="onRequestChange(this.value)" class="border rounded-lg px-3 py-2 text-sm w-full bg-white"></select>

        <label class="text-xs text-gray-500">Vehicle</label>
        <select id="f_vehicle_id" class="border rounded-lg px-3 py-2 text-sm w-full bg-white"></select>

        <label class="text-xs text-gray-500">Driver</label>
        <select id="f_driver_id" class="border rounded-lg px-3 py-2 text-sm w-full bg-white"></select>

        <div id="statusField">
            <label class="text-xs text-gray-500">Trip Status (reassign only)</label>
            <select id="f_status" class="border rounded-lg px-3 py-2 text-sm w-full bg-white">
                <option value="">— unchanged —</option>
                <option value="scheduled">Scheduled</option>
                <option value="in_progress">In progress</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>

        <label class="text-xs text-gray-500">Notes</label>
        <textarea id="f_notes" placeholder="Notes (optional)" rows="2" class="border rounded-lg px-3 py-2 text-sm w-full"></textarea>

        <p id="modalError" class="text-xs text-red-500"></p>

        <div class="flex gap-2 pt-1">
            <x-primary-button onclick="saveAllocation()" class="w-full">Save</x-primary-button>
            <x-primary-button variant="ghost" onclick="closeModal()">Cancel</x-primary-button>
        </div>
    </div>
</div>
