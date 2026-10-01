{{-- Usage: <x-auth-panel /> --}}
<div id="authPanel" class="hidden bg-white border-t border-b px-4 py-3 space-y-2">
    <p class="text-xs font-semibold text-gray-500">Log in to load/manage trips</p>
    <div class="flex flex-col gap-2">
        <input id="loginEmail" type="email" placeholder="email" class="border rounded-lg px-3 py-2 text-sm">
        <input id="loginPassword" type="password" placeholder="password" class="border rounded-lg px-3 py-2 text-sm">
        <x-primary-button onclick="login()" class="w-full !rounded-lg">Log in</x-primary-button>
        <p id="authStatus" class="text-xs text-gray-500"></p>
    </div>
</div>
