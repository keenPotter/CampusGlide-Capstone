<script setup>
import { ref } from 'vue'
import AppButton from './AppButton.vue'
import Icon from './Icon.vue'
defineProps({ request: { type: Object, required: true } })
const emit = defineEmits(['allocate'])
const detailsOpen = ref(false)
</script>

<template>
  <article class="cg-card cg-request-compact">
    <div class="cg-request-compact__summary">
      <div class="cg-request-compact__main">
        <p class="cg-card__title">{{ request.destination ?? '—' }}</p>
        <p class="cg-muted">{{ request.requester ?? '—' }} · {{ request.trip_date ?? '—' }}</p>
      </div>
      <div class="cg-request-compact__controls">
        <AppButton @click="emit('allocate', request.id)">Allocate</AppButton>
        <button
          type="button"
          class="cg-collapse-btn"
          :aria-expanded="detailsOpen"
          :aria-controls="`allocation-request-details-${request.id}`"
          @click="detailsOpen = !detailsOpen"
        >
          {{ detailsOpen ? 'Less' : 'Details' }}
          <Icon name="chevron" />
        </button>
      </div>
    </div>
    <div v-if="detailsOpen" :id="`allocation-request-details-${request.id}`" class="cg-request-compact__details">
      <p class="cg-muted">{{ request.purpose ?? '' }}</p>
      <p class="cg-meta"><Icon name="clock" /> {{ request.departure_time ?? '—' }} → {{ request.estimated_return_time ?? '—' }}</p>
      <p v-if="request.passengers" class="cg-meta"><Icon name="users" /> {{ request.passengers }} passengers</p>
    </div>
  </article>
</template>
