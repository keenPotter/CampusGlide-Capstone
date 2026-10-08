<template>
  <div class="app">
    <main class="page">
      <div class="page-heading">
        <h1>Trip Schedule</h1>
        <p>View scheduled trips and allocation details</p>
      </div>

      <!-- Schedule type -->
      <div class="type-switcher" role="tablist" aria-label="Trip type">
        <button
          type="button"
          :class="['type-button', { active: currentTripType === 'inclusive' }]"
          @click="changeTripType('inclusive')"
        >
          <span class="type-dot inclusive-dot"></span>
          Inclusive
        </button>
        <button
          type="button"
          :class="['type-button', { active: currentTripType === 'exclusive' }]"
          @click="changeTripType('exclusive')"
        >
          <span class="type-dot exclusive-dot"></span>
          Exclusive
        </button>
      </div>

      <div class="legend">
        <span><i class="legend-dot inclusive-dot"></i> Inclusive schedule</span>
        <span><i class="legend-dot exclusive-dot"></i> Exclusive schedule</span>
      </div>

      <!-- View switcher -->
      <div class="view-buttons" role="tablist" aria-label="Schedule view">
        <button
          v-for="view in views"
          :key="view"
          type="button"
          role="tab"
          :aria-selected="currentView === view"
          :class="{ active: currentView === view }"
          @click="currentView = view"
        >
          {{ view.charAt(0).toUpperCase() + view.slice(1) }}
        </button>
      </div>

      <div v-if="loadError" class="load-error">
        <p>{{ loadError }}</p>
        <button type="button" class="retry-button" @click="fetchTrips">Try again</button>
      </div>

      <!-- Summary -->
      <section class="summary-card">
        <div class="summary-heading">
          <div>
            <span class="small-label">Schedule summary</span>
            <h2>{{ currentTripTypeLabel }} trips</h2>
          </div>
          <span v-if="newTripCount" class="new-summary-indicator" title="New scheduled trip" aria-label="New scheduled trip"></span>
        </div>

        <div class="summary-grid">
          <div class="summary-item">
            <strong>{{ filteredTrips.length }}</strong>
            <span>Total scheduled</span>
          </div>
          <div class="summary-item">
            <strong>{{ upcomingTripCount }}</strong>
            <span>Upcoming</span>
          </div>
          <div class="summary-item new-summary-item">
            <span class="new-summary-indicator large" title="Unviewed scheduled trips" aria-label="Unviewed scheduled trips"></span>
            <span>Unviewed scheduled trips</span>
          </div>
        </div>
      </section>

      <!-- MONTH VIEW -->
      <section v-if="currentView === 'month'" class="schedule-section">
        <div class="calendar-card">
          <div :class="['calendar-header', currentTripType]">
            <div class="calendar-title-group">
              <span class="calendar-type-label">{{ currentTripType === 'inclusive' ? 'Inclusive Trips' : 'Exclusive Trips' }}</span>
              <button type="button" class="icon-button" aria-label="Previous month" @click="changeMonth(-1)">‹</button>
              <h2>{{ monthName }} {{ currentDate.getFullYear() }}</h2>
              <button type="button" class="icon-button" aria-label="Next month" @click="changeMonth(1)">›</button>
            </div>
          </div>

          <div class="calendar-weekdays">
            <span v-for="name in weekdayNames" :key="name">{{ name }}</span>
          </div>

          <div class="calendar-grid">
            <div
              v-for="blank in firstDayOfMonth"
              :key="'blank-' + blank"
              class="calendar-day empty"
            ></div>

            <button
              v-for="day in daysInMonth"
              :key="day"
              type="button"
              class="calendar-day"
              :class="{
                scheduled: isScheduled(day),
                today: isToday(day),
                inclusive: hasTripTypeOnDate(day, 'inclusive'),
                exclusive: hasTripTypeOnDate(day, 'exclusive'),
                new: hasNewTripOnDate(day)
              }"
              :disabled="!isScheduled(day)"
              @click="openDateTrips(day)"
            >
              <span class="day-number">{{ day }}</span>
              <span v-if="isScheduled(day)" class="event-dot"></span>
              <span v-if="hasNewTripOnDate(day)" class="new-dot" title="New scheduled trip" aria-label="New scheduled trip"></span>
            </button>
          </div>
        </div>

        <div class="section-heading">
          <div>
            <h2>{{ currentTripTypeLabel }} scheduled trips</h2>
            <p class="section-note">{{ monthName }} {{ currentDate.getFullYear() }}</p>
          </div>
          <span class="trip-count">{{ monthTrips.length }}</span>
        </div>

        <div v-if="monthTrips.length" class="approved-list">
          <button
            v-for="trip in monthTrips"
            :key="trip.id"
            type="button"
            class="approved-trip"
            @click="openTripModal(trip)"
          >
            <div class="approved-main">
              <div class="approved-title-row">
                <strong>{{ trip.request_code || `TRIP-${trip.id}` }}</strong>
                <div class="badge-row">
                  <span v-if="isNewTrip(trip)" class="new-indicator" title="New scheduled trip" aria-label="New scheduled trip"></span>
                  <span :class="['type-badge', tripTypeClass(trip)]">{{ tripTypeLabel(trip) }}</span>
                </div>
              </div>

              <p class="requester">{{ requesterName(trip) }}</p>

              <div class="approved-info">
                <span>{{ formatDateRange(trip) }}</span>
                <span>{{ formatTime(trip.departure_time) }}</span>
              </div>

              <div class="approved-info">
                <span>{{ vehicleName(trip) }}</span>
                <span>{{ driverName(trip) }}</span>
              </div>

              <p class="approved-purpose">{{ trip.purpose || '—' }}</p>
            </div>
            <span class="arrow">›</span>
          </button>
        </div>

        <div v-else class="no-trips">
          <p>No {{ currentTripTypeLabel.toLowerCase() }} scheduled trips for this month.</p>
        </div>
      </section>

      <!-- WEEK VIEW -->
      <section v-if="currentView === 'week'" class="schedule-section">
        <div class="calendar-card week-card">
          <div :class="['calendar-header', currentTripType]">
            <div class="calendar-title-group">
              <span class="calendar-type-label">{{ currentTripType === 'inclusive' ? 'Inclusive Trips' : 'Exclusive Trips' }}</span>
              <button type="button" class="icon-button" aria-label="Previous week" @click="changeWeek(-1)">‹</button>
              <h2>{{ weekRange }}</h2>
              <button type="button" class="icon-button" aria-label="Next week" @click="changeWeek(1)">›</button>
            </div>
          </div>

          <div class="week-calendar">
            <div v-for="day in weekDays" :key="day.date" class="week-day">
              <button
                type="button"
                class="week-day-header"
                :class="{ active: getTripsForDate(day.date).length }"
                :disabled="!getTripsForDate(day.date).length"
                @click="openDateString(day.date)"
              >
                <span>{{ day.name }}</span>
                <strong>{{ day.number }}</strong>
              </button>

              <div class="week-events">
                <button
                  v-for="trip in getTripsForDate(day.date)"
                  :key="trip.id"
                  type="button"
                  :class="['week-event', tripTypeClass(trip)]"
                  @click="openTripModal(trip)"
                >
                  <div class="week-event-title">
                    <strong>{{ formatTime(trip.departure_time) }}</strong>
                    <span v-if="isNewTrip(trip)" class="new-indicator small" title="New scheduled trip" aria-label="New scheduled trip"></span>
                  </div>
                  <span>{{ trip.destination }}</span>
                </button>
                <span v-if="!getTripsForDate(day.date).length" class="no-event">—</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- LIST VIEW -->
      <section v-if="currentView === 'list'" class="schedule-section">
        <div class="section-heading">
          <div>
            <h2>{{ currentTripTypeLabel }} scheduled trips</h2>
            <p class="section-note">All allocated trips of this type</p>
          </div>
          <span class="trip-count">{{ scheduledTripList.length }}</span>
        </div>

        <div v-if="scheduledTripList.length" class="trip-list">
          <button
            v-for="trip in scheduledTripList"
            :key="trip.id"
            type="button"
            class="list-trip-card"
            @click="openTripModal(trip)"
          >
            <div class="list-trip-date">
              <strong>{{ dayNumber(trip.trip_date) }}</strong>
              <span>{{ shortMonth(trip.trip_date) }}</span>
            </div>

            <div class="list-trip-content">
              <div class="list-title-row">
                <h3>{{ trip.destination || 'Trip' }}</h3>
                <div class="badge-row">
                  <span v-if="isNewTrip(trip)" class="new-indicator" title="New scheduled trip" aria-label="New scheduled trip"></span>
                  <span :class="['type-badge', tripTypeClass(trip)]">{{ tripTypeLabel(trip) }}</span>
                </div>
              </div>

              <p class="list-purpose">{{ trip.purpose || '—' }}</p>

              <div class="list-details">
                <span>{{ formatDateRange(trip) }}</span>
                <span>{{ formatTime(trip.departure_time) }}</span>
                <span>{{ vehicleName(trip) }}</span>
                <span>{{ driverName(trip) }}</span>
              </div>
            </div>

            <span class="arrow">›</span>
          </button>
        </div>

        <div v-else class="no-trips">
          <p>No {{ currentTripTypeLabel.toLowerCase() }} scheduled trips found.</p>
        </div>
      </section>
    </main>

    <!-- TRIP DETAILS MODAL -->
    <div v-if="activeTrip" class="modal-overlay" @click.self="closeTripModal">
      <div class="modal-card">
        <div class="sheet-handle"></div>

        <div class="modal-header">
          <div>
            <div class="badge-row modal-badges">
              <span v-if="isNewTrip(activeTrip)" class="new-indicator" title="New scheduled trip" aria-label="New scheduled trip"></span>
              <span :class="['type-badge', tripTypeClass(activeTrip)]">{{ tripTypeLabel(activeTrip) }}</span>
            </div>
            <span class="small-label">Scheduled trip</span>
            <h2>{{ activeTrip.destination || 'Trip details' }}</h2>
          </div>
          <button type="button" class="icon-button" aria-label="Close" @click="closeTripModal">×</button>
        </div>

        <div class="modal-status">
          <span class="status approved">{{ activeTrip.trip_status || 'Scheduled' }}</span>
        </div>

        <div class="detail-group">
          <div v-for="row in tripDetailRows" :key="row.label" class="detail-row">
            <span>{{ row.label }}</span>
            <strong>{{ row.value }}</strong>
          </div>
        </div>

        <button type="button" class="primary-button" @click="closeTripModal">Close</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'

