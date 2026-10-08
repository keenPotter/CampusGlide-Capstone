<template>
  <div class="app">
    <main class="page">
      <div class="page-heading">
        <h1>Trip Schedule</h1>
        <p>View and manage scheduled trips</p>
      </div>

      <!-- View switcher -->
      <div class="view-buttons" role="tablist">
        <button v-for="view in views" :key="view" role="tab" :aria-selected="currentView === view"
          :class="{ active: currentView === view }" @click="currentView = view">
          {{ view.charAt(0).toUpperCase() + view.slice(1) }}
        </button>
      </div>

      <div v-if="loadError" class="load-error">
        <p>{{ loadError }}</p>
        <button class="retry-button" @click="fetchTrips">Try again</button>
      </div>

      <!-- ============ MONTH VIEW ============ -->
      <section v-if="currentView === 'month'" class="schedule-section">
        <div class="calendar-card">
          <div class="calendar-header">
            <button class="icon-button" aria-label="Previous month" @click="changeMonth(-1)">
              ‹
            </button>
            <h2>{{ monthName }} {{ currentDate.getFullYear() }}</h2>
            <button class="icon-button" aria-label="Next month" @click="changeMonth(1)">
              ›
            </button>
          </div>

          <div class="calendar-weekdays">
            <span v-for="name in weekdayNames" :key="name">{{ name }}</span>
          </div>

          <div class="calendar-grid">
            <div v-for="blank in firstDayOfMonth" :key="'blank-' + blank" class="calendar-day empty"></div>

            <button v-for="day in daysInMonth" :key="day" class="calendar-day" :class="{
              scheduled: isScheduled(day),
              today: isToday(day)
            }" @click="selectedDate = day">
              <span class="day-number">{{ day }}</span>
              <span v-if="isScheduled(day)" class="event-dot"></span>
            </button>
          </div>
        </div>

        <!-- Selected day preview -->
        <div v-if="selectedTrip" class="selected-trip-card">
          <div class="selected-trip-top">
            <div>
              <span class="small-label">Scheduled trip</span>
              <h3>{{ selectedTrip.destination }}</h3>
            </div>
            <span class="status approved">
              {{ selectedTrip.trip_status || 'Scheduled' }}
            </span>
          </div>

          <div class="trip-summary">
            <span>{{ formatDate(selectedTrip.trip_date) }}</span>
            <span>{{ formatTime(selectedTrip.departure_time) }}</span>
          </div>

          <p class="trip-purpose">{{ selectedTrip.purpose }}</p>

          <button class="details-button" @click="openTripModal(selectedTrip)">
            View details
          </button>
        </div>

        <div v-else class="empty-selection">
          <p>Tap a highlighted date to see the trip for that day.</p>
        </div>

        <!-- Trips this month -->
        <div class="approved-section">
          <div class="section-heading">
            <h2>Scheduled trips this month</h2>
          </div>

          <div v-if="monthTrips.length" class="approved-list">
            <button v-for="trip in monthTrips" :key="trip.id" class="approved-trip" @click="openTripModal(trip)">
              <div class="approved-main">
                <div class="approved-title-row">
                  <strong>{{ trip.request_code || `TRIP-${trip.id}` }}</strong>
                  <span class="status approved">Approved</span>
                </div>

                <p class="requester">{{ requesterName(trip) }}</p>

                <div class="approved-info">
                  <span>{{ formatDate(trip.trip_date) }}</span>
                  <span>{{ formatTime(trip.departure_time) }}</span>
                </div>

                <div class="approved-info">
                  <span>{{ vehicleName(trip) }}</span>
                  <span>{{ driverName(trip) }}</span>
                </div>

                <p class="approved-purpose">{{ trip.purpose }}</p>
              </div>

              <span class="arrow">›</span>
            </button>
          </div>

          <div v-else class="no-trips">
            <p>No approved trips this month yet.</p>
          </div>
        </div>
      </section>

      <!-- ============ WEEK VIEW ============ -->
      <section v-if="currentView === 'week'" class="schedule-section">
        <div class="calendar-card week-card">
          <div class="calendar-header">
            <button class="icon-button" aria-label="Previous week" @click="changeWeek(-1)">
              ‹
            </button>
            <h2>{{ weekRange }}</h2>
            <button class="icon-button" aria-label="Next week" @click="changeWeek(1)">
              ›
            </button>
          </div>

          <div class="week-calendar">
            <div v-for="day in weekDays" :key="day.date" class="week-day">
              <button class="week-day-header" :class="{ active: selectedWeekDate === day.date }"
                @click="selectedWeekDate = day.date">
                <span>{{ day.name }}</span>
                <strong>{{ day.number }}</strong>
              </button>

              <div class="week-events">
                <button v-for="trip in getTripsForDate(day.date)" :key="trip.id" class="week-event"
                  @click="openTripModal(trip)">
                  <strong>{{ formatTime(trip.departure_time) }}</strong>
                  <span>{{ trip.destination }}</span>
                </button>

                <span v-if="!getTripsForDate(day.date).length" class="no-event">—</span>
              </div>
            </div>
          </div>
        </div>

        <div v-if="selectedWeekTrip" class="selected-trip-card">
          <div class="selected-trip-top">
            <div>
              <span class="small-label">Scheduled trip</span>
              <h3>{{ selectedWeekTrip.destination }}</h3>
            </div>
            <span class="status approved">
              {{ selectedWeekTrip.trip_status || 'Scheduled' }}
            </span>
          </div>

          <div class="trip-summary">
            <span>{{ formatDate(selectedWeekTrip.trip_date) }}</span>
            <span>{{ formatTime(selectedWeekTrip.departure_time) }}</span>
          </div>

          <p class="trip-purpose">{{ selectedWeekTrip.purpose }}</p>

          <button class="details-button" @click="openTripModal(selectedWeekTrip)">
            View details
          </button>
        </div>

        <div v-else class="empty-selection">
          <p>Tap a day to see the trip scheduled for it.</p>
        </div>
      </section>

      <!-- ============ LIST VIEW ============ -->
      <section v-if="currentView === 'list'" class="schedule-section">
        <div class="section-heading">
          <h2>Scheduled trips</h2>
        </div>

        <div v-if="scheduledTripList.length" class="trip-list">
          <button v-for="trip in scheduledTripList" :key="trip.id" class="list-trip-card" @click="openTripModal(trip)">
            <div class="list-trip-date">
              <strong>{{ dayNumber(trip.trip_date) }}</strong>
              <span>{{ shortMonth(trip.trip_date) }}</span>
            </div>

            <div class="list-trip-content">
              <div class="list-title-row">
                <h3>{{ trip.destination }}</h3>
                <span class="status approved">
                  {{ trip.trip_status || 'Scheduled' }}
                </span>
              </div>

              <p class="list-purpose">{{ trip.purpose }}</p>

              <div class="list-details">
                <span>{{ formatTime(trip.departure_time) }}</span>
                <span>{{ vehicleName(trip) }}</span>
                <span>{{ driverName(trip) }}</span>
              </div>
            </div>

            <span class="arrow">›</span>
          </button>
        </div>

        <div v-else class="no-trips">
          <p>No scheduled trips found.</p>
        </div>
      </section>
    </main>

    <!-- ============ TRIP DETAILS MODAL ============ -->
    <div v-if="activeTrip" class="modal-overlay" @click.self="closeTripModal">
      <div class="modal-card">
        <div class="sheet-handle"></div>

        <div class="modal-header">
          <div>
            <span class="small-label">Trip details</span>
            <h2>{{ activeTrip.destination }}</h2>
          </div>
          <button class="icon-button" aria-label="Close" @click="closeTripModal">×</button>
        </div>

        <div class="modal-status">
          <span class="status approved">
            {{ activeTrip.trip_status || 'Scheduled' }}
          </span>
        </div>

        <div class="detail-group">
          <div v-for="row in tripDetailRows" :key="row.label" class="detail-row">
            <span>{{ row.label }}</span>
            <strong>{{ row.value }}</strong>
          </div>
        </div>

        <button class="primary-button" @click="closeTripModal">Close</button>
      </div>
    </div>

    <!-- ============ EDIT TRIP MODAL ============ -->
    <div v-if="editingTrip" class="modal-overlay" @click.self="closeEditTrip">
      <div class="modal-card">
        <div class="sheet-handle"></div>

        <div class="modal-header">
          <div>
            <span class="small-label">Scheduled trip</span>
            <h2>Edit trip assignment</h2>
          </div>
          <button class="icon-button" aria-label="Close" @click="closeEditTrip">×</button>
        </div>

        <p class="modal-note">
          Only the assigned vehicle and driver can be changed. The approved request and trip details stay unchanged.
        </p>

        <div class="form-group">
          <div class="read-only-box">
            <span>Trip</span>
            <strong>{{ formatDate(editingTrip.trip_date) }} · {{ formatTime(editingTrip.departure_time) }}</strong>
          </div>

          <label class="form-label">
            Vehicle
            <select v-model="editingTrip.vehicle_id">
              <option value="">Select vehicle</option>
              <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id"
                :disabled="isVehicleUnavailable(vehicle.id, editingTrip)">
                {{ vehicleOptionLabel(vehicle, editingTrip) }}
              </option>
            </select>
          </label>

          <label class="form-label">
            Driver
            <select v-model="editingTrip.driver_id">
              <option value="">Select driver</option>
              <option v-for="driver in drivers" :key="driver.id" :value="driver.id"
                :disabled="isDriverUnavailable(driver.id, editingTrip)">
                {{ driverOptionLabel(driver, editingTrip) }}
              </option>
            </select>
          </label>
        </div>

        <p v-if="editTripError" class="error-message">{{ editTripError }}</p>

        <button class="primary-button" :disabled="savingEdit" @click="saveEditTrip">
          {{ savingEdit ? 'Saving...' : 'Save changes' }}
        </button>
      </div>
    </div>

    <!-- ============ ADD TRIP MODAL ============ -->
    <div v-if="showAddTripModal" class="modal-overlay" @click.self="showAddTripModal = false">
      <div class="modal-card">
        <div class="sheet-handle"></div>

        <div class="modal-header">
          <div>
            <span class="small-label">Trip schedule</span>
            <h2>Add trip</h2>
          </div>
          <button class="icon-button" aria-label="Close" @click="showAddTripModal = false">
            ×
          </button>
        </div>

        <p class="modal-note">
          Select an approved vehicle request. The request details are read-only because they were submitted by the
          requester.
        </p>

        <div class="form-group">
          <label class="form-label">
            Approved Vehicle Request
            <select v-model="newTrip.vehicle_request_id" @change="selectApprovedRequest(newTrip.vehicle_request_id)">
              <option value="">Select approved request</option>
              <option v-for="request in approvedRequests" :key="request.id" :value="request.id">
                #{{ request.id }} · {{ request.requester_name || requesterName(request) }} · {{ request.destination }}
              </option>
            </select>
          </label>

          <div v-if="newTrip.vehicle_request_id" class="request-preview">
            <div class="request-preview-title">Approved request details</div>
            <div class="detail-row">
              <span>Requester</span>
              <strong>{{ selectedRequest?.requester_name || '—' }}</strong>
            </div>
            <div class="detail-row">
              <span>Date</span>
              <strong>{{ formatDate(newTrip.trip_date) }}</strong>
            </div>
            <div class="detail-row">
              <span>Departure</span>
              <strong>{{ formatTime(newTrip.departure_time) }}</strong>
            </div>
            <div class="detail-row">
              <span>Estimated return</span>
              <strong>{{ formatTime(newTrip.estimated_return_time) }}</strong>
            </div>
            <div class="detail-row">
              <span>Destination</span>
              <strong>{{ newTrip.destination || '—' }}</strong>
            </div>
            <div class="detail-row">
              <span>Purpose</span>
              <strong>{{ newTrip.purpose || '—' }}</strong>
            </div>
          </div>

          <label class="form-label">
            Vehicle
            <select v-model="newTrip.vehicle_id">
              <option value="">Select vehicle</option>
              <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id"
                :disabled="isVehicleUnavailable(vehicle.id, newTrip)">
                {{ vehicleOptionLabel(vehicle, newTrip) }}
              </option>
            </select>
          </label>

          <label class="form-label">
            Driver
            <select v-model="newTrip.driver_id">
              <option value="">Select driver</option>
              <option v-for="driver in drivers" :key="driver.id" :value="driver.id"
                :disabled="isDriverUnavailable(driver.id, newTrip)">
                {{ driverOptionLabel(driver, newTrip) }}
              </option>
            </select>
          </label>
        </div>

        <p v-if="addTripError" class="error-message">{{ addTripError }}</p>

        <button class="primary-button" :disabled="savingTrip" @click="addTrip">
          {{ savingTrip ? 'Saving...' : 'Save trip' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'

const API_URL = 'http://127.0.0.1:8000/api'

const views = ['month', 'week', 'list']
const weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

/* ================= STATE ================= */

const currentView = ref('month')
const currentDate = ref(new Date())
const weekStart = ref(getSunday(new Date()))

const selectedDate = ref(null)      // day number inside the current month
const selectedWeekDate = ref(null)  // 'YYYY-MM-DD'

const scheduledTrips = ref([])
const vehicles = ref([])
const drivers = ref([])
const approvedRequests = ref([])

const activeTrip = ref(null)
const editingTrip = ref(null)
const showAddTripModal = ref(false)
const savingTrip = ref(false)
const addTripError = ref('')
const editTripError = ref('')
const loadError = ref('')
const savingEdit = ref(false)

const newTrip = ref(emptyTrip())

function emptyTrip() {
  return {
    vehicle_request_id: '',
    vehicle_id: '',
    driver_id: '',
    trip_date: '',
    departure_time: '',
    estimated_return_time: '',
    destination: '',
    purpose: ''
  }
}

/* ================= DATA LOADING ================= */

async function loadList(path, target) {
  try {
    const response = await fetch(`${API_URL}/${path}`)
    if (!response.ok) throw new Error(`Unable to load ${path}.`)
    target.value = await response.json()
  } catch (error) {
    console.error(`${path} error:`, error)
    if (path === 'trips') {
      loadError.value = 'Unable to load scheduled trips. Please try again.'
    }
  }
}

async function fetchTrips() {
  loadError.value = ''
  await loadList('trips', scheduledTrips)
  autoSelectScheduledDate()
}

onMounted(() => {
  fetchTrips()
  loadList('vehicles', vehicles)
  loadList('drivers', drivers)
  loadApprovedRequests()
})

async function loadApprovedRequests() {
  try {
    const response = await fetch(`${API_URL}/vehicle-requests/approved`)
    if (!response.ok) throw new Error('Unable to load approved vehicle requests.')
    approvedRequests.value = await response.json()
  } catch (error) {
    console.error('approved vehicle requests error:', error)
    addTripError.value = 'Unable to load approved vehicle requests. Please try again.'
  }
}

function selectApprovedRequest(requestId) {
  const request = approvedRequests.value.find(item => item.id === Number(requestId))
  if (!request) {
    newTrip.value = { ...emptyTrip() }
    return
  }

  newTrip.value = {
    ...newTrip.value,
    vehicle_request_id: request.id,
    trip_date: request.trip_date || '',
    departure_time: request.departure_time || '',
    estimated_return_time: request.estimated_return_time || '',
    destination: request.destination || '',
    purpose: request.purpose || ''
  }
}

function openAddTripModal() {
  addTripError.value = ''
  newTrip.value = emptyTrip()
  loadApprovedRequests()
  loadList('vehicles', vehicles)
  loadList('drivers', drivers)
  showAddTripModal.value = true
}

/* ================= MONTH ================= */

const monthName = computed(() =>
  currentDate.value.toLocaleString('default', { month: 'long' })
)

const daysInMonth = computed(() =>
  new Date(
    currentDate.value.getFullYear(),
    currentDate.value.getMonth() + 1,
    0
  ).getDate()
)

const firstDayOfMonth = computed(() =>
  new Date(
    currentDate.value.getFullYear(),
    currentDate.value.getMonth(),
    1
  ).getDay()
)

function dateForDay(day) {
  return toDateString(
    new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), day)
  )
}

