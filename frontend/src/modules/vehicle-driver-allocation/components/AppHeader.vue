<script setup>
import { computed } from 'vue'
import { auth } from '../api'
import Icon from './Icon.vue'

defineProps({ title: { type: String, default: 'Vehicle-Driver Allocation' }, menuOpen: { type: Boolean, default: false } })
const emit = defineEmits(['toggle-panel', 'logout'])
const accountLabel = computed(() => auth.email || 'CampusGlide user')
const roleLabel = computed(() => {
  const role = String(auth.role || 'user').replaceAll('_', ' ')
  return role.charAt(0).toUpperCase() + role.slice(1)
})
const initials = computed(() => {
  const name = String(auth.email || 'CG').split('@')[0].replace(/[._-]+/g, ' ').trim()
  return name.split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0].toUpperCase()).join('') || 'CG'
})
</script>

<template>
  <header class="cg-header">
    <div class="cg-container cg-header__row">
      <h1 class="cg-title">{{ title }}</h1>
      <div class="cg-header__actions">
        <div class="cg-header__account">
          <span class="cg-header__account-copy">
            <strong>{{ accountLabel }}</strong>
            <small>{{ roleLabel }}</small>
          </span>
          <span class="cg-header__avatar" aria-hidden="true">{{ initials }}</span>
        </div>
        <button type="button" class="cg-icon-btn cg-logout-btn" aria-label="Log out" title="Sign out" @click="emit('logout')">
          <svg class="cg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
          </svg>
        </button>
        <button type="button" class="cg-icon-btn cg-account-menu" aria-label="Toggle navigation" :aria-expanded="menuOpen" @click="emit('toggle-panel')">
          <Icon name="menu" />
        </button>
      </div>
    </div>
  </header>
</template>
