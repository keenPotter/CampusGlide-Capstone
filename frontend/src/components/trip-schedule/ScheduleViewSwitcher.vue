<script setup>
defineProps({
  views: { type: Array, required: true },
  modelValue: { type: String, required: true },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div class="view-buttons" role="tablist" aria-label="Schedule view">
    <button
      v-for="view in views"
      :key="view"
      type="button"
      role="tab"
      :aria-selected="modelValue === view"
      :class="{ active: modelValue === view }"
      @click="emit('update:modelValue', view)"
    >
      {{ view.charAt(0).toUpperCase() + view.slice(1) }}
    </button>
  </div>
</template>
