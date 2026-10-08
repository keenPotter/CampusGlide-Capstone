<template>
  <div class="maint">
    <!-- ============ ADMINISTRATOR ============ -->
    <main class="page">
      <div class="page-heading">
        <div class="heading-icon">
          <svg viewBox="0 0 24 24">
            <path
              d="M14.7 6.3a5.5 5.5 0 0 0-7.4 7.4L3.8 17.2a2.1 2.1 0 1 0 3 3l3.5-3.5a5.5 5.5 0 0 0 7.4-7.4l-3.3 3.3-3-3 3.3-3.3z" />
          </svg>
        </div>
        <div>
          <h1>Vehicle Maintenance</h1>
          <p>Track and manage vehicle maintenance records</p>
        </div>
        <button v-if="isAdmin" class="add-button heading-action" @click="openAdd">
          <span>+</span> Add Maintenance
        </button>
      </div>

      <section class="stats">
        <div v-for="s in stats" :key="s.label" class="stat">
          <div class="stat-top">
            <span class="stat-icon" :class="s.tone">
              <svg viewBox="0 0 24 24">
                <path :d="s.path" />
              </svg>
            </span>
            <span class="stat-arrow">›</span>
          </div>
          <strong>{{ s.value }}</strong>
          <span class="stat-label">{{ s.label }}</span>
        </div>
      </section>

      <section class="control-card">
        <div class="toolbar">
          <label class="search">
            <svg viewBox="0 0 24 24">
              <path d="M21 21l-4.3-4.3M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z" />
            </svg>
            <input v-model="search" type="search" placeholder="Search maintenance records..."
              aria-label="Search maintenance" />
          </label>
          <button class="filter-button" :aria-expanded="showFilters" @click="showFilters = !showFilters">
            <svg viewBox="0 0 24 24">
              <path d="M22 3H2l8 9.5V19l4 2v-8.5L22 3z" />
            </svg>
            Filter
            <span class="filter-chevron">⌄</span>
          </button>
        </div>

        <div v-if="showFilters" class="filters">
          <select v-model="fStatus" aria-label="Status">
            <option value="">All status</option>
            <option v-for="(label, key) in STATUS_LABELS" :key="key" :value="key">{{ label }}</option>
          </select>
          <select v-model="fVehicle" aria-label="Vehicle">
            <option value="">All Vehicles</option>
            <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.plate_number }}</option>
          </select>
          <select v-model="fType" aria-label="Type">
            <option value="">All Types</option>
            <option v-for="[value, label] in TYPES" :key="value" :value="value">{{ label }}</option>
          </select>
          <select v-model="fTime" aria-label="Time">
            <option value="">All Time</option>
            <option value="month">This month</option>
            <option value="30">Last 30 days</option>
            <option value="year">This year</option>
          </select>
        </div>
      </section>

      <div v-if="loadError" class="load-error">
        <p>{{ loadError }}</p>
        <button @click="loadAll">Try again</button>
      </div>

      <section class="records-card">
        <div class="records-header">
          <div>
            <h2>Maintenance records</h2>
            <p>Recent maintenance activity for the motorpool fleet</p>
          </div>
        </div>

        <p v-if="loading" class="empty">Loading maintenance records…</p>
        <p v-else-if="!filtered.length" class="empty">
          <span class="empty-icon">
            <svg viewBox="0 0 24 24">
              <path
                d="M14.7 6.3a5.5 5.5 0 0 0-7.4 7.4L3.8 17.2a2.1 2.1 0 1 0 3 3l3.5-3.5a5.5 5.5 0 0 0 7.4-7.4l-3.3 3.3-3-3 3.3-3.3z" />
            </svg>
          </span>
          <strong>No maintenance records found</strong>
          <span>Get started by adding your first maintenance record.</span>
          <button v-if="isAdmin" class="empty-action" @click="openAdd">+ Add Maintenance</button>
        </p>

        <div v-else class="list">
          <article v-for="log in pageItems" :key="log.id" class="m-card">
            <div class="m-top">
              <div class="m-title">
                <strong>{{ log.vehicle.plate_number }}</strong>
                <span> · {{ log.vehicle.model || 'Vehicle' }}</span>
                <p>{{ log.description || typeLabel(log.type) }}</p>
              </div>
              <div class="m-side">
                <span class="status" :class="log.display">{{ STATUS_LABELS[log.display] }}</span>
                <div v-if="isAdmin" class="kebab-wrap">
                  <button class="kebab" aria-label="More actions" @click.stop="toggleMenu(log.id)">⋮</button>
                  <div v-if="openMenuId === log.id" class="kebab-menu" @click.stop>
                    <button @click="openEdit(log)">Edit</button>
                    <button v-if="log.status !== 'completed'" @click="markCompleted(log)">Mark as completed</button>
                  </div>
                </div>
              </div>
            </div>
            <dl class="m-meta">
              <div>
                <dt>Date</dt>
                <dd>{{ formatDate(log.date_performed) }}</dd>
              </div>
              <div>
                <dt>Odometer</dt>
                <dd>{{ formatKm(log.vehicle.mileage) }}</dd>
              </div>
              <div>
                <dt>Cost</dt>
                <dd>{{ formatMoney(log.cost) }}</dd>
              </div>
            </dl>
          </article>
        </div>
      </section>

      <nav v-if="pageCount > 1" class="pager" aria-label="Pages">
        <button aria-label="Previous page" :disabled="page === 1" @click="page--">‹</button>
        <button v-for="n in visiblePages" :key="n" :class="{ active: n === page }"
          :aria-current="n === page ? 'page' : undefined" @click="page = n">{{ n }}</button>
        <button aria-label="Next page" :disabled="page === pageCount" @click="page++">›</button>
      </nav>
    </main>

    <!-- Add / edit modal -->
    <div v-if="showForm" class="modal-overlay" @click.self="showForm = false">
      <div class="modal-card">
        <div class="sheet-handle"></div>
        <div class="modal-header">
          <div>
            <span class="small-label">Maintenance record</span>
            <h2>{{ editingId ? 'Edit maintenance' : 'Add maintenance' }}</h2>
          </div>
          <button class="icon-button" aria-label="Close" @click="showForm = false">×</button>
        </div>

        <p class="modal-note">The odometer on each card is the vehicle's current mileage from its vehicle record.</p>

        <div class="form-group">
          <label class="form-label">Vehicle
            <select v-model="form.vehicle_id">
              <option value="" disabled>Select vehicle</option>
              <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.plate_number }} — {{ v.vehicle_model }}
              </option>
            </select>
          </label>
          <label class="form-label">Type
            <select v-model="form.type">
              <option v-for="[value, label] in TYPES" :key="value" :value="value">{{ label }}</option>
            </select>
          </label>
          <label class="form-label">Description
            <textarea v-model="form.description" maxlength="500" rows="3"
              placeholder="What work is being done?"></textarea>
          </label>
          <div class="form-row">
            <label class="form-label">Date <input v-model="form.date_performed" type="date" /></label>
            <label class="form-label">Next due <input v-model="form.next_due_date" type="date"
                :min="form.date_performed" /></label>
          </div>
          <div class="form-row">
            <label class="form-label">Cost (₱) <input v-model="form.cost" type="number" min="0" step="0.01"
                placeholder="0.00" /></label>
            <label class="form-label">Status
              <select v-model="form.status">
                <option value="scheduled">Scheduled</option>
                <option value="in_progress">In progress</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </label>
          </div>
        </div>

        <p v-if="formError" class="error-message">{{ formError }}</p>
        <button class="primary-button" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save maintenance'
          }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import api, { errorMessage, validationErrors } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'