const API_URL = 'http://127.0.0.1:8000/api'
const views = ['month', 'week', 'list']
const weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const NAV_SEEN_KEY = 'campusglide_trip_schedule_nav_seen'
const VIEWED_KEY = 'campusglide_trip_schedule_viewed'

const currentView = ref('month')
const currentTripType = ref('inclusive')
const currentDate = ref(new Date())
const weekStart = ref(getSunday(new Date()))
const scheduledTrips = ref([])
const activeTrip = ref(null)
const loadError = ref('')

const currentTripTypeLabel = computed(() =>
  currentTripType.value === 'inclusive' ? 'Inclusive' : 'Exclusive'
)

function getStoredIds(key) {
  try {
    const value = JSON.parse(localStorage.getItem(key) || '[]')
    return Array.isArray(value) ? value.map(Number) : []
  } catch {
    return []
  }
}

function storeIds(key, ids) {
  localStorage.setItem(key, JSON.stringify([...new Set(ids.map(Number))]))
}

const navSeenIds = ref(getStoredIds(NAV_SEEN_KEY))
const viewedIds = ref(getStoredIds(VIEWED_KEY))

async function fetchTrips() {
  loadError.value = ''
  try {
    const response = await fetch(`${API_URL}/trips`, { headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error('Unable to load scheduled trips.')
    scheduledTrips.value = await response.json()
  } catch (error) {
    console.error(error)
    loadError.value = error.message || 'Unable to load scheduled trips.'
  }
}

onMounted(async () => {
  await fetchTrips()

  // Opening Trip Schedule clears only the navigation notification.
  const currentIds = scheduledTripList.value.map(trip => Number(trip.id))
  navSeenIds.value = [...new Set([...navSeenIds.value, ...currentIds])]
  storeIds(NAV_SEEN_KEY, navSeenIds.value)
})

const filteredTrips = computed(() =>
  scheduledTrips.value.filter(trip =>
    isScheduledTrip(trip) && tripType(trip) === currentTripType.value
  )
)

const scheduledTripList = computed(() =>
  [...filteredTrips.value].sort((a, b) => parseDate(getTripStartDate(a)) - parseDate(getTripStartDate(b)))
)

const upcomingTripCount = computed(() => {
  const today = parseDate(toDateString(new Date()))
  return filteredTrips.value.filter(trip => parseDate(getTripEndDate(trip)) >= today).length
})

const newTripCount = computed(() => filteredTrips.value.filter(isNewTrip).length)

function isNewTrip(trip) {
  return !viewedIds.value.includes(Number(trip?.id))
}

function markTripViewed(trip) {
  if (!trip?.id) return
  if (!viewedIds.value.includes(Number(trip.id))) {
    viewedIds.value = [...viewedIds.value, Number(trip.id)]
    storeIds(VIEWED_KEY, viewedIds.value)
  }
}

function changeTripType(type) {
  currentTripType.value = type
  activeTrip.value = null
}

/* ================= CALENDAR ================= */

const monthName = computed(() => currentDate.value.toLocaleString('default', { month: 'long' }))
const daysInMonth = computed(() => new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 0).getDate())
const firstDayOfMonth = computed(() => new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), 1).getDay())