function changeMonth(offset) {
  currentDate.value = new Date(
    currentDate.value.getFullYear(),
    currentDate.value.getMonth() + offset,
    1
  )
  autoSelectScheduledDate()
}

function isScheduled(day) {
  return getTripsForDate(dateForDay(day)).length > 0
}

function isToday(day) {
  return dateForDay(day) === toDateString(new Date())
}

const selectedTrip = computed(() =>
  selectedDate.value
    ? getTripsForDate(dateForDay(selectedDate.value))[0] || null
    : null
)

const monthTrips = computed(() =>
  scheduledTripList.value.filter(trip => {
    const date = parseDate(trip.trip_date)
    return (
      date.getFullYear() === currentDate.value.getFullYear() &&
      date.getMonth() === currentDate.value.getMonth()
    )
  })
)

function autoSelectScheduledDate() {
  selectedDate.value = null
}

/* ================= WEEK ================= */

const weekDays = computed(() =>
  Array.from({ length: 7 }, (_, i) => {
    const date = new Date(weekStart.value)
    date.setDate(weekStart.value.getDate() + i)

    return {
      date: toDateString(date),
      number: date.getDate(),
      name: date.toLocaleDateString('en-US', { weekday: 'short' })
    }
  })
)

const weekRange = computed(() => {
  const start = weekStart.value
  const end = new Date(start)
  end.setDate(start.getDate() + 6)

  const endLabel =
    start.getMonth() === end.getMonth()
      ? end.getDate()
      : `${shortMonthName(end)} ${end.getDate()}`

  return `${shortMonthName(start)} ${start.getDate()}–${endLabel}, ${end.getFullYear()}`
})

