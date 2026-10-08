<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: { type: String, default: 'primary' }, // primary (dark green) | warning (orange) | danger (red) | secondary (outlined)
  type: { type: String, default: 'button' },
  block: { type: Boolean, default: false },
})

const classes = computed(() => ['cg-btn', `cg-btn--${props.variant}`, { 'cg-btn--block': props.block }])
</script>

<template>
  <button :type="type" :class="classes"><slot /></button>
</template>
