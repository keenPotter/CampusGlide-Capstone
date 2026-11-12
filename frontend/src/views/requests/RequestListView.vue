<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Check, Eye, X } from 'lucide-vue-next'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useVehicleRequestStore } from '@/stores/vehicleRequests'
import api, { errorMessage, unwrap } from '@/lib/api'
import {
  formatDate,
  formatDateTime,
  formatTime,
  fullName,
  MONTH_OPTIONS,
  STATUS_LABELS,
  TRIP_TYPE_LABELS,
  YEAR_OPTIONS,
} from '@/lib/format'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import RefreshButton from '@/components/ui/RefreshButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import ApproveAssignModal from '@/components/requests/ApproveAssignModal.vue'
import DisapproveRequestModal from '@/components/requests/DisapproveRequestModal.vue'
import VehicleRequestModal from '@/components/requests/VehicleRequestModal.vue'

const auth = useAuthStore()
const store = useVehicleRequestStore()
const router = useRouter()
const route = useRoute()
const toast = useToast()
const search = ref('')
const selectedRequest = ref(null)
const detailRequest = ref(null)
const detailLoading = ref(false)
const showNewRequest = ref(false)

watch(
  () => route.query.create,
  (value) => {
    if (value === '1') showNewRequest.value = true
  },
  { immediate: true },
)

// Same style of filter as the Maintenance page: dropdowns with an "All" choice.
const statusOptions = ['pending', 'approved', 'disapproved'].map((value) => ({
  value,
  label: STATUS_LABELS[value],
}))

const typeOptions = ['inclusive', 'exclusive'].map((value) => ({
  value,
  label: TRIP_TYPE_LABELS[value],
}))

const disapproving = ref(null)
// The pending request that is being approved. The assign window is open while this is set.
const approving = ref(null)
const canEditSelectedRequest = computed(
  () => auth.isFaculty && ['pending', 'approved'].includes(detailRequest.value?.status),
)
const requestDetailRows = computed(() => {
  const request = detailRequest.value
  if (!request) return []

  return [
    { label: 'Requesting official', value: fullName(request.requested_by) },
    { label: 'Destination', value: request.destination },
    { label: 'Purpose', value: request.purpose },
    { label: 'Date of travel', value: formatDate(request.trip_date) },
    { label: 'End of travel', value: formatDate(request.trip_end_date) },
    { label: 'Days of travel', value: request.travel_days ?? '—' },
    { label: 'Trip type', value: request.trip_type },
    { label: 'Time of departure', value: formatTime(request.departure_time) },
    { label: 'Estimated return', value: formatTime(request.return_time) },
    { label: 'Authorized passengers', value: request.passengers },
    { label: 'Number of passengers', value: request.number_of_passengers ?? '—' },
    { label: 'Date requested', value: formatDateTime(request.created_at) },
    { label: 'Approved on', value: request.approved_date ? formatDateTime(request.approved_date) : '—' },
  ]
})

// Reset the filters too, because the store is shared with other pages.
onMounted(() => store.fetch({ status: '', trip_type: '', month: '', year: '', search: '', page: 1 }))

watch(search, (_, __, onCleanup) => {
  const timeout = setTimeout(() => store.fetch({ search: search.value, page: 1 }), 250)
  onCleanup(() => clearTimeout(timeout))
})

function changeStatus(value) {
  store.fetch({ status: value, search: search.value, page: 1 })
}

function changeType(value) {
  store.fetch({ trip_type: value, search: search.value, page: 1 })
}

function changeMonth(value) {
  store.fetch({ month: value, search: search.value, page: 1 })
}

function changeYear(value) {
  store.fetch({ year: value, search: search.value, page: 1 })
}

function openRequest(request) {
  selectedRequest.value = request
  detailRequest.value = request
  detailLoading.value = true

  api.get(`/vehicle-requests/${request.id}`)
    .then((response) => {
      if (selectedRequest.value?.id === request.id) detailRequest.value = unwrap(response)
    })
    .catch((error) => toast.error(errorMessage(error, 'Unable to load this request.')))
    .finally(() => {
      if (selectedRequest.value?.id === request.id) detailLoading.value = false
    })
}