const auth = useAuthStore()
const notifications = useNotificationStore()
const PAGE_SIZE = 5

const TYPES = [
  ['oil_change', 'Oil change'],
  ['repair', 'Repair'],
  ['refueling', 'Refueling'],
  ['inspection', 'Inspection'],
  ['tire_service', 'Tire service'],
  ['other', 'Other']
]
const STATUS_LABELS = {
  completed: 'Completed',
  upcoming: 'Upcoming',
  overdue: 'Overdue',
  in_progress: 'In progress',
  cancelled: 'Cancelled'
}
const ICONS = {
  wrench: 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94z',
  calendar: 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z',
  clock: 'M12 6v6l4 2M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z',
  alert: 'M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01'
}

/* ================= STATE ================= */

const isAdmin = computed(() => auth.isAdministrator)

const logs = ref([])
const vehicles = ref([])
const loading = ref(true)
const loadError = ref('')

const search = ref('')
const showFilters = ref(true)
const fStatus = ref('')
const fVehicle = ref('')
const fType = ref('')
const fTime = ref('')
const page = ref(1)
const openMenuId = ref(null)

const showForm = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formError = ref('')
const form = ref(emptyForm())

const today = toDateString(new Date())

/* ================= API ================= */

async function request(path, options = {}) {
  try {
    const response = await api.request({
      url: path,
      method: options.method ?? 'get',
      data: options.data
    })
    return response.data
  } catch (error) {
    const details = Object.values(validationErrors(error)).join(' ')
    throw new Error(details || errorMessage(error, 'Request failed.'))
  }
}

