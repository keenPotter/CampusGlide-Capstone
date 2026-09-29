import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  { path: '/', redirect: { name: 'tripSchedule' } },
  {
    path: '/trip-schedule',
    name: 'tripSchedule',
    component: () => import('@/views/TripScheduleView.vue'),
  },
  // These are the same route names/paths used by the leader's navigation.
  // The actual pages will come from the teammates' branches after merge.
  { path: '/dashboard', name: 'dashboard' },
  { path: '/requests', name: 'requests.index' },
  { path: '/requests/new', name: 'requests.create' },
  { path: '/guard-logs', name: 'guardLogs.index' },
  { path: '/login', name: 'login' },
]

export default createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})
