<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useGuardLogStore } from '@/stores/guardLogs'
import { useVehicleRequestStore } from '@/stores/vehicleRequests'
import { errorMessage, validationErrors } from '@/lib/api'
import { formatDate, formatTime, todayIso } from '@/lib/format'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'

const auth = useAuthStore()
const store = useGuardLogStore()
const requests = useVehicleRequestStore()
const toast = useToast()

const departureOpen = ref(false)
const returnTarget = ref(null)
const submitting = ref(false)
const errors = ref({})
const approvedOptions = ref([])

const departure = reactive({
  vehicle_request_id: '',
  vehicle_used: '',
  actual_departure_date: todayIso(),
  actual_departure_time: '',
  vehicle_condition_departure: '',
  remarks: '',
})

const arrival = reactive({
  actual_return_date: todayIso(),
  actual_return_time: '',
  vehicle_condition_return: '',
  remarks: '',
})

onMounted(() => store.fetch(1))

async function openDeparture() {
  errors.value = {}
  departureOpen.value = true

  try {
    const items = await requests.fetch({ status: 'approved', page: 1 })
    approvedOptions.value = items.map((item) => ({
      value: item.id,
      label: `#${item.id} · ${item.destination} · ${formatDate(item.trip_date)}`,
    }))
  } catch (error) {
    toast.error(errorMessage(error, 'Unable to load approved requests.'))
  }
}

async function submitDeparture() {
  submitting.value = true
  errors.value = {}

  try {
    await store.recordDeparture({ ...departure })
    toast.success('Departure recorded at the main gate.')
    departureOpen.value = false
    Object.assign(departure, {
      vehicle_request_id: '',
      vehicle_used: '',
      actual_departure_date: todayIso(),
      actual_departure_time: '',
      vehicle_condition_departure: '',
      remarks: '',
    })
    await store.fetch(1)
  } catch (error) {
    errors.value = validationErrors(error)
    if (!Object.keys(errors.value).length) {
      toast.error(errorMessage(error, 'Unable to record the departure.'))
    }
  } finally {
    submitting.value = false
  }
}

function openReturn(log) {
  errors.value = {}
  returnTarget.value = log
  Object.assign(arrival, {
    actual_return_date: todayIso(),
    actual_return_time: '',
    vehicle_condition_return: '',
    remarks: log.remarks ?? '',
  })
}

