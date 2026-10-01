<template>
  <div class="app" @click="openMenuId = null">
    <!-- CampusGlide / Keen-style mobile header -->
    <header class="mobile-header">
      <button class="menu-button" aria-label="Open navigation" :aria-expanded="sidebarOpen" @click.stop="sidebarOpen = !sidebarOpen">
        <svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
      </button>
      <div class="mobile-brand">
        <span class="brand-mark">CG</span>
        <span>CampusGlide</span>
      </div>
      <a class="mobile-logout" href="/login" aria-label="Logout" title="Logout">
        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" /></svg>
      </a>
    </header>

    <div v-if="sidebarOpen" class="sidebar-overlay" @click="sidebarOpen = false"></div>

    <!-- CampusGlide / Keen-style sidebar -->
    <aside class="sidebar" :class="{ open: sidebarOpen }" @click.stop>
      <div class="sidebar-brand">
        <div class="brand-logo">CG</div>
        <div class="brand-copy">
          <strong>CampusGlide</strong>
          <span>NVSU Motorpool</span>
        </div>
      </div>

      <nav class="nav-list" aria-label="Main navigation">
        <a class="nav-link" :class="{ active: isDriverPage }" href="/driver" @click.prevent="navigateTo('/driver')">
          <svg viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5M5 9v11h14V9M9 20v-6h6v6" /></svg>
          <span>Dashboard</span>
        </a>
        <a class="nav-link" href="/guard-logs" @click="sidebarOpen = false">
          <svg viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3zM9 12l2 2 4-4" /></svg>
          <span>Gate Logs</span>
        </a>
        <a class="nav-link" :class="{ active: !isDriverPage }" href="/maintenance" @click.prevent="navigateTo('/maintenance')">
          <svg viewBox="0 0 24 24"><path d="M14.7 6.3a5.5 5.5 0 0 0-7.4 7.4L3.8 17.2a2.1 2.1 0 1 0 3 3l3.5-3.5a5.5 5.5 0 0 0 7.4-7.4l-3.3 3.3-3-3 3.3-3.3z" /></svg>
          <span>Maintenance</span>
        </a>
      </nav>

      <div class="sidebar-spacer"></div>
      <a class="nav-link nav-logout" href="/login">
        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" /></svg>
        <span>Logout</span>
      </a>

      <div class="sidebar-footer">
        <div class="brand-logo small">CG</div>
        <div>
          <strong>CampusGlide</strong>
          <span>Safer Campus. Smarter Mobility.</span>
        </div>
      </div>
    </aside>

    <div class="content-shell">
      <!-- Keen-style desktop header -->
      <header class="desktop-header">
        <div class="header-search" @click.stop>
          <svg viewBox="0 0 24 24"><path d="M21 21l-4.3-4.3M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z" /></svg>
          <input ref="globalSearchInput" v-model="search" type="search" placeholder="Search anything..." aria-label="Search anything" @keydown.esc="search = ''" />
          <button v-if="search" class="clear-search" type="button" aria-label="Clear search" @click="search = ''">×</button>
          <kbd>Ctrl + K</kbd>
        </div>
        <div class="desktop-user">
          <button class="notification-button" type="button" aria-label="Notifications">
            <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
            <span></span>
          </button>
          <div class="user-menu-wrapper" @click.stop>
            <button class="user-profile" type="button" @click="menuOpen = !menuOpen">
              <div class="avatar">{{ role === 'administrator' ? 'A' : 'D' }}</div>
              <div class="user-copy">
                <strong>{{ role === 'administrator' ? 'Administrator' : 'Driver' }}</strong>
                <small>NVSU Motorpool</small>
              </div>
              <span class="chevron">⌄</span>
            </button>
            <div v-if="menuOpen" class="user-dropdown">
              <button type="button" :class="{ selected: role === 'administrator' }" @click="switchRole('administrator')">
                <span class="dropdown-avatar">A</span>
                <span><strong>Administrator</strong><small>NVSU Motorpool</small></span>
              </button>
              <button type="button" :class="{ selected: role === 'driver' }" @click="switchRole('driver')">
                <span class="dropdown-avatar">D</span>
                <span><strong>Driver</strong><small>NVSU Motorpool</small></span>
              </button>
            </div>
          </div>
        </div>
      </header>

      <main v-if="isDriverPage" class="page driver-page">
        <div class="page-heading">
          <div class="heading-icon driver-icon">
            <svg viewBox="0 0 24 24"><path d="M5 17h14l-1-6H6l-1 6zM7 11l1.5-4h7L17 11M7 17v2M17 17v2M8 14h.01M16 14h.01" /></svg>
          </div>
          <div>
            <h1>Driver Dashboard</h1>
            <p>View your vehicle maintenance schedule and upcoming service dates</p>
          </div>
        </div>

        <section class="driver-welcome">
          <div>
            <span class="eyebrow">NVSU Motorpool</span>
            <h2>Welcome, Driver</h2>
            <p>Use this page to check maintenance history and upcoming service dates. Maintenance actions are view-only for drivers.</p>
          </div>
          <div class="driver-badge">
            <span class="driver-badge-icon">D</span>
            <span><strong>Driver access</strong><small>View only</small></span>
          </div>
        </section>

        <section class="stats driver-stats">
          <div class="stat">
            <div class="stat-top"><span class="stat-icon wrench"><svg viewBox="0 0 24 24"><path :d="ICONS.wrench" /></svg></span></div>
            <strong>{{ driverRows.length }}</strong>
            <span class="stat-label">Maintenance records</span>
          </div>
          <div class="stat">
            <div class="stat-top"><span class="stat-icon calendar"><svg viewBox="0 0 24 24"><path :d="ICONS.calendar" /></svg></span></div>
            <strong>{{ driverRows.filter(l => l.display === 'upcoming').length }}</strong>
            <span class="stat-label">Upcoming service</span>
          </div>
          <div class="stat">
            <div class="stat-top"><span class="stat-icon alert"><svg viewBox="0 0 24 24"><path :d="ICONS.alert" /></svg></span></div>
            <strong>{{ driverRows.filter(l => l.display === 'overdue').length }}</strong>
            <span class="stat-label">Overdue service</span>
          </div>
        </section>

        <section class="control-card driver-controls">
          <div class="toolbar">
            <label class="search">
              <svg viewBox="0 0 24 24"><path d="M21 21l-4.3-4.3M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z" /></svg>
              <input v-model="driverSearch" type="search" placeholder="Search maintenance schedule..." aria-label="Search maintenance schedule" />
            </label>
            <button class="secondary-button" type="button" :disabled="loading" @click="loadAll">
              {{ loading ? 'Refreshing...' : 'Refresh schedule' }}
            </button>
          </div>
        </section>

        <section class="records-card driver-records">
          <div class="records-header">
            <div>
              <h2>Maintenance schedule</h2>
              <p>Upcoming and previous maintenance dates for the motorpool vehicles</p>
            </div>
          </div>

          <p v-if="loading" class="empty">Loading maintenance schedule…</p>
          <p v-else-if="!driverFiltered.length" class="empty">
            <span class="empty-icon"><svg viewBox="0 0 24 24"><path :d="ICONS.calendar" /></svg></span>
            <strong>No maintenance schedule found</strong>
            <span>There are no maintenance records matching your search.</span>
          </p>

          <div v-else class="schedule-list">
            <article v-for="log in driverFiltered" :key="log.id" class="schedule-row">
              <div class="schedule-date" :class="log.display">
                <strong>{{ log.next_due_date ? scheduleDay(log.next_due_date) : '—' }}</strong>
                <span>{{ log.next_due_date ? scheduleMonth(log.next_due_date) : 'DATE' }}</span>
              </div>
              <div class="schedule-main">
                <div class="schedule-title">
                  <strong>{{ log.vehicle.plate_number }}</strong>
                  <span>{{ log.vehicle.model || 'Vehicle' }}</span>
                </div>
                <p>{{ log.description || typeLabel(log.type) }}</p>
                <div class="schedule-meta">
                  <span><b>Last serviced:</b> {{ formatDate(log.date_performed) }}</span>
                  <span><b>Next due:</b> {{ log.next_due_date ? formatDate(log.next_due_date) : 'Not scheduled' }}</span>
                </div>
              </div>
              <span class="status" :class="log.display">{{ driverStatusLabel(log) }}</span>
            </article>
          </div>
        </section>
      </main>

      <main v-else class="page">
        <div class="page-heading">
          <div class="heading-icon">
            <svg viewBox="0 0 24 24"><path d="M14.7 6.3a5.5 5.5 0 0 0-7.4 7.4L3.8 17.2a2.1 2.1 0 1 0 3 3l3.5-3.5a5.5 5.5 0 0 0 7.4-7.4l-3.3 3.3-3-3 3.3-3.3z" /></svg>
          </div>
          <div>
            <h1>Vehicle Maintenance</h1>
            <p>Track and manage vehicle maintenance records</p>
          </div>
          <button v-if="isAdmin" class="add-button heading-action" @click="openAdd">
            <span>+</span> Add Maintenance
          </button>
        </div>

        <!-- Stats -->
        <section class="stats">
          <div v-for="s in stats" :key="s.label" class="stat">
            <div class="stat-top">
              <span class="stat-icon" :class="s.tone">
                <svg viewBox="0 0 24 24"><path :d="s.path" /></svg>
              </span>
              <span class="stat-arrow">›</span>
            </div>
            <strong>{{ s.value }}</strong>
            <span class="stat-label">{{ s.label }}</span>
          </div>
        </section>

        <!-- Search + filters -->
        <section class="control-card">
          <div class="toolbar">
            <label class="search">
              <svg viewBox="0 0 24 24"><path d="M21 21l-4.3-4.3M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z" /></svg>
              <input v-model="search" type="search" placeholder="Search maintenance records..." aria-label="Search maintenance" />
            </label>
            <button class="filter-button" :aria-expanded="showFilters" @click="showFilters = !showFilters">
              <svg viewBox="0 0 24 24"><path d="M22 3H2l8 9.5V19l4 2v-8.5L22 3z" /></svg>
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

        <!-- List -->
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
              <svg viewBox="0 0 24 24"><path d="M14.7 6.3a5.5 5.5 0 0 0-7.4 7.4L3.8 17.2a2.1 2.1 0 1 0 3 3l3.5-3.5a5.5 5.5 0 0 0 7.4-7.4l-3.3 3.3-3-3 3.3-3.3z" /></svg>
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
                <div><dt>Date</dt><dd>{{ formatDate(log.date_performed) }}</dd></div>
                <div><dt>Odometer</dt><dd>{{ formatKm(log.vehicle.mileage) }}</dd></div>
                <div><dt>Cost</dt><dd>{{ formatMoney(log.cost) }}</dd></div>
              </dl>
            </article>
          </div>
        </section>

        <nav v-if="pageCount > 1" class="pager" aria-label="Pages">
          <button aria-label="Previous page" :disabled="page === 1" @click="page--">‹</button>
          <button v-for="n in visiblePages" :key="n" :class="{ active: n === page }" :aria-current="n === page ? 'page' : undefined" @click="page = n">{{ n }}</button>
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
                <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.plate_number }} — {{ v.vehicle_model }}</option>
              </select>
            </label>
            <label class="form-label">Type
              <select v-model="form.type">
                <option v-for="[value, label] in TYPES" :key="value" :value="value">{{ label }}</option>
              </select>
            </label>
            <label class="form-label">Description
              <textarea v-model="form.description" maxlength="500" rows="3" placeholder="What work is being done?"></textarea>
            </label>
            <div class="form-row">
              <label class="form-label">Date <input v-model="form.date_performed" type="date" /></label>
              <label class="form-label">Next due <input v-model="form.next_due_date" type="date" :min="form.date_performed" /></label>
            </div>
            <div class="form-row">
              <label class="form-label">Cost (₱) <input v-model="form.cost" type="number" min="0" step="0.01" placeholder="0.00" /></label>
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
          <button class="primary-button" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save maintenance' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

