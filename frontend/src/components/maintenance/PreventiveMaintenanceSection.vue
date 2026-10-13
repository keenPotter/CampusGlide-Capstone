<script setup>
import { computed, onMounted, ref } from 'vue'
import api, { errorMessage, unwrap, validationErrors } from '@/lib/api'
import { formatDate, formatKm, todayIso } from '@/lib/format'
import { printPreventiveMaintenanceChecklist } from '@/lib/printPreventiveMaintenanceChecklist'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import BaseModal from '@/components/ui/BaseModal.vue'

const props = defineProps({
  vehicles: { type: Array, default: () => [] },
})

const toast = useToast()
const records = ref([])
const loading = ref(true)
const loadError = ref('')
const showForm = ref(false)
const editingId = ref(null)
const saving = ref(false)
const printingId = ref(null)
const errors = ref({})
const formError = ref('')

const DATE_FIELDS = [
  { field: 'last_oil_change', label: 'Last oil change' },
  { field: 'last_air_filter_change', label: 'Last air filter change' },
  { field: 'last_cabin_filter_change', label: 'Last cabin filter change' },
  { field: 'last_oil_filter_change', label: 'Last oil filter change' },
  { field: 'last_engine_tune_up', label: 'Last engine tune-up' },
]

const CONDITION_FIELDS = [
  { field: 'belts_condition', label: 'Belts' },
  { field: 'hoses_condition', label: 'Hoses' },
  { field: 'engine_condition', label: 'Engine' },
  { field: 'air_conditioning_condition', label: 'Air conditioning' },
  { field: 'wipers_condition', label: 'Wipers' },
  { field: 'headlights_condition', label: 'Headlights' },
  { field: 'driving_lights_condition', label: 'Driving lights' },
  { field: 'brake_lights_condition', label: 'Brake lights' },
  { field: 'hazard_lights_condition', label: 'Hazard lights' },
  { field: 'door_locks_condition', label: 'Door locks' },
  { field: 'windows_windshield_condition', label: 'Windows / windshield' },
  { field: 'radio_condition', label: 'Radio' },
  { field: 'tires_condition', label: 'Tires' },
  { field: 'liquid_levels_condition', label: 'Liquid levels' },
  { field: 'other_parts_condition', label: 'Other parts' },
]

const ratingOptions = [
  { value: 'excellent', label: 'Excellent' },
  { value: 'good', label: 'Good' },
  { value: 'poor', label: 'Poor' },
]

const vehicleOptions = computed(() => props.vehicles.map((vehicle) => ({
  value: vehicle.id,
  label: `${vehicle.plate_number} — ${vehicle.vehicle_model ?? vehicle.model ?? 'Vehicle'}`,
})))

const form = ref(emptyForm())

function emptyForm() {
  return {
    vehicle_id: '',
    pmuv_no: '',
    inspection_date: todayIso(),
    inspector_mechanic: '',
    current_mileage: '',
    last_oil_change: '',
    last_air_filter_change: '',
    last_cabin_filter_change: '',
    last_oil_filter_change: '',
    last_engine_tune_up: '',
    ...Object.fromEntries(CONDITION_FIELDS.map(({ field }) => [field, ''])),
    other_parts: '',
    remarks: '',
    supervisor_recommendation: '',
  }
}

onMounted(loadRecords)

async function loadRecords() {
  loading.value = true
  loadError.value = ''
  try {
    const response = await api.get('/preventive-maintenance-checklists')
    const payload = unwrap(response)
    records.value = Array.isArray(payload) ? payload : []
  } catch (error) {
    loadError.value = errorMessage(error, 'Unable to load preventive maintenance checklists.')
  } finally {
    loading.value = false
  }
}

function openAdd() {
  editingId.value = null
  form.value = emptyForm()
  errors.value = {}
  formError.value = ''
  showForm.value = true
}

function openEdit(record) {
  editingId.value = record.id
  form.value = {
    ...emptyForm(),
    ...record,
    vehicle_id: record.vehicle_id ?? record.vehicle?.id ?? '',
    current_mileage: record.current_mileage ?? '',
  }
  errors.value = {}
  formError.value = ''
  showForm.value = true
}

async function save() {
  errors.value = {}
  formError.value = ''
  if (!form.value.vehicle_id) errors.value.vehicle_id = 'Please select a vehicle.'
  if (!form.value.inspection_date) errors.value.inspection_date = 'Please select an inspection date.'
  if (Object.keys(errors.value).length) return

  const payload = {
    ...form.value,
    vehicle_id: Number(form.value.vehicle_id),
    current_mileage: form.value.current_mileage === '' ? null : Number(form.value.current_mileage),
  }

  saving.value = true
  try {
    if (editingId.value) {
      await api.patch(`/preventive-maintenance-checklists/${editingId.value}`, payload)
    } else {
      await api.post('/preventive-maintenance-checklists', payload)
    }
    toast.success(editingId.value ? 'Checklist updated.' : 'Checklist saved.')
    showForm.value = false
    await loadRecords()
  } catch (error) {
    errors.value = validationErrors(error)
    if (!Object.keys(errors.value).length) {
      formError.value = errorMessage(error, 'Unable to save this checklist.')
    }
  } finally {
    saving.value = false
  }
}

