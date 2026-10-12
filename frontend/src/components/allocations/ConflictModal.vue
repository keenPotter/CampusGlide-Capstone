<script setup>
import { computed } from 'vue'
import { formatDate, formatTime } from '@/lib/format'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'

// Open when `conflict` is set. `conflict` is the "conflict" object the backend sends with a 422 response.
const props = defineProps({
  conflict: { type: Object, default: null },
  hint: { type: String, default: '' },
})

const emit = defineEmits(['close'])

const isDriver = computed(() => props.conflict?.type === 'driver')

const when = computed(() => {
  const c = props.conflict
  if (!c) return ''
  return `${formatDate(c.date)}, ${formatTime(c.start)}${c.end ? ` – ${formatTime(c.end)}` : ''}`
})

const footer = computed(
  () => props.hint || `Please select another ${isDriver.value ? 'driver' : 'vehicle'}.`,
)
</script>

<template>
  <BaseModal
    :open="Boolean(conflict)"
    :title="isDriver ? 'Driver unavailable' : 'Vehicle unavailable'"
    @close="emit('close')"
  >
    <template v-if="conflict">
      <p class="text-body">
        <strong>{{ conflict.subject }}</strong> is already scheduled for {{ when }}.
      </p>

      <div class="mt-3 rounded-card bg-surface p-3 text-small">
        <p class="text-ink-muted">Current trip</p>
        <p class="mt-1 font-medium text-ink">{{ conflict.destination ?? '—' }}</p>
        <p v-if="conflict.driver" class="mt-1 text-ink-muted">Driver: {{ conflict.driver }}</p>
        <p v-if="conflict.vehicle" class="text-ink-muted">Vehicle: {{ conflict.vehicle }}</p>
      </div>

      <p class="mt-3 text-body">{{ footer }}</p>
    </template>

    <template #footer>
      <BaseButton @click="emit('close')">OK</BaseButton>
    </template>
  </BaseModal>
</template>