function changeWeek(offset) {
  const date = new Date(weekStart.value)
  date.setDate(date.getDate() + offset * 7)

  weekStart.value = getSunday(date)
  selectedWeekDate.value = null
}

const selectedWeekTrip = computed(() =>
  selectedWeekDate.value ? getTripsForDate(selectedWeekDate.value)[0] || null : null
)

function isScheduledTrip(trip) {
  return String(trip?.trip_status || '').toLowerCase() === 'scheduled'
}

const scheduledTripList = computed(() =>
  scheduledTrips.value.filter(isScheduledTrip)
)

function getTripsForDate(date) {
  return scheduledTrips.value.filter(trip => {
    const tripDate = String(trip?.trip_date || '').slice(0, 10)
    return tripDate === date && isScheduledTrip(trip)
  })
}

/* ================= TRIP MODAL ================= */

function openTripModal(trip) {
  activeTrip.value = trip
}

function closeTripModal() {
  activeTrip.value = null
}

const selectedRequest = computed(() =>
  approvedRequests.value.find(item => item.id === Number(newTrip.value.vehicle_request_id)) || null
)

const tripDetailRows = computed(() => {
  const trip = activeTrip.value
  if (!trip) return []

  return [
    { label: 'Requester', value: requesterName(trip) },
    { label: 'Date', value: formatDate(trip.trip_date) },
    { label: 'Departure', value: formatTime(trip.departure_time) },
    { label: 'Estimated return', value: formatTime(trip.estimated_return_time) },
    { label: 'Vehicle', value: vehicleName(trip) },
    { label: 'Driver', value: driverName(trip) },
    { label: 'Purpose', value: trip.purpose },
    { label: 'Destination', value: trip.destination }
  ]
})

