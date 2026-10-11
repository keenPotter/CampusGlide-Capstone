import { formatDateRange, fullName } from '@/lib/format'

const TRIP_TYPES = ['inclusive', 'exclusive']

function requestOf(trip) {
  // Laravel serializes vehicleRequest as vehicle_request by default.
  return trip?.vehicle_request || trip?.vehicleRequest || null
}

/** Returns 'inclusive', 'exclusive', or null. Missing values are never treated as Inclusive. */
export function tripType(trip) {
  const type = String(trip?.trip_type || requestOf(trip)?.trip_type || '')
    .trim()
    .toLowerCase()
  return TRIP_TYPES.includes(type) ? type : null
}

/** Trip Schedule only shows active trips that belong to approved requests. */
export function isScheduledTrip(trip) {
  const activeStatus = ['scheduled', 'in_progress'].includes(
    String(trip?.trip_status || '').toLowerCase(),
  )
  if (!activeStatus) return false

  const request = requestOf(trip)
  return Boolean(request && String(request.status || '').toLowerCase() === 'approved')
}

export function getTripStartDate(trip) {
  return String(trip?.trip_date || requestOf(trip)?.trip_date || '').slice(0, 10)
}

export function getTripEndDate(trip) {
  return String(
    trip?.trip_end_date || requestOf(trip)?.trip_end_date || getTripStartDate(trip),
  ).slice(0, 10)
}

export function dateIsWithinTrip(date, trip) {
  const start = getTripStartDate(trip)
  const end = getTripEndDate(trip)
  return Boolean(start) && date >= start && date <= end
}

export function rangesOverlap(startA, endA, startB, endB) {
  return startA <= endB && endA >= startB
}

export function tripDateRange(trip) {
  return formatDateRange(getTripStartDate(trip), getTripEndDate(trip))
}

export function requesterName(trip) {
  if (trip?.requester_name) return trip.requester_name

  const request = requestOf(trip)
  const requester =
    trip?.requester || trip?.requested_by || request?.requester || request?.requested_by
  return requester?.name || fullName(requester) || 'Approved trip'
}

export function vehicleName(trip) {
  const vehicle = trip?.vehicle
  if (!vehicle) return '—'
  return [vehicle.vehicle_model, vehicle.plate_number].filter(Boolean).join(' - ') || '—'
}

/** Works whether the driver's name is on driver.user or on the driver itself. */
export function driverName(trip) {
  const driver = trip?.driver
  const person = driver?.user ?? driver
  return person?.name || fullName(person) || '—'
}

export function driverContact(trip) {
  const driver = trip?.driver
  return driver?.user?.phone_number || driver?.phone_number || driver?.contact_number || '—'
}