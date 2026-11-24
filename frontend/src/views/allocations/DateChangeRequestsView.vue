<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'
import api, { errorMessage, unwrap } from '@/lib/api'
import { useToast } from '@/composables/useToast'
import RefreshButton from '@/components/ui/RefreshButton.vue'
import LoadError from '@/components/ui/LoadError.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import ConflictModal from '@/components/allocations/ConflictModal.vue'
import DateChangeDecisionModal from '@/components/allocations/DateChangeDecisionModal.vue'
import RescheduleRequestDetailsModal from '@/components/allocations/RescheduleRequestDetailsModal.vue'
import RescheduleRequestCard from '@/components/allocations/RescheduleRequestCard.vue'

const auth = useAuthStore()
const notifications = useNotificationStore()
const toast = useToast()

const PAGE_SIZE = 10
const requests = ref([])
const loading = ref(true)
const loadError = ref('')
const page = ref(1)
const decisionItem = ref(null)
const detailItem = ref(null)
const conflicts = ref([])

const pageCount = computed(() => Math.max(1, Math.ceil(requests.value.length / PAGE_SIZE)))
const pagedRequests = computed(() =>
  requests.value.slice((page.value - 1) * PAGE_SIZE, page.value * PAGE_SIZE),
)
const paginationMeta = computed(() => ({
  current_page: page.value,
  last_page: pageCount.value,
  total: requests.value.length,
}))

watch(pageCount, (count) => {
  if (page.value > count) page.value = count
})

async function load() {
  loading.value = true
  loadError.value = ''

  try {
    const params = auth.isAdministratorOrStaff ? { status: 'pending' } : {}
    const data = unwrap(await api.get('/trip-modification-requests', { params }))
    requests.value = data
  } catch (error) {
    loadError.value = errorMessage(error, 'Unable to load Date Change Requests.')
  } finally {
    loading.value = false
  }
}

function changePage(value) {
  page.value = value
}

function review(item) {
  decisionItem.value = item
}

function viewDetails(item) {
  detailItem.value = item
}

function closeDecision() {
  decisionItem.value = null
}

async function onDecided(message) {
  closeDecision()
  await load()
  notifications.refresh()
  toast.success(message)
}

function onDecisionConflict(found) {
  closeDecision()
  conflicts.value = found
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-page">
    <div class="page-header">
      <div class="page-header-copy">
        <h1 class="text-page-title">Date Change Requests</h1>
        <p class="mt-1 text-small text-ink-muted">
          {{
            auth.isAdministratorOrStaff
              ? 'Review requests to change trip dates.'
              : "Track your requests and read the administrator's decision notes."
          }}
        </p>
      </div>
      <div class="page-actions">
        <RefreshButton :loading="loading" label="Refresh Date Change Requests" @click="load" />
      </div>
    </div>

    <LoadError :message="loadError" @retry="load" />

    <div v-if="loading" class="flex justify-center p-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-primary" />
    </div>

    <EmptyState
      v-else-if="!requests.length"
      :title="auth.isAdministratorOrStaff ? 'No pending Date Change Requests' : 'No Date Change Requests'"
      :message="auth.isAdministratorOrStaff
        ? 'Faculty date change requests for approved trips will appear here.'
        : 'Date change requests you submit will appear here with their decision notes.'"
    />

    <template v-else>
      <p class="text-small text-ink-muted">
        {{
          auth.isAdministratorOrStaff
            ? `${requests.length} waiting for your decision`
            : `${requests.length} requests`
        }}
      </p>
      <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
        <RescheduleRequestCard
          v-for="item in pagedRequests"
          :key="item.id"
          :item="item"
          :is-admin="auth.isAdministratorOrStaff"
          @review="review"
          @view="viewDetails"
        />
      </div>
      <PaginationBar :meta="paginationMeta" @change="changePage" />
    </template>

    <DateChangeDecisionModal
      v-if="auth.isAdministratorOrStaff"
      :item="decisionItem"
      @close="closeDecision"
      @saved="onDecided"
      @conflict="onDecisionConflict"
    />
    <RescheduleRequestDetailsModal
      :item="detailItem"
      @close="detailItem = null"
    />
    <ConflictModal
      :conflicts="conflicts"
      hint="Choose an available vehicle or driver, or disapprove the request if none are available."
      @close="conflicts = []"
    />
  </div>
</template>
