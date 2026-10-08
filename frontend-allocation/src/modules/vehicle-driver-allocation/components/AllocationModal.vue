<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { ApiError, isAdmin, request, UnauthorizedError } from '../api'
import AppButton from './AppButton.vue'
import ConflictModal from './ConflictModal.vue'
import Icon from './Icon.vue'
import PickerList from './PickerList.vue'
import QuickAddModal from './QuickAddModal.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  allocation: { type: Object, default: null }, 
  presetRequestId: { type: [Number, String], default: null },
})
const emit = defineEmits(['close', 'saved'])

const form = reactive({ requestId: '', vehicleId: '', driverId: '', status: '', notes: '' })
const requests = ref([])
const vehicles = ref([])
const drivers = ref([])
const error = ref('')
const saving = ref(false)
const loadFailed = ref(false)
const conflict = ref(null) 
const quickAdd = ref('')

const vehicleOptions = computed(() =>
  vehicles.value.map((v) => ({
    id: v.id,
    title: v.vehicle_model || v.label,
    subtitle: [v.plate_number, v.capacity ? `${v.capacity} seats` : ''].filter(Boolean).join(' · '),
  })),
)
const driverOptions = computed(() =>
  drivers.value.map((d) => ({ id: d.id, title: d.name || d.label, subtitle: d.contact_number || '' })),
)

const isEdit = computed(() => !!props.allocation)
const title = computed(() => (isEdit.value ? `Reassign Allocation #${props.allocation.id}` : 'New Allocation'))

const requestPlaceholder = computed(() => {
  if (isEdit.value) return ''
  if (loadFailed.value) return 'Unable to load requests'
  return requests.value.length ? '— select approved request —' : 'No approved requests waiting'
})
const vehiclePlaceholder = computed(() => (vehicles.value.length ? '— select vehicle —' : 'No vehicle yet — add one'))
const driverPlaceholder = computed(() => (drivers.value.length ? '— select driver —' : 'No driver yet — add one'))

watch(
  () => props.open,
  (open) => {
    if (open) init()
  },
)

function resetForm() {
  Object.assign(form, { requestId: '', vehicleId: '', driverId: '', status: '', notes: '' })
  requests.value = []
  vehicles.value = []
  drivers.value = []
  error.value = ''
  loadFailed.value = false
  conflict.value = null
  quickAdd.value = ''
}

async function loadResources(requestId, keep) {
  try {
    const q = requestId ? `?vehicle_request_id=${encodeURIComponent(requestId)}` : ''
    const d = await request(`/allocation-options${q}`)

    const v = d.vehicles.slice()
    const dr = d.drivers.slice()
    if (keep?.vehicle && !v.some((x) => x.id === keep.vehicle.id)) v.unshift(keep.vehicle)
    if (keep?.driver && !dr.some((x) => x.id === keep.driver.id)) dr.unshift(keep.driver)

    vehicles.value = v
    drivers.value = dr
    form.vehicleId = keep?.vehicle?.id ?? ''
    form.driverId = keep?.driver?.id ?? ''
    return d
  } catch (e) {
    if (!(e instanceof UnauthorizedError)) error.value = e.message
    return null
  }
}

async function init() {
  resetForm()
  const a = props.allocation

  if (a) {
    form.status = a.status ?? ''
    form.notes = a.notes ?? ''
    form.requestId = a.vehicle_request?.id ?? ''
    requests.value = [{ id: a.vehicle_request?.id, label: `#${a.vehicle_request?.id} · ${a.trip?.destination ?? ''}` }]
    await loadResources(a.vehicle_request?.id, {
      vehicle: { id: a.vehicle?.id, label: `${a.vehicle?.plate_number ?? ''} — ${a.vehicle?.vehicle_model ?? ''}`, vehicle_model: a.vehicle?.vehicle_model, plate_number: a.vehicle?.plate_number },
      driver: { id: a.driver?.id, label: a.driver?.name ?? '', name: a.driver?.name, contact_number: a.driver?.contact_number },
    })
    return
  }

  const d = await loadResources('')
  loadFailed.value = !d
  requests.value = d?.requests ?? []

  if (d && props.presetRequestId) {
    form.requestId = Number(props.presetRequestId)
    await loadResources(form.requestId)
  }
}

