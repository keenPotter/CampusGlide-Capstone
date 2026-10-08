<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { ApiError, auth, isAdmin, isLoggedIn, request, UnauthorizedError } from './api'
import './styles/allocation.css'
import AllocationCard from './components/AllocationCard.vue'
import AllocationModal from './components/AllocationModal.vue'
import AppButton from './components/AppButton.vue'
import AppHeader from './components/AppHeader.vue'
import AccountChip from './components/AccountChip.vue'
import AuthPanel from './components/AuthPanel.vue'
import ConflictModal from './components/ConflictModal.vue'
import Icon from './components/Icon.vue'
import PrintableTripRequest from './components/PrintableTripRequest.vue'
import RequestCard from './components/RequestCard.vue'
import RescheduleModal from './components/RescheduleModal.vue'
import RescheduleRequestCard from './components/RescheduleRequestCard.vue'
import SearchBar from './components/SearchBar.vue'
import StatCard from './components/StatCard.vue'
import StatusBadge from './components/StatusBadge.vue'
import { groupByLocality } from './locality'
import { formatDate, formatTime } from './format'

const allocations = ref([])
const waiting = ref([]) 
const optionVehicles = ref([])
const optionDrivers = ref([])
const search = ref('')
const listError = ref('')
const panelOpen = ref(!isLoggedIn.value) 
const modalOpen = ref(false)
const editing = ref(null)
const presetRequestId = ref(null)


const printOpen = ref(false)
const printShareOpen = ref(false)
const printLoading = ref(false)
const printError = ref('')
const printData = ref(null)
const printNotice = ref('')
const printSendError = ref('')
const printSending = ref(false)
const adminRecipients = ref([])
const adminRecipientsError = ref('')
const inboxShares = ref([])
const inboxError = ref('')
const inboxLoading = ref(false)

const changeRequests = ref([]) 
const changeRequestsError = ref('')
const rescheduleTarget = ref(null) 
const pageConflict = ref(null) 
const toast = ref('')
const vehicleRequests = ref([])
const disapprovedVehicleRequestCount = ref(0)
const vehicleRequestsError = ref('')
const vehicleRequestsLoading = ref(false)
const vehicleRequestSaving = ref(false)
const approvingVehicleRequestId = ref(null)
const vehicleDecisionError = ref('')
const vehicleDisapprovalReason = ref('')
const disapprovingVehicleRequest = ref(null)
const vehicleNotificationsOpen = ref(false)
const vehicleRequestTypeFilter = ref('')
const selectedFacultyLocality = ref('local')
const vehicleRequestsLoaded = ref(false)
const dashboardLoading = ref(false)
const waitingError = ref('')
const expandedVehicleRequestIds = ref(new Set())
let adminRefreshTimer
const today = new Date()
today.setMinutes(today.getMinutes() - today.getTimezoneOffset())
const minimumTripDate = today.toISOString().slice(0, 10)
const vehicleRequestForm = ref({
  trip_date: '',
  trip_end_date: '',
  trip_type: 'inclusive',
  departure_time: '',
  estimated_return_time: '',
  destination: '',
  purpose: '',
  passengers: '',
  number_of_passengers: 1,
})

const pendingRequests = computed(() => changeRequests.value.filter((r) => r.status === 'pending'))
const pendingVehicleRequestCount = computed(() =>
  vehicleRequests.value.filter((item) => item.status === 'pending').length,
)
const filteredVehicleRequests = computed(() => vehicleRequests.value.filter((item) =>
  !vehicleRequestTypeFilter.value || item.trip_type === vehicleRequestTypeFilter.value,
))
const facultyVehicleRequestLocalityGroups = computed(() =>
  groupByLocality(filteredVehicleRequests.value, (item) => item.destination),
)
const facultyAllocationLocalityGroups = computed(() =>
  groupByLocality(filtered.value, (allocation) => allocation.trip?.destination),
)
const vehicleRequestTypeCounts = computed(() => ({
  all: vehicleRequests.value.length,
  inclusive: vehicleRequests.value.filter((item) => item.trip_type === 'inclusive').length,
  exclusive: vehicleRequests.value.filter((item) => item.trip_type === 'exclusive').length,
}))
const vehicleRequestNotice = computed(() => {
  if (isAdmin.value) return ''
  if (vehicleRequests.value.some((item) => item.status === 'pending')) {
    return 'Your vehicle request is waiting for Admin approval.'
  }
  if (vehicleRequests.value.some((item) => item.status === 'disapproved')) {
    return 'One or more vehicle requests were disapproved. Open their details to see the reasons.'
  }

  const approvedAwaitingAllocation = vehicleRequests.value.some((item) =>
    item.status === 'approved'
    && !allocations.value.some((allocation) => Number(allocation.vehicle_request?.id) === Number(item.id)),
  )
  if (approvedAwaitingAllocation) {
    return 'Your request was approved. The Admin will assign a vehicle and driver.'
  }
  if (vehicleRequests.value.length) {
    return 'Your vehicle request has been allocated. See your trip in Allocations below.'
  }
  return 'Submit a vehicle request below. The Admin must approve it before assigning a vehicle and driver.'
})
const pendingByAllocation = computed(() => {
  const map = {}
  pendingRequests.value.forEach((r) => (map[r.allocation_id] = r))
  return map
})

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return allocations.value

  return allocations.value.filter((a) =>
    [a.trip?.destination, a.driver?.name, a.vehicle?.plate_number, a.vehicle?.vehicle_model]
      .join(' ')
      .toLowerCase()
      .includes(q),
  )
})

const groupedWaiting = computed(() =>
  groupByLocality(waiting.value, (item) => item.destination),
)
const groupedVehicleRequests = computed(() =>
  groupByLocality(vehicleRequests.value, (item) => item.destination),
)
const selectedSummaryLocality = ref('local')

// ---------- Admin dashboard (Vehicle-Driver Allocation overview) ----------
const ACTIVE = ['scheduled', 'in_progress']
const activeAllocations = computed(() => allocations.value.filter((a) => ACTIVE.includes(a.status)))

