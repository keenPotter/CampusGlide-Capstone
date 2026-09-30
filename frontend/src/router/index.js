import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  { path: '/', redirect: { name: 'tripSchedule' } },
  {
    path: '/trip-schedule',
    name: 'tripSchedule',
    component: () => import('@/views/TripScheduleView.vue'),
  },
  { path: '/', redirect: '/dashboard' },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/views/RegisterView.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('@/views/DashboardView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/requests',
    name: 'requests.index',
    component: () => import('@/views/requests/RequestListView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/requests/new',
    name: 'requests.create',
    component: () => import('@/views/requests/RequestFormView.vue'),
    meta: { requiresAuth: true, roles: ['faculty'] },
  },
  {
    path: '/requests/:id',
    name: 'requests.show',
    component: () => import('@/views/requests/RequestDetailView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/requests/:id/edit',
    name: 'requests.edit',
    component: () => import('@/views/requests/RequestFormView.vue'),
    meta: { requiresAuth: true, roles: ['faculty'] },
  },
  {
    path: '/guard-logs',
    name: 'guardLogs.index',
    component: () => import('@/views/guard/GuardLogListView.vue'),
    meta: { requiresAuth: true, roles: ['guard', 'administrator'] },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'notFound',
    component: () => import('@/views/NotFoundView.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.ready) {
    await auth.fetchUser()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  if (to.meta.roles && !to.meta.roles.includes(auth.role)) {
    return { name: 'dashboard' }
  }

  return true
})

export default router
