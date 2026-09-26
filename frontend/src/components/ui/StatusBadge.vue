<script setup>
import { computed } from 'vue'
import { STATUS_LABELS } from '@/lib/format'

const props = defineProps({
  status: { type: String, required: true },
})

const styles = {
  pending: 'bg-secondary-50 text-secondary-700 ring-secondary-200',
  approved: 'bg-primary-50 text-primary-700 ring-primary-200',
  rejected: 'bg-red-50 text-red-700 ring-red-200',
  cancelled: 'bg-neutral-100 text-ink-muted ring-line',
}

const label = computed(() => STATUS_LABELS[props.status] ?? props.status)
const classes = computed(() => styles[props.status] ?? styles.cancelled)
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2.5 py-1 text-small font-medium ring-1 ring-inset"
    :class="classes"
  >
    {{ label }}
  </span>
</template>