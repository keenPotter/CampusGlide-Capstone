<script setup>
defineProps({
  message: { type: String, default: '' },
})
defineEmits(['retry'])
</script>

<template>
  <div v-if="message" class="load-error">
    <p>{{ message }}</p>
    <button type="button" class="retry-button" @click="$emit('retry')">Try again</button>
  </div>
</template>
