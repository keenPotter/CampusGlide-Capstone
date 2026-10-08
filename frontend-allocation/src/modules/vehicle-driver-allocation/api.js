import { computed, reactive } from 'vue'

// Pwedeng palitan sa .env ng Vite: VITE_API_BASE=http://127.0.0.1:8000/api
export const API_BASE = import.meta.env.VITE_API_BASE || '/api'

// Mga trip status na pwede pang i-reassign / i-cancel.
export const ACTIVE_STATUSES = ['scheduled', 'in_progress']

// sessionStorage: mawawala ang login kapag isinara ang tab o browser.
const store = window.sessionStorage

export const auth = reactive({
  token: store.getItem('trip_token'),
  role: store.getItem('trip_role'),
  email: store.getItem('trip_email'),
  notice: '', // mensahe sa login panel (hal. "Session expired")
})

export const isLoggedIn = computed(() => !!auth.token)
// Dalawa lang ang klase ng user: Admin at End User (Chief Motorpool at Boss ay parehong Admin).
// Dapat tugma sa AllocationService::ADMIN_ROLES sa backend.
export const ADMIN_ROLES = ['administrator', 'admin']
export const isAdmin = computed(() => ADMIN_ROLES.includes(auth.role))

function setAuth(token, role, email) {
  auth.token = token
  auth.role = role
  auth.email = email
  auth.notice = ''
  store.setItem('trip_token', token)
  store.setItem('trip_role', role)
  store.setItem('trip_email', email)
}

export function clearAuth(notice = '') {
  auth.token = null
  auth.role = null
  auth.email = null
  auth.notice = notice
  store.removeItem('trip_token')
  store.removeItem('trip_role')
  store.removeItem('trip_email')
}

export class UnauthorizedError extends Error {}

// May dala ng buong response (hal. `conflict` details ng 422) para sa conflict modal.
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

export async function login(email, password) {
  let res
  try {
    res = await fetch(`${API_BASE}/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email, password }),
    })
  } catch (e) {
    throw new Error('Error: ' + e.message)
  }

  const data = await res.json().catch(() => ({}))
  const token = data.token || data.access_token || data.data?.token
  const role = data.role || data.user?.role || data.data?.role || data.data?.user?.role || ''

  if (!res.ok || !token) {
    throw new Error('Login failed: ' + (data.message || res.status))
  }

  setAuth(token, role, email)
}

export function logout() {
  clearAuth('Logged out.')
}

// fetch na may token. 401 -> awtomatikong logout at UnauthorizedError.
export async function request(path, { method = 'GET', body } = {}) {
  const headers = { Accept: 'application/json', Authorization: `Bearer ${auth.token}` }
  if (body !== undefined) headers['Content-Type'] = 'application/json'

  let res
  try {
    res = await fetch(`${API_BASE}${path}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    })
  } catch (e) {
    throw new Error(`Cannot reach the server (${e.message}). Is the backend running?`)
  }

  const data = await res.json().catch(() => ({}))

  if (res.status === 401) {
    clearAuth('Session expired — please log in again.')
    throw new UnauthorizedError('Session expired — please log in again.')
  }
  if (!res.ok) throw new ApiError(firstError(data, res.status), res.status, data)

  return data
}
