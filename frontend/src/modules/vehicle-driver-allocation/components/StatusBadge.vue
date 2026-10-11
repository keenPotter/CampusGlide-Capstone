<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, default: 'scheduled' },
})

const known = ['scheduled', 'in_progress', 'completed', 'cancelled', 'pending', 'approved', 'disapproved']
const tone = computed(() => (known.includes(props.status) ? props.status : 'unknown'))
const label = computed(() => (props.status || 'unknown').replace('_', ' '))
</script>

<template>
  <span :class="['cg-badge', `cg-badge--${tone}`]">{{ label }}</span>
</template>