function startEditTrip() {
  if (!activeTrip.value) return

  editTripError.value = ''
  editingTrip.value = {
    ...activeTrip.value,
    vehicle_id: String(activeTrip.value.vehicle_id),
    driver_id: String(activeTrip.value.driver_id)
  }
  activeTrip.value = null
  loadList('vehicles', vehicles)
  loadList('drivers', drivers)
}

function closeEditTrip() {
  if (savingEdit.value) return
  editingTrip.value = null
  editTripError.value = ''
}

function hasTimeConflict(trip, candidateDate, candidateStart, candidateEnd, ignoreTripId = null) {
  if (!trip || !candidateDate || !candidateStart) return false
  if (trip.trip_date !== candidateDate) return false
  if (ignoreTripId && Number(trip.id) === Number(ignoreTripId)) return false
  if (!['scheduled', 'in_progress'].includes(String(trip.trip_status || '').toLowerCase())) return false

  const existingStart = String(trip.departure_time || '').slice(0, 5)
  const existingEnd = String(trip.estimated_return_time || '23:59:59').slice(0, 5)
  const start = String(candidateStart).slice(0, 5)
  const end = String(candidateEnd || '23:59').slice(0, 5)

  return start < existingEnd && end > existingStart
}

function isVehicleUnavailable(vehicleId, candidate) {
  if (!candidate?.trip_date) return false

  const vehicle = vehicles.value.find(item => Number(item.id) === Number(vehicleId))
  if (vehicle && (String(vehicle.status).toLowerCase() !== 'available' || Number(vehicle.is_active) === 0)) return true

  return scheduledTrips.value.some(trip =>
    Number(trip.vehicle_id) === Number(vehicleId) &&
    hasTimeConflict(trip, candidate.trip_date, candidate.departure_time, candidate.estimated_return_time, candidate.id)
  )
}

