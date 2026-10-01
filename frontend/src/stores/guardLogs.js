import { defineStore } from 'pinia'
import api, { unwrap } from '@/lib/api'

export const useGuardLogStore = defineStore('guardLogs', {
  state: () => ({
    items: [],
    meta: null,
    loading: false,
    page: 1,
  }),

  getters: {
    onTrip: (state) => state.items.filter((log) => !log.actual_return_date),
    completed: (state) => state.items.filter((log) => Boolean(log.actual_return_date)),
  },

  actions: {
    async fetch(page = this.page) {
      this.loading = true
      this.page = page

      try {
        const { data } = await api.get('/guard-logs', { params: { page } })
        this.items = data.data ?? []
        this.meta = data.meta ?? null
        return this.items
      } finally {
        this.loading = false
      }
    },

    async recordDeparture(payload) {
      const response = await api.post('/guard-logs', payload)
      return unwrap(response)
    },

    async recordReturn(id, payload) {
      const response = await api.patch(`/guard-logs/${id}/return`, payload)
      return unwrap(response)
    },
  },
})