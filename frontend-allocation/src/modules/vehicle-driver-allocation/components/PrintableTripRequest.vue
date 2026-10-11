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
const official = computed(() => r.value.official ?? a.value.vehicle_request?.requester ?? '')
const travelStart = computed(() => r.value.trip_date ?? a.value.trip?.trip_date)
const travelEnd = computed(() => r.value.trip_end_date ?? travelStart.value)
const travelDate = computed(() => {
  if (!travelStart.value) return ''
  const start = formatDate(travelStart.value)
  const end = travelEnd.value && travelEnd.value !== travelStart.value
    ? ` to ${formatDate(travelEnd.value)}`
    : ''
  return `${start}${end}`
})
const travelDays = computed(() => {
  if (r.value.travel_days) return r.value.travel_days
  if (!travelStart.value || !travelEnd.value) return ''
  const start = Date.parse(`${travelStart.value}T00:00:00Z`)
  const end = Date.parse(`${travelEnd.value}T00:00:00Z`)
  return Number.isNaN(start) || Number.isNaN(end) ? '' : Math.floor((end - start) / 86400000) + 1
})
const requestedTime = computed(() => r.value.requested_time
  ? formatTime(r.value.requested_time)
  : '')
const tripType = computed(() => String(r.value.trip_type ?? '').trim().toLowerCase())
const copies = [1, 2]

const vehicle = computed(() => {
  const v = a.value.vehicle
  if (!v?.vehicle_model && !v?.plate_number) return '—'
  return `${v.vehicle_model ?? ''} (${v.plate_number ?? '—'})`.trim()
})
const status = computed(() => String(r.value.status || 'approved').toUpperCase())

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
        <section v-for="copy in copies" :key="copy" class="cg-sheet__form">
          <table class="cg-sheet__header">
            <tbody>
              <tr>
                <td class="cg-sheet__logo-cell">
                  <img src="/images/nvsu-logo.png" alt="NVSU logo" class="cg-sheet__logo" />
                </td>
                <td>
                  <p class="cg-sheet__school">Republic of the Philippines</p>
                  <p class="cg-sheet__school cg-sheet__school--bold">NUEVA VIZCAYA STATE UNIVERSITY</p>
                  <p class="cg-sheet__school">Bayombong, Nueva Vizcaya</p>
                </td>
              </tr>
              <tr>
                <td colspan="2" class="cg-sheet__form-title-cell">
                  <p class="cg-sheet__form-title">REQUEST FOR USE OF VEHICLE</p>
                </td>
              </tr>
            </tbody>
          </table>

          <p class="cg-sheet__ruv"><strong>RUV No.:</strong> <span class="cg-paper-blank cg-paper-blank--45"></span></p>

          <div class="cg-sheet__grid">
            <div class="cg-sheet__col-left">
              <p><strong>Requesting Official:</strong> <span class="cg-paper-value cg-paper-value--bold">{{ official }}</span></p>
              <p><strong>Position/Designation:</strong> <span class="cg-paper-value">{{ r.position }}</span></p>
              <p><strong>Destination/Place(s):</strong> <span class="cg-paper-value">{{ a.trip?.destination }}</span></p>
              <p><strong>Purpose(s):</strong> <span class="cg-paper-value">{{ a.trip?.purpose }}</span></p>
              <p><strong>Authorized Passenger(s):</strong> <span class="cg-paper-value">{{ r.authorized_passengers }}</span></p>
            </div>
            <div class="cg-sheet__col-right">
              <p><strong>Request No.:</strong> <span class="cg-paper-value">{{ dash(r.id) }}</span></p>
              <p><strong>Date Requested:</strong> <span class="cg-paper-value">{{ formatDate(r.requested_on) }}</span></p>
              <p><strong>Time Requested:</strong> <span class="cg-paper-value">{{ requestedTime }}</span></p>
            </div>
          </div>

          <div class="cg-sheet__grid cg-sheet__travel">
            <div class="cg-sheet__col-left">
              <p><strong>Date of Travel:</strong> <span class="cg-paper-value">{{ travelDate }}</span></p>
              <p>
                <strong>Days of Travel:</strong>
                <span :class="{ 'cg-paper-underlined': tripType === 'inclusive' }">Inclusive</span>/
                <span :class="{ 'cg-paper-underlined': tripType === 'exclusive' }">Exclusive</span>:
                <span class="cg-paper-value">{{ travelDays }}</span>
              </p>
              <p class="cg-sheet__hint">(Please underline)</p>
            </div>
            <div class="cg-sheet__col-right">
              <p><strong>Time of Travel:</strong> <span class="cg-paper-value">{{ formatTime(a.trip?.departure_time) }}</span></p>
            </div>
          </div>

          <div class="cg-sheet__requester-sign">
            <p class="cg-sheet__sign-name">{{ official }}</p>
            <p class="cg-sheet__sign-label">Name and Signature of Requesting Official</p>
          </div>

          <div class="cg-sheet__rule"><em>For the Motorpool only</em></div>
          <p class="cg-sheet__action-title">ACTION ON REQUEST</p>

          <div class="cg-sheet__action-top">
            <div>
              <p>Vehicle Plate No.: <span class="cg-paper-value">{{ a.vehicle?.plate_number }}</span></p>
              <p>Driver: <span class="cg-paper-value">{{ a.driver?.name }}</span></p>
            </div>
            <div class="cg-sheet__request-checks">
              <span>Request:</span>
              <div>
                <p><span class="cg-paper-box">{{ status === 'APPROVED' ? '✓' : '' }}</span> Approved</p>
                <p><span class="cg-paper-box"></span> Disapproved due to: <span class="cg-paper-blank cg-paper-blank--30"></span></p>
              </div>
            </div>
          </div>

          <div class="cg-sheet__approvals">
            <div>
              <p><strong>Dispatched by:</strong></p>
              <p class="cg-sheet__approval-name"></p>
              <p>Chief, Mechanic</p>
            </div>
            <div>
              <p><strong>Recommending Approval:</strong></p>
              <p class="cg-sheet__approval-name">TONY O. BASATAN</p>
              <p>Chief, Motorpool</p>
            </div>
            <div>
              <p><strong>Approved:</strong></p>
              <p class="cg-sheet__approval-name">JAYSON R. PIMENTEL</p>
              <p>Unit Head-GSU</p>
            </div>
          </div>

          <footer class="cg-sheet__foot">
            <p><em>Note: Please accomplish this form three (3) days before travel.</em></p>
            <small>NVSU-FR-PPS-13-02 (092524)</small>
          </footer>
        </section>
      </article>
    </div>
  </div>
</template>