async function printRecord(record) {
  printingId.value = record.id ?? 'blank'
  try {
    printPreventiveMaintenanceChecklist(record)
  } catch (error) {
    toast.error(errorMessage(error, 'Unable to print this checklist.'))
  } finally {
    printingId.value = null
  }
}

function vehicleName(record) {
  const vehicle = record.vehicle
  return [vehicle?.plate_number, vehicle?.vehicle_model ?? vehicle?.model].filter(Boolean).join(' — ') || 'Vehicle'
}
</script>

<template>
  <BaseCard
    title="Preventive maintenance checklists"
    subtitle="Record vehicle inspections and print the checklist form"
    :padded="false"
  >
    <div class="flex flex-wrap justify-end gap-2 border-b border-line p-card">
      <BaseButton @click="openAdd">Add checklist</BaseButton>
    </div>

    <div v-if="loadError" class="m-card rounded-card bg-red-50 p-3 text-small text-red-700">
      {{ loadError }}
      <button type="button" class="ml-2 font-semibold underline" @click="loadRecords">Retry</button>
    </div>

    <div v-if="loading" class="flex justify-center p-8">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-primary" />
    </div>
    <div v-else-if="records.length === 0" class="p-card text-center text-small text-ink-muted">
      No preventive maintenance checklists yet.
    </div>
    <ul v-else class="divide-y divide-line">
      <li
        v-for="record in records"
        :key="record.id"
        class="flex flex-col gap-3 p-card sm:flex-row sm:items-center sm:justify-between"
      >
        <div>
          <p class="font-medium text-ink">{{ vehicleName(record) }}</p>
          <p class="mt-0.5 text-small text-ink-muted">
            {{ formatDate(record.inspection_date) }}
            <span v-if="record.pmuv_no"> · PMUV No. {{ record.pmuv_no }}</span>
            <span v-if="record.current_mileage != null"> · {{ formatKm(record.current_mileage) }}</span>
          </p>
        </div>
        <div class="flex justify-end gap-2">
          <BaseButton
            size="sm"
            variant="outline"
            :loading="printingId === record.id"
            @click="printRecord(record)"
          >
            Print
          </BaseButton>
          <BaseButton size="sm" variant="outline" @click="openEdit(record)">Edit</BaseButton>
        </div>
      </li>
    </ul>

    <BaseModal
      :open="showForm"
      :title="editingId ? 'Edit preventive maintenance checklist' : 'Add preventive maintenance checklist'"
      subtitle="Vehicle inspection record"
      @close="showForm = false"
    >
      <div class="flex flex-col gap-4">
        <BaseSelect
          v-model="form.vehicle_id"
          label="Vehicle"
          placeholder="Select vehicle"
          :options="vehicleOptions"
          :error="errors.vehicle_id"
          required
        />

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput v-model="form.pmuv_no" label="PMUV No." :error="errors.pmuv_no" />
          <BaseInput
            v-model="form.inspection_date"
            label="Inspection date"
            type="date"
            :error="errors.inspection_date"
            required
          />
          <BaseInput
            v-model="form.inspector_mechanic"
            label="Inspector / mechanic"
            :error="errors.inspector_mechanic"
          />
          <BaseInput
            v-model="form.current_mileage"
            label="Current mileage (km)"
            type="number"
            min="0"
            step="1"
            :error="errors.current_mileage"
          />
        </div>

        <div>
          <h3 class="mb-3 font-semibold">Last service dates</h3>
          <div class="grid gap-4 sm:grid-cols-2">
            <BaseInput
              v-for="item in DATE_FIELDS"
              :key="item.field"
              v-model="form[item.field]"
              :label="item.label"
              type="date"
              :error="errors[item.field]"
            />
          </div>
        </div>

        <div>
          <h3 class="mb-3 font-semibold">Vehicle condition</h3>
          <div class="grid gap-4 sm:grid-cols-2">
            <BaseSelect
              v-for="item in CONDITION_FIELDS"
              :key="item.field"
              v-model="form[item.field]"
              :label="item.label"
              placeholder="Not rated"
              :options="ratingOptions"
              allow-empty
              :error="errors[item.field]"
            />
          </div>
        </div>

        <BaseTextarea
          v-model="form.other_parts"
          label="Other parts inspected"
          :error="errors.other_parts"
        />
        <BaseTextarea v-model="form.remarks" label="Remarks" :error="errors.remarks" />
        <BaseTextarea
          v-model="form.supervisor_recommendation"
          label="Supervisor's recommendation"
          :error="errors.supervisor_recommendation"
        />

        <p v-if="formError" class="rounded-card bg-red-50 p-3 text-small font-medium text-red-700">
          {{ formError }}
        </p>
      </div>

      <template #footer>
        <BaseButton variant="outline" @click="showForm = false">Cancel</BaseButton>
        <BaseButton :loading="saving" @click="save">Save checklist</BaseButton>
      </template>
    </BaseModal>
  </BaseCard>
</template>
