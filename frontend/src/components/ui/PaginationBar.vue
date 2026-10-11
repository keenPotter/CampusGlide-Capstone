<script setup>
import BaseButton from '@/components/ui/BaseButton.vue'

defineProps({
  meta: { type: Object, default: null },
})

const emit = defineEmits(['change'])
</script>

<template>
  <div
    v-if="meta && meta.last_page > 1"
    class="flex items-center justify-between gap-4 border-t border-line px-card py-3"
  >
    <p class="text-small text-ink-muted">
      Page {{ meta.current_page }} of {{ meta.last_page }} · {{ meta.total }} records
    </p>

    <div class="flex gap-2">
      <BaseButton
        variant="outline"
        size="sm"
        :disabled="meta.current_page <= 1"
        @click="emit('change', meta.current_page - 1)"
      >
        Previous
      </BaseButton>
      <BaseButton
        variant="outline"
        size="sm"
        :disabled="meta.current_page >= meta.last_page"
        @click="emit('change', meta.current_page + 1)"
      >
        Next
      </BaseButton>
    </div>
  </div>
</template>