import { defineStore } from 'pinia'
import api, { unwrap } from '@/lib/api'
import { useNotificationStore } from '@/stores/notifications'

export const useVehicleRequestStore = defineStore('vehicleRequests', {
  state: () => ({
    items: [],
    current: null,
    meta: null,
    loading: false,
    filters: {
      status: '',
      trip_type: '',
      page: 1,
    },
  }),

  getters: {
    countByStatus: (state) =>
      state.items.reduce((acc, item) => {
        acc[item.status] = (acc[item.status] ?? 0) + 1
        return acc
      }, {}),
  },

  actions: {
    async fetch(overrides = {}) {
      this.loading = true
      this.filters = { ...this.filters, ...overrides }

      try {
        const { data } = await api.get('/vehicle-requests', {
          params: {
            status: this.filters.status || undefined,
            trip_type: this.filters.trip_type || undefined,
            page: this.filters.page,
          },
        })

        let items = data.data ?? []

        // Safety net: if the backend ignores trip_type, filter the current page here.
        if (this.filters.trip_type) {
          items = items.filter(
            (item) => String(item.trip_type).toLowerCase() === this.filters.trip_type,
          )
        }

        this.items = items
        this.meta = data.meta ?? null
        return this.items
      } finally {
        this.loading = false
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
      const { data } = await api.put(`/vehicle-requests/${id}/edit`, payload)
      return data.data
    },

    // status is 'approved' or 'disapproved'. 'remarks' is required when disapproving.
    async updateStatus(id, payload) {
      const response = await api.patch(`/vehicle-requests/${id}/status`, payload)
      // Update the sidebar numbers right after a decision.
      useNotificationStore().refresh()
      return unwrap(response)
    },
  },
})