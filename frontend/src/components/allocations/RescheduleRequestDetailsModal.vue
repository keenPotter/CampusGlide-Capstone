<script setup>
import { computed } from 'vue'
import { formatDateTime, TRIP_TYPE_LABELS } from '@/lib/format'
import { tripModificationChanges } from '@/lib/tripModifications'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'

const props = defineProps({
  item: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const changes = computed(() => tripModificationChanges(props.item ?? {}))
const details = computed(() => {
  if (!props.item) return []

  const item = props.item
  const detail = (label, oldValue, newValue) => ({
    label,
    oldValue: oldValue ?? '—',
    newValue: newValue ?? '—',
  })

  return [
    detail('Destination', item.old_destination ?? item.trip?.destination, item.new_destination ?? item.trip?.destination),
    detail('Purpose', item.old_purpose ?? item.trip?.purpose, item.new_purpose ?? item.trip?.purpose),
    detail(
      'Trip type',
      TRIP_TYPE_LABELS[item.old_trip_type] ?? item.old_trip_type,
      TRIP_TYPE_LABELS[item.new_trip_type] ?? item.new_trip_type,
    ),
    detail('Authorized passengers', item.old_authorized_passengers, item.new_authorized_passengers),
    detail('Number of passengers', item.old_number_of_passengers, item.new_number_of_passengers),
  ]
})
</script>

<template>
  <BaseModal
    :open="Boolean(item)"
    title="Date Change Request Details"
    :subtitle="item?.trip?.destination ?? ''"
    @close="emit('close')"
  >
    <div v-if="item" class="flex flex-col gap-4 text-small">
      <div class="flex items-center justify-between gap-3">
        <span class="font-medium text-ink">Request status</span>
        <StatusBadge :status="item.status" />
      </div>

      <section>
        <h3 class="font-medium text-ink">Trip details</h3>
        <dl class="mt-2 grid gap-2 sm:grid-cols-2">
          <div
            v-for="detail in details"
            :key="detail.label"
            class="rounded-card bg-surface p-3"
          >
            <dt class="text-ink-muted">{{ detail.label }}</dt>
            <dd class="mt-1 break-words text-ink">
              {{ detail.oldValue }} <span aria-label="requested as">→</span>
              <strong>{{ detail.newValue }}</strong>
            </dd>
          </div>
        </dl>
      </section>

      <section>
        <h3 class="font-medium text-ink">Schedule</h3>
        <dl class="mt-2 grid gap-2 sm:grid-cols-2">
          <div
            v-for="change in changes"
            :key="change.label"
            class="rounded-card bg-surface p-3"
          >
            <dt class="text-ink-muted">{{ change.label }}</dt>
            <dd class="mt-1 text-ink">
              {{ change.oldValue }} <span aria-label="requested as">→</span>
              <strong>{{ change.newValue }}</strong>
            </dd>
          </div>
        </dl>
      </section>

      <div class="rounded-card border border-line p-3">
        <p class="font-medium text-ink">Reason for request</p>
        <p class="mt-1 whitespace-pre-wrap break-words text-ink-muted">{{ item.reason || '—' }}</p>
      </div>

      <div v-if="item.status !== 'pending'" class="rounded-card border border-line p-3">
        <p class="font-medium text-ink">Administrator/staff decision note</p>
        <p class="mt-1 whitespace-pre-wrap break-words text-ink-muted">
          {{ item.admin_reason || 'No decision note was provided.' }}
        </p>
        <p v-if="item.handled_by || item.handled_at" class="mt-2 text-ink-muted">
          {{ item.handled_by || 'Reviewed' }}
          <template v-if="item.handled_at"> · {{ formatDateTime(item.handled_at) }}</template>
        </p>
      </div>

      <p class="text-ink-muted">Submitted {{ formatDateTime(item.created_at) }}</p>
    </div>

    <template #footer>
      <BaseButton variant="outline" @click="emit('close')">Close</BaseButton>
    </template>
  </BaseModal>
</template>