async function submitReturn() {
  submitting.value = true
  errors.value = {}

  try {
    await store.recordReturn(returnTarget.value.id, { ...arrival })
    toast.success('Return recorded.')
    returnTarget.value = null
    await store.fetch(store.page)
  } catch (error) {
    errors.value = validationErrors(error)
    if (!Object.keys(errors.value).length) {
      toast.error(errorMessage(error, 'Unable to record the return.'))
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-page">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-page-title">Gate logs</h1>
        <p class="mt-0.5 text-small text-ink-muted">
          To be filled by the guard on duty at the main gate
        </p>
      </div>

      <BaseButton v-if="auth.isGuard" @click="openDeparture">Record departure</BaseButton>
    </div>

    <div class="overflow-hidden rounded-card border border-line bg-white shadow-card">
      <div v-if="store.loading" class="flex justify-center p-12">
        <div class="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-primary" />
      </div>

      <EmptyState
        v-else-if="!store.items.length"
        title="No gate logs yet"
        message="Departures recorded at the main gate will appear here."
      />

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[860px] border-collapse">
          <thead class="table-head">
            <tr>
              <th class="px-card py-3">Request</th>
              <th class="px-card py-3">Vehicle</th>
              <th class="px-card py-3">Date out</th>
              <th class="px-card py-3">Time out</th>
              <th class="px-card py-3">Date in</th>
              <th class="px-card py-3">Time in</th>
              <th class="px-card py-3">Guard</th>
              <th class="px-card py-3 text-right">Actions</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-line text-body">
            <tr v-for="log in store.items" :key="log.id" class="hover:bg-neutral-50">
              <td class="px-card py-3">
                <p class="font-medium">#{{ log.vehicle_request_id }}</p>
                <p class="truncate text-small text-ink-muted">{{ log.destination ?? '—' }}</p>
              </td>
              <td class="px-card py-3 text-small">{{ log.vehicle_used ?? '—' }}</td>
              <td class="px-card py-3 text-small">{{ formatDate(log.actual_departure_date) }}</td>
              <td class="px-card py-3 text-small">{{ formatTime(log.actual_departure_time) }}</td>
              <td class="px-card py-3 text-small">
                <span v-if="log.actual_return_date">{{ formatDate(log.actual_return_date) }}</span>
                <span
                  v-else
                  class="inline-flex rounded-full bg-secondary-50 px-2.5 py-1 text-small font-medium text-secondary-700"
                >
                  On trip
                </span>
              </td>
              <td class="px-card py-3 text-small">{{ formatTime(log.actual_return_time) }}</td>
              <td class="px-card py-3 text-small">{{ log.guard?.name }}</td>
              <td class="px-card py-3 text-right">
                <BaseButton
                  v-if="auth.isGuard && !log.actual_return_date"
                  size="sm"
                  variant="outline"
                  @click="openReturn(log)"
                >
                  Record return
                </BaseButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <PaginationBar :meta="store.meta" @change="(page) => store.fetch(page)" />
    </div>

    <!-- Departure -->
    <BaseModal
      :open="departureOpen"
      title="Record departure"
      subtitle="Log the vehicle leaving the main gate."
      @close="departureOpen = false"
    >
      <div class="flex flex-col gap-4">
        <BaseSelect
          v-model="departure.vehicle_request_id"
          label="Approved request"
          placeholder="Select an approved trip"
          :options="approvedOptions"
          :error="errors.vehicle_request_id"
          required
        />

        <BaseInput
          v-model="departure.vehicle_used"
          label="Vehicle plate no. / unit"
          placeholder="e.g. SBA 1173"
          :error="errors.vehicle_used"
        />

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput
            v-model="departure.actual_departure_date"
            label="Date"
            type="date"
            :error="errors.actual_departure_date"
            required
          />
          <BaseInput
            v-model="departure.actual_departure_time"
            label="Timeout"
            type="time"
            :error="errors.actual_departure_time"
            required
          />
        </div>

        <BaseInput
          v-model="departure.vehicle_condition_departure"
          label="Vehicle condition on departure"
          placeholder="e.g. Good running condition"
          :error="errors.vehicle_condition_departure"
        />

        <BaseTextarea v-model="departure.remarks" label="Remarks" :error="errors.remarks" />
      </div>

      <template #footer>
        <BaseButton variant="outline" @click="departureOpen = false">Cancel</BaseButton>
        <BaseButton :loading="submitting" @click="submitDeparture">Save departure</BaseButton>
      </template>
    </BaseModal>

    <!-- Return -->
    <BaseModal
      :open="Boolean(returnTarget)"
      title="Record return"
      :subtitle="returnTarget ? `Request #${returnTarget.vehicle_request_id}` : ''"
      @close="returnTarget = null"
    >
      <div class="flex flex-col gap-4">
        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput
            v-model="arrival.actual_return_date"
            label="Date"
            type="date"
            :error="errors.actual_return_date"
            required
          />
          <BaseInput
            v-model="arrival.actual_return_time"
            label="Time in"
            type="time"
            :error="errors.actual_return_time"
            required
          />
        </div>

        <BaseInput
          v-model="arrival.vehicle_condition_return"
          label="Vehicle condition on return"
          :error="errors.vehicle_condition_return"
        />

        <BaseTextarea v-model="arrival.remarks" label="Remarks" :error="errors.remarks" />
      </div>

      <template #footer>
        <BaseButton variant="outline" @click="returnTarget = null">Cancel</BaseButton>
        <BaseButton :loading="submitting" @click="submitReturn">Save return</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>