function toMinutes(t) {
  if (!t) return null
  const [h, m] = String(t).split(':')
  return Number(h) * 60 + Number(m || 0)
}
function overlaps(a, b) {
  if (!a.trip?.trip_date || a.trip.trip_date !== b.trip?.trip_date) return false
  const aS = toMinutes(a.trip.departure_time) ?? 0
  const bS = toMinutes(b.trip.departure_time) ?? 0
  const aE = toMinutes(a.trip.estimated_return_time) ?? aS + 1
  const bE = toMinutes(b.trip.estimated_return_time) ?? bS + 1
  return aS < bE && bS < aE
}

// conflict: parehong driver o vehicle, parehong petsa at nagtatagpo ang oras
const conflictMap = computed(() => {
  const map = {}
  const list = activeAllocations.value
  for (let i = 0; i < list.length; i++) {
    for (let j = i + 1; j < list.length; j++) {
      const a = list[i]
      const b = list[j]
      if (!overlaps(a, b)) continue
      const sameDriver = a.driver?.id && a.driver.id === b.driver?.id
      const sameVehicle = a.vehicle?.id && a.vehicle.id === b.vehicle?.id
      if (!sameDriver && !sameVehicle) continue
      const who = sameDriver ? a.driver.name : a.vehicle.vehicle_model
      const text = `${who} is assigned to 2 trips on ${formatDate(a.trip.trip_date)}`
      ;[a, b].forEach((x) => {
        map[x.id] = map[x.id] || { text, driverId: sameDriver ? a.driver.id : null, vehicleId: sameVehicle ? a.vehicle.id : null }
        if (sameDriver) map[x.id].driverId = a.driver.id
        if (sameVehicle) map[x.id].vehicleId = a.vehicle.id
      })
    }
  }
  return map
})
const conflictAllocations = computed(() => allocations.value.filter((a) => conflictMap.value[a.id]))
const conflictTexts = computed(() => [...new Set(conflictAllocations.value.map((a) => conflictMap.value[a.id].text))])
const conflictDriverIds = computed(() => new Set(Object.values(conflictMap.value).map((c) => c.driverId).filter(Boolean)))
const conflictVehicleIds = computed(() => new Set(Object.values(conflictMap.value).map((c) => c.vehicleId).filter(Boolean)))

// dot: green = libre, orange = may nakatakdang trip, red = may conflict
function resourceState(kind, id, conflicts) {
  if (conflicts.has(id)) return 'red'
  const busy = activeAllocations.value.some((a) => a[kind]?.id === id)
  return busy ? 'orange' : 'green'
}
const dotLabel = { green: 'Available', orange: 'Has scheduled trip', red: 'Conflict' }

const dashVehicles = computed(() =>
  optionVehicles.value.map((v) => ({ ...v, state: resourceState('vehicle', v.id, conflictVehicleIds.value) })),
)
const dashDrivers = computed(() =>
  optionDrivers.value.map((d) => ({ ...d, state: resourceState('driver', d.id, conflictDriverIds.value) })),
)
function initials(name) {
  return String(name || '?').replace(/^(Drv\.|Dr\.)\s*/i, '').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join('')
}

// Allocation Summary combines allocations, pending assignments, and received requests.
const summaryRows = computed(() => {
  const q = search.value.trim().toLowerCase()
  const rows = [
    ...filtered.value.map((a) => ({ key: `a${a.id}`, kind: 'allocation', allocation: a, date: a.trip?.trip_date, time: a.trip?.departure_time })),
    ...waiting.value
      .filter((r) => !q || [r.destination, r.requester].join(' ').toLowerCase().includes(q))
      .map((r) => ({ key: `r${r.id}`, kind: 'waiting', request: r, date: r.trip_date, time: r.departure_time })),
    ...inboxShares.value.map((share) => ({
      key: `s${share.id}`,
      kind: 'received',
      share,
      date: share.allocation.trip?.trip_date,
      time: share.allocation.trip?.departure_time,
    })),
  ]
  return rows.sort((x, y) => `${x.date ?? ''} ${x.time ?? ''}`.localeCompare(`${y.date ?? ''} ${y.time ?? ''}`))
})
const summaryLocalityGroups = computed(() => groupByLocality(summaryRows.value, (row) => {
  if (row.kind === 'allocation') return row.allocation.trip?.destination
  if (row.kind === 'waiting') return row.request.destination
  return row.share.allocation.trip?.destination
}))
const selectedSummaryRows = computed(() => summaryLocalityGroups.value[selectedSummaryLocality.value])
function whenText(date, time) {
  return `${date ? formatDate(date) : '—'}${time ? ' · ' + formatTime(time) : ''}`
}
function resolveConflict(allocation) {
  const target = allocation || conflictAllocations.value[0]
  if (target) openEdit(target)
}

const counts = computed(() => {
  const c = { scheduled: 0, in_progress: 0, completed: 0, cancelled: 0 }
  allocations.value.forEach((a) => {
    if (c[a.status] !== undefined) c[a.status]++
  })
  return c
})

async function loadAllocations() {
  if (!isLoggedIn.value) return
  listError.value = ''

  try {
    // per_page=100 para kumpleto ang listahan at stats (default ng API ay 20 lang).
    const data = await request('/allocations?per_page=100')
    allocations.value = data.data || data
  } catch (e) {
    if (e instanceof UnauthorizedError) return // inaayos na ng watcher sa baba
    console.error(e)
    listError.value = e.message
  }
}

// "Needs allocation": approved requests na wala pang vehicle at driver.
async function loadWaiting() {
  if (!isLoggedIn.value || !isAdmin.value) {
    waiting.value = []
    optionVehicles.value = []
    optionDrivers.value = []
    waitingError.value = ''
    return
  }
  waitingError.value = ''
  try {
    const data = await request('/allocation-options')
    waiting.value = data.requests || []
    optionVehicles.value = data.vehicles || []
    optionDrivers.value = data.drivers || []
  } catch (e) {
    if (!(e instanceof UnauthorizedError)) {
      console.error(e)
      waitingError.value = e.message
    }
  }
}