async function loadAll() {
  loading.value = true
  loadError.value = ''
  try {
    const [logData, vehicleData] = await Promise.all([
      request('maintenance-logs'),
      request('vehicles')
    ])
    logs.value = logData.data
    vehicles.value = Array.isArray(vehicleData) ? vehicleData : vehicleData.data
  } catch (error) {
    console.error(error)
    loadError.value = `Unable to load maintenance records. ${error.message}`
  } finally {
    loading.value = false
    notifications.refresh()
  }
}

function onDocumentClick() {
  openMenuId.value = null
}

onMounted(() => {
  loadAll()
  document.addEventListener('click', onDocumentClick)
})
onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick)
})

/* ================= DERIVED DATA ================= */

function displayStatus(log) {
  if (log.status === 'cancelled') return 'cancelled'
  if (log.status === 'in_progress') return 'in_progress'

  // Schedule status is based on the backend's next_due_date.
  if (log.next_due_date) {
    if (log.next_due_date < today) return 'overdue'
    return 'upcoming'
  }

  return log.status === 'completed' ? 'completed' : 'upcoming'
}

const rows = computed(() => logs.value.map(log => ({ ...log, display: displayStatus(log) })))

const stats = computed(() => [
  { label: 'Total Maintenance', value: rows.value.length, path: ICONS.wrench, tone: 'wrench' },
  {
    label: 'This Month',
    value: rows.value.filter(l => (l.date_performed || '').startsWith(today.slice(0, 7))).length,
    path: ICONS.calendar, tone: 'calendar'
  },
  { label: 'Upcoming', value: rows.value.filter(l => l.display === 'upcoming').length, path: ICONS.clock, tone: 'clock' },
  { label: 'Urgent', value: rows.value.filter(l => l.display === 'overdue').length, path: ICONS.alert, tone: 'alert' }
])

function inTimeRange(date) {
  if (fTime.value === 'month') return date.startsWith(today.slice(0, 7))
  if (fTime.value === 'year') return date.startsWith(today.slice(0, 4))
  if (fTime.value === '30') {
    const start = new Date()
    start.setDate(start.getDate() - 30)
    return date >= toDateString(start) && date <= today
  }
  return true
}

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return rows.value.filter(log => {
    if (fStatus.value && log.display !== fStatus.value) return false
    if (fVehicle.value && String(log.vehicle.id) !== String(fVehicle.value)) return false
    if (fType.value && log.type !== fType.value) return false
    if (fTime.value && !inTimeRange(log.date_performed || '')) return false
    if (q) {
      const text = [log.vehicle.plate_number, log.vehicle.model, log.description, typeLabel(log.type)]
        .join(' ')
        .toLowerCase()
      if (!text.includes(q)) return false
    }
    return true
  })
})

