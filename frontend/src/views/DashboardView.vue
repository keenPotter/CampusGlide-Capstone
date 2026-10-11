<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useVehicleRequestStore } from '@/stores/vehicleRequests'
import { formatDate, formatTime, ROLE_LABELS } from '@/lib/format'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

const auth = useAuthStore()
const router = useRouter()
const requests = useVehicleRequestStore()
const loading = ref(true)

onMounted(async () => {
  await Promise.allSettled([requests.fetch({ status: '', trip_type: '', page: 1 })]),
    loading.value = false
})

const stats = computed(() => {
  const counts = requests.countByStatus
  return [
    { label: 'Pending', value: counts.pending ?? 0, tone: 'secondary' },
    { label: 'Approved', value: counts.approved ?? 0, tone: 'primary' },
    { label: 'Disapproved', value: counts.disapproved ?? 0, tone: 'danger' },
  ]
})

const tones = {
  primary: 'bg-primary-50 text-primary-700',
  secondary: 'bg-secondary-50 text-secondary-700',
  danger: 'bg-red-50 text-red-700',
  neutral: 'bg-neutral-100 text-ink-muted',
}

const upcoming = computed(() =>
  [...requests.items]
    .filter((item) => ['pending', 'approved'].includes(item.status))
    .sort((a, b) => String(a.trip_date).localeCompare(String(b.trip_date)))
    .slice(0, 5),
)
</script>

<template>
  <div class="flex flex-col gap-page">
    <!-- Greeting -->
    <div>
      <h1 class="text-page-title">Hello, {{ auth.user?.first_name }}</h1>
      <p class="mt-1 text-small text-ink-muted">
        Signed in as {{ ROLE_LABELS[auth.role] ?? auth.role }}
      </p>
    </div>

    <!-- Quick Action Button (Mobile) -->
    <div v-if="auth.isFaculty" class="md:hidden">
      <BaseButton block @click="router.push({ name: 'requests.create' })">
        New request
      </BaseButton>
    </div>

    <!-- Stats Grid -->
    <div class="responsive-grid">
      <div
        v-for="stat in stats"
        :key="stat.label"
        class="stat-card"
      >
        <span
          class="inline-flex rounded-full px-2 py-0.5 text-small font-medium"
          :class="tones[stat.tone]"
        >
          {{ stat.label }}
        </span>
        <p class="mt-2 text-2xl font-bold">{{ stat.value }}</p>
      </div>
    </div>

    <!-- Upcoming Trips Card -->
    <BaseCard title="Upcoming trips" subtitle="Pending and approved requests, soonest first">
      <template #actions>
        <RouterLink
          :to="{ name: 'requests.index' }"
          class="text-small font-medium text-primary hover:underline"
        >
          View all
        </RouterLink>
      </template>

      <EmptyState
        v-if="!loading && !upcoming.length"
        title="No upcoming trips"
        message="Requests you create or approve will appear here."
      />

      <ul v-else class="flex flex-col divide-y divide-line">
        <li v-for="request in upcoming" :key="request.id" class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0">
          <RouterLink
            :to="{ name: 'requests.show', params: { id: request.id } }"
            class="block text-body font-medium text-ink hover:text-primary"
          >
            {{ request.destination }}
          </RouterLink>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-small text-ink-muted">
              {{ formatDate(request.trip_date) }} · {{ formatTime(request.departure_time) }}
            </p>
            <StatusBadge :status="request.status" />
          </div>
          <p class="text-small text-ink-muted">{{ request.requested_by?.first_name }} {{ request.requested_by?.last_name }}</p>
        </li>
      </ul>
    </BaseCard>
  </div>
</template>