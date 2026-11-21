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
const decisionAction = ref('')
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
    const data = unwrap(await api.get('/trip-modification-requests', { params: { status: 'pending' } }))
    requests.value = data
  } catch (error) {
    loadError.value = errorMessage(error, 'Unable to load Trip Modification Requests.')
  } finally {
    loading.value = false
  }
}

function changePage(value) {
  page.value = value
}

function decide(item, action) {
  decisionItem.value = item
  decisionAction.value = action
}

function closeDecision() {
  decisionItem.value = null
  decisionAction.value = ''
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
        <h1 class="text-page-title">Trip Modification Requests</h1>
        <p class="mt-1 text-small text-ink-muted">
          Review faculty requests to modify approved trips.
        </p>
      </div>
      <div class="page-actions">
        <RefreshButton :loading="loading" label="Refresh Trip Modification Requests" @click="load" />
      </div>
    </div>

    <LoadError :message="loadError" @retry="load" />

    <div v-if="loading" class="flex justify-center p-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-primary" />
    </div>

    <EmptyState
      v-else-if="!requests.length"
      title="No pending Trip Modification Requests"
      message="Faculty requests to modify approved trips will appear here."
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
          @approve="decide($event, 'approve')"
          @disapprove="decide($event, 'disapprove')"
        />
      </div>
      <PaginationBar :meta="paginationMeta" @change="changePage" />
    </template>

    <DateChangeDecisionModal
      v-if="auth.isAdministratorOrStaff"
      :item="decisionItem"
      :action="decisionAction"
      @close="closeDecision"
      @saved="onDecided"
      @conflict="onDecisionConflict"
    />
    <ConflictModal
      :conflicts="conflicts"
      hint="Please choose another date."
      @close="conflicts = []"
    />
  </div>
</template>