function dateForDay(day) {
  return toDateString(new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), day))
}

function changeMonth(offset) {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + offset, 1)
}

function isToday(day) {
  return dateForDay(day) === toDateString(new Date())
}

function getTripsForDate(date) {
  return filteredTrips.value.filter(trip => dateIsWithinTrip(date, trip))
}

function isScheduled(day) {
  return getTripsForDate(dateForDay(day)).length > 0
}

function hasTripTypeOnDate(day, type) {
  return scheduledTrips.value.some(trip =>
    isScheduledTrip(trip) && tripType(trip) === type && dateIsWithinTrip(dateForDay(day), trip)
  )
}

function hasNewTripOnDate(day) {
  return getTripsForDate(dateForDay(day)).some(isNewTrip)
}

function openDateTrips(day) {
  const trips = getTripsForDate(dateForDay(day))
  if (!trips.length) return
  openTripModal(trips[0])
}

function openDateString(date) {
  const trips = getTripsForDate(date)
  if (!trips.length) return
  openTripModal(trips[0])
}

const monthTrips = computed(() =>
  scheduledTripList.value.filter(trip => {
    const monthStart = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), 1)
    const monthEnd = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 0)
    return rangesOverlap(getTripStartDate(trip), getTripEndDate(trip), toDateString(monthStart), toDateString(monthEnd))
  })
)

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
  const endLabel = start.getMonth() === end.getMonth()
    ? end.getDate()
    : `${shortMonthName(end)} ${end.getDate()}`
  return `${shortMonthName(start)} ${start.getDate()}–${endLabel}, ${end.getFullYear()}`
})

