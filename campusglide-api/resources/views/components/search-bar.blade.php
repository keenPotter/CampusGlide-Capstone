{{-- Usage: <x-search-bar id="searchInput" placeholder="Search trips..." /> --}}
@props(['id' => 'searchInput', 'placeholder' => 'Search...'])
<div class="px-4 pb-3 bg-white">
    <div class="relative">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">🔍</span>
        <input id="{{ $id }}" type="text" placeholder="{{ $placeholder }}"
               class="w-full rounded-full border border-gray-300 pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
    </div>
</div>
