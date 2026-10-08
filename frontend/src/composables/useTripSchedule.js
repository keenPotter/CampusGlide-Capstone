import { computed, onMounted, ref } from 'vue'

export function useTripSchedule() {
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

return {
  views,
  weekdayNames,
  currentView,
  currentTripType,
  currentDate,
  scheduledTrips,
  activeTrip,
  loadError,
  currentTripTypeLabel,
  filteredTrips,
  scheduledTripList,
  upcomingTripCount,
  newTripCount,
  monthName,
  daysInMonth,
  firstDayOfMonth,
  weekDays,
  weekRange,
  monthTrips,
  tripDetailRows,
  fetchTrips,
  changeTripType,
  changeMonth,
  changeWeek,
  isToday,
  isScheduled,
  hasTripTypeOnDate,
  hasNewTripOnDate,
  openDateTrips,
  openDateString,
  getTripsForDate,
  openTripModal,
  closeTripModal,
  isNewTrip,
  tripTypeClass,
  tripTypeLabel,
  formatDateRange,
  requesterName,
  vehicleName,
  driverName,
  driverContact,
  formatTime,
  dayNumber,
  shortMonth,
}

}