function closeRequest() {
  selectedRequest.value = null
  detailRequest.value = null
  detailLoading.value = false
}

function closeNewRequest() {
  showNewRequest.value = false
  if (route.query.create === '1') router.replace({ name: 'requests.index' })
}

async function onRequestSubmitted() {
  closeNewRequest()
  try {
    await store.fetch({ page: 1 })
  } catch (error) {
    toast.error(errorMessage(error, 'Request submitted, but the list could not be refreshed.'))
  }
}

function editSelectedRequest() {
  const request = detailRequest.value
  closeRequest()
  if (request) router.push({ name: 'requests.edit', params: { id: request.id } })
}

// Clicking anywhere on a row or card opens the request.
// Clicks on a button or link are skipped, so they keep doing their own job.
function onRowClick(event, request) {
  if (event.target.closest('a, button')) return
  openRequest(request)
}

// Approve opens the assign window. The request is approved only after the vehicle and driver are saved.
function openApprove(request) {
  closeRequest()
  approving.value = request
}

function onApproved() {
  const request = approving.value
  approving.value = null
  toast.success(`Request #${request.id} approved. Vehicle and driver assigned.`)
  store.fetch()
}

function openDisapprove(request) {
  closeRequest()
  disapproving.value = request
}

function onDisapproved() {
  disapproving.value = null
  store.fetch()
}
</script>

