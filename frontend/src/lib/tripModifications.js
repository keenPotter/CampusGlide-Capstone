import { formatDate, formatTime } from '@/lib/format'

function formatted(value, formatter = (item) => item) {
  return value === null || value === undefined || value === '' ? '—' : formatter(value)
}

function timeValue(value) {
  return value ? String(value).slice(0, 5) : ''
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
      label: 'Time of departure',
      oldValue: item.old_departure_time,
      newValue: item.new_departure_time,
      compare: timeValue,
      format: formatTime,
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
