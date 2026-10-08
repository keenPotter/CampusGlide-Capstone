const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

function dateParts(value) {
  const s = String(value)

  // ISO na may timezone (hal. 2026-10-04T01:23:45.000000Z): gamitin ang lokal na petsa.
  if (/T.*(Z|[+-]\d{2}:?\d{2})$/.test(s)) {
    const d = new Date(s)
    if (!isNaN(d)) return { y: d.getFullYear(), m: d.getMonth() + 1, d: d.getDate() }
  }

  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/)
  return m ? { y: +m[1], m: +m[2], d: +m[3] } : null
}

// '2026-10-15' -> 'October 15, 2026'
export function formatDate(value) {
  if (!value) return '—'
  const p = dateParts(value)
  return p ? `${MONTHS[p.m - 1]} ${p.d}, ${p.y}` : String(value)
}

// '08:00:00' -> '8:00 AM'
export function formatTime(value) {
  if (!value) return '—'
  const m = String(value).match(/^(\d{1,2}):(\d{2})/)
  if (!m) return String(value)

  let h = Number(m[1])
  const suffix = h >= 12 ? 'PM' : 'AM'
  h = h % 12 || 12
  return `${h}:${m[2]} ${suffix}`
}
