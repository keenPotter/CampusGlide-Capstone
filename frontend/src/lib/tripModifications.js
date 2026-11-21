import { formatDate, formatTime, TRIP_TYPE_DESCRIPTIONS } from '@/lib/format'

function formatted(value, formatter = (item) => item) {
  return value === null || value === undefined || value === '' ? '—' : formatter(value)
}

function timeValue(value) {
  return value ? String(value).slice(0, 5) : ''
}

function passengerValue(value) {
  return value === null || value === undefined || value === '' ? '' : String(Number(value))
}

function stringValue(value) {
  return value === null || value === undefined ? '' : String(value)
}

export function tripModificationChanges(item) {
  const fields = [
    {
      label: 'Date of travel',
      oldValue: item.old_date,
      newValue: item.new_date,
      format: formatDate,
    },
    {
      label: 'End date of travel',
      oldValue: item.old_trip_end_date,
      newValue: item.new_trip_end_date,
      format: formatDate,
    },
    {
      label: 'Trip type',
      oldValue: item.old_trip_type,
      newValue: item.new_trip_type,
      compare: stringValue,
      format: (value) => TRIP_TYPE_DESCRIPTIONS[value] ?? value,
    },
    {
      label: 'Time of departure',
      oldValue: item.old_departure_time,
      newValue: item.new_departure_time,
      compare: timeValue,
      format: formatTime,
    },
    {
      label: 'Estimated return time',
      oldValue: item.old_estimated_return_time,
      newValue: item.new_estimated_return_time,
      compare: timeValue,
      format: formatTime,
    },
    {
      label: 'Destination / place',
      oldValue: item.old_destination,
      newValue: item.new_destination,
    },
    {
      label: 'Purpose',
      oldValue: item.old_purpose,
      newValue: item.new_purpose,
    },
    {
      label: 'Number of passengers',
      oldValue: item.old_number_of_passengers,
      newValue: item.new_number_of_passengers,
      compare: passengerValue,
    },
    {
      label: 'Authorized passenger(s)',
      oldValue: item.old_authorized_passengers,
      newValue: item.new_authorized_passengers,
      compare: stringValue,
    },
  ]

  return fields
    .map(({ label, oldValue, newValue, compare = stringValue, format }) => ({
      label,
      oldValue: formatted(oldValue, format),
      newValue: formatted(newValue, format),
      changed: compare(oldValue) !== compare(newValue),
    }))
}