function isDriverUnavailable(driverId, candidate) {
  if (!candidate?.trip_date) return false

  const driver = drivers.value.find(item => Number(item.id) === Number(driverId))
  if (driver && Number(driver.is_available) === 0) return true

  return scheduledTrips.value.some(trip =>
    Number(trip.driver_id) === Number(driverId) &&
    hasTimeConflict(trip, candidate.trip_date, candidate.departure_time, candidate.estimated_return_time, candidate.id)
  )
}

function vehicleAvailabilityLabel(vehicle, candidate) {
  if (Number(vehicle.is_active) === 0) return 'Inactive'
  if (String(vehicle.status).toLowerCase() === 'maintenance') return 'Maintenance'
  if (String(vehicle.status).toLowerCase() === 'retired') return 'Retired'
  if (String(vehicle.status).toLowerCase() === 'in_use') return 'In use'
  if (isVehicleUnavailable(vehicle.id, candidate)) return 'Scheduled / unavailable'
  return 'Available'
}

function driverAvailabilityLabel(driver, candidate) {
  if (Number(driver.is_available) === 0) return 'Unavailable'
  if (isDriverUnavailable(driver.id, candidate)) return 'Scheduled / unavailable'
  return 'Available'
}

function vehicleOptionLabel(vehicle, candidate) {
  return `${vehicle.vehicle_model} - ${vehicle.plate_number} · ${vehicleAvailabilityLabel(vehicle, candidate)}`
}

function driverOptionLabel(driver, candidate) {
  return `${driverLabel(driver)} · ${driverAvailabilityLabel(driver, candidate)}`
}

/* ================= HELPERS ================= */

function toDateString(date) {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

function parseDate(dateString) {
  const value = String(dateString || '').slice(0, 10)
  return value ? new Date(`${value}T00:00:00`) : new Date('Invalid')
}

function getSunday(date) {
  const result = new Date(date)
  result.setHours(0, 0, 0, 0)
  result.setDate(result.getDate() - result.getDay())
  return result
}

function shortMonthName(date) {
  return date.toLocaleString('default', { month: 'short' })
}

function fullName(person) {
  if (!person) return ''
  return [person.first_name, person.last_name].filter(Boolean).join(' ')
}

function formatDate(dateString) {
  if (!dateString) return '—'
  return parseDate(dateString).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  })
}

function formatTime(timeString) {
  if (!timeString) return '—'

  const [hours, minutes] = timeString.split(':')
  const date = new Date()
  date.setHours(Number(hours), Number(minutes), 0)

  return date.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit'
  })
}

function dayNumber(dateString) {
  return parseDate(dateString).getDate()
}

function shortMonth(dateString) {
  return shortMonthName(parseDate(dateString))
}

