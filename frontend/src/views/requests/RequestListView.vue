<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useVehicleRequestStore } from '@/stores/vehicleRequests'
import { errorMessage, validationErrors } from '@/lib/api'
import { formatDate, formatTime } from '@/lib/format'
import { printRequest } from '@/lib/printRequest'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'

const auth = useAuthStore()
const store = useVehicleRequestStore()
const router = useRouter()
const toast = useToast()

const tabs = [
  { value: '', label: 'All', field: 'status' },
  { value: 'pending', label: 'Pending', field: 'status' },
  { value: 'approved', label: 'Approved', field: 'status' },
  { value: 'disapproved', label: 'Disapproved', field: 'status' },
  { value: 'inclusive', label: 'Inclusive', field: 'trip_type' },
  { value: 'exclusive', label: 'Exclusive', field: 'trip_type' },
]

// Only one filter is active at a time: either a status or a trip type.
const activeTab = computed(() => store.filters.trip_type || store.filters.status)

const disapproving = ref(null)
const disapproveReason = ref('')
const disapproveError = ref('')
const submitting = ref(false)

// Reset the filters too, because the store is shared with other pages.
onMounted(() => store.fetch({ status: '', trip_type: '', page: 1 }))

function changeTab(tab) {
  store.fetch({
    status: tab.field === 'status' ? tab.value : '',
    trip_type: tab.field === 'trip_type' ? tab.value : '',
    page: 1,
  })
}

// True when the row already shows a button besides "View"
// (Approve/Disapprove for pending requests, Print for approved requests).
// Both of these are for administrators only.
function hasOtherActions(request) {
  return auth.isAdministrator && (request.status === 'approved' || request.status === 'pending')
}

async function print(request) {
  try {
    await printRequest(request)
  } catch (error) {
    toast.error(errorMessage(error, 'Unable to print this request.'))
  }
}

async function approve(request) {
  submitting.value = true
  try {
    await store.updateStatus(request.id, { status: 'approved' })
    toast.success(`Request #${request.id} approved.`)
    await store.fetch()
  } catch (error) {
    toast.error(errorMessage(error, 'Unable to approve this request.'))
  } finally {
    submitting.value = false
  }
}