async function loadVehicleRequests() {
  if (!isLoggedIn.value) return
  if (!vehicleRequestsLoaded.value) vehicleRequestsLoading.value = true
  vehicleRequestsError.value = ''
  try {
    const [data, disapprovedData] = await Promise.all([
      request(isAdmin.value ? '/vehicle-requests?status=pending' : '/vehicle-requests'),
      isAdmin.value ? request('/vehicle-requests?status=disapproved') : Promise.resolve(null),
    ])
    const nextRequests = data.data || []
    if (isAdmin.value && vehicleRequestsLoaded.value) {
      const knownIds = new Set(vehicleRequests.value.map((item) => item.id))
      const newRequests = nextRequests.filter((item) => !knownIds.has(item.id))
      if (newRequests.length) {
        const requester = newRequests[0].requester || 'A faculty member'
        showToast(`${requester} submitted a vehicle request.`)
      }
    }
    vehicleRequests.value = nextRequests
    if (disapprovedData) disapprovedVehicleRequestCount.value = (disapprovedData.data || []).length
    vehicleRequestsLoaded.value = true
  } catch (e) {
    if (!(e instanceof UnauthorizedError)) {
      console.error(e)
      vehicleRequestsError.value = e.message
    }
  } finally {
    vehicleRequestsLoading.value = false
  }
}

async function submitVehicleRequest() {
  vehicleRequestsError.value = ''
  vehicleRequestSaving.value = true
  try {
    await request('/vehicle-requests', { method: 'POST', body: vehicleRequestForm.value })
    vehicleRequestForm.value = {
      trip_date: '',
      trip_end_date: '',
      trip_type: 'inclusive',
      departure_time: '',
      estimated_return_time: '',
      destination: '',
      purpose: '',
      passengers: '',
      number_of_passengers: 1,
    }
    showToast('Vehicle request submitted. Waiting for Admin approval.')
    await loadVehicleRequests()
  } catch (e) {
    if (!(e instanceof UnauthorizedError)) vehicleRequestsError.value = e.message
  } finally {
    vehicleRequestSaving.value = false
  }
}

async function approveVehicleRequest(item) {
  approvingVehicleRequestId.value = item.id
  vehicleRequestsError.value = ''
  try {
    await request(`/vehicle-requests/${item.id}/approve`, { method: 'POST', body: {} })
    showToast('Vehicle request approved. It is now ready for allocation.')
    await Promise.all([loadVehicleRequests(), loadWaiting()])
  } catch (e) {
    if (!(e instanceof UnauthorizedError)) vehicleRequestsError.value = e.message
  } finally {
    approvingVehicleRequestId.value = null
  }
}

function beginVehicleRequestDisapproval(item) {
  vehicleDecisionError.value = ''
  vehicleDisapprovalReason.value = ''
  disapprovingVehicleRequest.value = item
  vehicleNotificationsOpen.value = false
}

async function disapproveVehicleRequest() {
  const item = disapprovingVehicleRequest.value
  const reason = vehicleDisapprovalReason.value.trim()
  if (!item || !reason) {
    vehicleDecisionError.value = 'Please provide a reason for disapproval.'
    return
  }

  approvingVehicleRequestId.value = item.id
  vehicleDecisionError.value = ''
  try {
    await request(`/vehicle-requests/${item.id}/status`, {
      method: 'PATCH',
      body: { status: 'disapproved', remarks: reason },
    })
    disapprovingVehicleRequest.value = null
    showToast(`Vehicle request #${item.id} disapproved.`)
    await loadVehicleRequests()
  } catch (e) {
    if (!(e instanceof UnauthorizedError)) vehicleDecisionError.value = e.message
  } finally {
    approvingVehicleRequestId.value = null
  }
}

async function loadChangeRequests() {
  if (!isLoggedIn.value) return
  changeRequestsError.value = ''
  try {
    const data = await request('/reschedule-requests')
    changeRequests.value = data.data || []
  } catch (e) {
    if (!(e instanceof UnauthorizedError)) {
      console.error(e)
      changeRequestsError.value = e.message
    }
  }
}

async function loadAdminRecipients() {
  if (!isLoggedIn.value || !isAdmin.value) {
    adminRecipients.value = []
    return
  }

  try {
    const data = await request('/admins')
    adminRecipients.value = data.data || []
    adminRecipientsError.value = ''
  } catch (e) {
    if (!(e instanceof UnauthorizedError)) {
      console.error(e)
      adminRecipientsError.value = e.message
    }
  }
}

async function loadInbox() {
  if (!isLoggedIn.value || !isAdmin.value) {
    inboxShares.value = []
    return
  }

  inboxLoading.value = true
  try {
    const data = await request('/trip-request-shares')
    inboxShares.value = data.data || []
    inboxError.value = ''
  } catch (e) {
    if (!(e instanceof UnauthorizedError)) {
      console.error(e)
      inboxError.value = e.message
    }
  } finally {
    inboxLoading.value = false
  }
}

async function refresh() {
  dashboardLoading.value = true
  try {
    await Promise.all([
      loadAllocations(),
      loadWaiting(),
      loadVehicleRequests(),
      loadChangeRequests(),
      loadAdminRecipients(),
      loadInbox(),
    ])
  } finally {
    dashboardLoading.value = false
  }
}

function startDashboardRefresh() {
  if (adminRefreshTimer || !isLoggedIn.value) return
  adminRefreshTimer = window.setInterval(() => {
    if (isLoggedIn.value) {
      loadVehicleRequests()
      loadAllocations()
      if (isAdmin.value) loadWaiting()
    }
  }, 10000)
}

function stopAdminRefresh() {
  if (!adminRefreshTimer) return
  window.clearInterval(adminRefreshTimer)
  adminRefreshTimer = undefined
}

function showToast(message) {
  toast.value = message
  setTimeout(() => (toast.value = ''), 4000)
}

async function onLoggedIn() {
  panelOpen.value = false
  await refresh()
  startDashboardRefresh()
}

function toggleVehicleRequestDetails(id) {
  const expanded = new Set(expandedVehicleRequestIds.value)
  if (expanded.has(id)) expanded.delete(id)
  else expanded.add(id)
  expandedVehicleRequestIds.value = expanded
}

// Kapag nag-logout o nag-expire ang token: linisin ang screen at ipakita ang login.
watch(
  () => auth.token,
  (token) => {
    if (token) return
    allocations.value = []
    waiting.value = []
    vehicleRequests.value = []
    vehicleRequestsLoaded.value = false
    stopAdminRefresh()
    changeRequests.value = []
    changeRequestsError.value = ''
    waitingError.value = ''
    expandedVehicleRequestIds.value = new Set()
    rescheduleTarget.value = null
    pageConflict.value = null
    listError.value = ''
    modalOpen.value = false
    printOpen.value = false
    adminRecipients.value = []
    inboxShares.value = []
    panelOpen.value = true
  },
)

