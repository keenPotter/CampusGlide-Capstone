import { defineStore } from 'pinia'
import api from '@/lib/api'

// Maintenance records that still need the admin's attention.
const OPEN_MAINTENANCE = ['in_progress']

export const useNotificationStore = defineStore('notifications', {
  state: () => ({
    pendingRequests: 0,
    openMaintenance: 0,
  }),

  getters: {
    total: (state) => state.pendingRequests + state.openMaintenance,
  },

  actions: {
    async refresh() {
      const [requests, maintenance] = await Promise.allSettled([
        api.get('/vehicle-requests', { params: { status: 'pending' } }),
        api.get('/maintenance-logs'),
      ])

      if (requests.status === 'fulfilled') {
        const body = requests.value.data
        // meta.total counts every page, not just the first one.
        this.pendingRequests = body?.meta?.total ?? body?.data?.length ?? 0
      }

      if (maintenance.status === 'fulfilled') {
        const body = maintenance.value.data
        const logs = Array.isArray(body) ? body : (body?.data ?? [])
        this.openMaintenance = logs.filter((log) => OPEN_MAINTENANCE.includes(log.status)).length
      }
    },

    reset() {
      this.pendingRequests = 0
      this.openMaintenance = 0
    },
  },
})