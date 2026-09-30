<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useVehicleRequestStore } from '@/stores/vehicleRequests'
import { errorMessage, validationErrors } from '@/lib/api'
import { formatDate, formatTime } from '@/lib/format'
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
  { value: '', label: 'All' },
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'cancelled', label: 'Cancelled' },
]

const rejecting = ref(null)
const rejectReason = ref('')
const rejectError = ref('')
const submitting = ref(false)

onMounted(() => store.fetch({ page: 1 }))

function changeTab(status) {
  store.fetch({ status, page: 1 })
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

async function confirmReject() {
  submitting.value = true
  rejectError.value = ''

  try {
    await store.updateStatus(rejecting.value.id, {
      status: 'rejected',
      remarks: rejectReason.value,
    })
    toast.success(`Request #${rejecting.value.id} rejected.`)
    rejecting.value = null
    rejectReason.value = ''
    await store.fetch()
  } catch (error) {
    rejectError.value = validationErrors(error).remarks ?? errorMessage(error)
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
      <button
        v-for="tab in tabs"
        :key="tab.value"
        type="button"
        class="flex-shrink-0 h-9 rounded-card px-3 text-small font-medium transition whitespace-nowrap"
        :class="
          store.filters.status === tab.value
            ? 'bg-primary text-white'
            : 'border border-line bg-white text-ink-muted hover:bg-neutral-50'
        "
        @click="changeTab(tab.value)"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Content Area -->
    <div v-if="store.loading" class="flex justify-center p-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-primary" />
    </div>

    <EmptyState
      v-else-if="!store.items.length"
      title="No requests found"
      message="Try a different status filter, or create a new request."
    />

    <!-- Mobile Card View -->
    <div v-else class="flex flex-col gap-3 md:hidden">
      <div
        v-for="request in store.items"
        :key="request.id"
        class="card-row"
      >
        <div class="card-row-header">
          <div class="flex-1 min-w-0">
            <RouterLink
              :to="{ name: 'requests.show', params: { id: request.id } }"
              class="card-row-value truncate hover:text-primary"
            >
              {{ request.destination }}
            </RouterLink>
            <p class="text-small text-ink-muted truncate mt-0.5">{{ request.purpose }}</p>
          </div>
          <StatusBadge :status="request.status" />
        </div>

        <div class="space-y-1.5 mb-3">
          <div class="flex items-center justify-between">
            <span class="card-row-label">Requested by</span>
            <span class="text-small text-ink">{{ request.requested_by?.first_name }} {{ request.requested_by?.last_name }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="card-row-label">Travel date</span>
            <span class="text-small text-ink">{{ formatDate(request.trip_date) }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="card-row-label">Departure</span>
            <span class="text-small text-ink">{{ formatTime(request.departure_time) }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="card-row-label">Passengers</span>
            <span class="text-small text-ink">{{ request.number_of_passengers ?? '—' }}</span>
          </div>
        </div>

        <div class="flex gap-2 pt-3 border-t border-line">
          <template v-if="auth.isAdministrator && request.status === 'pending'">
            <BaseButton size="sm" :disabled="submitting" @click="approve(request)" class="flex-1">
              Approve
            </BaseButton>
            <BaseButton
              size="sm"
              variant="outline"
              @click="((rejecting = request), (rejectReason = ''))"
              class="flex-1"
            >
              Reject
            </BaseButton>
          </template>

          <BaseButton
            size="sm"
            variant="ghost"
            @click="router.push({ name: 'requests.show', params: { id: request.id } })"
            :class="auth.isAdministrator && request.status === 'pending' ? '' : 'w-full'"
          >
            View
          </BaseButton>
        </div>
      </div>
    </div>

    <!-- Desktop Table View -->
    <div class="hidden md:block overflow-hidden rounded-card border border-line bg-white shadow-card">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[820px] border-collapse">
          <thead class="table-head">
            <tr>
              <th class="px-card py-3">Destination</th>
              <th class="px-card py-3">Requested by</th>
              <th class="px-card py-3">Travel date</th>
              <th class="px-card py-3">Departure</th>
              <th class="px-card py-3">Pax</th>
              <th class="px-card py-3">Status</th>
              <th class="px-card py-3 text-right">Actions</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-line text-body">
            <tr v-for="request in store.items" :key="request.id" class="hover:bg-neutral-50">
              <td class="px-card py-3">
                <RouterLink
                  :to="{ name: 'requests.show', params: { id: request.id } }"
                  class="font-medium hover:text-primary"
                >
                  {{ request.destination }}
                </RouterLink>
                <p class="truncate text-small text-ink-muted">{{ request.purpose }}</p>
              </td>
              <td class="px-card py-3 text-small">{{ request.requested_by?.first_name }} {{ request.requested_by?.last_name }}</td>
              <td class="px-card py-3 text-small">
                {{ formatDate(request.trip_date) }}
                <span v-if="request.travel_days > 1" class="text-ink-muted">
                  ({{ request.travel_days }} days)
                </span>
              </td>
              <td class="px-card py-3 text-small">{{ formatTime(request.departure_time) }}</td>
              <td class="px-card py-3 text-small">{{ request.number_of_passengers ?? '—' }}</td>
              <td class="px-card py-3"><StatusBadge :status="request.status" /></td>
              <td class="px-card py-3">
                <div class="flex justify-end gap-2">
                  <template v-if="auth.isAdministrator && request.status === 'pending'">
                    <BaseButton size="sm" :disabled="submitting" @click="approve(request)">
                      Approve
                    </BaseButton>
                    <BaseButton
                      size="sm"
                      variant="outline"
                      @click="((rejecting = request), (rejectReason = ''))"
                    >
                      Reject
                    </BaseButton>
                  </template>

                  <BaseButton
                    size="sm"
                    variant="ghost"
                    @click="router.push({ name: 'requests.show', params: { id: request.id } })"
                  >
                    View
                  </BaseButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <PaginationBar :meta="store.meta" @change="(page) => store.fetch({ page })" />
    </div>

    <!-- Reject Modal -->
    <BaseModal
      :open="Boolean(rejecting)"
      title="Reject request"
      :subtitle="rejecting ? `Request #${rejecting.id} — ${rejecting.destination}` : ''"
      @close="rejecting = null"
    >
      <BaseTextarea
        v-model="rejectReason"
        label="Reason for disapproval"
        placeholder="e.g. Vehicle already assigned for that date."
        :error="rejectError"
        required
      />

      <template #footer>
        <BaseButton variant="outline" @click="rejecting = null">Cancel</BaseButton>
        <BaseButton variant="danger" :loading="submitting" @click="confirmReject">
          Reject request
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>