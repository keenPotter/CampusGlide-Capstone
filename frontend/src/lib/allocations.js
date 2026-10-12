import { formatDate, formatTime } from '@/lib/format'

/** Trip statuses that still block a vehicle or driver. */
export const ACTIVE_STATUSES = ['scheduled', 'in_progress']

function toMinutes(time) {
  if (!time) return null
  const [hours, minutes] = String(time).split(':')
  return Number(hours) * 60 + Number(minutes || 0)
}

// Two trips overlap when they are on the same date and their times cross.
function overlaps(a, b) {
  if (!a.trip?.trip_date || a.trip.trip_date !== b.trip?.trip_date) return false

  const aStart = toMinutes(a.trip.departure_time) ?? 0
  const bStart = toMinutes(b.trip.departure_time) ?? 0
  const aEnd = toMinutes(a.trip.estimated_return_time) ?? aStart + 1
  const bEnd = toMinutes(b.trip.estimated_return_time) ?? bStart + 1

  return aStart < bEnd && bStart < aEnd
}

/**
 * Finds active allocations that share a driver or a vehicle at overlapping times.
 * Returns { [allocationId]: { text, driverId, vehicleId } }.
 */
export function findConflicts(allocations) {
  const map = {}

  for (let i = 0; i < allocations.length; i++) {
    for (let j = i + 1; j < allocations.length; j++) {
      const a = allocations[i]
      const b = allocations[j]
      if (!overlaps(a, b)) continue

      const sameDriver = a.driver?.id && a.driver.id === b.driver?.id
      const sameVehicle = a.vehicle?.id && a.vehicle.id === b.vehicle?.id
      if (!sameDriver && !sameVehicle) continue

      const who = sameDriver ? a.driver.name : a.vehicle.vehicle_model
      const text = `${who} is assigned to 2 trips on ${formatDate(a.trip.trip_date)}`

      for (const item of [a, b]) {
        const entry = (map[item.id] ??= { text, driverId: null, vehicleId: null })
        if (sameDriver) entry.driverId = a.driver.id
        if (sameVehicle) entry.vehicleId = a.vehicle.id
      }
    }
  }

  return map
}

/**
 * State of a vehicle or driver on the dashboard.
 * green = free, orange = has a scheduled trip, red = in a conflict.
 * @param {'vehicle'|'driver'} kind
 */
export function resourceState(kind, id, activeAllocations, conflictIds) {
  if (conflictIds.has(id)) return 'red'
  return activeAllocations.some((allocation) => allocation[kind]?.id === id) ? 'orange' : 'green'
}

/** "Oct 12, 2026 · 7:30 AM" */
export function whenText(date, time) {
  return `${date ? formatDate(date) : '—'}${time ? ` · ${formatTime(time)}` : ''}`
}

/** 7 -> "REQ-007" */
export function requestNumber(id) {
  return `REQ-${String(id).padStart(3, '0')}`
}

/** The "conflict" details the backend sends with a 422 response, or null. */
export function conflictOf(error) {
  return error?.response?.status === 422 ? (error.response.data?.conflict ?? null) : null
}

/**
 * A trip request can be printed only for an approved request
 * that has a vehicle and a driver assigned. The backend checks this too.
 */
export function canPrint(allocation) {
  return (
    allocation?.vehicle_request?.status === 'approved' &&
    Boolean(allocation.vehicle?.id) &&
    Boolean(allocation.driver?.id)
  )
}