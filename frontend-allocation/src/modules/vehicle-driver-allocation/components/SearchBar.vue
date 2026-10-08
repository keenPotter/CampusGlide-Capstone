<script setup>
defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Search...' },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div class="cg-search">
    <input
      type="text"
      class="cg-input"
      :value="modelValue"
      :placeholder="placeholder"
      :aria-label="placeholder"
      @input="emit('update:modelValue', $event.target.value)"
    />
  </div>
</template>
