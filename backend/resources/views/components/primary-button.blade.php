{{-- Usage: <x-primary-button variant="danger" onclick="deleteTrip(1)">Delete</x-primary-button>
     Variants: primary (default), danger, warning, ghost --}}
@props(['variant' => 'primary'])
@php
    $variants = [
        'primary' => 'bg-green-600 text-white',
        'danger'  => 'bg-red-600 text-white',
        'warning' => 'bg-yellow-500 text-white',
        'ghost'   => 'bg-gray-200 text-gray-700',
    ];
    $variantClass = $variants[$variant] ?? $variants['primary'];
@endphp
<button {{ $attributes->merge(['type' => 'button', 'class' => "text-sm font-medium px-4 py-2 rounded-full shadow $variantClass"]) }}>
    {{ $slot }}
</button>
