{{-- Usage: <x-auth-panel /> --}}
<div id="authPanel" class="hidden bg-white border-t border-b px-4 py-3 space-y-2">
    <div id="loginForm" class="space-y-2">
        <p class="text-xs font-semibold text-gray-500">Log in to load/manage trips</p>
        <div class="flex flex-col gap-2">
            <input id="loginEmail" type="email" placeholder="email" class="border rounded-lg px-3 py-2 text-sm">
            <input id="loginPassword" type="password" placeholder="password" class="border rounded-lg px-3 py-2 text-sm">
            <x-primary-button onclick="login()" class="w-full !rounded-lg">Log in</x-primary-button>
            <p id="authStatus" class="text-xs text-gray-500"></p>
        </div>
    </div>

    <div id="accountBox" class="hidden space-y-2">
        <p id="accountInfo" class="text-xs font-semibold text-gray-500"></p>
        <button onclick="logoutLocal('Logged out.')" class="w-full border border-red-300 text-red-600 rounded-lg px-3 py-2 text-sm">Log out</button>
    </div>
</div>