const API_URL = import.meta.env.VITE_API_BASE_URL || '/api'
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

const initialPath = window.location.pathname
const role = ref(initialPath === '/driver' ? 'driver' : 'administrator')
const isAdmin = computed(() => role.value === 'administrator')
const isDriverPage = computed(() => role.value === 'driver')
const menuOpen = ref(false)
const sidebarOpen = ref(false)
const globalSearchInput = ref(null)

const logs = ref([])
const vehicles = ref([])
const loading = ref(true)
const loadError = ref('')

const search = ref('')
const driverSearch = ref('')
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
  const headers = { Accept: 'application/json' }
  if (options.body) headers['Content-Type'] = 'application/json'

  const response = await fetch(`${API_URL}/${path}`, { ...options, headers })
  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    const details = data.errors ? Object.values(data.errors).flat().join(' ') : ''
    throw new Error(details || data.message || 'Request failed.')
  }
  return data
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
    vehicles.value = vehicleData.data
  } catch (error) {
    console.error(error)
    loadError.value = `Unable to load maintenance records. ${error.message}`
  } finally {
    loading.value = false
  }
}

function navigateTo(path) {
  if (window.location.pathname !== path) {
    window.history.pushState({}, '', path)
  }
  role.value = path === '/driver' ? 'driver' : 'administrator'
  menuOpen.value = false
  sidebarOpen.value = false
}

