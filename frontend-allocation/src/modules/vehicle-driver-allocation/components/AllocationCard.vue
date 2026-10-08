<script setup>
import { computed, ref } from 'vue'
import { ACTIVE_STATUSES } from '../api'
import AppButton from './AppButton.vue'
import Icon from './Icon.vue'
import StatusBadge from './StatusBadge.vue'

const props = defineProps({
  allocation: { type: Object, required: true },
  isAdmin: { type: Boolean, default: false },
  pendingChange: { type: Object, default: null }, // pending date-change request ng trip na ito
})
const emit = defineEmits(['reassign', 'print', 'share', 'reschedule'])
const detailsOpen = ref(false)

// Palit ng petsa: scheduled pa lang. Admin = direct; Faculty = request lang (isa lang na pending).
const canReschedule = computed(
  () => props.allocation.status === 'scheduled' && (props.isAdmin || !props.pendingChange),
)

const canManage = computed(() => props.isAdmin && ACTIVE_STATUSES.includes(props.allocation.status))
const displayStatus = computed(() =>
  !props.isAdmin && props.allocation.status === 'cancelled'
    ? 'disapproved'
    : props.allocation.status ?? 'unknown',
)
// Kapag na-allocate na ang vehicle at driver, pwedeng i-print ang Trip Request (hindi na kung cancelled).
const canPrint = computed(() => props.isAdmin && props.allocation.status !== 'cancelled')
</script>

<template>
  <article class="cg-card cg-request-compact">
    <div class="cg-request-compact__summary">
      <div class="cg-request-compact__main">
        <p class="cg-card__title">{{ allocation.trip?.destination ?? '—' }}</p>
        <p class="cg-muted">{{ allocation.trip?.trip_date ?? '—' }} · {{ allocation.driver?.name ?? 'No driver' }}</p>
      </div>
      <StatusBadge :status="displayStatus" />
      <button
        type="button"
        class="cg-collapse-btn"
        :aria-expanded="detailsOpen"
        :aria-controls="`allocation-details-${allocation.id}`"
        @click="detailsOpen = !detailsOpen"
      >
        {{ detailsOpen ? 'Less' : 'Details' }}
        <Icon name="chevron" />
      </button>
    </div>

    <div v-if="detailsOpen" :id="`allocation-details-${allocation.id}`" class="cg-request-compact__details">
      <p class="cg-muted">{{ allocation.trip?.purpose ?? '' }}</p>
      <p class="cg-muted">Requested by: {{ allocation.vehicle_request?.requester ?? '—' }}</p>
      <p class="cg-meta"><Icon name="clock" /> {{ allocation.trip?.departure_time ?? '—' }} → {{ allocation.trip?.estimated_return_time ?? '—' }}</p>
      <div class="cg-box">
        <p class="cg-meta cg-meta--text">
          <Icon name="car" />
          {{ allocation.vehicle?.vehicle_model ?? '—' }} ({{ allocation.vehicle?.plate_number ?? '—' }})
        </p>
        <p class="cg-meta cg-meta--text"><Icon name="user" /> {{ allocation.driver?.name ?? '—' }}</p>
        <p class="cg-meta cg-meta--text"><Icon name="phone" /> {{ allocation.driver?.contact_number ?? '—' }}</p>
      </div>

      <p v-if="allocation.notes" class="cg-note">Note: {{ allocation.notes }}</p>

      <p v-if="pendingChange" class="cg-alert">
        Date change requested: {{ pendingChange.new_date }} (waiting for Admin approval)
      </p>

      <div v-if="canManage || canPrint || canReschedule" class="cg-actions">
        <AppButton v-if="canReschedule" variant="secondary" @click="emit('reschedule', allocation)">
          <Icon name="calendar" /> {{ isAdmin ? 'Change date' : 'Request date change' }}
        </AppButton>
        <AppButton v-if="canPrint" variant="secondary" @click="emit('print', allocation)">
          <Icon name="printer" /> Print
        </AppButton>
        <AppButton v-if="canPrint" variant="secondary" @click="emit('share', allocation)">
          <Icon name="send" /> Send to admin
        </AppButton>
        <AppButton v-if="canManage" variant="warning" @click="emit('reassign', allocation)">Reassign</AppButton>
      </div>
    </div>
  </article>
</template>
