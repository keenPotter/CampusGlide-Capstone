{{-- Usage: <x-app-header title="Trip Schedule" /> --}}
@props(['title' => 'Trip Schedule'])
<div class="bg-white sticky top-0 z-20">
    <div class="flex items-center justify-between px-4 py-3">
        <h1 class="text-lg font-bold text-gray-900">{{ $title }}</h1>
        <div class="flex items-center gap-3 text-lg">
            <button title="Notifications" class="text-gray-500">🔔</button>
            <button title="Account" onclick="toggleAuthPanel()" class="text-gray-500">☰</button>
        </div>
    </div>
</div>
