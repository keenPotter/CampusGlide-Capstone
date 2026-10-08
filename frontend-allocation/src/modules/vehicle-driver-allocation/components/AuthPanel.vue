<script setup>
import { ref } from 'vue'
import { auth, isAdmin, isLoggedIn, login, logout } from '../api'
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
  <div v-if="open" :class="['cg-card', 'cg-panel', 'cg-stack', { 'cg-panel--menu': menu }]">
    <!-- Hindi pa naka-login: login form -->
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


    <template v-else>
      <p class="cg-card__title">Logged in as {{ isAdmin ? 'Admin' : 'End User' }}</p>
      <AppButton variant="secondary" block @click="logout()">Log out</AppButton>
    </template>
  </div>
</template>