function openNew(requestId = null) {
  editing.value = null
  presetRequestId.value = requestId
  modalOpen.value = true
}

function openEdit(allocation) {
  editing.value = allocation
  presetRequestId.value = null
  modalOpen.value = true
}

function onSaved(created) {
  modalOpen.value = false
  refresh()
  if (created?.id) openPrint(created, 'Allocation complete. Print this Trip Request yourself, or save it as a PDF')
}

async function openPrint(allocation, notice = '', share = false) {
  printShareOpen.value = share
  printNotice.value = notice
  printSendError.value = ''
  printOpen.value = true
  printLoading.value = true
  printError.value = ''
  printData.value = null

  try {
    const res = await request(`/allocations/${allocation.id}/print`)
    printData.value = res.data
  } catch (e) {
    if (!(e instanceof UnauthorizedError)) printError.value = e.message
  } finally {
    printLoading.value = false
  }
}

async function sendTripRequest(recipientId) {
  if (!printData.value?.allocation?.id || printSending.value) return

  printSending.value = true
  printSendError.value = ''
  try {
    const response = await request('/trip-request-shares', {
      method: 'POST',
      body: { allocation_id: printData.value.allocation.id, recipient_id: recipientId },
    })
    printNotice.value = `Trip request sent to ${response.data.recipient.name}.`
    await loadInbox()
  } catch (e) {
    if (!(e instanceof UnauthorizedError)) printSendError.value = e.message
  } finally {
    printSending.value = false
  }
}

function showWaiting() {
  if (isAdmin.value) {
    vehicleNotificationsOpen.value = !vehicleNotificationsOpen.value
    vehicleDecisionError.value = ''
    return
  }
  document.getElementById('vehicle-requests')?.scrollIntoView({ behavior: 'smooth' })
}