async function confirmDisapprove() {
  submitting.value = true
  disapproveError.value = ''

  try {
    await store.updateStatus(disapproving.value.id, {
      status: 'disapproved',
      remarks: disapproveReason.value,
    })
    toast.success(`Request #${disapproving.value.id} disapproved.`)
    disapproving.value = null
    disapproveReason.value = ''
    await store.fetch()
  } catch (error) {
    disapproveError.value = validationErrors(error).remarks ?? errorMessage(error)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-page">
    <!-- Page Header -->
    <div class="flex flex-col gap-3">
      <div>
        <h1 class="text-page-title">
          {{ auth.isFaculty ? 'My vehicle requests' : 'Vehicle requests' }}
        </h1>
        <p class="mt-1 text-small text-ink-muted">Request for Use of Vehicle (NVSU-FR-PPS-13-02)</p>
      </div>

      <BaseButton v-if="auth.isFaculty" @click="router.push({ name: 'requests.create' })" block>
        New request
      </BaseButton>
    </div>

    <!-- Tab Filter - Horizontal Scroll on Mobile -->
    <div class="flex gap-2 overflow-x-auto pb-2 -mx-page px-page md:flex-wrap md:pb-0 md:mx-0 md:px-0">
      <button v-for="tab in tabs" :key="tab.label" type="button"
        class="flex-shrink-0 h-9 rounded-card px-3 text-small font-medium transition whitespace-nowrap" :class="activeTab === tab.value
            ? 'bg-primary text-white'
            : 'border border-line bg-white text-ink-muted hover:bg-neutral-50'
          " @click="changeTab(tab)">
        {{ tab.label }}
      </button>
    </div>

    <!-- Content Area -->
    <div v-if="store.loading" class="flex justify-center p-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-primary" />
    </div>

    <EmptyState v-else-if="!store.items.length" title="No requests found"
      message="Try a different status filter, or create a new request." />

    <!-- Mobile + Tablet Card View (1 column on mobile, 2 columns on tablet) -->
    <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4 lg:hidden">
      <div v-for="request in store.items" :key="request.id" class="card-row !mb-0 flex flex-col">
        <div class="card-row-header">
          <div class="flex-1 min-w-0">
            <RouterLink :to="{ name: 'requests.show', params: { id: request.id } }"
              class="card-row-value block truncate hover:text-primary md:text-section-title"
              :title="request.destination">
              {{ request.destination }}
            </RouterLink>
            <p class="text-small text-ink-muted truncate mt-0.5 md:text-body">{{ request.purpose }}</p>
          </div>
          <StatusBadge :status="request.status" class="shrink-0" />
        </div>

        <div class="space-y-1.5 mb-3 md:space-y-2">
          <div class="flex items-center justify-between">
            <span class="card-row-label md:text-body">Requested by</span>
            <span class="text-small text-ink md:text-body">{{ request.requested_by?.first_name }} {{
              request.requested_by?.last_name }}</span>
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
        </div>

        <div class="mt-auto flex gap-2 pt-3 border-t border-line">
          <template v-if="auth.isAdministrator && request.status === 'pending'">
            <BaseButton size="sm" :disabled="submitting" @click="approve(request)" class="flex-1">
              Approve
            </BaseButton>
            <BaseButton size="sm" variant="outline"
              @click="((disapproving = request), (disapproveReason = ''), (disapproveError = ''))" class="flex-1">
              Disapprove
            </BaseButton>
          </template>

          <!-- Print is for administrators only, and only after the request is approved -->
          <BaseButton v-if="auth.isAdministrator && request.status === 'approved'" size="sm" variant="outline"
            @click="print(request)" class="flex-1">
            Print
          </BaseButton>

          <BaseButton size="sm" variant="ghost"
            @click="router.push({ name: 'requests.show', params: { id: request.id } })"
            :class="hasOtherActions(request) ? '' : 'w-full'">
            View
          </BaseButton>
        </div>
      </div>
    </div>

    <!-- Laptop Table View (fits the screen, no side scrolling) -->
    <div v-if="!store.loading && store.items.length"
      class="hidden lg:block overflow-hidden rounded-card border border-line bg-white shadow-card">
      <table class="w-full table-fixed border-collapse">
        <thead class="table-head">
          <tr>
            <th class="w-[20%] px-2 py-3 xl:px-card">Destination</th>
            <th class="w-[14%] px-2 py-3 xl:px-card">Requested by</th>
            <th class="w-[16%] px-2 py-3 xl:px-card">Travel</th>
            <th class="w-[7%] px-2 py-3 xl:px-card">Pax</th>
            <th class="w-[14%] px-2 py-3 xl:px-card">Status</th>
            <th class="w-[29%] px-2 py-3 text-right xl:px-card">Actions</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-line text-body">
          <tr v-for="request in store.items" :key="request.id" class="hover:bg-neutral-50">
            <td class="px-2 py-3 align-top xl:px-card">
              <RouterLink :to="{ name: 'requests.show', params: { id: request.id } }"
                class="font-medium hover:text-primary">
                {{ request.destination }}
              </RouterLink>
              <p class="truncate text-small text-ink-muted">{{ request.purpose }}</p>
            </td>
            <td class="px-2 py-3 align-top text-small xl:px-card">
              {{ request.requested_by?.first_name }} {{ request.requested_by?.last_name }}
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
            <td class="px-2 py-3 align-top xl:px-card">
              <div class="flex flex-wrap justify-end gap-1.5">
                <template v-if="auth.isAdministrator && request.status === 'pending'">
                  <BaseButton size="sm" :disabled="submitting" @click="approve(request)">
                    Approve
                  </BaseButton>
                  <BaseButton size="sm" variant="outline"
                    @click="((disapproving = request), (disapproveReason = ''), (disapproveError = ''))">
                    Disapprove
                  </BaseButton>
                </template>

                <!-- Print is for administrators only, and only after the request is approved -->
                <BaseButton v-if="auth.isAdministrator && request.status === 'approved'" size="sm"
                  @click="print(request)">
                  Print
                </BaseButton>

                <BaseButton size="sm" variant="ghost"
                  @click="router.push({ name: 'requests.show', params: { id: request.id } })">
                  View
                </BaseButton>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <PaginationBar :meta="store.meta" @change="(page) => store.fetch({ page })" />
    </div>

    <!-- Pagination for the card view (mobile + tablet) -->
    <div v-if="!store.loading && store.items.length"
      class="overflow-hidden rounded-card border border-line bg-white lg:hidden">
      <PaginationBar :meta="store.meta" @change="(page) => store.fetch({ page })" />
    </div>

    <!-- Disapprove Modal -->
    <BaseModal :open="Boolean(disapproving)" title="Disapprove request"
      :subtitle="disapproving ? `Request #${disapproving.id} — ${disapproving.destination}` : ''"
      @close="disapproving = null">
      <BaseTextarea v-model="disapproveReason" label="Reason for disapproval"
        placeholder="e.g. Vehicle already assigned for that date." :error="disapproveError" required />

      <template #footer>
        <BaseButton variant="outline" @click="disapproving = null">Cancel</BaseButton>
        <BaseButton variant="danger" :loading="submitting" @click="confirmDisapprove">
          Disapprove request
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>