function changeWeek(offset) {
  const date = new Date(weekStart.value)
  date.setDate(date.getDate() + offset * 7)
  weekStart.value = getSunday(date)
}

/* ================= MODAL ================= */

function openTripModal(trip) {
  if (!trip) return
  // Open the modal first so the notification belongs to this specific trip.
  activeTrip.value = trip
  // Mark only this trip as viewed; other trips keep their indicators.
  markTripViewed(trip)
}

function closeTripModal() {
  activeTrip.value = null
}

const tripDetailRows = computed(() => {
  const trip = activeTrip.value
  if (!trip) return []

  return [
    { label: 'Trip type', value: tripTypeLabel(trip) },
    { label: 'Requester', value: requesterName(trip) },
    { label: 'Date', value: formatDateRange(trip) },
    { label: 'Departure', value: formatTime(trip.departure_time) },
    { label: 'Estimated return', value: formatTime(trip.estimated_return_time) },
    { label: 'Vehicle', value: vehicleName(trip) },
    { label: 'Driver', value: driverName(trip) },
    { label: 'Driver contact', value: driverContact(trip) },
    { label: 'Destination', value: trip.destination || '—' },
    { label: 'Purpose', value: trip.purpose || '—' },
    { label: 'Passengers', value: trip.vehicle_request?.number_of_passengers ?? '—' }
  ]
})

