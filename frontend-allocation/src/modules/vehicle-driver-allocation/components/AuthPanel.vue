<script setup>
import { ref } from 'vue'
import { auth, isLoggedIn, login } from '../api'
import AppButton from './AppButton.vue'

defineProps({
  open: { type: Boolean, default: false },
  menu: { type: Boolean, default: false },
})
const emit = defineEmits(['login'])

const email = ref('')
const password = ref('')
const status = ref('')
const busy = ref(false)

async function submit() {
  busy.value = true
  auth.notice = ''
  status.value = 'Logging in...'
  try {
    await login(email.value, password.value)
    status.value = ''
    password.value = ''
    emit('login')
  } catch (e) {
    status.value = e.message
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <aside v-if="menu || open" :class="['cg-card', 'cg-panel', 'cg-stack', { 'cg-panel--menu': menu, 'cg-panel--menu-open': menu && open }]">
    <div v-if="menu" class="cg-sidebar__brand">
      <span class="cg-sidebar__logo">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 17V9l2-4h9l3 4h4v8M3 17h18M7 17a2 2 0 1 0 4 0m2 0a2 2 0 1 0 4 0" />
        </svg>
      </span>
      <span>
        <span class="cg-sidebar__name">CampusGlide</span>
        <span class="cg-sidebar__subname">NVSU Motorpool</span>
      </span>
    </div>

    <nav v-if="menu && isLoggedIn" class="cg-sidebar-nav" aria-label="Main navigation">
      <a class="cg-sidebar-link cg-sidebar-link--active" href="#dashboard" @click="open = false">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z" /></svg>
        <span>Dashboard</span>
      </a>
      <a class="cg-sidebar-link" href="#vehicle-requests" @click="open = false">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16M4 12h16M4 19h16M2 5h.01M2 12h.01M2 19h.01" /></svg>
        <span>Vehicle Requests</span>
      </a>
      <a class="cg-sidebar-link" href="#allocation-summary" @click="open = false">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h18M3 12h18M3 17h18" /></svg>
        <span>Allocations</span>
      </a>
    </nav>

    <div :class="{ 'cg-sidebar__content': menu }">
      <template v-if="!isLoggedIn">
        <p class="cg-card__title">Log in to manage trips</p>
        <form class="cg-stack" @submit.prevent="submit">
          <div>
            <label class="cg-label" for="login-email">Email</label>
            <input id="login-email" v-model="email" type="email" class="cg-input" autocomplete="username" />
          </div>
          <div>
            <label class="cg-label" for="login-password">Password</label>
            <input id="login-password" v-model="password" type="password" class="cg-input" autocomplete="current-password" />
          </div>
          <AppButton type="submit" block :disabled="busy">Log in</AppButton>
          <p class="cg-muted">{{ auth.notice || status }}</p>
        </form>
      </template>

    </div>
  </aside>
</template>
