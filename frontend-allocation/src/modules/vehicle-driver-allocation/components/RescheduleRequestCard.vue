<script setup>
import { ref } from 'vue'
import { formatDate } from '../format'
import AppButton from './AppButton.vue'
import Icon from './Icon.vue'

defineProps({
  item: { type: Object, required: true },
  isAdmin: { type: Boolean, default: false },
})
const emit = defineEmits(['approve'])
const detailsOpen = ref(false)
</script>

<template>
  <article class="cg-card cg-request-compact">
    <div class="cg-request-compact__summary">
      <div class="cg-request-compact__main">
        <p class="cg-card__title">{{ item.trip?.destination ?? '—' }}</p>
        <p class="cg-muted">{{ isAdmin ? `Requested by: ${item.requested_by ?? '—'} · ` : '' }}{{ formatDate(item.new_date) }}</p>
      </div>
      <div class="cg-request-compact__controls">
        <span :class="['cg-badge', `cg-badge--rs-${item.status}`]">{{ item.status }}</span>
        <button
          type="button"
          class="cg-collapse-btn"
          :aria-expanded="detailsOpen"
          :aria-controls="`date-change-details-${item.id}`"
          @click="detailsOpen = !detailsOpen"
        >
          {{ detailsOpen ? 'Less' : 'Details' }}
          <Icon name="chevron" />
        </button>
        <div v-if="isAdmin && item.status === 'pending'" class="cg-actions">
          <AppButton @click="emit('approve', item)">Approve</AppButton>
        </div>
      </div>
    </div>

    <div v-if="detailsOpen" :id="`date-change-details-${item.id}`" class="cg-request-compact__details">
      <p class="cg-meta"><Icon name="calendar" /> {{ formatDate(item.old_date) }} → <strong>{{ formatDate(item.new_date) }}</strong></p>
      <p class="cg-note">Reason: {{ item.reason }}</p>
      <p v-if="item.admin_reason" class="cg-note">Admin: {{ item.admin_reason }}</p>
    </div>
  </article>
</template>
