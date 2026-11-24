import { defineStore } from 'pinia'
import api, { unwrap } from '@/lib/api'
import { todayIso } from '@/lib/format'
import { useNotificationStore } from '@/stores/notifications'

async function fetchAllByStatus(status, search) {
  const params = {
    status,
    search: search.trim() || undefined,
    page: 1,
    per_page: 100,
  }
  const firstResponse = await api.get('/vehicle-requests', { params })
  const requests = firstResponse.data?.data ?? []
  const lastPage = Number(firstResponse.data?.meta?.last_page ?? 1)

  if (lastPage <= 1) return requests

  const remainingPages = await Promise.all(
    Array.from({ length: lastPage - 1 }, (_, index) =>
      api.get('/vehicle-requests', { params: { ...params, page: index + 2 } }),
    ),
  )

  return requests.concat(...remainingPages.map((response) => response.data?.data ?? []))
}

export const useVehicleRequestStore = defineStore('vehicleRequests', {
  state: () => ({
    items: [],
    current: null,
    meta: null,
    loading: false,
    fetchSequence: 0,
    filters: {
      status: '',
      trip_type: '',
      month: '',
      year: '',
      search: '',
      page: 1,
    },
  }),

  // Removed the `countByStatus` getter: it counted only the current page,
  // so the dashboard numbers were wrong. See fetchSummary() below.

  actions: {
    async fetch(overrides = {}) {
      const requestId = ++this.fetchSequence
      this.loading = true
      this.filters = { ...this.filters, ...overrides }
      const filters = { ...this.filters }

      try {
        const { data } = await api.get('/vehicle-requests', {
          params: {
            status: filters.status || undefined,
            trip_type: filters.trip_type || undefined,
            month: filters.month || undefined,
            year: filters.year || undefined,
            search: filters.search || undefined,
            page: filters.page,
          },
        })

        if (requestId === this.fetchSequence) {
          // The backend filters by status and trip_type, so nothing is filtered here.
          this.items = data.data ?? []
          this.meta = data.meta ?? null
        }
        return data.data ?? []
      } finally {
        if (requestId === this.fetchSequence) this.loading = false
      }
    },

    async find(id) {
      this.loading = true
      try {
        const response = await api.get(`/vehicle-requests/${id}`)
        this.current = unwrap(response)
        return this.current
      } finally {
        this.loading = false
      }
    },

    async create(payload) {
      const response = await api.post('/vehicle-requests', payload)
      return unwrap(response)
    },

    async update(id, payload) {
      const response = await api.put(`/vehicle-requests/${id}/edit`, payload)
      return unwrap(response)
    },

    // status is 'approved' or 'disapproved'. 'remarks' is required when disapproving.
    async updateStatus(id, payload) {
      const response = await api.patch(`/vehicle-requests/${id}/status`, payload)
      // Update the sidebar numbers right after a decision.
      useNotificationStore().refresh()
      return unwrap(response)
    },

    // Numbers and upcoming trips for the dashboard.
    // Counts come from meta.total, so they include every page.
    // This does not touch `items` or `filters`, which belong to the list page.
    async fetchSummary(search = '') {
      const [pending, approved, disapproved] = await Promise.all(
        ['pending', 'approved', 'disapproved'].map((status) =>
          api.get('/vehicle-requests', { params: { status, per_page: 20 } }),
        ),
      )

      const total = (response) => response.data?.meta?.total ?? response.data?.data?.length ?? 0
      const today = todayIso()
      const allPending = await fetchAllByStatus('pending', search)
      const submittedAt = (request) => Date.parse(request.created_at ?? '') || 0

      // The dashboard preview shows only upcoming pending requests, newest submission first.
      const upcoming = allPending
        .filter((item) => (item.trip_end_date || item.trip_date) >= today)
        .sort((a, b) => submittedAt(b) - submittedAt(a) || Number(b.id) - Number(a.id))

      return {
        counts: {
          pending: total(pending),
          approved: total(approved),
          disapproved: total(disapproved),
        },
        upcoming: upcoming.slice(0, 5),
      }
    },
  },
})