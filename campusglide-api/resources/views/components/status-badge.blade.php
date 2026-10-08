{{-- Usage: <x-status-badge status="scheduled" /> --}}
@props(['status' => 'scheduled'])
@php
    $colors = [
        'scheduled'   => 'bg-blue-100 text-blue-700',
        'in_progress' => 'bg-yellow-100 text-yellow-700',
        'completed'   => 'bg-green-100 text-green-700',
        'cancelled'   => 'bg-red-100 text-red-700',
    ];
    $class = $colors[$status] ?? 'bg-gray-100 text-gray-600';
    $label = $slot->isEmpty() ? ucfirst(str_replace('_', ' ', $status)) : $slot;
@endphp
<span {{ $attributes->merge(['class' => "text-[10px] px-2 py-1 rounded-full font-medium $class"]) }}>
    {{ $label }}
</span>
