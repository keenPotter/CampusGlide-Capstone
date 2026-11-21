<script setup>
import { computed } from 'vue'
import { tripModificationChanges } from '@/lib/tripModifications'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'

const props = defineProps({
  item: { type: Object, required: true },
  isAdmin: { type: Boolean, default: false },
})

const emit = defineEmits(['review'])

const changes = computed(() => tripModificationChanges(props.item).filter((change) => change.changed))
const canReview = computed(() => props.isAdmin && props.item.status === 'pending')

function openReview() {
  if (canReview.value) emit('review', props.item)
}
</script>

<template>
  <article
    class="rounded-card border border-line bg-white p-card transition-colors hover:bg-neutral-50"
    :class="{ 'cursor-pointer': canReview }"
    @click="openReview"
  >
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <p class="truncate font-medium text-ink">{{ item.trip?.destination ?? '—' }}</p>
        <p v-if="isAdmin" class="mt-0.5 text-small text-ink-muted">
          Requested by {{ item.requested_by ?? '—' }}
        </p>
      </div>
      <StatusBadge :status="item.status" />
    </div>

    <p class="mt-2 text-small text-ink-muted">Reason: {{ item.reason }}</p>
    <p v-if="item.admin_reason" class="mt-1 text-small text-ink-muted">
      Reviewer's note: {{ item.admin_reason }}
    </p>

    <dl v-if="changes.length" class="mt-2 grid gap-1 text-small">
      <div v-for="change in changes" :key="change.label" class="flex flex-wrap gap-x-1">
        <dt class="text-ink-muted">{{ change.label }}:</dt>
        <dd>{{ change.oldValue }} → <strong class="text-ink">{{ change.newValue }}</strong></dd>
      </div>
    </dl>

    <div v-if="isAdmin && item.status === 'pending'" class="mt-3 flex justify-end">
      <BaseButton size="sm" variant="outline" @click.stop="openReview">
        Review changes
      </BaseButton>
    </div>
  </article>
</template>