function switchRole(nextRole) {
  navigateTo(nextRole === 'driver' ? '/driver' : '/maintenance')
}

function handlePopState() {
  role.value = window.location.pathname === '/driver' ? 'driver' : 'administrator'
}

function focusGlobalSearch(event) {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault()
    globalSearchInput.value?.focus()
  }
}

function onDocumentClick() {
  menuOpen.value = false
  openMenuId.value = null
}

onMounted(() => {
  loadAll()
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', focusGlobalSearch)
  window.addEventListener('popstate', handlePopState)
})
onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', focusGlobalSearch)
  window.removeEventListener('popstate', handlePopState)
})

/* ================= DERIVED DATA ================= */

function displayStatus(log) {
  if (log.status === 'cancelled') return 'cancelled'
  if (log.status === 'in_progress') return 'in_progress'

  // Maintenance schedule status is based on the backend's next_due_date.
  // Do not treat the date it was performed as the next service date.
  if (log.next_due_date) {
    if (log.next_due_date < today) return 'overdue'
    if (log.next_due_date === today) return 'upcoming'
    return 'upcoming'
  }

  return log.status === 'completed' ? 'completed' : 'upcoming'
}

const rows = computed(() => logs.value.map(log => ({ ...log, display: displayStatus(log) })))

const driverRows = computed(() => {
  return [...rows.value].sort((a, b) => {
    const aDate = a.next_due_date || '9999-12-31'
    const bDate = b.next_due_date || '9999-12-31'
    return aDate.localeCompare(bDate)
  })
})