function vehicleName(trip) {
  const vehicle =
    trip?.vehicle || vehicles.value.find(item => item.id === trip?.vehicle_id)

  if (!vehicle) return '—'

  return (
    [vehicle.vehicle_model, vehicle.plate_number].filter(Boolean).join(' - ') || '—'
  )
}

function driverLabel(driver) {
  return fullName(driver) || '—'
}

function driverName(trip) {
  const driver =
    trip?.driver || drivers.value.find(item => item.id === trip?.driver_id)

  return driverLabel(driver)
}

function requesterName(trip) {
  return (
    trip?.requester_name ||
    fullName(trip?.requester || trip?.vehicle_request?.requester) ||
    'Approved trip'
  )
}
</script>

<style>
/* =========================================================
   DESIGN TOKENS
   Font: Inter
   Page title 24 / Section 20 / Normal 16 / Small 14
   Control height 44 · Page padding 20 · Card padding 16
   Radius 10 · Icon 24
   Primary #27AF30 · Secondary #FFA500 · Grey #DBDBDB
   ========================================================= */

:root {
  --primary: #27af30;
  --primary-dark: #1d8725;
  /* pressed / header bar, same hue */
  --primary-tint: #e9f7ea;
  /* 10% primary, for chips and fills */
  --secondary: #ffa500;
  --secondary-tint: #fff4e0;

  --grey: #dbdbdb;
  /* borders and dividers */
  --grey-bg: #f6f6f6;
  /* page background */
  --text: #1a1a1a;
  /* normal text */
  --text-muted: #6b6b6b;
  /* small / supporting text */

  --text-page: 24px;
  --text-section: 20px;
  --text-normal: 16px;
  --text-small: 14px;

  --control: 44px;
  --pad-page: 20px;
  --pad-card: 16px;
  --radius: 10px;
  --icon: 24px;
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  font-family: Inter, system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
  font-size: var(--text-normal);
  line-height: 1.5;
  background: var(--grey-bg);
  color: var(--text);
  -webkit-font-smoothing: antialiased;
}

button,
input,
select,
textarea {
  font-family: inherit;
  font-size: var(--text-normal);
}

button {
  cursor: pointer;
}

:focus-visible {
  outline: 3px solid var(--primary);
  outline-offset: 2px;
}

.app {
  min-height: 100vh;
  min-height: 100dvh;
}

/* ================= PAGE ================= */

.page {
  width: 100%;
  max-width: 900px;
  margin: 0 auto;
  padding: var(--pad-page);
  padding-bottom: calc(40px + env(safe-area-inset-bottom, 0px));
}

.page-heading h1 {
  margin: 0;
  font-size: var(--text-page);
  line-height: 1.2;
  font-weight: 700;
}

.page-heading p {
  margin: 6px 0 0;
  font-size: var(--text-small);
  color: var(--text);
  padding-bottom: calc(40px + env(safe-area-inset-bottom, 0px));
}


/* Main headings */
.page-heading h1,
.calendar-header h2,
.section-heading h2,
.modal-header h2 {
  color: #000;
}

/* ================= BUTTONS ================= */

.primary-button {
  margin-top: 18px;
}

.primary-button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.icon {
  font-size: var(--icon);
  line-height: 1;
  vertical-align: -2px;
}

.details-button {
  width: 100%;
  height: var(--control);
  border: 1.5px solid var(--primary);
  border-radius: var(--radius);
  background: #fff;
  color: var(--primary-dark);
  font-size: var(--text-normal);
  font-weight: 700;
}

.icon-button {
  width: var(--control);
  height: var(--control);
  flex-shrink: 0;
  border: none;
  border-radius: var(--radius);
  background: var(--grey-bg);
  color: var(--text);
  font-size: var(--icon);
  line-height: 1;
}

/* ================= VIEW SWITCHER ================= */

.view-buttons {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 4px;
  padding: 4px;
  margin-bottom: 20px;
  background: var(--grey);
  border-radius: var(--radius);
}

.view-buttons button {
  height: var(--control);
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--text);
  font-size: var(--text-small);
  font-weight: 600;
}

.view-buttons button.active {
  background: #fff;
  color: var(--primary-dark);
}

/* ================= SECTIONS ================= */

.schedule-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.section-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.section-heading h2 {
  margin: 0;
  font-size: var(--text-section);
  font-weight: 700;
}

.trip-count {
  min-width: 30px;
  height: 28px;
  padding: 0 10px;
  border-radius: 999px;
  background: var(--primary-tint);
  color: var(--primary-dark);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: var(--text-small);
  font-weight: 700;
}

/* ================= CALENDAR ================= */

.calendar-card {
  background: #fff;
  border: 1px solid var(--grey);
  border-radius: var(--radius);
  padding: var(--pad-card);
}

.calendar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 14px;
}

.calendar-header h2 {
  margin: 0;
  font-size: var(--text-section);
  font-weight: 700;
  text-align: center;
}

.calendar-weekdays,
.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
}

