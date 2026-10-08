<script setup>
import Icon from './Icon.vue'

defineProps({
  title: { type: String, default: 'Vehicle-Driver Allocation' },
  badge: { type: Number, default: 0 }, 
  menuOpen: { type: Boolean, default: false },
  notificationsOpen: { type: Boolean, default: false },
  showNotifications: { type: Boolean, default: true },
})
const emit = defineEmits(['toggle-panel', 'bell'])
</script>

<template>
  <header class="cg-header">
    <div class="cg-container cg-header__row">
      <h1 class="cg-title">{{ title }}</h1>
      <div class="cg-header__actions">
        <button v-if="showNotifications" type="button" class="cg-icon-btn" :aria-label="`Pending vehicle requests: ${badge}`" :aria-expanded="notificationsOpen" @click="emit('bell')">
          <Icon name="bell" />
          <span v-if="badge > 0" class="cg-count">{{ badge }}</span>
        </button>
        <button type="button" class="cg-icon-btn" aria-label="Account menu" :aria-expanded="menuOpen" @click="emit('toggle-panel')">
          <Icon name="menu" />
        </button>
      </div>
    </div>
  </header>
</template>
