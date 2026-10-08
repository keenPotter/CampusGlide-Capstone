const LOCAL_DESTINATIONS = [
  'Alfonso Castañeda',
  'Ambaguio',
  'Aritao',
  'Bagabag',
  'Bambang',
  'Bayombong',
  'Diadi',
  'Dupax del Norte',
  'Dupax del Sur',
  'Kasibu',
  'Kayapa',
  'Quezon',
  'Santa Fe',
  'Solano',
  'Villaverde',
]

function normalizeDestination(destination) {
  return String(destination ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
}

export function isLocalDestination(destination) {
  const normalized = normalizeDestination(destination)
  if (/(^|[^a-z])nueva\s+vizcaya($|[^a-z])/.test(normalized)) return true

  return LOCAL_DESTINATIONS.some((municipality) => {
    const name = normalizeDestination(municipality).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    if (municipality === 'Quezon' && /(^|[^a-z])quezon\s+city($|[^a-z])/.test(normalized)) return false
    return new RegExp(`(^|[^a-z])${name}($|[^a-z])`).test(normalized)
  })
}

export function groupByLocality(items, getDestination) {
  return items.reduce(
    (groups, item) => {
      groups[isLocalDestination(getDestination(item)) ? 'local' : 'nonLocal'].push(item)
      return groups
    },
    { local: [], nonLocal: [] },
  )
}