const driverFiltered = computed(() => {
  const q = driverSearch.value.trim().toLowerCase()
  if (!q) return driverRows.value
  return driverRows.value.filter(log => {
    const text = [
      log.vehicle?.plate_number,
      log.vehicle?.model,
      log.description,
      typeLabel(log.type),
      log.next_due_date
    ].join(' ').toLowerCase()
    return text.includes(q)
  })
})

function driverStatusLabel(log) {
  if (log.display === 'overdue') return 'Overdue'
  if (log.display === 'in_progress') return 'In progress'
  if (log.display === 'cancelled') return 'Cancelled'
  if (log.display === 'completed') return 'Completed'
  return 'Upcoming'
}

function scheduleDay(value) {
  if (!value) return '—'
  return new Date(`${value}T00:00:00`).toLocaleDateString('en-US', { day: '2-digit' })
}

function scheduleMonth(value) {
  if (!value) return ''
  return new Date(`${value}T00:00:00`).toLocaleDateString('en-US', { month: 'short' }).toUpperCase()
}

const stats = computed(() => [
  { label: 'Total Maintenance', value: rows.value.length, path: ICONS.wrench, tone: 'wrench' },
  {
    label: 'This Month',
    value: rows.value.filter(l => l.date_performed.startsWith(today.slice(0, 7))).length,
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
    if (fTime.value && !inTimeRange(log.date_performed)) return false
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

watch([search, fStatus, fVehicle, fType, fTime, driverSearch], () => { page.value = 1 })

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
      method: editingId.value ? 'PATCH' : 'POST',
      body: JSON.stringify({
        vehicle_id: Number(f.vehicle_id),
        type: f.type,
        description: f.description || null,
        date_performed: f.date_performed,
        next_due_date: f.next_due_date || null,
        cost: f.cost === '' ? null : Number(f.cost),
        status: f.status
      })
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
      method: 'PATCH',
      body: JSON.stringify({ status: 'completed' })
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

<style>
:root {
  --primary: #27af30;
  --primary-dark: #1f8c26;
  --primary-50: #f0fbf1;
  --primary-100: #d9f4dc;
  --secondary: #ffa500;
  --line: #dbdbdb;
  --surface: #f7f8f9;
  --ink: #1f2933;
  --muted: #6b7280;
  --white: #fff;
  --radius: 10px;
  --page: 20px;
  --sidebar: 256px;
}

* { box-sizing: border-box; }
html, body, #app { margin: 0; min-height: 100%; width: 100%; }
body {
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  background: var(--surface);
  color: var(--ink);
  font-size: 16px;
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
}
button, input, select, textarea { font: inherit; }
button, a { -webkit-tap-highlight-color: transparent; }
button { cursor: pointer; }
a { color: inherit; text-decoration: none; }
svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }

.app { min-height: 100vh; background: var(--surface); }

/* Sidebar */
.sidebar {
  position: fixed;
  inset: 0 auto 0 0;
  z-index: 50;
  width: var(--sidebar);
  display: flex;
  flex-direction: column;
  background: #fff;
  border-right: 1px solid #e5e7eb;
  transform: translateX(0);
}
.sidebar-brand {
  height: 72px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 24px;
  border-bottom: 1px solid #eef0f2;
}
.brand-logo {
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  border-radius: 10px;
  background: var(--primary);
  color: #fff;
  font-size: 13px;
  font-weight: 800;
  letter-spacing: -.5px;
}
.brand-copy { display: flex; flex-direction: column; line-height: 1.2; }
.brand-copy strong { font-size: 15px; }
.brand-copy span { margin-top: 3px; color: var(--muted); font-size: 11px; }
.nav-list { display: flex; flex-direction: column; gap: 4px; padding: 16px 8px; }
.nav-link {
  min-height: 44px;
  display: flex;
  align-items: center;
  gap: 13px;
  padding: 0 16px;
  border-radius: 9px;
  color: #536174;
  font-size: 15px;
  font-weight: 500;
  transition: background .15s ease, color .15s ease;
}
.nav-link svg { width: 21px; height: 21px; flex: 0 0 auto; }
.nav-link:hover { background: #f3f5f7; color: var(--ink); }
.nav-link.active { background: var(--primary-50); color: var(--primary-dark); font-weight: 600; }
.nav-link.active::before {
  content: "";
  position: absolute;
  left: 0;
  width: 3px;
  height: 28px;
  border-radius: 0 3px 3px 0;
  background: var(--primary);
}
.nav-logout { margin: 0 8px 10px; }
.sidebar-spacer { flex: 1; }
.sidebar-footer {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 18px 24px 22px;
  color: #27415d;
}
.sidebar-footer .small { width: 31px; height: 31px; border-radius: 9px; font-size: 11px; }
.sidebar-footer strong, .sidebar-footer span { display: block; }
.sidebar-footer strong { font-size: 12px; }
.sidebar-footer span { margin-top: 2px; font-size: 9px; color: #8a96a3; }

/* Desktop header */
.content-shell { margin-left: var(--sidebar); min-height: 100vh; }
.desktop-header {
  position: sticky;
  top: 0;
  z-index: 30;
  height: 72px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 0 32px;
  background: rgba(255,255,255,.96);
  border-bottom: 1px solid #e7eaee;
  backdrop-filter: blur(8px);
}
.header-search {
  width: min(620px, 65%);
  height: 44px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 12px;
  border: 1px solid #e2e7ed;
  border-radius: 10px;
  color: #8a96a3;
  background: #fff;
  font-size: 14px;
}
.header-search svg { width: 19px; height: 19px; }
.header-search input { flex: 1; width: 100%; min-width: 0; border: 0; outline: 0; background: transparent; color: #24344d; font-size: 14px; font-family: inherit; }
.header-search input::placeholder { color: #8a96a3; }
.clear-search { width: 26px; height: 26px; display: grid; place-items: center; flex: 0 0 auto; border: 0; border-radius: 6px; background: transparent; color: #7b8795; font-size: 20px; line-height: 1; }
.clear-search:hover { background: #f1f3f5; color: #27364a; }
.header-search kbd { margin-left: auto; padding: 3px 8px; border: 1px solid #e4e8ed; border-radius: 6px; background: #fafbfc; color: #98a2ad; font-size: 11px; }
.desktop-user { display: flex; align-items: center; gap: 10px; }
.user-menu-wrapper { position: relative; }
.user-profile { display: flex; align-items: center; gap: 10px; padding: 4px; border: 0; background: transparent; cursor: pointer; font-family: inherit; text-align: left; border-radius: 9px; }
.user-profile:hover { background: #f5f7f8; }
.user-dropdown { position: absolute; right: 0; top: calc(100% + 8px); width: 235px; padding: 7px; background: #fff; border: 1px solid #e1e7ed; border-radius: 11px; box-shadow: 0 12px 30px rgba(31,41,51,.14); z-index: 100; }
.user-dropdown button { width: 100%; display: flex; align-items: center; gap: 10px; padding: 9px; border: 0; border-radius: 8px; background: transparent; text-align: left; font-family: inherit; }
.user-dropdown button:hover, .user-dropdown button.selected { background: var(--primary-50); }
.dropdown-avatar { width: 32px; height: 32px; display: grid; place-items: center; border-radius: 50%; background: var(--primary); color: #fff; font-weight: 700; font-size: 13px; }
.user-dropdown button > span:last-child { display: flex; flex-direction: column; }
.user-dropdown strong { color: #20334d; font-size: 13px; }
.user-dropdown small { margin-top: 2px; color: #7b8795; font-size: 11px; }
.notification-button { position: relative; width: 40px; height: 40px; display: grid; place-items: center; border: 0; background: transparent; color: #536174; border-radius: 8px; }
.notification-button:hover { background: #f3f5f7; }
.notification-button span { position: absolute; top: 8px; right: 8px; width: 7px; height: 7px; border-radius: 50%; background: #ef4444; border: 1px solid #fff; }
.avatar { width: 40px; height: 40px; display: grid; place-items: center; border-radius: 50%; background: var(--primary); color: #fff; font-weight: 700; font-size: 14px; }
.user-copy { display: flex; flex-direction: column; line-height: 1.25; }
.user-copy strong { font-size: 14px; }
.user-copy small { color: var(--muted); font-size: 11px; }
.chevron { margin-left: 2px; color: #667384; font-size: 18px; }

/* Driver dashboard */
.driver-icon { background: #e4f7e7; }
.driver-welcome { display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-bottom: 20px; padding: 22px 24px; background: #fff; border: 1px solid #e1e7ed; border-radius: 12px; }
.driver-welcome .eyebrow { color: var(--primary-dark); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
.driver-welcome h2 { margin: 4px 0 3px; color: #182333; font-size: 21px; }
.driver-welcome p { margin: 0; color: var(--muted); font-size: 14px; }
.driver-action { flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; height: 42px; padding: 0 16px; border-radius: 9px; background: var(--primary); color: #fff; font-weight: 700; }
.driver-action:hover { background: var(--primary-dark); }
.driver-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.driver-records { margin-top: 4px; }

/* Driver dashboard details */
.driver-badge {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  flex: 0 0 auto;
  padding: 10px 13px;
  border: 1px solid #dcebdd;
  border-radius: 10px;
  background: #f5fbf6;
}
.driver-badge-icon {
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: var(--primary);
  color: #fff;
  font-weight: 800;
}
.driver-badge strong, .driver-badge small { display: block; }
.driver-badge strong { color: #24422a; font-size: 13px; }
.driver-badge small { margin-top: 2px; color: #718276; font-size: 11px; }
.driver-controls { margin-bottom: 18px; }
.secondary-button {
  min-height: 40px;
  padding: 0 14px;
  border: 1px solid #d7dee7;
  border-radius: 8px;
  background: #fff;
  color: #334155;
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}
.secondary-button:hover:not(:disabled) { background: #f7f9fb; }
.secondary-button:disabled { opacity: .6; cursor: not-allowed; }

.schedule-list { display: flex; flex-direction: column; }
.schedule-row {
  display: grid;
  grid-template-columns: 64px minmax(0, 1fr) auto;
  align-items: center;
  gap: 16px;
  padding: 18px 20px;
  border-bottom: 1px solid #eef1f4;
}
.schedule-row:last-child { border-bottom: 0; }
.schedule-date {
  width: 58px;
  height: 62px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border-radius: 11px;
  background: var(--primary-50);
  color: var(--primary-dark);
}
.schedule-date strong { font-size: 21px; line-height: 1; }
.schedule-date span { margin-top: 4px; font-size: 10px; font-weight: 800; letter-spacing: .05em; }
.schedule-date.overdue { background: #fff0f0; color: #b3261e; }
.schedule-date.in_progress { background: #edf5ff; color: #155a9c; }
.schedule-date.completed { background: #f0f2f4; color: #687482; }
.schedule-main { min-width: 0; }
.schedule-title { display: flex; align-items: baseline; gap: 7px; flex-wrap: wrap; }
.schedule-title strong { color: #1c2c42; font-size: 15px; }
.schedule-title span { color: #667487; font-size: 13px; }
.schedule-main p { margin: 4px 0 8px; color: #526174; font-size: 13px; }
.schedule-meta { display: flex; gap: 18px; flex-wrap: wrap; color: #7b8795; font-size: 12px; }
.schedule-meta b { color: #526174; font-weight: 600; }

/* Mobile */
.mobile-header { display: none; }
.sidebar-overlay { display: none; }

/* Page */
.page { padding: 30px 28px 56px; max-width: 1500px; margin: 0 auto; }
.page-heading { display: flex; align-items: center; gap: 16px; margin-bottom: 28px; }
.heading-icon { width: 58px; height: 58px; display: grid; place-items: center; flex: 0 0 auto; border-radius: 14px; background: var(--primary-100); color: var(--primary-dark); }
.heading-icon svg { width: 30px; height: 30px; }
.page-heading h1 { margin: 0; font-size: 28px; line-height: 1.2; font-weight: 700; color: #182333; }
.page-heading p { margin: 5px 0 0; color: var(--muted); font-size: 15px; }
.heading-action { margin-left: auto; }

/* Stats */
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
.stat { min-height: 145px; padding: 17px 18px; background: #fff; border: 1px solid #e1e7ed; border-radius: 12px; box-shadow: 0 1px 2px rgba(16,24,40,.02); }
.stat-top { display: flex; align-items: center; justify-content: space-between; }
.stat-icon { width: 40px; height: 40px; display: grid; place-items: center; border-radius: 11px; background: var(--primary-100); color: var(--primary-dark); }
.stat-icon.calendar { background: #e7f8ef; color: #15905c; }
.stat-icon.clock { background: #f0eafe; color: #7652d5; }
.stat-icon.alert { background: #ffebeb; color: #d62f2f; }
.stat-icon svg { width: 22px; height: 22px; }
.stat-arrow { color: #8793a1; font-size: 26px; line-height: 1; }
.stat strong { display: block; margin-top: 12px; font-size: 28px; line-height: 1; color: #17263b; }
.stat-label { display: block; margin-top: 8px; color: #45546a; font-size: 14px; }

/* Buttons */
.add-button { height: 44px; padding: 0 18px; display: inline-flex; align-items: center; justify-content: center; gap: 7px; border: 0; border-radius: 9px; background: var(--primary); color: #fff; font-weight: 700; box-shadow: 0 2px 4px rgba(39,175,48,.18); }
.add-button:hover { background: var(--primary-dark); }
.add-button span { font-size: 22px; line-height: 1; }
.primary-button { width: 100%; height: 46px; margin-top: 18px; border: 0; border-radius: 9px; background: var(--primary); color: #fff; font-weight: 700; }
.primary-button:hover:not(:disabled) { background: var(--primary-dark); }
.primary-button:disabled { opacity: .55; cursor: not-allowed; }

/* Search / filter card */
.control-card { padding: 16px; background: #fff; border: 1px solid #e1e7ed; border-radius: 12px; box-shadow: 0 1px 2px rgba(16,24,40,.02); margin-bottom: 18px; }
.toolbar { display: flex; gap: 12px; }
.search { flex: 1; min-width: 0; height: 44px; display: flex; align-items: center; gap: 9px; padding: 0 13px; border: 1px solid #e1e7ed; border-radius: 9px; background: #fbfcfd; color: #7b8795; }
.search svg { width: 20px; height: 20px; flex: 0 0 auto; }
.search input { width: 100%; min-width: 0; border: 0; outline: 0; background: transparent; color: var(--ink); font-size: 14px; }
.search input::placeholder { color: #8b97a5; }
.filter-button { height: 44px; min-width: 112px; display: flex; align-items: center; justify-content: center; gap: 7px; padding: 0 14px; border: 1px solid #dce3e9; border-radius: 9px; background: #fff; color: #34445a; font-weight: 600; }
.filter-button:hover { background: #f7f9fa; }
.filter-button svg { width: 19px; height: 19px; }
.filter-chevron { margin-left: 3px; color: #7a8795; }
.filters { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #eef1f4; }
.filters select { width: 100%; height: 42px; padding: 0 12px; border: 1px solid #dce3e9; border-radius: 9px; background: #fff; color: #3b4a5f; font-size: 14px; outline: 0; }
.filters select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(39,175,48,.12); }

/* Records */
.records-card { background: #fff; border: 1px solid #e1e7ed; border-radius: 12px; box-shadow: 0 1px 2px rgba(16,24,40,.02); overflow: hidden; }
.records-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; border-bottom: 1px solid #eef1f4; }
.records-header h2 { margin: 0; font-size: 18px; color: #1b2a3e; }
.records-header p { margin: 3px 0 0; color: var(--muted); font-size: 13px; }
.list { display: flex; flex-direction: column; gap: 0; }
.m-card { padding: 18px 20px; border-bottom: 1px solid #eef1f4; }
.m-card:last-child { border-bottom: 0; }
.m-top { display: flex; justify-content: space-between; gap: 16px; }
.m-title { min-width: 0; }
.m-title strong { color: #1c2c42; font-size: 15px; }
.m-title > span { color: #667487; font-size: 14px; }
.m-title p { margin: 5px 0 0; color: #667487; font-size: 13px; }
.m-side { display: flex; align-items: flex-start; gap: 8px; }
.status { display: inline-flex; align-items: center; padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; white-space: nowrap; }
.status.completed { background: var(--primary-50); color: var(--primary-dark); }
.status.upcoming { background: #fff6df; color: #9a6500; }
.status.overdue { background: #fff0f0; color: #b3261e; }
.status.in_progress { background: #edf5ff; color: #155a9c; }
.status.cancelled { background: #f0f2f4; color: #687482; }
.kebab-wrap { position: relative; }
.kebab { width: 34px; height: 34px; border: 0; border-radius: 8px; background: transparent; color: #667487; font-size: 22px; line-height: 1; }
.kebab:hover { background: #f3f5f7; }
.kebab-menu { position: absolute; right: 0; top: 36px; z-index: 10; min-width: 180px; padding: 5px; background: #fff; border: 1px solid #dfe5ea; border-radius: 9px; box-shadow: 0 10px 25px rgba(31,41,51,.12); }
.kebab-menu button { width: 100%; padding: 9px 11px; border: 0; border-radius: 7px; background: transparent; text-align: left; color: #34445a; font-size: 13px; }
.kebab-menu button:hover { background: #f4f6f8; }
.m-meta { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; margin: 16px 0 0; padding-top: 14px; border-top: 1px solid #f0f2f4; }
.m-meta dt { color: #8a96a3; font-size: 11px; }
.m-meta dd { margin: 3px 0 0; color: #34445a; font-size: 13px; font-weight: 600; }

.empty { min-height: 300px; margin: 0; padding: 48px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; color: var(--muted); font-size: 14px; }
.empty-icon { width: 60px; height: 60px; display: grid; place-items: center; margin-bottom: 13px; border-radius: 50%; background: var(--primary-50); color: var(--primary-dark); }
.empty-icon svg { width: 29px; height: 29px; }
.empty strong { color: #23334a; font-size: 15px; }
.empty > span:not(.empty-icon) { margin-top: 4px; }
.empty-action { margin-top: 20px; height: 42px; padding: 0 18px; border: 0; border-radius: 9px; background: var(--primary); color: #fff; font-size: 14px; font-weight: 700; }

.pager { display: flex; justify-content: center; gap: 7px; margin-top: 20px; }
.pager button { min-width: 38px; height: 38px; border: 1px solid #dce3e9; border-radius: 8px; background: #fff; color: #44536a; font-weight: 600; }
.pager button.active { border-color: var(--primary); background: var(--primary); color: #fff; }
.pager button:disabled { opacity: .45; cursor: not-allowed; }

.load-error { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin: 0 0 18px; padding: 14px 16px; background: #fff4f4; border: 1px solid #f1c2c2; border-radius: 10px; color: #9d2727; }
.load-error p { margin: 0; font-size: 13px; font-weight: 600; }
.load-error button { height: 36px; padding: 0 14px; border: 1px solid #c33a3a; border-radius: 8px; background: #fff; color: #9d2727; font-weight: 700; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center; padding: 20px; background: rgba(20,28,38,.48); backdrop-filter: blur(2px); }
.modal-card { width: min(500px, 100%); max-height: 90vh; overflow-y: auto; padding: 24px; background: #fff; border-radius: 14px; box-shadow: 0 24px 60px rgba(20,30,40,.2); }
.sheet-handle { display: none; }
.modal-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.small-label { color: var(--muted); font-size: 12px; font-weight: 600; }
.modal-header h2 { margin: 3px 0 0; color: #1b2a3e; font-size: 21px; }
.icon-button { width: 38px; height: 38px; border: 0; border-radius: 8px; background: #f3f5f7; color: #526174; font-size: 23px; line-height: 1; }
.modal-note { margin: 16px 0 18px; padding: 11px 12px; border-left: 3px solid var(--secondary); border-radius: 8px; background: #fff8eb; color: #725319; font-size: 12px; }
.form-group { display: flex; flex-direction: column; gap: 13px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-label { display: flex; flex-direction: column; gap: 6px; color: #44536a; font-size: 12px; font-weight: 600; }
.form-label input, .form-label select, .form-label textarea { width: 100%; min-height: 42px; padding: 9px 11px; border: 1px solid #dce3e9; border-radius: 8px; outline: 0; background: #fff; color: #26364a; font-size: 14px; font-weight: 400; }
.form-label textarea { min-height: 88px; resize: vertical; }
.form-label input:focus, .form-label select:focus, .form-label textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(39,175,48,.12); }
.error-message { margin: 12px 0 0; padding: 10px 12px; border-radius: 8px; background: #fff0f0; color: #b3261e; font-size: 12px; font-weight: 600; }

@media (max-width: 767px) {
  :root { --page: 16px; }
  .sidebar { width: 280px; transform: translateX(-100%); box-shadow: 10px 0 30px rgba(20,30,40,.12); transition: transform .2s ease; }
  .sidebar.open { transform: translateX(0); }
  .sidebar-overlay { display: block; position: fixed; inset: 0; z-index: 40; background: rgba(20,28,38,.4); }
  .mobile-header { position: sticky; top: 0; z-index: 35; height: 58px; display: flex; align-items: center; justify-content: space-between; padding: 0 14px; background: #fff; border-bottom: 1px solid #e5e9ed; }
  .menu-button, .mobile-logout { width: 38px; height: 38px; display: grid; place-items: center; border: 0; border-radius: 8px; background: transparent; color: #526174; }
  .menu-button svg, .mobile-logout svg { width: 21px; height: 21px; }
  .mobile-brand { display: flex; align-items: center; gap: 8px; color: #20334d; font-weight: 700; font-size: 14px; }
  .mobile-brand .brand-mark { width: 30px; height: 30px; display: grid; place-items: center; border-radius: 8px; background: var(--primary); color: #fff; font-size: 10px; font-weight: 800; }
  .content-shell { margin-left: 0; }
  .desktop-header { display: none; }
  .page { padding: 22px var(--page) 40px; }
  .page-heading { align-items: flex-start; margin-bottom: 20px; }
  .heading-icon { width: 46px; height: 46px; border-radius: 12px; }
  .heading-icon svg { width: 24px; height: 24px; }
  .page-heading h1 { font-size: 23px; }
  .page-heading p { font-size: 13px; }
  .heading-action { display: none; }
  .driver-welcome { flex-direction: column; align-items: flex-start; padding: 18px; }
  .driver-badge { width: 100%; }
  .driver-stats { grid-template-columns: 1fr; }
  .stats { grid-template-columns: 1fr 1fr; gap: 10px; }
  .stat { min-height: 126px; padding: 13px; }
  .stat strong { font-size: 23px; }
  .stat-label { font-size: 12px; }
  .control-card { padding: 12px; }
  .toolbar { flex-direction: column; }
  .filter-button { width: 100%; }
  .filters { grid-template-columns: 1fr 1fr; }
  .records-header { padding: 15px; }
  .m-card { padding: 15px; }
  .m-top { flex-direction: column; }
  .schedule-row { grid-template-columns: 54px minmax(0, 1fr); gap: 12px; align-items: start; padding: 15px; }
  .schedule-date { width: 52px; height: 56px; }
  .schedule-row > .status { grid-column: 2; justify-self: start; margin-top: -5px; }
  .m-side { justify-content: space-between; }
  .m-meta { gap: 8px; }
  .modal-overlay { align-items: flex-end; padding: 0; }
  .modal-card { max-height: 92vh; border-radius: 14px 14px 0 0; padding: 10px 16px 24px; }
  .sheet-handle { display: block; width: 40px; height: 4px; margin: 0 auto 14px; border-radius: 999px; background: #d7dce1; }
}

@media (min-width: 768px) and (max-width: 1050px) {
  .stats { grid-template-columns: 1fr 1fr; }
  .filters { grid-template-columns: 1fr 1fr; }
  .header-search { width: 50%; }
}

@media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
</style>