function scrollToVehicleRequest() {
  document.getElementById('create-vehicle-request')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function onRescheduleSaved(message) {
  rescheduleTarget.value = null
  showToast(message)
  refresh()
}

function onRescheduleConflict(conflict) {
  pageConflict.value = conflict
}

async function approveChange(item) {
  if (!confirm(`Approve moving this trip to ${item.new_date}?`)) return

  try {
    await request(`/reschedule-requests/${item.id}/approve`, { method: 'POST', body: {} })
    showToast('Request approved. The trip date was changed.')
    refresh()
  } catch (e) {
    if (e instanceof UnauthorizedError) return
    if (e instanceof ApiError && e.data?.conflict) pageConflict.value = e.data.conflict
    else alert('Error: ' + e.message)
  }
}

onMounted(() => {
  refresh()
  startDashboardRefresh()
})
onUnmounted(stopAdminRefresh)
</script>

<template>
  <div class="cg-app">
    <main v-if="!isLoggedIn" class="cg-login-screen">
      <AuthPanel :open="true" @login="onLoggedIn" />
    </main>

    <template v-else>
      <div class="cg-header-menu">
        <AppHeader
          title="Vehicle-Driver Allocation"
          :badge="isAdmin ? pendingVehicleRequestCount : vehicleRequests.length"
          :notifications-open="vehicleNotificationsOpen"
          :menu-open="panelOpen"
          @toggle-panel="panelOpen = !panelOpen"
          @bell="showWaiting"
        />
        <AuthPanel :open="panelOpen" menu @login="onLoggedIn" />
        <aside v-if="isAdmin && vehicleNotificationsOpen" class="cg-notifications-panel" aria-label="Pending vehicle request notifications">
          <div class="cg-notifications-panel__head">
            <h2 class="cg-section-title">Vehicle requests ({{ pendingVehicleRequestCount }} pending)</h2>
            <button type="button" class="cg-collapse-btn" aria-label="Close notifications" @click="vehicleNotificationsOpen = false">Close</button>
          </div>
          <p v-if="vehicleRequestsError" class="cg-form-error">{{ vehicleRequestsError }}</p>
          <p v-else-if="vehicleRequestsLoading" class="cg-muted">Loading vehicle requests...</p>
          <div class="cg-request-type-filters" role="tablist" aria-label="Filter requests by trip type">
            <button
              v-for="filter in [
                { key: '', label: 'All', count: vehicleRequestTypeCounts.all },
                { key: 'inclusive', label: 'Inclusive', count: vehicleRequestTypeCounts.inclusive },
                { key: 'exclusive', label: 'Exclusive', count: vehicleRequestTypeCounts.exclusive },
              ]"
              :key="filter.key || 'all'"
              type="button"
              role="tab"
              :aria-selected="vehicleRequestTypeFilter === filter.key"
              :class="['cg-request-type-filter', { 'cg-request-type-filter--active': vehicleRequestTypeFilter === filter.key }]"
              @click="vehicleRequestTypeFilter = filter.key"
            >
              {{ filter.label }} <span>{{ filter.count }}</span>
            </button>
          </div>
          <p v-if="!vehicleRequestsLoading && !filteredVehicleRequests.length" class="cg-empty">
            No {{ vehicleRequestTypeFilter || '' }} vehicle requests are waiting for approval.
          </p>
          <div v-else-if="filteredVehicleRequests.length" class="cg-notifications-panel__list">
            <article v-for="item in filteredVehicleRequests" :key="item.id" class="cg-notification-item">
              <div class="cg-notification-item__head">
                <div>
                  <p class="cg-card__title">REQ-{{ String(item.id).padStart(3, '0') }} · {{ item.destination }}</p>
                  <p class="cg-muted">{{ item.requester || 'Faculty' }} · {{ item.trip_date }}{{ item.trip_end_date && item.trip_end_date !== item.trip_date ? ` – ${item.trip_end_date}` : '' }} · {{ item.departure_time }}</p>
                </div>
                <StatusBadge :status="item.status" />
              </div>
              <p class="cg-muted">{{ item.trip_type }} trip</p>
              <p class="cg-muted">{{ item.purpose }}</p>
              <p class="cg-meta"><Icon name="users" /> {{ item.number_of_passengers }} passengers · {{ item.passengers }}</p>
              <div class="cg-notification-item__actions">
                <AppButton :disabled="approvingVehicleRequestId === item.id" @click="approveVehicleRequest(item)">
                  {{ approvingVehicleRequestId === item.id ? 'Saving...' : 'Approve' }}
                </AppButton>
                <AppButton variant="secondary" :disabled="approvingVehicleRequestId === item.id" @click="beginVehicleRequestDisapproval(item)">
                  Disapprove
                </AppButton>
              </div>
            </article>
          </div>
        </aside>
      </div>

      <main class="cg-container cg-main">

        <!-- ============ ADMIN DASHBOARD (Vehicle-Driver Allocation overview) ============ -->
        <section v-if="isAdmin" class="cg-dash">
          <div class="cg-dash__head">
            <div>
              <p class="cg-dash__sub">Assign vehicles and drivers to approved trip requests. The system will flag scheduling conflicts automatically.</p>
            </div>
            <AppButton @click="openNew()">+ Allocate</AppButton>
          </div>

          <div v-if="conflictAllocations.length" class="cg-conflict-banner" role="alert">
            <p class="cg-conflict-banner__text">
              <Icon name="alert" />
              <span>
                {{ conflictTexts.length }} conflict{{ conflictTexts.length > 1 ? 's' : '' }} detected: {{ conflictTexts.join(' · ') }}. Please resolve before confirming the schedule.
              </span>
            </p>
            <button type="button" class="cg-pill-btn" @click="resolveConflict()">Resolve</button>
          </div>

          <div class="cg-dash__cols">
            <div class="cg-card cg-dash__panel">
              <h3 class="cg-dash__panel-title"><Icon name="car" /> Available Vehicles</h3>
              <p v-if="waitingError" class="cg-form-error">{{ waitingError }}</p>
              <ul v-else-if="dashVehicles.length" class="cg-res-list">
                <li v-for="v in dashVehicles" :key="v.id" class="cg-res">
                  <span class="cg-res__avatar cg-res__avatar--icon"><Icon name="car" /></span>
                  <div class="cg-res__info">
                    <p class="cg-res__name">{{ v.vehicle_model }} · {{ v.plate_number }}</p>
                    <p class="cg-res__sub">Capacity: {{ v.capacity ?? '—' }}</p>
                  </div>
                  <span :class="['cg-state', `cg-state--${v.state}`]" :title="dotLabel[v.state]" :aria-label="dotLabel[v.state]"></span>
                </li>
              </ul>
              <p v-else class="cg-muted">No vehicles found.</p>
            </div>

            <div class="cg-card cg-dash__panel">
              <h3 class="cg-dash__panel-title"><Icon name="user" /> Drivers</h3>
              <ul v-if="dashDrivers.length" class="cg-res-list">
                <li v-for="d in dashDrivers" :key="d.id" class="cg-res">
                  <span class="cg-res__avatar">{{ initials(d.name) }}</span>
                  <div class="cg-res__info">
                    <p class="cg-res__name">{{ d.name }}</p>
                    <p class="cg-res__sub">{{ d.contact_number || 'No contact number' }}{{ d.license_expiry_date ? ` · Lic. exp. ${d.license_expiry_date}` : '' }}</p>
                  </div>
                  <span :class="['cg-state', `cg-state--${d.state}`]" :title="dotLabel[d.state]" :aria-label="dotLabel[d.state]"></span>
                </li>
              </ul>
              <p v-else class="cg-muted">No drivers found.</p>
            </div>
          </div>
          <p class="cg-legend">
            <span><i class="cg-state cg-state--green"></i> Available</span>
            <span><i class="cg-state cg-state--orange"></i> Has scheduled trip</span>
            <span><i class="cg-state cg-state--red"></i> Conflict</span>
          </p>

          <div class="cg-card cg-summary">
            <h3 class="cg-summary__title">Allocation Summary</h3>
            <div class="cg-summary__counts" aria-label="Allocation summary totals">
              <p><strong>{{ inboxShares.length }}</strong> Received requests</p>
              <p><strong>{{ waiting.length }}</strong> Needs allocation</p>
              <p><strong>{{ allocations.length }}</strong> Allocations</p>
            </div>
            <div class="cg-summary__tabs" role="tablist" aria-label="Filter allocation summary by locality">
              <button
                v-for="group in [
                  { key: 'local', label: 'Local', items: summaryLocalityGroups.local },
                  { key: 'nonLocal', label: 'Non-local', items: summaryLocalityGroups.nonLocal },
                ]"
                :key="group.key"
                type="button"
                role="tab"
                :aria-selected="selectedSummaryLocality === group.key"
                :class="['cg-summary__tab', { 'cg-summary__tab--active': selectedSummaryLocality === group.key }]"
                @click="selectedSummaryLocality = group.key"
              >
                {{ group.label }} <span>{{ group.items.length }}</span>
              </button>
            </div>
            <div class="cg-table-wrap">
              <table class="cg-table">
                <thead>
                  <tr><th>Trip</th><th>Request type</th><th>Vehicle</th><th>Assigned Driver</th><th>Trip Date</th><th>Conflict</th><th>Action</th></tr>
                </thead>
                <tbody>
                  <tr v-if="listError"><td colspan="7" class="cg-error-text">{{ listError }}</td></tr>
                  <tr v-else-if="waitingError"><td colspan="7" class="cg-error-text">{{ waitingError }}</td></tr>
                  <tr v-else-if="inboxError"><td colspan="7" class="cg-error-text">{{ inboxError }}</td></tr>
                  <tr v-else-if="dashboardLoading"><td colspan="7" class="cg-empty">Loading allocation summary...</td></tr>
                  <tr v-else-if="!selectedSummaryRows.length"><td colspan="7" class="cg-empty">No {{ selectedSummaryLocality === 'local' ? 'local' : 'non-local' }} requests found.</td></tr>
                  <template v-for="row in selectedSummaryRows" :key="row.key">
                    <tr v-if="row.kind === 'allocation'">
                      <td>
                        <p class="cg-table__main">REQ-{{ String(row.allocation.vehicle_request?.id ?? row.allocation.id).padStart(3, '0') }} · {{ row.allocation.trip?.destination ?? '—' }}</p>
                        <p class="cg-table__sub">{{ row.allocation.vehicle_request?.requester ?? '—' }} · <StatusBadge :status="row.allocation.status === 'cancelled' ? 'disapproved' : row.allocation.status ?? 'unknown'" /></p>
                      </td>
                      <td>Allocation</td>
                      <td>{{ row.allocation.vehicle?.vehicle_model ?? '—' }} · {{ row.allocation.vehicle?.plate_number ?? '—' }}</td>
                      <td>{{ row.allocation.driver?.name ?? '—' }}</td>
                      <td>
                        {{ whenText(row.allocation.trip?.trip_date, row.allocation.trip?.departure_time) }}
                        <p v-if="pendingByAllocation[row.allocation.id]" class="cg-table__sub cg-table__sub--warn">Date change requested: {{ pendingByAllocation[row.allocation.id].new_date }}</p>
                      </td>
                      <td>
                        <span v-if="conflictMap[row.allocation.id]" class="cg-flag cg-flag--conflict">⚠ Conflict</span>
                        <span v-else class="cg-flag cg-flag--ok">✓ None</span>
                      </td>
                      <td>
                        <div class="cg-table__actions">
                          <button v-if="conflictMap[row.allocation.id]" type="button" class="cg-pill-btn cg-pill-btn--danger" @click="resolveConflict(row.allocation)">Resolve</button>
                          <button v-if="ACTIVE.includes(row.allocation.status)" type="button" class="cg-pill-btn" @click="openEdit(row.allocation)">Reassign</button>
                          <button v-if="row.allocation.status === 'scheduled'" type="button" class="cg-pill-btn" @click="rescheduleTarget = row.allocation">Change date</button>
                          <button v-if="row.allocation.status !== 'cancelled'" type="button" class="cg-pill-btn" @click="openPrint(row.allocation)">Print</button>
                          <button v-if="row.allocation.status !== 'cancelled'" type="button" class="cg-pill-btn" @click="openPrint(row.allocation, '', true)">Send to admin</button>
                        </div>
                      </td>
                    </tr>
                    <tr v-else-if="row.kind === 'waiting'">
                      <td>
                        <p class="cg-table__main">REQ-{{ String(row.request.id).padStart(3, '0') }} · {{ row.request.destination ?? '—' }}</p>
                        <p class="cg-table__sub">{{ row.request.requester ?? '—' }}</p>
                      </td>
                      <td>Needs allocation</td>
                      <td class="cg-table__none">Not yet assigned</td>
                      <td class="cg-table__none">Not yet assigned</td>
                      <td>{{ whenText(row.request.trip_date, row.request.departure_time) }}</td>
                      <td>—</td>
                      <td><div class="cg-table__actions"><button type="button" class="cg-pill-btn cg-pill-btn--primary" @click="openNew(row.request.id)">Assign</button></div></td>
                    </tr>
                    <tr v-else>
                      <td>
                        <p class="cg-table__main">{{ row.share.allocation.trip?.destination ?? 'Trip request' }}</p>
                        <p class="cg-table__sub">Sent by {{ row.share.sender.name }}</p>
                      </td>
                      <td>Received request</td>
                      <td>{{ row.share.allocation.vehicle?.vehicle_model ?? '—' }} · {{ row.share.allocation.vehicle?.plate_number ?? '—' }}</td>
                      <td>{{ row.share.allocation.driver?.name ?? '—' }}</td>
                      <td>{{ whenText(row.share.allocation.trip?.trip_date, row.share.allocation.trip?.departure_time) }}</td>
                      <td>
                        <span v-if="conflictMap[row.share.allocation.id]" class="cg-flag cg-flag--conflict">⚠ Conflict</span>
                        <span v-else class="cg-flag cg-flag--ok">✓ None</span>
                      </td>
                      <td>
                        <div class="cg-table__actions">
                          <button type="button" class="cg-pill-btn" @click="openPrint({ id: row.share.allocation.id })">View / Print</button>
                        </div>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <section v-if="!isAdmin" class="cg-dash cg-faculty-dashboard">
          <div class="cg-dash__head">
            <div>
              <p class="cg-dash__sub">Submit vehicle requests and track your trip status and allocations.</p>
            </div>
            <AppButton @click="scrollToVehicleRequest">+ Request vehicle</AppButton>
          </div>
          <div class="cg-stats cg-faculty-stats">
            <StatCard label="My requests" tone="neutral" :value="vehicleRequests.length" />
            <StatCard label="Awaiting approval" tone="orange" :value="pendingVehicleRequestCount" />
            <StatCard label="Scheduled" tone="green" :value="counts.scheduled" />
            <StatCard label="Ongoing" tone="orange" :value="counts.in_progress" />
          </div>
        </section>
        <p v-if="!isAdmin && vehicleRequestNotice" class="cg-alert">{{ vehicleRequestNotice }}</p>

        <section v-if="!isAdmin" id="create-vehicle-request" class="cg-section cg-card cg-dash__panel">
          <h3 class="cg-dash__panel-title"><Icon name="car" /> Create vehicle request</h3>
          <form class="cg-stack cg-faculty-request-form" @submit.prevent="submitVehicleRequest">
            <div class="cg-fields">
              <div class="cg-field">
                <label class="cg-label" for="vehicle-request-destination">Destination</label>
                <input id="vehicle-request-destination" v-model.trim="vehicleRequestForm.destination" class="cg-input" maxlength="255" required />
              </div>
              <div class="cg-field">
                <label class="cg-label" for="vehicle-request-date">Trip date</label>
                <input id="vehicle-request-date" v-model="vehicleRequestForm.trip_date" type="date" class="cg-input" :min="minimumTripDate" required />
              </div>
              <div class="cg-field">
                <label class="cg-label" for="vehicle-request-end-date">End of travel</label>
                <input id="vehicle-request-end-date" v-model="vehicleRequestForm.trip_end_date" type="date" class="cg-input" :min="vehicleRequestForm.trip_date || minimumTripDate" required />
              </div>
              <div class="cg-field">
                <label class="cg-label" for="vehicle-request-type">Trip type</label>
                <select id="vehicle-request-type" v-model="vehicleRequestForm.trip_type" class="cg-input" required>
                  <option value="inclusive">Inclusive (vehicle stays with the group)</option>
                  <option value="exclusive">Exclusive (vehicle returns between trips)</option>
                </select>
              </div>
              <div class="cg-field">
                <label class="cg-label" for="vehicle-request-departure">Departure time</label>
                <input id="vehicle-request-departure" v-model="vehicleRequestForm.departure_time" type="time" class="cg-input" required />
              </div>
              <div class="cg-field">
                <label class="cg-label" for="vehicle-request-return">Estimated return time</label>
                <input id="vehicle-request-return" v-model="vehicleRequestForm.estimated_return_time" type="time" class="cg-input" required />
              </div>
              <div class="cg-field">
                <label class="cg-label" for="vehicle-request-passengers">Number of passengers</label>
                <input id="vehicle-request-passengers" v-model.number="vehicleRequestForm.number_of_passengers" type="number" min="1" max="999" class="cg-input" required />
              </div>
              <div class="cg-field">
                <label class="cg-label" for="vehicle-request-passenger-details">Passenger details</label>
                <input id="vehicle-request-passenger-details" v-model.trim="vehicleRequestForm.passengers" class="cg-input" maxlength="255" required />
              </div>
              <div class="cg-field cg-field--full">
                <label class="cg-label" for="vehicle-request-purpose">Purpose</label>
                <textarea id="vehicle-request-purpose" v-model.trim="vehicleRequestForm.purpose" class="cg-input" rows="3" maxlength="255" required></textarea>
              </div>
            </div>
            <p v-if="vehicleRequestsError" class="cg-form-error">{{ vehicleRequestsError }}</p>
            <div><AppButton type="submit" :disabled="vehicleRequestSaving">{{ vehicleRequestSaving ? 'Submitting...' : 'Submit vehicle request' }}</AppButton></div>
          </form>
        </section>

        <section v-if="!isAdmin" id="vehicle-requests" class="cg-section cg-card cg-dash__panel">
          <h3 class="cg-dash__panel-title"><Icon name="clock" /> My vehicle requests</h3>
          <div class="cg-faculty-panel-content">
            <p v-if="vehicleRequestsError" class="cg-form-error">{{ vehicleRequestsError }}</p>
            <p v-else-if="vehicleRequestsLoading" class="cg-muted">Loading vehicle requests...</p>
            <div v-if="!vehicleRequestsError && !vehicleRequestsLoading" class="cg-request-type-filters" role="tablist" aria-label="Filter my vehicle requests by trip type">
              <button
                v-for="filter in [
                  { key: '', label: 'All', count: vehicleRequestTypeCounts.all },
                  { key: 'inclusive', label: 'Inclusive', count: vehicleRequestTypeCounts.inclusive },
                  { key: 'exclusive', label: 'Exclusive', count: vehicleRequestTypeCounts.exclusive },
                ]"
                :key="filter.key || 'all'"
                type="button"
                role="tab"
                :aria-selected="vehicleRequestTypeFilter === filter.key"
                :class="['cg-request-type-filter', { 'cg-request-type-filter--active': vehicleRequestTypeFilter === filter.key }]"
                @click="vehicleRequestTypeFilter = filter.key"
              >
                {{ filter.label }} <span>{{ filter.count }}</span>
              </button>
            </div>
            <div v-if="!vehicleRequestsError && !vehicleRequestsLoading" class="cg-summary__tabs cg-faculty-locality-tabs" role="tablist" aria-label="Filter my vehicle requests by locality">
              <button
                v-for="group in [
                  { key: 'local', label: 'Local', items: facultyVehicleRequestLocalityGroups.local },
                  { key: 'nonLocal', label: 'Non-local', items: facultyVehicleRequestLocalityGroups.nonLocal },
                ]"
                :key="group.key"
                type="button"
                role="tab"
                :aria-selected="selectedFacultyLocality === group.key"
                :class="['cg-summary__tab', { 'cg-summary__tab--active': selectedFacultyLocality === group.key }]"
                @click="selectedFacultyLocality = group.key"
              >
                {{ group.label }} <span>{{ group.items.length }}</span>
              </button>
            </div>
            <div v-if="!vehicleRequestsError && !vehicleRequestsLoading && facultyVehicleRequestLocalityGroups[selectedFacultyLocality].length" class="cg-grid">
              <article v-for="item in facultyVehicleRequestLocalityGroups[selectedFacultyLocality]" :key="item.id" class="cg-card cg-request-compact">
                <div class="cg-request-compact__summary">
                  <div class="cg-request-compact__main">
                    <p class="cg-card__title">{{ item.destination }}</p>
                    <p class="cg-muted">{{ item.trip_date }}{{ item.trip_end_date && item.trip_end_date !== item.trip_date ? ` – ${item.trip_end_date}` : '' }} · {{ item.trip_type }} · <StatusBadge :status="item.status" /></p>
                  </div>
                  <div class="cg-request-compact__controls">
                    <button
                      type="button"
                      class="cg-collapse-btn"
                      :aria-expanded="expandedVehicleRequestIds.has(item.id)"
                      :aria-controls="`vehicle-request-details-${item.id}`"
                      @click="toggleVehicleRequestDetails(item.id)"
                    >
                      {{ expandedVehicleRequestIds.has(item.id) ? 'Less' : 'Details' }}
                      <Icon name="chevron" />
                    </button>
                  </div>
                </div>
                <div v-if="expandedVehicleRequestIds.has(item.id)" :id="`vehicle-request-details-${item.id}`" class="cg-request-compact__details">
                  <p class="cg-muted">{{ item.purpose }}</p>
                  <p class="cg-meta"><Icon name="clock" /> {{ item.departure_time }} → {{ item.estimated_return_time }}</p>
                  <p class="cg-meta"><Icon name="users" /> {{ item.number_of_passengers }} passengers · {{ item.passengers }}</p>
                  <p v-if="item.status === 'disapproved' && item.disapproval_reason" class="cg-alert">
                    Disapproval reason: {{ item.disapproval_reason }}
                  </p>
                </div>
              </article>
            </div>
            <p v-if="!vehicleRequestsError && !vehicleRequestsLoading && !filteredVehicleRequests.length" class="cg-empty">
              {{ vehicleRequestTypeFilter ? `No ${vehicleRequestTypeFilter} vehicle requests found.` : 'You have not submitted a vehicle request yet.' }}
            </p>
            <p v-else-if="!vehicleRequestsError && !vehicleRequestsLoading && !facultyVehicleRequestLocalityGroups[selectedFacultyLocality].length" class="cg-empty">
              No {{ selectedFacultyLocality === 'local' ? 'local' : 'non-local' }} vehicle requests found.
            </p>
          </div>
        </section>

        <div v-if="isAdmin" class="cg-stats">
          <StatCard label="Scheduled" tone="neutral" :value="counts.scheduled" />
          <StatCard label="Ongoing" tone="orange" :value="counts.in_progress" />
          <StatCard label="Done" tone="green" :value="counts.completed" />
          <StatCard label="Disapproved" tone="red" :value="disapprovedVehicleRequestCount" />
        </div>

        <section v-if="isAdmin && (pendingRequests.length || dashboardLoading || changeRequestsError)" id="date-changes" class="cg-section">
          <div class="cg-section-head">
            <h2 class="cg-section-title">Date change requests ({{ pendingRequests.length }})</h2>
          </div>
          <p v-if="changeRequestsError" class="cg-form-error">{{ changeRequestsError }}</p>
          <p v-else-if="dashboardLoading" class="cg-muted">Loading date change requests...</p>
          <div v-else-if="pendingRequests.length" class="cg-grid">
            <RescheduleRequestCard v-for="r in pendingRequests" :key="r.id" :item="r" is-admin @approve="approveChange" />
          </div>
        </section>

        <section v-if="!isAdmin && changeRequests.length" class="cg-section cg-card cg-dash__panel">
          <h3 class="cg-dash__panel-title"><Icon name="calendar" /> My date change requests</h3>
          <div class="cg-faculty-panel-content">
            <div class="cg-grid">
              <RescheduleRequestCard v-for="r in changeRequests" :key="r.id" :item="r" />
            </div>
          </div>
        </section>

        <section v-if="!isAdmin" class="cg-section cg-card cg-dash__panel">
          <h3 class="cg-dash__panel-title"><Icon name="car" /> My allocations ({{ filtered.length }})</h3>
          <div class="cg-faculty-panel-content">
            <p v-if="listError" class="cg-error-text">{{ listError }}</p>
            <p v-else-if="dashboardLoading" class="cg-muted">Loading allocations...</p>
            <div v-if="!listError && !dashboardLoading" class="cg-summary__tabs cg-faculty-locality-tabs" role="tablist" aria-label="Filter my allocations by locality">
              <button
                v-for="group in [
                  { key: 'local', label: 'Local', items: facultyAllocationLocalityGroups.local },
                  { key: 'nonLocal', label: 'Non-local', items: facultyAllocationLocalityGroups.nonLocal },
                ]"
                :key="group.key"
                type="button"
                role="tab"
                :aria-selected="selectedFacultyLocality === group.key"
                :class="['cg-summary__tab', { 'cg-summary__tab--active': selectedFacultyLocality === group.key }]"
                @click="selectedFacultyLocality = group.key"
              >
                {{ group.label }} <span>{{ group.items.length }}</span>
              </button>
            </div>
            <template v-if="!listError && !dashboardLoading && facultyAllocationLocalityGroups[selectedFacultyLocality].length">
              <div class="cg-grid">
                <AllocationCard
                  v-for="a in facultyAllocationLocalityGroups[selectedFacultyLocality]"
                  :key="a.id"
                  :allocation="a"
                  :is-admin="isAdmin"
                  :pending-change="pendingByAllocation[a.id] ?? null"
                  @reschedule="rescheduleTarget = $event"
                  @reassign="openEdit"
                  @print="openPrint"
                  @share="(allocation) => openPrint(allocation, '', true)"
                />
              </div>
            </template>
            <p v-if="!listError && !dashboardLoading && !filtered.length" class="cg-empty">No allocations found.</p>
            <p v-else-if="!listError && !dashboardLoading && !facultyAllocationLocalityGroups[selectedFacultyLocality].length" class="cg-empty">
              No {{ selectedFacultyLocality === 'local' ? 'local' : 'non-local' }} allocations found.
            </p>
          </div>
        </section>
      </main>

      <AccountChip />
      <AllocationModal
        :open="modalOpen"
        :allocation="editing"
        :preset-request-id="presetRequestId"
        @close="modalOpen = false"
        @saved="onSaved"
      />
      <PrintableTripRequest
        :open="printOpen"
        :open-share-form="printShareOpen"
        :loading="printLoading"
        :error="printError"
        :data="printData"
        :notice="printNotice"
        :admins="adminRecipients"
        :sending="printSending"
        :send-error="printSendError"
        @close="printOpen = false"
        @send="sendTripRequest"
      />
      <RescheduleModal
        :allocation="rescheduleTarget"
        :is-admin="isAdmin"
        @close="rescheduleTarget = null"
        @saved="onRescheduleSaved"
        @conflict="onRescheduleConflict"
      />
      <div v-if="disapprovingVehicleRequest" class="cg-overlay">
        <form class="cg-dialog cg-dialog--sm" role="dialog" aria-modal="true" aria-labelledby="vehicle-disapproval-title" @submit.prevent="disapproveVehicleRequest">
          <h2 id="vehicle-disapproval-title" class="cg-section-title">Disapprove vehicle request</h2>
          <p class="cg-muted">REQ-{{ String(disapprovingVehicleRequest.id).padStart(3, '0') }} · {{ disapprovingVehicleRequest.destination }}</p>
          <div>
            <label class="cg-label" for="vehicle-disapproval-reason">Reason for disapproval</label>
            <textarea id="vehicle-disapproval-reason" v-model="vehicleDisapprovalReason" class="cg-input" rows="4" maxlength="500" required></textarea>
          </div>
          <p v-if="vehicleDecisionError" class="cg-form-error">{{ vehicleDecisionError }}</p>
          <div class="cg-actions">
            <AppButton variant="secondary" :disabled="approvingVehicleRequestId === disapprovingVehicleRequest.id" @click="disapprovingVehicleRequest = null">Back</AppButton>
            <AppButton type="submit" :disabled="approvingVehicleRequestId === disapprovingVehicleRequest.id">
              {{ approvingVehicleRequestId === disapprovingVehicleRequest.id ? 'Disapproving...' : 'Confirm disapproval' }}
            </AppButton>
          </div>
        </form>
      </div>
      <ConflictModal :conflict="pageConflict" hint="Please choose another date." @close="pageConflict = null" />
      <p v-if="toast" class="cg-toast" role="status">{{ toast }}</p>
    </template>
  </div>
</template>
