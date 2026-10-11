import { computed, reactive } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/lib/api'

// Use the same backend URL and authentication token as CampusGlide.
export const API_BASE =
  import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api'

export const ACTIVE_STATUSES = ['scheduled', 'in_progress']

export const auth = reactive({
  token: null,
  role: null,
  email: null,
  notice: '',
})

let mainAuthStore = null

export function bindMainAuth(store) {
  mainAuthStore = store
  syncAuth()
}

function syncAuth() {
  if (!mainAuthStore) return

  auth.token = mainAuthStore.token
  auth.role = mainAuthStore.role
  auth.email = mainAuthStore.user?.email ?? ''
}

export const isLoggedIn = computed(() => Boolean(auth.token))

export const ADMIN_ROLES = ['administrator', 'admin']
export const isAdmin = computed(() => ADMIN_ROLES.includes(auth.role))

export class UnauthorizedError extends Error {}

export class ApiError extends Error {
  constructor(message, status, data) {
    super(message)
    this.status = status
    this.data = data
  }
}

export function firstError(data, status) {
  const list = Object.values(data?.errors || {}).flat()
  return list.length ? list.join(' ') : data?.message || `Request failed (${status})`
}

// Login and logout are handled by the main CampusGlide application.
export async function login() {
  throw new Error('Please use the main CampusGlide login.')
}

export function logout() {
  if (mainAuthStore) {
    return mainAuthStore.logout()
  }
}

// Keep allocation requests using their existing endpoints and response handling.
export async function request(path, { method = 'GET', body } = {}) {
  syncAuth()

  if (!auth.token) {
    throw new UnauthorizedError('Please log in to CampusGlide.')
  }

  try {
    const response = await api.request({
      url: path,
      method,
      data: body,
    })

    return response.data
  } catch (error) {
    const status = error?.response?.status
    const data = error?.response?.data ?? {}

    if (status === 401) {
      if (mainAuthStore) {
        await mainAuthStore.logout()
      }
      throw new UnauthorizedError('Your session expired. Please log in again.')
    }

    if (status) {
      throw new ApiError(firstError(data, status), status, data)
    }

    throw new Error(`Cannot reach the server (${error.message}). Is the backend running?`)
  }
}