<template>
  <div class="flex flex-col gap-page">
    <!-- Page Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h1 class="text-page-title">
          {{ auth.isFaculty ? 'My vehicle requests' : 'Vehicle requests' }}
        </h1>
        <p class="mt-1 text-small text-ink-muted">Request for Use of Vehicle (NVSU-FR-PPS-13-02)</p>
      </div>

      <div class="flex items-center justify-end gap-2">
        <RefreshButton :loading="store.loading" label="Refresh vehicle requests" @click="store.fetch()" />
        <BaseButton v-if="auth.isFaculty" size="sm" @click="showNewRequest = true">
          New request
        </BaseButton>
      </div>
    </div>

    <!-- Filters -->
    <BaseCard>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <BaseInput
          v-model="search"
          class="sm:col-span-2 lg:col-span-4"
          type="search"
          placeholder="Search requests..."
        />
        <BaseSelect
          :model-value="store.filters.status"
          :options="statusOptions"
          placeholder="All status"
          allow-empty
          @update:model-value="changeStatus"
        />
        <BaseSelect
          :model-value="store.filters.trip_type"
          :options="typeOptions"
          placeholder="All types"
          allow-empty
          @update:model-value="changeType"
        />
        <BaseSelect
          :model-value="store.filters.month"
          :options="MONTH_OPTIONS"
          placeholder="All months"
          allow-empty
          @update:model-value="changeMonth"
        />
        <BaseSelect
          :model-value="store.filters.year"
          :options="YEAR_OPTIONS"
          placeholder="All years"
          allow-empty
          @update:model-value="changeYear"
        />
      </div>
    </BaseCard>

    <!-- Content Area -->
    <div v-if="store.loading" class="flex justify-center p-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-primary" />
    </div>

    <EmptyState
      v-else-if="!store.items.length"
      title="No requests found"
      message="Try a different filter, or create a new request."
    />

    <!-- Mobile + Tablet Card View (1 column on mobile, 2 columns on tablet) -->
    <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4 lg:hidden">
      <div
        v-for="request in store.items"
        :key="request.id"
        class="card-row !mb-0 flex cursor-pointer flex-col transition active:bg-neutral-50"
        @click="onRowClick($event, request)"
      >
        <div class="card-row-header">
          <div class="min-w-0 flex-1">
            <button
              type="button"
              class="card-row-value block truncate text-left md:text-section-title"
              :title="request.destination"
              @click="openRequest(request)"
            >
              {{ request.destination }}
            </button>
            <p class="mt-0.5 truncate text-small text-ink-muted md:text-body">{{ request.purpose }}</p>
          </div>
          <StatusBadge :status="request.status" class="shrink-0" />
        </div>

        <div class="mb-3 space-y-1.5 md:space-y-2">
          <div class="flex items-center justify-between">
            <span class="card-row-label md:text-body">Requested by</span>
            <span class="text-small text-ink md:text-body">{{ fullName(request.requested_by) }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="card-row-label md:text-body">Travel date</span>
            <span class="text-small text-ink md:text-body">{{ formatDate(request.trip_date) }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="card-row-label md:text-body">Departure</span>
            <span class="text-small text-ink md:text-body">{{ formatTime(request.departure_time) }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="card-row-label md:text-body">Passengers</span>
            <span class="text-small text-ink md:text-body">{{ request.number_of_passengers ?? '—' }}</span>
          </div>
          <div
            v-if="request.status === 'disapproved' && request.disapproval_reason"
            class="flex items-start justify-between gap-3"
          >
            <span class="card-row-label shrink-0 md:text-body">Reason</span>
            <span class="text-right text-small text-red-700 md:text-body">
              {{ request.disapproval_reason }}
            </span>
          </div>
        </div>

        <div class="mt-auto border-t border-line pt-3">
          <template v-if="auth.isAdministrator && request.status === 'pending'">
            <div class="flex items-center gap-2">
              <BaseButton size="sm" class="flex-1" @click="openApprove(request)">
                <Check :size="16" aria-hidden="true" /> Approve
              </BaseButton>
              <BaseButton size="sm" variant="outline" class="flex-1" @click="openDisapprove(request)">
                <X :size="16" aria-hidden="true" /> Disapprove
              </BaseButton>
            </div>
          </template>

          <BaseButton
            size="sm"
            variant="ghost"
            class="mt-2 w-full"
            @click="openRequest(request)"
          >
            <Eye :size="16" aria-hidden="true" /> View
          </BaseButton>
        </div>
      </div>
    </div>

    <!-- Laptop Table View (fits the screen, no side scrolling) -->
    <div
      v-if="!store.loading && store.items.length"
      class="hidden overflow-hidden rounded-card border border-line bg-white shadow-card lg:block"
    >
      <table class="w-full table-fixed border-collapse">
        <thead class="table-head">
          <tr>
            <th scope="col" class="w-[16%] px-2 py-3 xl:px-card">Destination</th>
            <th scope="col" class="w-[12%] px-2 py-3 xl:px-card">Requested by</th>
            <th scope="col" class="w-[13%] px-2 py-3 xl:px-card">Travel</th>
            <th scope="col" class="w-[5%] px-2 py-3 xl:px-card">Pax</th>
            <th scope="col" class="w-[12%] px-2 py-3 xl:px-card">Status</th>
            <th scope="col" class="w-[17%] px-2 py-3 xl:px-card">Reason</th>
            <th scope="col" class="w-[25%] px-2 py-3 text-right xl:px-card">Actions</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-line text-body">
          <tr
            v-for="request in store.items"
            :key="request.id"
            class="table-row-interactive"
            tabindex="0"
            @click="onRowClick($event, request)"
            @keydown.enter="onRowClick($event, request)"
          >
            <td class="px-2 py-3 align-top xl:px-card">
              <button
                type="button"
                class="text-left font-medium"
                @click="openRequest(request)"
              >
                {{ request.destination }}
              </button>
              <p class="truncate text-small text-ink-muted">{{ request.purpose }}</p>
            </td>
            <td class="px-2 py-3 align-top text-small xl:px-card">
              {{ fullName(request.requested_by) }}
            </td>
            <td class="px-2 py-3 align-top text-small xl:px-card">
              <p>
                {{ formatDate(request.trip_date) }}
                <span v-if="request.travel_days > 1" class="text-ink-muted">
                  ({{ request.travel_days }} days)
                </span>
              </p>
              <p class="text-ink-muted">{{ formatTime(request.departure_time) }}</p>
            </td>
            <td class="px-2 py-3 align-top text-small xl:px-card">{{ request.number_of_passengers ?? '—' }}</td>
            <td class="px-2 py-3 align-top xl:px-card">
              <StatusBadge :status="request.status" />
            </td>
            <!-- Reason for disapproval. Blank when there is none. -->
            <td class="px-2 py-3 align-top text-small text-ink-muted xl:px-card">
              <p
                v-if="request.disapproval_reason"
                class="line-clamp-3 break-words text-red-700"
                :title="request.disapproval_reason"
              >
                {{ request.disapproval_reason }}
              </p>
            </td>
            <td class="px-2 py-3 align-top xl:px-card">
              <div class="flex flex-nowrap items-center justify-end gap-1.5">
                <template v-if="auth.isAdministrator && request.status === 'pending'">
                  <BaseButton size="sm" @click="openApprove(request)">
                    <Check :size="16" aria-hidden="true" /> Approve
                  </BaseButton>
                  <BaseButton size="sm" variant="outline" @click="openDisapprove(request)">
                    <X :size="16" aria-hidden="true" /> Disapprove
                  </BaseButton>
                </template>

                <BaseButton size="sm" variant="ghost" @click="openRequest(request)">
                  <Eye :size="16" aria-hidden="true" /> View
                </BaseButton>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <PaginationBar :meta="store.meta" @change="(page) => store.fetch({ page })" />
    </div>

    <!-- Pagination for the card view (mobile + tablet) -->
    <div
      v-if="!store.loading && store.items.length"
      class="overflow-hidden rounded-card border border-line bg-white lg:hidden"
    >
      <PaginationBar :meta="store.meta" @change="(page) => store.fetch({ page })" />
    </div>

    <BaseModal
      :open="Boolean(selectedRequest)"
      :title="selectedRequest ? `Vehicle request #${selectedRequest.id}` : 'Vehicle request'"
      :subtitle="detailRequest?.destination ?? selectedRequest?.destination ?? ''"
      @close="closeRequest"
    >
      <div v-if="detailLoading" class="flex justify-center p-8" aria-label="Loading request details">
        <div class="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-primary" />
      </div>

      <template v-else-if="detailRequest">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <StatusBadge :status="detailRequest.status" />
        </div>

        <div
          v-if="detailRequest.disapproval_reason"
          class="mb-3 rounded-card border border-red-200 bg-red-50 p-3 text-small text-red-800"
        >
          <p class="font-medium">Disapproved</p>
          <p class="mt-1">{{ detailRequest.disapproval_reason }}</p>
        </div>

        <div
          v-if="detailRequest.edit_reason && detailRequest.status === 'pending'"
          class="mb-3 rounded-card border-l-4 border-secondary bg-secondary-50 p-3 text-small text-secondary-700"
        >
          <p class="font-medium">Edited after approval</p>
          <p class="mt-1">Reason: {{ detailRequest.edit_reason }}</p>
        </div>

        <dl class="divide-y divide-line">
          <div
            v-for="row in requestDetailRows"
            :key="row.label"
            class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-3 sm:gap-4"
          >
            <dt class="text-small text-ink-muted">{{ row.label }}</dt>
            <dd class="text-body sm:col-span-2 sm:capitalize">{{ row.value || '—' }}</dd>
          </div>
        </dl>
      </template>

      <template #footer>
        <BaseButton variant="outline" @click="closeRequest">Close</BaseButton>
        <BaseButton v-if="canEditSelectedRequest" variant="outline" @click="editSelectedRequest">
          Edit
        </BaseButton>
        <template v-if="auth.isAdministrator && detailRequest?.status === 'pending'">
          <BaseButton @click="openApprove(detailRequest)">Approve</BaseButton>
          <BaseButton variant="outline" @click="openDisapprove(detailRequest)">
            Disapprove
          </BaseButton>
        </template>
      </template>
    </BaseModal>

    <ApproveAssignModal :request="approving" @close="approving = null" @done="onApproved" />
    <DisapproveRequestModal
      :request="disapproving"
      @close="disapproving = null"
      @done="onDisapproved"
    />
    <VehicleRequestModal
      :open="showNewRequest"
      @close="closeNewRequest"
      @submitted="onRequestSubmitted"
    />
  </div>
</template>