.calendar-weekdays {
  margin-bottom: 6px;
}

.calendar-weekdays span {
  text-align: center;
  font-size: var(--text-small);
  color: var(--text);
  font-weight: 600;
}

.calendar-grid {
  gap: 4px;
}

.calendar-day {
  position: relative;
  aspect-ratio: 1;
  min-height: 42px;
  border: none;
  border-radius: var(--radius);
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text);
}

.calendar-day:hover {
  background: var(--grey-bg);
}

.calendar-day.empty {
  pointer-events: none;
}

/* today uses the secondary colour so it never competes with "scheduled" */
.calendar-day.today {
  box-shadow: inset 0 0 0 2px var(--secondary);
  font-weight: 700;
}

.calendar-day.scheduled {
  background: var(--primary-tint);
  color: var(--primary-dark);
  font-weight: 700;
}


.day-number {
  font-size: var(--text-normal);
}

.event-dot {
  position: absolute;
  bottom: 6px;
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--primary);
}

/* ================= TRIP CARDS ================= */

.selected-trip-card {
  background: #fff;
  border: 1px solid var(--grey);
  border-left: 4px solid var(--primary);
  border-radius: var(--radius);
  padding: var(--pad-card);
}

.selected-trip-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.selected-trip-top h3 {
  margin: 3px 0 0;
  font-size: var(--text-section);
  font-weight: 700;
}

.small-label {
  display: block;
  font-size: var(--text-small);
  color: var(--text);
  font-weight: 600;
}

.status {
  display: inline-flex;
  align-items: center;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: var(--text-small);
  font-weight: 700;
  white-space: nowrap;
}

.status.approved {
  color: var(--primary-dark);
  background: var(--primary-tint);
}

.status.pending {
  color: #8a5a00;
  background: var(--secondary-tint);
}

.trip-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 16px;
  margin-top: 12px;
  font-size: var(--text-normal);
}

.trip-purpose {
  margin: 10px 0 14px;
  font-size: var(--text-small);
  color: var(--text-muted);
}

.empty-selection,
.no-trips {
  padding: 24px var(--pad-card);
  background: #fff;
  border: 1px dashed var(--grey);
  border-radius: var(--radius);
  text-align: center;
  color: var(--text-muted);
  font-size: var(--text-small);
}

.empty-selection p,
.no-trips p {
  margin: 0;
}

.approved-list,
.trip-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 10px;
}

.approved-trip,
.list-trip-card {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: var(--pad-card);
  background: #fff;
  border: 1px solid var(--grey);
  border-radius: var(--radius);
  text-align: left;
  color: var(--text);
}

.approved-trip:active,
.list-trip-card:active {
  background: var(--grey-bg);
}

.approved-main,
.list-trip-content {
  min-width: 0;
  flex: 1;
}

.approved-title-row,
.list-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}

.approved-title-row strong {
  font-size: var(--text-normal);
}

.requester {
  margin: 3px 0 10px;
  font-size: var(--text-small);
  color: var(--text-muted);
}

.approved-info {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  margin-bottom: 4px;
  font-size: var(--text-small);
}

.approved-purpose {
  margin: 8px 0 0;
  font-size: var(--text-normal);
}

.arrow {
  font-size: var(--icon);
  line-height: 1;
  color: #9a9a9a;
}

/* ================= WEEK ================= */

.week-card {
  overflow: hidden;
}

.week-calendar {
  display: grid;
  grid-template-columns: repeat(7, minmax(92px, 1fr));
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  border: 1px solid var(--grey);
  border-radius: var(--radius);
}

.week-day {
  min-width: 92px;
  min-height: 200px;
  border-right: 1px solid var(--grey);
}

.week-day:last-child {
  border-right: none;
}

.week-day-header {
  width: 100%;
  min-height: 60px;
  padding: 8px 4px;
  border: none;
  border-bottom: 1px solid var(--grey);
  background: #fff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
}

.week-day-header span {
  font-size: var(--text-small);
  color: var(--text);
  font-weight: 600;
}

.week-day-header strong {
  font-size: var(--text-section);
}

.week-day-header.active {
  background: var(--primary);
}

.week-day-header.active span,
.week-day-header.active strong {
  color: #fff;
}