/* ================= TRIP DATA HELPERS ================= */

function isScheduledTrip(trip) {
  return ['scheduled', 'in_progress'].includes(String(trip?.trip_status || '').toLowerCase())
}

function tripType(trip) {
  return String(trip?.trip_type || trip?.vehicle_request?.trip_type || '').toLowerCase() === 'exclusive'
    ? 'exclusive'
    : 'inclusive'
}

function tripTypeLabel(trip) {
  return tripType(trip) === 'exclusive' ? 'Exclusive' : 'Inclusive'
}

function tripTypeClass(trip) {
  return tripType(trip) === 'exclusive' ? 'exclusive' : 'inclusive'
}

function getTripStartDate(trip) {
  return String(trip?.trip_date || trip?.vehicle_request?.trip_date || '').slice(0, 10)
}

function getTripEndDate(trip) {
  return String(
    trip?.trip_end_date ||
    trip?.vehicle_request?.trip_end_date ||
    getTripStartDate(trip)
  ).slice(0, 10)
}

function dateIsWithinTrip(date, trip) {
  const start = getTripStartDate(trip)
  const end = getTripEndDate(trip)
  return !!start && date >= start && date <= end
}

function rangesOverlap(startA, endA, startB, endB) {
  return startA <= endB && endA >= startB
}

function formatDateRange(trip) {
  const start = getTripStartDate(trip)
  const end = getTripEndDate(trip)
  if (!start) return '—'
  if (!end || start === end) return formatDate(start)
  return `${formatDate(start)} – ${formatDate(end)}`
}

function requesterName(trip) {
  const requester = trip?.requester || trip?.vehicle_request?.requester
  if (trip?.requester_name) return trip.requester_name
  if (requester?.name) return requester.name
  return [requester?.first_name, requester?.last_name].filter(Boolean).join(' ') || 'Approved trip'
}

function vehicleName(trip) {
  const vehicle = trip?.vehicle
  if (!vehicle) return '—'
  return [vehicle.vehicle_model, vehicle.plate_number].filter(Boolean).join(' - ') || '—'
}

function driverName(trip) {
  const driver = trip?.driver
  const user = driver?.user
  return user?.name || [user?.first_name, user?.last_name].filter(Boolean).join(' ') || '—'
}

function driverContact(trip) {
  return trip?.driver?.user?.phone_number || trip?.driver?.phone_number || trip?.driver?.contact_number || '—'
}

/* ================= DATE HELPERS ================= */

