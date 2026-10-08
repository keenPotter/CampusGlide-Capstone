{{-- Usage: <x-stat-card value-id="statScheduled" label="Scheduled" color="blue" /> --}}
@props(['label', 'color' => 'gray', 'valueId' => null])
@php
    $colorMap = [
        'blue'   => 'text-blue-600',
        'yellow' => 'text-yellow-600',
        'green'  => 'text-green-600',
        'red'    => 'text-red-600',
        'gray'   => 'text-gray-600',
    ];
    $colorClass = $colorMap[$color] ?? $colorMap['gray'];
@endphp
<div class="bg-white rounded-xl shadow-sm p-2 text-center">
    <p id="{{ $valueId }}" class="text-base font-bold {{ $colorClass }}">0</p>
    <p class="text-[9px] text-gray-400 leading-tight">{{ $label }}</p>
</div>