const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / PAGE_SIZE)))
const pageItems = computed(() => filtered.value.slice((page.value - 1) * PAGE_SIZE, page.value * PAGE_SIZE))
const visiblePages = computed(() => {
  const start = Math.max(1, Math.min(page.value - 2, pageCount.value - 4))
  const end = Math.min(pageCount.value, start + 4)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

watch([search, fStatus, fVehicle, fType, fTime], () => { page.value = 1 })

/* ================= ACTIONS ================= */

function emptyForm() {
  return {
    vehicle_id: '',
    type: 'oil_change',
    description: '',
    date_performed: toDateString(new Date()),
    next_due_date: '',
    cost: '',
    status: 'scheduled'
  }
}

function toggleMenu(id) {
  openMenuId.value = openMenuId.value === id ? null : id
}

function openAdd() {
  editingId.value = null
  form.value = emptyForm()
  formError.value = ''
  showForm.value = true
}

function openEdit(log) {
  openMenuId.value = null
  editingId.value = log.id
  form.value = {
    vehicle_id: log.vehicle.id,
    type: log.type,
    description: log.description || '',
    date_performed: log.date_performed,
    next_due_date: log.next_due_date || '',
    cost: log.cost ?? '',
    status: log.status || 'scheduled'
  }
  formError.value = ''
  showForm.value = true
}

async function save() {
  formError.value = ''
  const f = form.value

  if (!f.vehicle_id || !f.date_performed) {
    formError.value = 'Please select a vehicle and a date.'
    return
  }
  if (f.next_due_date && f.next_due_date < f.date_performed) {
    formError.value = 'The next due date cannot be before the maintenance date.'
    return
  }

  saving.value = true
  try {
    await request(editingId.value ? `maintenance-logs/${editingId.value}` : 'maintenance-logs', {
      method: editingId.value ? 'patch' : 'post',
      data: {
        vehicle_id: Number(f.vehicle_id),
        type: f.type,
        description: f.description || null,
        date_performed: f.date_performed,
        next_due_date: f.next_due_date || null,
        cost: f.cost === '' ? null : Number(f.cost),
        status: f.status
      }
    })
    showForm.value = false
    await loadAll()
  } catch (error) {
    formError.value = error.message
  } finally {
    saving.value = false
  }
}

async function markCompleted(log) {
  openMenuId.value = null
  try {
    await request(`maintenance-logs/${log.id}`, {
      method: 'patch',
      data: { status: 'completed' }
    })
    await loadAll()
  } catch (error) {
    loadError.value = error.message
  }
}

/* ================= HELPERS ================= */

function toDateString(date) {
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${m}-${d}`
}

function typeLabel(type) {
  return TYPES.find(([value]) => value === type)?.[1] ?? type
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(`${value}T00:00:00`).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  })
}

function formatKm(value) {
  return value === null || value === undefined ? '—' : `${Number(value).toLocaleString('en-US')} km`
}

const money = new Intl.NumberFormat('en-PH', {
  style: 'currency',
  currency: 'PHP',
  minimumFractionDigits: 0,
  maximumFractionDigits: 2
})

function formatMoney(value) {
  return value === null || value === undefined ? '—' : money.format(value)
}
</script>

<style scoped>
.maint {
  --primary: #27af30;
  --primary-dark: #1f8c26;
  --primary-50: #f0fbf1;
  --primary-100: #d9f4dc;
  --secondary: #ffa500;
  --ink: #1f2933;
  --muted: #6b7280;
  color: var(--ink);
}

button,
input,
select,
textarea {
  font: inherit;
}

button {
  cursor: pointer;
}

svg {
  width: 22px;
  height: 22px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

/* Page */
.page {
  max-width: 1500px;
  margin: 0 auto;
  padding-bottom: 40px;
}

.page-heading {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 28px;
}

.heading-icon {
  width: 58px;
  height: 58px;
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  border-radius: 14px;
  background: var(--primary-100);
  color: var(--primary-dark);
}

.heading-icon svg {
  width: 30px;
  height: 30px;
}

.page-heading h1 {
  margin: 0;
  font-size: 28px;
  line-height: 1.2;
  font-weight: 700;
  color: #182333;
}

.page-heading p {
  margin: 5px 0 0;
  color: var(--muted);
  font-size: 15px;
}

.heading-action {
  margin-left: auto;
}

/* Stats */
.stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.stat {
  min-height: 145px;
  padding: 17px 18px;
  background: #fff;
  border: 1px solid #e1e7ed;
  border-radius: 12px;
}

.stat-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.stat-icon {
  width: 40px;
  height: 40px;
  display: grid;
  place-items: center;
  border-radius: 11px;
  background: var(--primary-100);
  color: var(--primary-dark);
}

.stat-icon.calendar {
  background: #e7f8ef;
  color: #15905c;
}

.stat-icon.clock {
  background: #f0eafe;
  color: #7652d5;
}

.stat-icon.alert {
  background: #ffebeb;
  color: #d62f2f;
}

.stat-arrow {
  color: #8793a1;
  font-size: 26px;
  line-height: 1;
}

.stat strong {
  display: block;
  margin-top: 12px;
  font-size: 28px;
  line-height: 1;
  color: #17263b;
}

.stat-label {
  display: block;
  margin-top: 8px;
  color: #45546a;
  font-size: 14px;
}

/* Buttons */
.add-button {
  height: 44px;
  padding: 0 18px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  border: 0;
  border-radius: 9px;
  background: var(--primary);
  color: #fff;
  font-weight: 700;
}

.add-button:hover {
  background: var(--primary-dark);
}

.add-button span {
  font-size: 22px;
  line-height: 1;
}

.primary-button {
  width: 100%;
  height: 46px;
  margin-top: 18px;
  border: 0;
  border-radius: 9px;
  background: var(--primary);
  color: #fff;
  font-weight: 700;
}

.primary-button:hover:not(:disabled) {
  background: var(--primary-dark);
}

.primary-button:disabled {
  opacity: .55;
  cursor: not-allowed;
}

/* Search / filter */
.control-card {
  padding: 16px;
  background: #fff;
  border: 1px solid #e1e7ed;
  border-radius: 12px;
  margin-bottom: 18px;
}

.toolbar {
  display: flex;
  gap: 12px;
}

.search {
  flex: 1;
  min-width: 0;
  height: 44px;
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 0 13px;
  border: 1px solid #e1e7ed;
  border-radius: 9px;
  background: #fbfcfd;
  color: #7b8795;
}

.search svg {
  width: 20px;
  height: 20px;
  flex: 0 0 auto;
}

.search input {
  width: 100%;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--ink);
  font-size: 14px;
}

.filter-button {
  height: 44px;
  min-width: 112px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 0 14px;
  border: 1px solid #dce3e9;
  border-radius: 9px;
  background: #fff;
  color: #34445a;
  font-weight: 600;
}

.filter-button:hover {
  background: #f7f9fa;
}

.filter-button svg {
  width: 19px;
  height: 19px;
}

.filter-chevron {
  margin-left: 3px;
  color: #7a8795;
}

.filters {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #eef1f4;
}

.filters select {
  width: 100%;
  height: 42px;
  padding: 0 12px;
  border: 1px solid #dce3e9;
  border-radius: 9px;
  background: #fff;
  color: #3b4a5f;
  font-size: 14px;
  outline: 0;
}

.filters select:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(39, 175, 48, .12);
}

/* Records */
.records-card {
  background: #fff;
  border: 1px solid #e1e7ed;
  border-radius: 12px;
  overflow: hidden;
}

.records-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px;
  border-bottom: 1px solid #eef1f4;
}

.records-header h2 {
  margin: 0;
  font-size: 18px;
  color: #1b2a3e;
}

.records-header p {
  margin: 3px 0 0;
  color: var(--muted);
  font-size: 13px;
}

.list {
  display: flex;
  flex-direction: column;
}

.m-card {
  padding: 18px 20px;
  border-bottom: 1px solid #eef1f4;
}

.m-card:last-child {
  border-bottom: 0;
}

.m-top {
  display: flex;
  justify-content: space-between;
  gap: 16px;
}

.m-title {
  min-width: 0;
}

.m-title strong {
  color: #1c2c42;
  font-size: 15px;
}

.m-title>span {
  color: #667487;
  font-size: 14px;
}

.m-title p {
  margin: 5px 0 0;
  color: #667487;
  font-size: 13px;
}

.m-side {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.status {
  display: inline-flex;
  align-items: center;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}

.status.completed {
  background: var(--primary-50);
  color: var(--primary-dark);
}

.status.upcoming {
  background: #fff6df;
  color: #9a6500;
}

.status.overdue {
  background: #fff0f0;
  color: #b3261e;
}

.status.in_progress {
  background: #edf5ff;
  color: #155a9c;
}

.status.cancelled {
  background: #f0f2f4;
  color: #687482;
}

.kebab-wrap {
  position: relative;
}

.kebab {
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #667487;
  font-size: 22px;
  line-height: 1;
}

.kebab:hover {
  background: #f3f5f7;
}

.kebab-menu {
  position: absolute;
  right: 0;
  top: 36px;
  z-index: 10;
  min-width: 180px;
  padding: 5px;
  background: #fff;
  border: 1px solid #dfe5ea;
  border-radius: 9px;
  box-shadow: 0 10px 25px rgba(31, 41, 51, .12);
}

.kebab-menu button {
  width: 100%;
  padding: 9px 11px;
  border: 0;
  border-radius: 7px;
  background: transparent;
  text-align: left;
  color: #34445a;
  font-size: 13px;
}

.kebab-menu button:hover {
  background: #f4f6f8;
}

.m-meta {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
  margin: 16px 0 0;
  padding-top: 14px;
  border-top: 1px solid #f0f2f4;
}

.m-meta dt {
  color: #8a96a3;
  font-size: 11px;
}

.m-meta dd {
  margin: 3px 0 0;
  color: #34445a;
  font-size: 13px;
  font-weight: 600;
}

.empty {
  min-height: 300px;
  margin: 0;
  padding: 48px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: var(--muted);
  font-size: 14px;
}

.empty-icon {
  width: 60px;
  height: 60px;
  display: grid;
  place-items: center;
  margin-bottom: 13px;
  border-radius: 50%;
  background: var(--primary-50);
  color: var(--primary-dark);
}

.empty-icon svg {
  width: 29px;
  height: 29px;
}

.empty strong {
  color: #23334a;
  font-size: 15px;
}

.empty>span:not(.empty-icon) {
  margin-top: 4px;
}

.empty-action {
  margin-top: 20px;
  height: 42px;
  padding: 0 18px;
  border: 0;
  border-radius: 9px;
  background: var(--primary);
  color: #fff;
  font-size: 14px;
  font-weight: 700;
}

.pager {
  display: flex;
  justify-content: center;
  gap: 7px;
  margin-top: 20px;
}

.pager button {
  min-width: 38px;
  height: 38px;
  border: 1px solid #dce3e9;
  border-radius: 8px;
  background: #fff;
  color: #44536a;
  font-weight: 600;
}

.pager button.active {
  border-color: var(--primary);
  background: var(--primary);
  color: #fff;
}

.pager button:disabled {
  opacity: .45;
  cursor: not-allowed;
}

.load-error {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  margin: 0 0 18px;
  padding: 14px 16px;
  background: #fff4f4;
  border: 1px solid #f1c2c2;
  border-radius: 10px;
  color: #9d2727;
}

.load-error p {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
}

.load-error button {
  height: 36px;
  padding: 0 14px;
  border: 1px solid #c33a3a;
  border-radius: 8px;
  background: #fff;
  color: #9d2727;
  font-weight: 700;
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(20, 28, 38, .48);
}

.modal-card {
  width: min(500px, 100%);
  max-height: 90vh;
  overflow-y: auto;
  padding: 24px;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 24px 60px rgba(20, 30, 40, .2);
}

.sheet-handle {
  display: none;
}

.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.small-label {
  color: var(--muted);
  font-size: 12px;
  font-weight: 600;
}

.modal-header h2 {
  margin: 3px 0 0;
  color: #1b2a3e;
  font-size: 21px;
}

.icon-button {
  width: 38px;
  height: 38px;
  border: 0;
  border-radius: 8px;
  background: #f3f5f7;
  color: #526174;
  font-size: 23px;
  line-height: 1;
}

.modal-note {
  margin: 16px 0 18px;
  padding: 11px 12px;
  border-left: 3px solid var(--secondary);
  border-radius: 8px;
  background: #fff8eb;
  color: #725319;
  font-size: 12px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 13px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.form-label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  color: #44536a;
  font-size: 12px;
  font-weight: 600;
}

.form-label input,
.form-label select,
.form-label textarea {
  width: 100%;
  min-height: 42px;
  padding: 9px 11px;
  border: 1px solid #dce3e9;
  border-radius: 8px;
  outline: 0;
  background: #fff;
  color: #26364a;
  font-size: 14px;
  font-weight: 400;
}

.form-label textarea {
  min-height: 88px;
  resize: vertical;
}

.form-label input:focus,
.form-label select:focus,
.form-label textarea:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(39, 175, 48, .12);
}

.error-message {
  margin: 12px 0 0;
  padding: 10px 12px;
  border-radius: 8px;
  background: #fff0f0;
  color: #b3261e;
  font-size: 12px;
  font-weight: 600;
}

@media (max-width: 767px) {
  .page-heading {
    align-items: flex-start;
    margin-bottom: 20px;
  }

  .heading-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
  }

  .heading-icon svg {
    width: 24px;
    height: 24px;
  }

  .page-heading h1 {
    font-size: 23px;
  }

  .page-heading p {
    font-size: 13px;
  }

  .heading-action {
    display: none;
  }

  .stats {
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }

  .stat {
    min-height: 126px;
    padding: 13px;
  }

  .stat strong {
    font-size: 23px;
  }

  .stat-label {
    font-size: 12px;
  }

  .control-card {
    padding: 12px;
  }

  .toolbar {
    flex-direction: column;
  }

  .filter-button {
    width: 100%;
  }

  .filters {
    grid-template-columns: 1fr 1fr;
  }

  .records-header {
    padding: 15px;
  }

  .m-card {
    padding: 15px;
  }

  .m-top {
    flex-direction: column;
  }

  .m-side {
    justify-content: space-between;
  }

  .m-meta {
    gap: 8px;
  }

  .modal-overlay {
    align-items: flex-end;
    padding: 0;
  }

  .modal-card {
    max-height: 92vh;
    border-radius: 14px 14px 0 0;
    padding: 10px 16px 24px;
  }

  .sheet-handle {
    display: block;
    width: 40px;
    height: 4px;
    margin: 0 auto 14px;
    border-radius: 999px;
    background: #d7dce1;
  }
}

@media (min-width: 768px) and (max-width: 1050px) {
  .stats {
    grid-template-columns: 1fr 1fr;
  }

  .filters {
    grid-template-columns: 1fr 1fr;
  }
}
</style>