function toDateString(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function parseDate(dateString) {
  if (!dateString) return new Date(NaN)
  const [year, month, day] = String(dateString).slice(0, 10).split('-').map(Number)
  return new Date(year, month - 1, day)
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
  const [hours, minutes] = String(timeString).split(':')
  const date = new Date()
  date.setHours(Number(hours), Number(minutes), 0, 0)
  return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
}

function dayNumber(dateString) {
  return parseDate(dateString).getDate()
}

function shortMonth(dateString) {
  return shortMonthName(parseDate(dateString))
}
</script>

<style>
:root {
  --primary: #27af30;
  --primary-dark: #1d8725;
  --primary-tint: #e9f7ea;
  --secondary: #ffa500;
  --secondary-tint: #fff4e0;
  --grey: #dbdbdb;
  --grey-bg: #f6f6f6;
  --text: #1a1a1a;
  --text-muted: #6b6b6b;
  --text-page: 24px;
  --text-section: 20px;
  --text-normal: 16px;
  --text-small: 14px;
  --control: 44px;
  --pad-page: 20px;
  --pad-card: 16px;
  --radius: 10px;
}

* { box-sizing: border-box; }
body {
  margin: 0;
  font-family: Inter, system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
  font-size: var(--text-normal);
  line-height: 1.5;
  background: var(--grey-bg);
  color: var(--text);
  -webkit-font-smoothing: antialiased;
}
button, input, select, textarea { font-family: inherit; font-size: var(--text-normal); }
button { cursor: pointer; }
button:disabled { cursor: default; }
:focus-visible { outline: 3px solid var(--primary); outline-offset: 2px; }
.app { min-height: 100vh; min-height: 100dvh; }
.page { width: 100%; max-width: 900px; margin: 0 auto; padding: var(--pad-page); padding-bottom: calc(40px + env(safe-area-inset-bottom, 0px)); }
.page-heading h1 { margin: 0; font-size: var(--text-page); line-height: 1.2; font-weight: 700; color: #000; }
.page-heading p { margin: 6px 0 0; font-size: var(--text-small); color: var(--text); }

.type-switcher { display: grid; grid-template-columns: repeat(2, 1fr); gap: 4px; padding: 4px; margin: 18px 0 8px; background: var(--grey); border-radius: var(--radius); }
.type-button { height: var(--control); border: 0; border-radius: 8px; background: transparent; color: var(--text); font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; }
.type-button.active { background: #fff; color: var(--primary-dark); }
.type-dot, .legend-dot { display: inline-block; border-radius: 50%; flex: 0 0 auto; }
.type-dot { width: 9px; height: 9px; }
.legend-dot { width: 8px; height: 8px; margin-right: 5px; }
.inclusive-dot { background: var(--primary); }
.exclusive-dot { background: var(--secondary); }
.legend { display: flex; flex-wrap: wrap; gap: 8px 18px; margin: 8px 2px 14px; color: var(--text-muted); font-size: var(--text-small); }
.legend span { display: inline-flex; align-items: center; }

.view-buttons { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; padding: 4px; margin-bottom: 16px; background: var(--grey); border-radius: var(--radius); }
.view-buttons button { height: var(--control); border: 0; border-radius: 8px; background: transparent; color: var(--text); font-size: var(--text-small); font-weight: 600; }
.view-buttons button.active { background: #fff; color: var(--primary-dark); }

.summary-card { background: #fff; border: 1px solid var(--grey); border-radius: var(--radius); padding: var(--pad-card); margin-bottom: 16px; }
.summary-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.summary-heading h2 { margin: 2px 0 0; font-size: var(--text-section); color: #000; }
.summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 14px; }
.summary-item { padding: 10px; border-radius: 8px; background: var(--grey-bg); text-align: center; }
.summary-item strong { display: block; font-size: 20px; }
.summary-item span { display: block; margin-top: 2px; color: var(--text-muted); font-size: 12px; }
.new-summary-indicator, .new-indicator { display: inline-block; width: 9px; height: 9px; flex: 0 0 9px; border-radius: 50%; background: #d93025; }
.new-summary-indicator { width: 11px; height: 11px; flex-basis: 11px; }
.new-summary-indicator.large { width: 10px; height: 10px; flex-basis: 10px; margin-right: 4px; }
.new-summary-item { display: flex; align-items: center; gap: 7px; }
.new-indicator.small { width: 7px; height: 7px; flex-basis: 7px; }

.schedule-section { display: flex; flex-direction: column; gap: 16px; }
.section-heading { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.section-heading h2 { margin: 0; font-size: var(--text-section); font-weight: 700; color: #000; }
.section-note { margin: 3px 0 0; color: var(--text-muted); font-size: var(--text-small); }
.trip-count { min-width: 30px; height: 28px; padding: 0 10px; border-radius: 999px; background: var(--primary-tint); color: var(--primary-dark); display: inline-flex; align-items: center; justify-content: center; font-size: var(--text-small); font-weight: 700; }

.calendar-card { background: #fff; border: 1px solid var(--grey); border-radius: var(--radius); padding: 12px; }
.calendar-header {
  margin-bottom: 12px;
  padding: 7px 9px;
  border-radius: 8px;
  transition: background .15s ease;
}
.calendar-header.inclusive { background: #e8f1e9; border: 1px solid #c7dcc9; }
.calendar-header.exclusive { background: #f5ede1; border: 1px solid #e4d2b5; }
.calendar-title-group { display: flex; align-items: center; justify-content: center; gap: 6px; }
.calendar-type-label { margin-right: 8px; padding: 5px 9px; border-radius: 999px; font-size: var(--text-small); font-weight: 700; white-space: nowrap; }
.calendar-header.inclusive .calendar-type-label { background: var(--primary-tint); color: var(--primary-dark); }
.calendar-header.exclusive .calendar-type-label { background: var(--secondary-tint); color: #8a5a00; }
.calendar-title-group h2 { margin: 0 4px; font-size: var(--text-section); font-weight: 700; color: #000; text-align: center; }
.icon-button { width: var(--control); height: var(--control); flex-shrink: 0; border: 0; border-radius: var(--radius); background: var(--grey-bg); color: var(--text); font-size: 24px; line-height: 1; }
.icon-button:hover { background: var(--grey); }
.calendar-weekdays, .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
.calendar-weekdays { margin-bottom: 6px; }
.calendar-weekdays span { text-align: center; font-size: var(--text-small); color: var(--text); font-weight: 600; }
.calendar-grid { gap: 3px; }
.calendar-day { position: relative; height: 54px; min-height: 0; aspect-ratio: auto; border: 1px solid transparent; border-radius: var(--radius); background: #fff; display: flex; align-items: center; justify-content: center; color: var(--text); }
.calendar-day:not(:disabled):hover { background: var(--grey-bg); }
.calendar-day.empty { pointer-events: none; }
.calendar-day:disabled { opacity: .55; }
.calendar-day.today { box-shadow: inset 0 0 0 2px var(--secondary); font-weight: 700; }
.calendar-day.inclusive.scheduled { background: #edf5ee; color: #28712e; border-color: #c7dcc9; }
.calendar-day.exclusive.scheduled { background: #f8f1e7; color: #795d2f; border-color: #e4d2b5; }
.day-number { font-size: var(--text-normal); }
.event-dot { position: absolute; bottom: 6px; width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.new-dot { position: absolute; top: 5px; right: 6px; width: 8px; height: 8px; border-radius: 50%; background: #d93025; }

.badge-row { display: inline-flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 5px; }
.type-badge { display: inline-flex; align-items: center; min-height: 22px; padding: 0 8px; border-radius: 999px; font-size: 11px; font-weight: 800; white-space: nowrap; }
.type-badge.inclusive { background: var(--primary-tint); color: var(--primary-dark); }
.type-badge.exclusive { background: var(--secondary-tint); color: #8a5a00; }

.approved-list, .trip-list { display: flex; flex-direction: column; gap: 10px; }
.approved-trip, .list-trip-card { width: 100%; display: flex; align-items: center; gap: 12px; padding: var(--pad-card); background: #fff; border: 1px solid var(--grey); border-radius: var(--radius); text-align: left; color: var(--text); }
.approved-trip:hover, .list-trip-card:hover { background: var(--grey-bg); }
.approved-main, .list-trip-content { min-width: 0; flex: 1; }
.approved-title-row, .list-title-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
.approved-title-row strong { font-size: var(--text-normal); }
.requester { margin: 3px 0 10px; font-size: var(--text-small); color: var(--text-muted); }
.approved-info { display: flex; flex-wrap: wrap; gap: 4px 12px; margin-bottom: 4px; font-size: var(--text-small); }
.approved-purpose, .list-purpose { margin: 8px 0 0; font-size: var(--text-normal); }
.arrow { font-size: 28px; color: var(--text-muted); }
.no-trips { padding: 24px var(--pad-card); background: #fff; border: 1px dashed var(--grey); border-radius: var(--radius); text-align: center; color: var(--text-muted); font-size: var(--text-small); }
.no-trips p { margin: 0; }

.week-calendar { display: grid; grid-template-columns: repeat(7, minmax(90px, 1fr)); overflow-x: auto; gap: 6px; }
.week-day { min-width: 90px; border: 1px solid var(--grey); border-radius: 8px; overflow: hidden; background: #fff; }
.week-day-header { width: 100%; border: 0; background: var(--grey-bg); padding: 8px 4px; color: var(--text); }
.week-day-header span, .week-day-header strong { display: block; }
.week-day-header span { font-size: 12px; }
.week-day-header strong { font-size: 18px; }
.week-day-header.active { background: #edf5ee; color: var(--primary-dark); }
.week-day-header:disabled { opacity: .55; }
.week-events { min-height: 86px; padding: 5px; display: flex; flex-direction: column; gap: 5px; }
.week-event { border: 0; border-left: 3px solid var(--primary); border-radius: 6px; padding: 7px; background: var(--primary-tint); color: var(--text); text-align: left; }
.week-event.exclusive { border-left-color: var(--secondary); background: var(--secondary-tint); }
.week-event-title { display: flex; align-items: center; justify-content: space-between; gap: 4px; }
.week-event strong { font-size: 12px; }
.week-event span:not(.new-indicator) { display: block; margin-top: 2px; font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.no-event { text-align: center; color: #aaa; margin-top: 20px; }

.list-trip-date { width: 48px; flex: 0 0 48px; text-align: center; }
.list-trip-date strong { display: block; font-size: 22px; line-height: 1; }
.list-trip-date span { font-size: 12px; color: var(--text-muted); }
.list-title-row h3 { margin: 0; font-size: var(--text-normal); }
.list-details { display: flex; flex-wrap: wrap; gap: 5px 12px; margin-top: 8px; font-size: var(--text-small); color: var(--text-muted); }

.small-label { display: block; font-size: var(--text-small); color: var(--text-muted); font-weight: 600; }
.load-error { padding: 12px 14px; border: 1px solid #e0a0a0; border-radius: var(--radius); background: #fff3f3; color: #9b1c1c; margin-bottom: 16px; }
.load-error p { margin: 0 0 8px; }
.retry-button { border: 1px solid #c96b6b; background: #fff; color: #9b1c1c; border-radius: 8px; height: 38px; padding: 0 12px; font-weight: 700; }

.modal-overlay { position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(0,0,0,.45); }
.modal-card { width: min(100%, 560px); max-height: calc(100vh - 32px); overflow-y: auto; background: #fff; border-radius: 14px; padding: 18px; box-shadow: 0 18px 50px rgba(0,0,0,.2); }
.sheet-handle { width: 42px; height: 4px; margin: 0 auto 14px; border-radius: 999px; background: var(--grey); }
.modal-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.modal-header h2 { margin: 3px 0 0; font-size: var(--text-section); color: #000; }
.modal-badges { justify-content: flex-start; margin-bottom: 5px; }
.modal-status { margin-top: 12px; }
.status { display: inline-flex; align-items: center; padding: 5px 10px; border-radius: 999px; font-size: var(--text-small); font-weight: 700; white-space: nowrap; }
.status.approved { color: var(--primary-dark); background: var(--primary-tint); }
.detail-group { margin-top: 14px; border-top: 1px solid var(--grey); }
.detail-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 11px 0; border-bottom: 1px solid var(--grey); }
.detail-row span { color: var(--text-muted); font-size: var(--text-small); }
.detail-row strong { max-width: 62%; text-align: right; font-size: var(--text-small); overflow-wrap: anywhere; }
.primary-button { width: 100%; height: var(--control); margin-top: 18px; border: 0; border-radius: var(--radius); background: var(--primary); color: #fff; font-weight: 700; }
.primary-button:hover { background: var(--primary-dark); }

@media (min-width: 768px) {
  .page { max-width: 1120px; padding: 24px 32px; }
  .page-heading h1 { font-size: 28px; }
  .summary-grid { grid-template-columns: repeat(3, minmax(120px, 180px)); }
  .calendar-title-group { gap: 8px; }
  .calendar-title-group h2 { min-width: 190px; }
  .calendar-day { min-height: 58px; }
  .week-calendar { overflow-x: visible; }
}

@media (max-width: 640px) {
  .summary-grid { grid-template-columns: repeat(3, 1fr); }
  .summary-item { padding: 8px 4px; }
  .summary-item strong { font-size: 18px; }
  .calendar-day { min-height: 40px; }
  .approved-title-row, .list-title-row { flex-direction: column; }
  .badge-row { justify-content: flex-start; }
  .detail-row { gap: 10px; }
  .detail-row strong { max-width: 58%; }
}
</style>

<style scoped>
@media (max-width: 700px) { .calendar-day { height: 46px; } }
</style>