.week-events {
  padding: 6px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.week-event {
  width: 100%;
  padding: 8px;
  border: none;
  border-radius: 8px;
  background: var(--primary-tint);
  color: var(--primary-dark);
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.week-event strong,
.week-event span {
  font-size: var(--text-small);
  line-height: 1.3;
}

.no-event {
  padding-top: 10px;
  text-align: center;
  color: var(--grey);
  font-size: var(--text-normal);
}

/* ================= LIST ================= */

.list-trip-date {
  width: 52px;
  min-width: 52px;
  height: 58px;
  border-radius: var(--radius);
  background: var(--primary-tint);
  color: var(--primary-dark);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.list-trip-date strong {
  font-size: var(--text-section);
  line-height: 1;
}

.list-trip-date span {
  margin-top: 3px;
  font-size: var(--text-small);
  font-weight: 700;
}

.list-title-row h3 {
  margin: 0;
  font-size: var(--text-normal);
  font-weight: 700;
}

.list-purpose {
  margin: 5px 0 8px;
  font-size: var(--text-small);
  color: var(--text-muted);
}

.list-details {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  font-size: var(--text-small);
}

/* ================= MODAL (bottom sheet on phones) ================= */

.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 100;
  background: rgba(26, 26, 26, 0.5);
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.modal-card {
  width: 100%;
  max-height: 92dvh;
  overflow-y: auto;
  padding: 10px var(--pad-page);
  padding-bottom: calc(24px + env(safe-area-inset-bottom, 0px));
  background: #fff;
  border-radius: var(--radius) var(--radius) 0 0;
}

.sheet-handle {
  width: 40px;
  height: 4px;
  margin: 0 auto 14px;
  border-radius: 999px;
  background: var(--grey);
}

.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.modal-header h2 {
  margin: 3px 0 0;
  font-size: var(--text-section);
  font-weight: 700;
}

.modal-status {
  margin: 14px 0;
}

.detail-group {
  display: flex;
  flex-direction: column;
}

.detail-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 0;
  border-bottom: 1px solid var(--grey);
}

.detail-row span {
  flex-shrink: 0;
  font-size: var(--text-small);
  color: var(--text);
}

.detail-row strong {
  font-size: var(--text-normal);
  font-weight: 600;
  text-align: right;
}

.modal-note {
  margin: 12px 0 18px;
  padding: 12px;
  border-radius: var(--radius);
  background: var(--secondary-tint);
  border-left: 4px solid var(--secondary);
  font-size: var(--text-small);
}

/* ================= FORM ================= */

.form-group {
  display: flex;
  flex-direction: column;
  gap: 14px;
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
  font-size: var(--text-small);
  font-weight: 600;
  color: var(--text);
}

.form-label input,
.form-label select,
.form-label textarea {
  width: 100%;
  height: var(--control);
  padding: 10px 12px;
  border: 1px solid var(--grey);
  border-radius: var(--radius);
  outline: none;
  background: #fff;
  color: var(--text);
  font-size: var(--text-normal);
  /* 16px also stops iOS zoom on focus */
  font-weight: 400;
}

.form-label textarea {
  height: auto;
  min-height: 88px;
  resize: vertical;
}

.form-label input:focus,
.form-label select:focus,
.form-label textarea:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(39, 175, 48, 0.18);
}

.secondary-button,
.danger-button {
  height: var(--control);
  border-radius: var(--radius);
  font-size: var(--text-normal);
  font-weight: 700;
}

.secondary-button {
  border: 1px solid var(--primary);
  background: var(--primary-tint);
  color: var(--primary-dark);
}

.danger-button {
  border: 1px solid #d33;
  background: #fdecec;
  color: #b3261e;
}

.read-only-box,
.request-preview {
  padding: 12px;
  border: 1px solid var(--grey);
  border-radius: var(--radius);
  background: var(--grey-bg);
}

.read-only-box {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.read-only-box span,
.request-preview-title {
  font-size: var(--text-small);
  color: var(--text-muted);
  font-weight: 600;
}

.request-preview-title {
  margin-bottom: 4px;
  color: var(--primary-dark);
}

.request-preview .detail-row {
  padding: 8px 0;
}

.error-message {
  margin: 12px 0 0;
  padding: 10px 12px;
  border-radius: var(--radius);
  background: #fdecec;
  color: #b3261e;
  font-size: var(--text-small);
  font-weight: 600;
}

.load-error {
  margin-bottom: 16px;
  padding: 14px var(--pad-card);
  background: #fdecec;
  border: 1px solid #f0b8b8;
  border-radius: var(--radius);
  color: #8f1d1d;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.load-error p {
  margin: 0;
  font-size: var(--text-small);
  font-weight: 600;
}

.retry-button {
  flex-shrink: 0;
  height: 36px;
  padding: 0 14px;
  border: 1px solid #8f1d1d;
  border-radius: var(--radius);
  background: #fff;
  color: #8f1d1d;
  font-weight: 700;
}

/* ================= TABLET / DESKTOP ================= */

@media (min-width: 768px) {
  .calendar-day {
    aspect-ratio: auto;
    min-height: 64px;
  }

  .add-trip-button {
    width: auto;
    min-width: 180px;
    padding: 0 24px;
  }

  .approved-list,
  .trip-list {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
  }

  .modal-overlay {
    align-items: center;
    padding: var(--pad-page);
  }

  .modal-card {
    max-width: 460px;
    max-height: 88vh;
    border-radius: var(--radius);
    padding: 20px var(--pad-page) 24px;
  }

  .sheet-handle {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  * {
    transition: none !important;
  }
}
</style>
