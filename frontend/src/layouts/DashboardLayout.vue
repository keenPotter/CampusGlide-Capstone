<script setup>
import { ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { initials, ROLE_LABELS } from '@/lib/format'
import { useToast } from '@/composables/useToast'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const sidebarOpen = ref(false)

const navigation = [
  { name: 'Dashboard', to: { name: 'dashboard' }, icon: 'grid' },
  { name: 'Vehicle Requests', to: { name: 'requests.index' }, icon: 'list' },
  { name: 'Trip Schedule', to: { name: 'tripSchedule' }, icon: 'list' },
  { name: 'Gate Logs', to: { name: 'guardLogs.index' }, icon: 'shield' },
]


const icons = {
  grid: 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
  list: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
  plus: 'M12 5v14M5 12h14',
  shield: 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',
}

function isActive(item) {
  return route.name === item.to.name
}

async function handleLogout() {
  await auth.logout()
  toast.success('Signed out successfully.')
  router.push({ name: 'login' })
}

function closeSidebar() {
  sidebarOpen.value = false
}
</script>

<template>
  <div class="min-h-screen bg-surface">
    <!-- Mobile Header -->
    <header class="sticky top-0 z-40 flex h-14 items-center justify-between gap-3 border-b border-line bg-white px-page md:hidden">
      <button
        type="button"
        class="rounded-card p-2 text-ink-muted hover:bg-neutral-100"
        @click="sidebarOpen = !sidebarOpen"
      >
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>

      <div class="flex h-8 w-8 items-center justify-center rounded-card bg-primary text-white text-small font-semibold">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 17V9l2-4h9l3 4h4v8" />
        </svg>
      </div>

      <button
        type="button"
        class="rounded-card p-2 text-ink-muted hover:bg-neutral-100 hover:text-red-600 ml-auto"
        title="Sign out"
        @click="handleLogout"
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
        </svg>
      </button>
    </header>

    <!-- Mobile Sidebar Overlay & Menu -->
    <Transition
      enter-active-class="transition duration-200"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-200"
      leave-to-class="opacity-0"
    >
      <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-30 bg-ink/40 md:hidden"
        @click="closeSidebar"
      />
    </Transition>

    <!-- Sidebar -->
    <aside
      class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full border-r border-line bg-white transition-transform md:translate-x-0"
      :class="{ 'translate-x-0': sidebarOpen }"
    >
      <div class="flex h-16 items-center gap-2 border-b border-line px-card">
        <div class="flex h-8 w-8 items-center justify-center rounded-card bg-primary text-white">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 17V9l2-4h9l3 4h4v8" />
          </svg>
        </div>
        <div>
          <p class="text-small font-semibold leading-tight">CampusGlide</p>
          <p class="text-xs text-ink-muted">NVSU Motorpool</p>
        </div>
      </div>

      <nav class="flex flex-col gap-1 p-2">
        <RouterLink
          v-for="item in navigation"
          :key="item.name"
          :to="item.to"
          class="flex h-control items-center gap-3 rounded-card px-3 text-small transition md:text-body"
          :class="
            isActive(item)
              ? 'bg-primary-50 font-medium text-primary-700'
              : 'text-ink-muted hover:bg-neutral-100 hover:text-ink'
          "
          @click="closeSidebar"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path :d="icons[item.icon]" />
          </svg>
          {{ item.name }}
        </RouterLink>
      </nav>
    </aside>

    <!-- Main Content -->
    <div class="md:pl-64">
      <!-- Desktop Header -->
      <header class="sticky top-0 z-20 hidden md:flex h-16 items-center justify-between gap-4 border-b border-line bg-white px-page">
        <div class="ml-auto flex items-center gap-3">
          <div class="text-right">
            <p class="text-small font-medium leading-tight">{{ auth.user?.name }}</p>
            <p class="text-small text-ink-muted">{{ ROLE_LABELS[auth.role] ?? auth.role }}</p>
          </div>
          <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-50 text-small font-semibold text-primary-700">
            {{ initials(auth.user?.name) }}
          </div>
          <button
            type="button"
            class="rounded-card p-2 text-ink-muted hover:bg-neutral-100 hover:text-red-600"
            title="Sign out"
            @click="handleLogout"
          >
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
            </svg>
          </button>
        </div>
      </header>

      <main class="p-page pb-20 md:pb-page">
        <slot />
      </main>
    </div>
  </div>
</template>