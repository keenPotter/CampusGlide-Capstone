<script setup>
import { computed, onMounted, ref } from 'vue'
import api, { errorMessage, unwrap } from '@/lib/api'
import { formatDate, formatTime } from '@/lib/format'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadError from '@/components/ui/LoadError.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import TripTypeBadge from '@/components/ui/TripTypeBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const allocations = ref([])
const search = ref('')
const loading = ref(false)
const loadError = ref('')

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    allocations.value = unwrap(await api.get('/allocations', { params: { per_page: 100 } }))
  } catch (error) {
    loadError.value = errorMessage(error, 'Unable to load allocations.')
  } finally {
    loading.value = false
  }
}

onMounted(load)

const visibleAllocations = computed(() => {
  const query = search.value.trim().toLowerCase()
  return allocations.value.filter((item) => {
    if (!query) return true
    return [
      item.trip?.destination,
      item.vehicle_request?.requester,
      item.vehicle?.vehicle_model,
      item.vehicle?.plate_number,
      item.driver?.name,
      item.status,
    ].join(' ').toLowerCase().includes(query)
  })
})
</script>

<template>
  <div class="flex flex-col gap-page">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-page-title">Allocations</h1>
        <p class="mt-1 text-small text-ink-muted">View assigned vehicles, drivers, and scheduled trips. Staff accounts cannot change allocations.</p>
      </div>
      <BaseButton variant="outline" size="sm" :disabled="loading" @click="load">Refresh</BaseButton>
    </div>

    <LoadError :message="loadError" @retry="load" />

    <BaseCard title="Allocation records" subtitle="Read-only view">
      <BaseInput v-model="search" type="search" placeholder="Search destination, requester, vehicle, driver, or status..." />
      <div v-if="loading" class="flex justify-center p-10">
        <div class="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-primary" />
      </div>
      <EmptyState
        v-else-if="!visibleAllocations.length"
        title="No allocations found"
        message="Assigned trips will appear here when available."
      />
      <ul v-else class="mt-4 divide-y divide-line">
        <li v-for="item in visibleAllocations" :key="item.id" class="flex flex-col gap-3 py-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <p class="font-semibold text-ink">{{ item.trip?.destination || 'Destination not provided' }}</p>
              <StatusBadge :status="item.status || 'scheduled'" />
              <TripTypeBadge :type="item.vehicle_request?.trip_type" />
            </div>
            <p class="mt-1 text-small text-ink-muted">
              {{ formatDate(item.trip?.trip_date) }} · {{ formatTime(item.trip?.departure_time) }}
              <span v-if="item.trip?.trip_end_date && item.trip.trip_end_date !== item.trip?.trip_date"> – {{ formatDate(item.trip.trip_end_date) }}</span>
            </p>
            <p class="mt-2 text-small text-ink">Requester: {{ item.vehicle_request?.requester || '—' }}</p>
            <p class="text-small text-ink-muted">Purpose: {{ item.trip?.purpose || '—' }}</p>
          </div>
          <div class="min-w-0 text-small sm:w-64">
            <p class="font-medium text-ink">{{ item.vehicle?.vehicle_model || 'No vehicle' }} · {{ item.vehicle?.plate_number || '—' }}</p>
            <p class="mt-1 text-ink-muted">Driver: {{ item.driver?.name || '—' }}</p>
            <p class="text-ink-muted">Contact: {{ item.driver?.contact_number || '—' }}</p>
          </div>
        </li>
      </ul>
    </BaseCard>
  </div>
</template>