function onRequestChange(requestId) {
  error.value = ''
  loadResources(requestId || '')
}

async function save() {
  if (!isAdmin.value) {
    error.value = 'Only the Admin can manage allocations.'
    return
  }
  error.value = ''

  const vehicleId = Number(form.vehicleId) || undefined
  const driverId = Number(form.driverId) || undefined
  const notes = form.notes || undefined

  saving.value = true
  try {
    if (isEdit.value) {
      await request(`/allocations/${props.allocation.id}`, {
        method: 'PUT',
        body: { vehicle_id: vehicleId, driver_id: driverId, status: form.status || undefined, notes },
      })
    } else {
      const requestId = Number(form.requestId)
      if (!requestId || !vehicleId || !driverId) {
        error.value = 'Please select a request, a vehicle, and a driver.'
        return
      }
      const res = await request('/allocations', {
        method: 'POST',
        body: { vehicle_request_id: requestId, vehicle_id: vehicleId, driver_id: driverId, notes },
      })
      emit('saved', res.data ?? res)
      return
    }
    emit('saved', null)
  } catch (e) {
    if (e instanceof UnauthorizedError) return
    if (e instanceof ApiError && e.data?.conflict) conflict.value = e.data.conflict
    else error.value = e.message
  } finally {
    saving.value = false
  }
}

function closeConflict() {
  if (conflict.value?.type === 'driver') form.driverId = ''
  else form.vehicleId = ''
  conflict.value = null
}

function onQuickAdded({ kind, item }) {
  if (kind === 'driver') {
    drivers.value = [...drivers.value, item]
    form.driverId = item.id
  } else {
    vehicles.value = [...vehicles.value, item]
    form.vehicleId = item.id
  }
  quickAdd.value = ''
}
</script>

<template>
  <div v-if="open" class="cg-overlay">
    <div class="cg-dialog" role="dialog" aria-modal="true" aria-labelledby="allocation-modal-title">
      <h2 id="allocation-modal-title" class="cg-section-title">{{ title }}</h2>

      <div>
        <label class="cg-label" for="f-request">Approved vehicle request</label>
        <select id="f-request" v-model="form.requestId" :disabled="isEdit" class="cg-input" @change="onRequestChange($event.target.value)">
          <option value="">{{ requestPlaceholder }}</option>
          <option v-for="r in requests" :key="r.id" :value="r.id">{{ r.label }}</option>
        </select>
      </div>

      <div>
        <div class="cg-label-row">
          <label class="cg-label" for="f-vehicle">Vehicle</label>
          <button type="button" class="cg-link" @click="quickAdd = 'vehicle'"><Icon name="plus" /> Add new vehicle</button>
        </div>
        <PickerList v-model="form.vehicleId" :options="vehicleOptions" :placeholder="vehiclePlaceholder" />
      </div>

      <div>
        <div class="cg-label-row">
          <label class="cg-label" for="f-driver">Driver</label>
          <button type="button" class="cg-link" @click="quickAdd = 'driver'"><Icon name="plus" /> Add new driver</button>
        </div>
        <PickerList v-model="form.driverId" :options="driverOptions" :placeholder="driverPlaceholder" icon="phone" />
      </div>

      <div v-if="isEdit">
        <label class="cg-label" for="f-status">Trip status</label>
        <select id="f-status" v-model="form.status" class="cg-input">
          <option value="">— unchanged —</option>
          <option value="scheduled">Scheduled</option>
          <option value="in_progress">In progress</option>
          <option value="completed">Completed</option>
        </select>
      </div>

      <div>
        <label class="cg-label" for="f-notes">Notes</label>
        <textarea id="f-notes" v-model="form.notes" class="cg-input" rows="2" placeholder="Notes (optional)"></textarea>
      </div>

      <p class="cg-form-error">{{ error }}</p>

      <div class="cg-actions">
        <AppButton variant="secondary" @click="emit('close')">Cancel</AppButton>
        <AppButton :disabled="saving" @click="save">{{ isEdit ? 'Save' : 'Allocate' }}</AppButton>
      </div>
    </div>

    <ConflictModal :conflict="conflict" @close="closeConflict" />
    <QuickAddModal :kind="quickAdd" @close="quickAdd = ''" @created="onQuickAdded" />
  </div>
</template>
