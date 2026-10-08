<script setup>
import { computed, ref, watch } from 'vue'
import { formatDate, formatTime } from '../format'
import AppButton from './AppButton.vue'
import Icon from './Icon.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  openShareForm: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
  data: { type: Object, default: null }, 
  notice: { type: String, default: '' }, 
  admins: { type: Array, default: () => [] },
  sending: { type: Boolean, default: false },
  sendError: { type: String, default: '' },
})
const emit = defineEmits(['close', 'send'])
const recipientId = ref('')
const shareFormOpen = ref(false)

watch(
  () => [props.open, props.openShareForm],
  ([open, openShareForm]) => {
    if (open) {
      shareFormOpen.value = openShareForm
      return
    }
    recipientId.value = ''
    shareFormOpen.value = false
  },
)

const a = computed(() => props.data?.allocation ?? {})
const r = computed(() => props.data?.request ?? {})
const dash = (v) => (v === null || v === undefined || v === '' ? '—' : v)

const vehicle = computed(() => {
  const v = a.value.vehicle
  if (!v?.vehicle_model && !v?.plate_number) return '—'
  return `${v.vehicle_model ?? ''} (${v.plate_number ?? '—'})`.trim()
})
const status = computed(() => String(r.value.status || 'approved').toUpperCase())
const printedOn = formatDate(new Date().toISOString())

function printNow() {
  window.print() 
}
</script>

<template>
  <div v-if="open" class="cg-overlay cg-print-overlay">
    <div class="cg-dialog cg-print-dialog" role="dialog" aria-modal="true" aria-label="Trip request">
      <div class="cg-print-toolbar">
        <h2 class="cg-section-title">Trip Request</h2>
        <div class="cg-print-toolbar__actions">
          <AppButton
            v-if="data"
            variant="secondary"
            @click="shareFormOpen = !shareFormOpen"
          >
            <Icon name="send" /> Send to admin
          </AppButton>
          <AppButton :disabled="!data" @click="printNow"><Icon name="printer" /> Print / Save as PDF</AppButton>
          <AppButton variant="secondary" @click="emit('close')">Close</AppButton>
        </div>
      </div>

      <form v-if="shareFormOpen" class="cg-share-form cg-no-print" @submit.prevent="emit('send', Number(recipientId))">
        <template v-if="admins.length">
          <label class="cg-label" for="trip-request-recipient">Send this trip request to</label>
          <select id="trip-request-recipient" v-model="recipientId" class="cg-input" required :disabled="sending">
            <option value="" disabled>Select an admin</option>
            <option v-for="admin in admins" :key="admin.id" :value="String(admin.id)">
              {{ admin.name }} · {{ admin.email }}
            </option>
          </select>
          <AppButton type="submit" :disabled="!recipientId || sending">
            {{ sending ? 'Sending...' : 'Send request' }}
          </AppButton>
        </template>
        <p v-else class="cg-muted">No other admin accounts are available to receive this request.</p>
      </form>
      <p v-if="sendError" class="cg-form-error cg-no-print">{{ sendError }}</p>
      <p v-if="notice" class="cg-alert cg-no-print">{{ notice }}</p>
      <p v-if="loading" class="cg-muted">Loading...</p>
      <p v-else-if="error" class="cg-form-error">{{ error }}</p>

      <article v-if="data" class="cg-sheet">
        <header class="cg-sheet__head">
          <div>
            <p class="cg-sheet__org">Nueva Vizcaya State University · Motor Pool</p>
            <h3 class="cg-sheet__title">TRIP REQUEST</h3>
            <p class="cg-sheet__org">Request No. {{ dash(r.id) }}</p>
          </div>
          <span class="cg-stamp">{{ status }}</span>
        </header>

        <section>
          <h4 class="cg-sheet__section-title">Request details</h4>
          <div class="cg-fields">
            <div class="cg-field">
              <p class="cg-field__label">Requested by</p>
              <p class="cg-field__value">{{ dash(a.vehicle_request?.requester) }}</p>
            </div>
            <div class="cg-field">
              <p class="cg-field__label">Date requested</p>
              <p class="cg-field__value">{{ formatDate(r.requested_on) }}</p>
            </div>
            <div class="cg-field">
              <p class="cg-field__label">Destination</p>
              <p class="cg-field__value">{{ dash(a.trip?.destination) }}</p>
            </div>
            <div class="cg-field">
              <p class="cg-field__label">Number of passengers</p>
              <p class="cg-field__value">{{ dash(r.passengers) }}</p>
            </div>
            <div class="cg-field cg-field--full">
              <p class="cg-field__label">Purpose</p>
              <p class="cg-field__value">{{ dash(a.trip?.purpose) }}</p>
            </div>
            <div class="cg-field">
              <p class="cg-field__label">Date of trip</p>
              <p class="cg-field__value">{{ formatDate(a.trip?.trip_date) }}</p>
            </div>
            <div class="cg-field">
              <p class="cg-field__label">Departure · Estimated return</p>
              <p class="cg-field__value">
                {{ formatTime(a.trip?.departure_time) }} · {{ formatTime(a.trip?.estimated_return_time) }}
              </p>
            </div>
          </div>
        </section>

        <section>
          <h4 class="cg-sheet__section-title">Vehicle &amp; driver assigned</h4>
          <div class="cg-fields">
            <div class="cg-field">
              <p class="cg-field__label">Vehicle</p>
              <p class="cg-field__value">{{ vehicle }}</p>
            </div>
            <div class="cg-field">
              <p class="cg-field__label">Driver</p>
              <p class="cg-field__value">{{ dash(a.driver?.name) }}</p>
            </div>
            <div class="cg-field">
              <p class="cg-field__label">Driver contact number</p>
              <p class="cg-field__value">{{ dash(a.driver?.contact_number) }}</p>
            </div>
            <div class="cg-field">
              <p class="cg-field__label">Driver license number</p>
              <p class="cg-field__value">{{ dash(a.driver?.license_number) }}</p>
            </div>
            <div v-if="a.notes" class="cg-field cg-field--full">
              <p class="cg-field__label">Remarks</p>
              <p class="cg-field__value">{{ a.notes }}</p>
            </div>
          </div>
        </section>

        <section>
          <h4 class="cg-sheet__section-title">Approval</h4>
          <div class="cg-fields">
            <div class="cg-field">
              <p class="cg-field__label">Approved by</p>
              <p class="cg-field__value">{{ dash(r.approved_by) }}</p>
            </div>
            <div class="cg-field">
              <p class="cg-field__label">Approval date</p>
              <p class="cg-field__value">{{ formatDate(r.approved_on) }}</p>
            </div>
          </div>
        </section>

        <section class="cg-sign">
          <div>
            <div class="cg-sign__space"></div>
            <p class="cg-sign__line">Chief General Service and Special Projects</p>
          </div>
          <div>
            <div class="cg-sign__space"></div>
            <p class="cg-sign__line">Date signed</p>
          </div>
        </section>

        <footer class="cg-sheet__foot">Printed on {{ printedOn }}</footer>
      </article>
    </div>
  </div>
</template>
