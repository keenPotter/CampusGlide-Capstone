<script setup>
import { computed, ref, watch } from 'vue'
import api, { errorMessage, unwrap, validationErrors } from '@/lib/api'
import { formatDate, formatTime } from '@/lib/format'
import {
  availabilityOptions,
  conflictOf,
  shiftedRange,
  vehicleAvailabilityOptions,
} from '@/lib/allocations'
import { tripModificationChanges } from '@/lib/tripModifications'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'

const props = defineProps({
  item: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved', 'conflict'])

const action = ref('')
const note = ref('')
const error = ref('')
const saving = ref(false)
const loadingOptions = ref(false)
const optionsError = ref('')
const vehicleItems = ref([])
const driverItems = ref([])
const vehicleId = ref('')
const driverId = ref('')
let optionsRequest = 0

const isApprove = computed(() => action.value === 'approve')
const isDisapprove = computed(() => action.value === 'disapprove')
const changes = computed(() => (props.item ? tripModificationChanges(props.item) : []))
const vehicleOptions = computed(() => vehicleAvailabilityOptions(vehicleItems.value))
const driverOptions = computed(() => availabilityOptions(driverItems.value))
const availableVehicles = computed(() => vehicleItems.value.filter((vehicle) => vehicle.available))
const availableDrivers = computed(() => driverItems.value.filter((driver) => driver.available))
const currentVehicleOption = computed(() => {
  const id = props.item?.trip?.vehicle?.id
  return vehicleItems.value.find((vehicle) => String(vehicle.id) === String(id))
})
const currentDriverOption = computed(() => {
  const id = props.item?.trip?.driver?.id
  return driverItems.value.find((driver) => String(driver.id) === String(id))
})
const currentVehicleUnavailable = computed(() =>
  !loadingOptions.value && !optionsError.value && currentVehicleOption.value?.available !== true,
)
const currentDriverUnavailable = computed(() =>
  !loadingOptions.value && !optionsError.value && currentDriverOption.value?.available !== true,
)
const canApprove = computed(() =>
  !loadingOptions.value
  && !optionsError.value
  && availableVehicles.value.some((vehicle) => String(vehicle.id) === vehicleId.value)
  && availableDrivers.value.some((driver) => String(driver.id) === driverId.value),
)

watch(() => props.item, () => {
  action.value = ''
  optionsRequest += 1
  resetForm()
  if (props.item) loadOptions(props.item, optionsRequest)
})

watch(action, () => {
  note.value = ''
  error.value = ''
})

function resetForm() {
  note.value = ''
  error.value = ''
  optionsError.value = ''
  loadingOptions.value = false
  vehicleItems.value = []
  driverItems.value = []
  vehicleId.value = ''
  driverId.value = ''
}

async function loadOptions(item, request) {
  const trip = item.trip
  const startDate = item.new_date || trip?.trip_date
  if (!trip?.id || !trip.trip_date || !startDate) {
    optionsError.value = 'Trip schedule details are missing, so availability cannot be checked.'
    return
  }

  const shifted = shiftedRange(trip.trip_date, trip.trip_end_date, startDate)
  const endDate = item.new_trip_end_date || shifted.end
  const startTime = String(item.new_departure_time ?? trip.departure_time ?? '').slice(0, 5)
  const endTime = String(item.new_estimated_return_time ?? trip.estimated_return_time ?? '').slice(0, 5)

  loadingOptions.value = true
  try {
    const data = unwrap(await api.get('/allocation-options', {
      params: {
        start_date: startDate,
        end_date: endDate,
        start_time: startTime || undefined,
        end_time: endTime || undefined,
        ignore_trip_id: trip.id,
      },
    }))
    if (request !== optionsRequest) return

    vehicleItems.value = data.vehicles
    driverItems.value = data.drivers
    const currentVehicleId = trip.vehicle?.id
    const currentDriverId = trip.driver?.id
    vehicleId.value = availableVehicles.value.some((vehicle) => String(vehicle.id) === String(currentVehicleId))
      ? String(currentVehicleId)
      : ''
    driverId.value = availableDrivers.value.some((driver) => String(driver.id) === String(currentDriverId))
      ? String(currentDriverId)
      : ''
  } catch (e) {
    if (request === optionsRequest) {
      optionsError.value = errorMessage(e, 'Unable to check vehicle and driver availability.')
    }
  } finally {
    if (request === optionsRequest) loadingOptions.value = false
  }
}

async function submit() {
  error.value = ''

  if (isDisapprove.value && !note.value.trim()) {
    error.value = 'Please give a reason for disapproving this request.'
    return
  }
  if (isApprove.value && !canApprove.value) {
    error.value = 'Select an available vehicle and driver before approving.'
    return
  }
  if (!props.item || !action.value) return

  saving.value = true
  try {
    const endpointAction = isDisapprove.value ? 'reject' : 'approve'
    await api.post(`/trip-modification-requests/${props.item.id}/${endpointAction}`, {
      admin_reason: note.value.trim() || undefined,
      ...(isApprove.value ? {
        vehicle_id: Number(vehicleId.value),
        driver_id: Number(driverId.value),
      } : {}),
    })
    emit(
      'saved',
      isApprove.value
        ? 'Request approved. The trip was updated.'
        : 'Request rejected. The faculty member can see your reason.',
    )
  } catch (e) {
    const conflict = conflictOf(e)
    if (conflict) {
      emit('conflict', conflict)
      return
    }

    error.value = validationErrors(e).admin_reason ?? errorMessage(e, 'Unable to save your decision.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <BaseModal
    :open="Boolean(item)"
    title="Review Trip Modification"
    :subtitle="item?.trip?.destination ?? ''"
    @close="emit('close')"
  >
    <div v-if="item" class="flex flex-col gap-4">
      <div class="rounded-card bg-surface p-3 text-small">
        <p class="text-ink-muted">Requested by {{ item.requested_by ?? '—' }}</p>
        <p class="mt-1 text-ink-muted">
          Current assignment: {{ item.trip?.vehicle?.plate_number ?? 'No vehicle' }} ·
          {{ item.trip?.driver?.name ?? 'No driver' }}
        </p>
        <p class="mt-1 text-ink-muted">Reason for change: {{ item.reason || '—' }}</p>
      </div>

      <div class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_2fr_2fr]">
        <div class="text-small font-medium text-ink-muted">Field</div>
        <div class="text-small font-medium text-ink-muted">Original</div>
        <div class="text-small font-medium text-ink-muted">Requested</div>
        <template v-for="change in changes" :key="change.label">
          <div class="flex items-center gap-2 text-small font-medium">
            {{ change.label }}
            <span
              class="rounded-full px-2 py-0.5 text-xs"
              :class="change.changed ? 'bg-secondary-50 text-secondary-700' : 'bg-neutral-100 text-ink-muted'"
            >
              {{ change.changed ? 'Changed' : 'No change' }}
            </span>
          </div>
          <div class="rounded bg-surface p-2 text-small">{{ change.oldValue }}</div>
          <div
            class="rounded p-2 text-small"
            :class="change.changed ? 'bg-secondary-50 font-medium text-secondary-700' : 'bg-surface'"
          >
            {{ change.newValue }}
          </div>
        </template>
      </div>

      <div class="rounded-card border border-line p-3 text-small" aria-live="polite">
        <p v-if="loadingOptions" class="text-ink-muted">
          Checking the assigned vehicle and driver for the requested dates and times…
        </p>
        <p v-else-if="optionsError" role="alert" class="text-red-700">
          {{ optionsError }} Approval is disabled until availability can be verified.
        </p>
        <template v-else>
          <p
            v-if="currentVehicleUnavailable"
            class="text-amber-800"
          >
            The originally assigned vehicle ({{ item.trip?.vehicle?.plate_number ?? 'unknown' }}) is
            unavailable{{ currentVehicleOption?.unavailable_reason ? `: ${currentVehicleOption.unavailable_reason}` : ' for this schedule' }}.
            Changing the vehicle is required before approval.
          </p>
          <p v-else class="text-green-700">
            The originally assigned vehicle is available for this schedule.
          </p>
          <p
            class="mt-1"
            :class="currentDriverUnavailable ? 'text-amber-800' : 'text-green-700'"
          >
            <template v-if="currentDriverUnavailable">
              The originally assigned driver ({{ item.trip?.driver?.name ?? 'unknown' }}) is
              unavailable{{ currentDriverOption?.unavailable_reason ? `: ${currentDriverOption.unavailable_reason}` : ' for this schedule' }}.
              Changing the driver is required before approval.
            </template>
            <template v-else>
              The originally assigned driver is available for this schedule.
            </template>
          </p>
        </template>
      </div>

      <template v-if="isApprove">
        <p class="text-small text-ink-muted">
          Checking availability for {{ formatDate(item.new_date ?? item.trip?.trip_date) }}
          – {{ formatDate(item.new_trip_end_date ?? item.trip?.trip_end_date) }}
          · {{ formatTime(item.new_departure_time ?? item.trip?.departure_time) }}
          <template v-if="item.new_estimated_return_time ?? item.trip?.estimated_return_time">
            – {{ formatTime(item.new_estimated_return_time ?? item.trip?.estimated_return_time) }}
          </template>
        </p>
        <BaseSelect
          v-model="vehicleId"
          label="Vehicle"
          :options="vehicleOptions"
          :placeholder="loadingOptions ? 'Checking availability...' : 'Select an available vehicle'"
          :disabled="loadingOptions || Boolean(optionsError)"
          required
        />
        <p v-if="!loadingOptions && !optionsError && !availableVehicles.length" class="text-small text-red-700">
          No vehicle is available for this schedule. Go back and disapprove the request instead.
        </p>
        <BaseSelect
          v-model="driverId"
          label="Driver"
          :options="driverOptions"
          :placeholder="loadingOptions ? 'Checking availability...' : 'Select an available driver'"
          :disabled="loadingOptions || Boolean(optionsError)"
          required
        />
        <p v-if="!loadingOptions && !optionsError && !availableDrivers.length" class="text-small text-red-700">
          No driver is available for this schedule. Go back and disapprove the request instead.
        </p>
      </template>

      <BaseTextarea
        v-if="isApprove || isDisapprove"
        v-model="note"
        :label="isApprove ? 'Note (optional)' : 'Reason for disapproving'"
        :placeholder="isApprove ? 'Add a note for the requester.' : 'Why is this request not possible?'"
        :error="error"
        :required="isDisapprove"
      />
      <p v-if="error && !isApprove && !isDisapprove" role="alert" class="text-small text-red-700">
        {{ error }}
      </p>
    </div>

    <template #footer>
      <BaseButton variant="outline" @click="emit('close')">Cancel</BaseButton>
      <template v-if="!action">
        <BaseButton variant="danger" @click="action = 'disapprove'">Disapprove</BaseButton>
        <BaseButton @click="action = 'approve'">Approve</BaseButton>
      </template>
      <template v-else>
        <BaseButton variant="outline" @click="action = ''">Back</BaseButton>
        <BaseButton
          :variant="isApprove ? 'primary' : 'danger'"
          :loading="saving"
          :disabled="(isApprove && !canApprove) || (isDisapprove && !note.trim())"
          @click="submit"
        >
          {{ isApprove ? 'Approve and apply changes' : 'Confirm disapproval' }}
        </BaseButton>
      </template>
    </template>
  </BaseModal>
</template>
