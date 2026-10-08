<script setup>
import { computed } from 'vue'
import { auth, isAdmin, isLoggedIn } from '../api'

const label = computed(() => {
  if (!isLoggedIn.value) return 'Not logged in'
  return `Logged in as ${isAdmin.value ? 'Admin' : 'End User'}`
})
</script>

<template>
  <div class="cg-chip" role="status">
    <span :class="['cg-dot', { 'cg-dot--on': isLoggedIn }]"></span>
    <span class="cg-chip__label">{{ label }}</span>
  </div>
</template>
