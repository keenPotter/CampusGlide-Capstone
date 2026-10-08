<script setup>
defineProps({
  modelValue: { type: String, required: true },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div class="type-switcher" role="tablist" aria-label="Trip type">
    <button
      type="button"
      :class="['type-button', { active: modelValue === 'inclusive' }]"
      @click="emit('update:modelValue', 'inclusive')"
    >
      <span class="type-dot inclusive-dot"></span>
      Inclusive
    </button>
    <button
      type="button"
      :class="['type-button', { active: modelValue === 'exclusive' }]"
      @click="emit('update:modelValue', 'exclusive')"
    >
      <span class="type-dot exclusive-dot"></span>
      Exclusive
    </button>
  </div>

  <div class="legend">
    <span><i class="legend-dot inclusive-dot"></i> Inclusive schedule</span>
    <span><i class="legend-dot exclusive-dot"></i> Exclusive schedule</span>
  </div>
</template>
