import { defineStore } from 'pinia'
import api, { unwrap } from '@/lib/api'

export const useVehicleRequestStore = defineStore('vehicleRequests', {
  state: () => ({
    items: [],
    current: null,
    meta: null,
    loading: false,
    filters: {
      status: '',
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
            page: this.filters.page,
          },
        })
        this.items = data.data ?? []
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

    async updateStatus(id, payload) {
      const response = await api.patch(`/vehicle-requests/${id}/status`, payload)
      return unwrap(response)
    },

    async cancel(id, cancellationRemarks) {
      const { data } = await api.patch(`/vehicle-requests/${id}/cancel`, {
        cancellation_remarks: cancellationRemarks,
      })
      return data.data
    },
  },
})