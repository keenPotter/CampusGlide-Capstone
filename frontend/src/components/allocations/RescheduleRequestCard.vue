<script setup>
import { ref } from 'vue'
import { Check, X } from 'lucide-vue-next'
import { formatDate, formatTime } from '@/lib/format'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'

defineProps({
  item: { type: Object, required: true },
  isAdmin: { type: Boolean, default: false },
})

defineEmits(['approve', 'disapprove'])

const detailsOpen = ref(false)
</script>

<template>
  <article class="rounded-card border border-line bg-white p-card transition-colors hover:bg-neutral-50">
    <button
      type="button"
      class="w-full rounded-card text-left hover:filter-none"
      :aria-label="`View Trip Modification Request for ${item.trip?.destination ?? 'trip'}`"
      @click="detailsOpen = true"
    >
      <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
          <p class="truncate font-medium text-ink">{{ item.trip?.destination ?? '—' }}</p>
          <p class="mt-0.5 text-small text-ink-muted">
            <template v-if="isAdmin">Requested by {{ item.requested_by ?? '—' }} · </template>
            {{ formatDate(item.old_date) }} → <strong>{{ formatDate(item.new_date) }}</strong>
          </p>
        </div>
        <StatusBadge :status="item.status" />
      </div>

      <p class="mt-2 text-small text-ink-muted">Reason: {{ item.reason }}</p>
      <p v-if="item.admin_reason" class="mt-1 text-small text-ink-muted">
        Administrator's note: {{ item.admin_reason }}
      </p>
    </button>

    <div v-if="isAdmin && item.status === 'pending'" class="mt-3 flex items-center justify-end gap-2">
      <BaseButton size="sm" variant="outline" @click="$emit('disapprove', item)">
        <X :size="16" aria-hidden="true" /> Disapprove
      </BaseButton>
      <BaseButton size="sm" @click="$emit('approve', item)">
        <Check :size="16" aria-hidden="true" /> Approve
      </BaseButton>
    </div>
  </article>

  <BaseModal
    :open="detailsOpen"
    title="Trip Modification Request"
    :subtitle="item.trip?.destination ?? ''"
    @close="detailsOpen = false"
  >
    <dl class="divide-y divide-line">
      <div v-if="isAdmin" class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-3 sm:gap-4">
        <dt class="text-small text-ink-muted">Requested by</dt>
        <dd class="text-body sm:col-span-2">{{ item.requested_by ?? '—' }}</dd>
      </div>
      <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-3 sm:gap-4">
        <dt class="text-small text-ink-muted">Status</dt>
        <dd class="sm:col-span-2"><StatusBadge :status="item.status" /></dd>
      </div>
      <div class="grid grid-cols-1 gap-2 py-3 sm:grid-cols-[1fr_2fr_2fr]">
        <div class="text-small font-medium text-ink-muted">Field</div>
        <div class="text-small font-medium text-ink-muted">Original</div>
        <div class="text-small font-medium text-ink-muted">Requested</div>
        <template v-if="item.old_date !== item.new_date">
          <dt class="text-small font-medium">Date</dt>
          <dd class="rounded bg-surface p-2 text-small">{{ formatDate(item.old_date) }}</dd>
          <dd class="rounded bg-secondary-50 p-2 text-small font-medium text-secondary-700">{{ formatDate(item.new_date) }}</dd>
        </template>
        <template v-if="item.old_departure_time !== item.new_departure_time">
          <dt class="text-small font-medium">Departure time</dt>
          <dd class="rounded bg-surface p-2 text-small">{{ formatTime(item.old_departure_time) }}</dd>
          <dd class="rounded bg-secondary-50 p-2 text-small font-medium text-secondary-700">{{ formatTime(item.new_departure_time) }}</dd>
        </template>
        <template v-if="item.old_estimated_return_time !== item.new_estimated_return_time">
          <dt class="text-small font-medium">Estimated return</dt>
          <dd class="rounded bg-surface p-2 text-small">{{ formatTime(item.old_estimated_return_time) }}</dd>
          <dd class="rounded bg-secondary-50 p-2 text-small font-medium text-secondary-700">{{ formatTime(item.new_estimated_return_time) }}</dd>
        </template>
        <template v-if="item.old_destination !== item.new_destination">
          <dt class="text-small font-medium">Destination</dt>
          <dd class="rounded bg-surface p-2 text-small">{{ item.old_destination || '—' }}</dd>
          <dd class="rounded bg-secondary-50 p-2 text-small font-medium text-secondary-700">{{ item.new_destination || '—' }}</dd>
        </template>
        <template v-if="item.old_purpose !== item.new_purpose">
          <dt class="text-small font-medium">Purpose</dt>
          <dd class="rounded bg-surface p-2 text-small">{{ item.old_purpose || '—' }}</dd>
          <dd class="rounded bg-secondary-50 p-2 text-small font-medium text-secondary-700">{{ item.new_purpose || '—' }}</dd>
        </template>
        <template v-if="Number(item.old_number_of_passengers) !== Number(item.new_number_of_passengers)">
          <dt class="text-small font-medium">Passengers</dt>
          <dd class="rounded bg-surface p-2 text-small">{{ item.old_number_of_passengers || '—' }}</dd>
          <dd class="rounded bg-secondary-50 p-2 text-small font-medium text-secondary-700">{{ item.new_number_of_passengers || '—' }}</dd>
        </template>
      </div>
      <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-3 sm:gap-4">
        <dt class="text-small text-ink-muted">Reason</dt>
        <dd class="text-body sm:col-span-2">{{ item.reason || '—' }}</dd>
      </div>
      <div v-if="item.admin_reason" class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-3 sm:gap-4">
        <dt class="text-small text-ink-muted">Administrator's note</dt>
        <dd class="text-body sm:col-span-2">{{ item.admin_reason }}</dd>
      </div>
    </dl>
  </BaseModal>
</template>