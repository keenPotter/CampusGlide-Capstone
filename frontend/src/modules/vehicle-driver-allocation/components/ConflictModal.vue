<script setup>
import { computed } from 'vue'
import { formatDate, formatTime } from '../format'
import AppButton from './AppButton.vue'
import Icon from './Icon.vue'

const props = defineProps({
  conflict: { type: Object, default: null },
  hint: { type: String, default: '' }, 
})
const emit = defineEmits(['close'])

const title = computed(() => (props.conflict?.type === 'driver' ? 'Driver unavailable' : 'Vehicle unavailable'))
const when = computed(() => {
  const c = props.conflict
  if (!c) return ''
  return `${formatDate(c.date)}, ${formatTime(c.start)}${c.end ? '–' + formatTime(c.end) : ''}`
})
const footer = computed(() => props.hint || `Please select another ${props.conflict?.type === 'driver' ? 'driver' : 'vehicle'}.`)
</script>

<template>
  <div v-if="conflict" class="cg-overlay cg-overlay--top">
    <div class="cg-dialog cg-dialog--sm" role="alertdialog" aria-modal="true" aria-labelledby="conflict-title">
      <h2 id="conflict-title" class="cg-section-title cg-warn-title"><Icon name="alert" /> {{ title }}</h2>

      <p><strong>{{ conflict.subject }}</strong> is already scheduled for {{ when }}.</p>

      <div class="cg-box cg-stack" style="gap: 4px">
        <p class="cg-muted">Current trip</p>
        <p class="cg-card__title">{{ conflict.destination ?? '—' }}</p>
        <p v-if="conflict.driver" class="cg-meta cg-meta--text"><Icon name="user" /> Driver: {{ conflict.driver }}</p>
        <p v-if="conflict.vehicle" class="cg-meta cg-meta--text"><Icon name="car" /> {{ conflict.vehicle }}</p>
      </div>

      <p>{{ footer }}</p>

      <div class="cg-actions">
        <AppButton @click="emit('close')">OK</AppButton>
      </div>
    </div>
  </div>
</template>
