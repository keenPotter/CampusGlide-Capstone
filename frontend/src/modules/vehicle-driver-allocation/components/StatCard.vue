<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: Number, default: 0 },
  tone: { type: String, default: 'neutral' }, // neutral | orange | green | red
})
</script>

<template>
  <div class="cg-stat">
    <p :class="['cg-stat__value', `cg-tone--${tone}`]">{{ value }}</p>
    <p class="cg-stat__label">{{ label }}</p>
  </div>
</template>
