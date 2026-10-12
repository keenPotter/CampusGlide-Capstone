<script setup>
import { computed, reactive, ref, watch } from 'vue'
import api, { errorMessage, unwrap, validationErrors } from '@/lib/api'
import { conflictOf } from '@/lib/allocations'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import ConflictModal from '@/components/allocations/ConflictModal.vue'
import QuickAddModal from '@/components/allocations/QuickAddModal.vue'

// Assigns a vehicle and driver to an approved request, or reassigns an existing allocation.
// `allocation` is set when reassigning. `presetRequestId` is set when "Assign" was clicked on a request.
// The lists (requests, vehicles, drivers) come from the page, so this window does not load them again.
const props = defineProps({
  open: { type: Boolean, default: false },
  allocation: { type: Object, default: null },
  presetRequestId: { type: [Number, String], default: null },
  requests: { type: Array, default: () => [] },
  vehicles: { type: Array, default: () => [] },
  drivers: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'saved', 'vehicle-added', 'driver-added'])

const statusOptions = [
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'completed', label: 'Completed' },
]

const form = reactive({ request_id: '', vehicle_id: '', driver_id: '', status: '', notes: '' })
const errors = ref({})
const formError = ref('')
const saving = ref(false)
const conflict = ref(null)
const quickAdd = ref('')

const isEdit = computed(() => Boolean(props.allocation))

const title = computed(() =>
  isEdit.value ? `Reassign allocation #${props.allocation.id}` : 'New allocation',
)

const requestOptions = computed(() => {
  if (isEdit.value) {
    const vehicleRequest = props.allocation.vehicle_request
    return [
      {
        value: vehicleRequest?.id,
        label: `#${vehicleRequest?.id} · ${props.allocation.trip?.destination ?? ''}`,
      },
    ]
  }

  return props.requests.map((item) => ({ value: item.id, label: item.label }))
})

// When reassigning, the current vehicle or driver is always in the list.
const vehicleOptions = computed(() => {
  const options = props.vehicles.map((item) => ({ value: item.id, label: item.label }))
  const current = props.allocation?.vehicle

  if (current?.id && !options.some((item) => item.value === current.id)) {
    options.unshift({ value: current.id, label: `${current.plate_number} — ${current.vehicle_model}` })
  }

  return options
})

const driverOptions = computed(() => {
  const options = props.drivers.map((item) => ({ value: item.id, label: item.label }))
  const current = props.allocation?.driver

  if (current?.id && !options.some((item) => item.value === current.id)) {
    options.unshift({ value: current.id, label: current.name })
  }

  return options
})

const requestPlaceholder = computed(() =>
  props.requests.length ? 'Select approved request' : 'No approved requests waiting',
)

// Start fresh each time the window opens.
watch(
  () => props.open,
  (open) => {
    if (!open) return

    errors.value = {}
    formError.value = ''
    conflict.value = null
    quickAdd.value = ''

    const current = props.allocation
    Object.assign(form, {
      request_id: current ? (current.vehicle_request?.id ?? '') : (props.presetRequestId ?? ''),
      vehicle_id: current?.vehicle?.id ?? '',
      driver_id: current?.driver?.id ?? '',
      status: current?.status ?? '',
      notes: current?.notes ?? '',
    })
  },
)

async function save() {
  errors.value = {}
  formError.value = ''

  const vehicleId = Number(form.vehicle_id) || null
  const driverId = Number(form.driver_id) || null

  if (!isEdit.value) {
    const problems = {}
    if (!form.request_id) problems.vehicle_request_id = 'Please select a request.'
    if (!vehicleId) problems.vehicle_id = 'Please select a vehicle.'
    if (!driverId) problems.driver_id = 'Please select a driver.'

    if (Object.keys(problems).length) {
      errors.value = problems
      return
    }
  }

  saving.value = true
  try {
    if (isEdit.value) {
      await api.put(`/allocations/${props.allocation.id}`, {
        vehicle_id: vehicleId,
        driver_id: driverId,
        status: form.status || undefined,
        notes: form.notes || null,
      })
      emit('saved', null)
    } else {
      const response = await api.post('/allocations', {
        vehicle_request_id: Number(form.request_id),
        vehicle_id: vehicleId,
        driver_id: driverId,
        notes: form.notes || null,
      })
      emit('saved', unwrap(response))
    }
  } catch (error) {
    // The vehicle or driver is already booked at that time.
    const found = conflictOf(error)
    if (found) {
      conflict.value = found
      return
    }

    errors.value = validationErrors(error)
    if (!Object.keys(errors.value).length) {
      formError.value = errorMessage(error, 'Unable to save this allocation.')
    }
  } finally {
    saving.value = false
  }
}

// After a conflict, clear the choice that caused it so a new one is picked.
function closeConflict() {
  if (conflict.value?.type === 'driver') form.driver_id = ''
  else form.vehicle_id = ''
  conflict.value = null
}

function onQuickAdded({ kind, item }) {
  if (kind === 'driver') {
    emit('driver-added', item)
    form.driver_id = item.id
  } else {
    emit('vehicle-added', item)
    form.vehicle_id = item.id
  }
  quickAdd.value = ''
}
</script>

<template>
  <BaseModal :open="open" :title="title" @close="emit('close')">
    <div class="flex flex-col gap-4">
      <BaseSelect
        v-model="form.request_id"
        label="Approved vehicle request"
        :options="requestOptions"
        :placeholder="requestPlaceholder"
        :disabled="isEdit"
        :error="errors.vehicle_request_id"
        required
      />

      <div>
        <BaseSelect
          v-model="form.vehicle_id"
          label="Vehicle"
          :options="vehicleOptions"
          :placeholder="vehicleOptions.length ? 'Select vehicle' : 'No vehicle yet — add one'"
          :error="errors.vehicle_id"
          required
        />
        <button
          type="button"
          class="mt-1.5 text-small font-medium text-primary-700 hover:underline"
          @click="quickAdd = 'vehicle'"
        >
          + Add new vehicle
        </button>
      </div>

      <div>
        <BaseSelect
          v-model="form.driver_id"
          label="Driver"
          :options="driverOptions"
          :placeholder="driverOptions.length ? 'Select driver' : 'No driver yet — add one'"
          :error="errors.driver_id"
          required
        />
        <button
          type="button"
          class="mt-1.5 text-small font-medium text-primary-700 hover:underline"
          @click="quickAdd = 'driver'"
        >
          + Add new driver
        </button>
      </div>

      <BaseSelect
        v-if="isEdit"
        v-model="form.status"
        label="Trip status"
        :options="statusOptions"
        placeholder="Unchanged"
        allow-empty
        :error="errors.status"
      />

      <BaseTextarea
        v-model="form.notes"
        label="Notes"
        placeholder="Notes (optional)"
        :error="errors.notes"
      />

      <p v-if="formError" class="rounded-card bg-red-50 p-3 text-small font-medium text-red-700">
        {{ formError }}
      </p>
    </div>

    <template #footer>
      <BaseButton variant="outline" @click="emit('close')">Cancel</BaseButton>
      <BaseButton :loading="saving" @click="save">{{ isEdit ? 'Save' : 'Allocate' }}</BaseButton>
    </template>
  </BaseModal>

  <ConflictModal :conflict="conflict" @close="closeConflict" />
  <QuickAddModal :kind="quickAdd" @close="quickAdd = ''" @created="onQuickAdded" />
</template>