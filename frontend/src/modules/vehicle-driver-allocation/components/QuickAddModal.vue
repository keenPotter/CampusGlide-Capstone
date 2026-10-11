<script setup>
import { reactive, ref, watch } from 'vue'
import { request, UnauthorizedError } from '../api'
import AppButton from './AppButton.vue'


const props = defineProps({
  kind: { type: String, default: '' },
})
const emit = defineEmits(['close', 'created'])

const blank = () => ({ name: '', contact_number: '', license_number: '', license_expiry_date: '', plate_number: '', vehicle_model: '', capacity: '' })
const form = reactive(blank())
const error = ref('')
const saving = ref(false)

watch(
  () => props.kind,
  () => {
    Object.assign(form, blank())
    error.value = ''
  },
)

async function save() {
  error.value = ''
  const isDriver = props.kind === 'driver'
  const body = isDriver
    ? { name: form.name.trim(), contact_number: form.contact_number.trim(), license_number: form.license_number.trim(), license_expiry_date: form.license_expiry_date }
    : { plate_number: form.plate_number.trim(), vehicle_model: form.vehicle_model.trim(), capacity: form.capacity ? Number(form.capacity) : undefined }

  saving.value = true
  try {
    const res = await request(isDriver ? '/allocation-drivers' : '/allocation-vehicles', { method: 'POST', body })
    emit('created', { kind: props.kind, item: res.data })
  } catch (e) {
    if (!(e instanceof UnauthorizedError)) error.value = e.message
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-if="kind" class="cg-overlay cg-overlay--top">
    <form class="cg-dialog cg-dialog--sm" @submit.prevent="save">
      <h2 class="cg-section-title">{{ kind === 'driver' ? 'Add new driver' : 'Add new vehicle' }}</h2>

      <template v-if="kind === 'driver'">
        <div>
          <label class="cg-label" for="qa-name">Full name</label>
          <input id="qa-name" v-model="form.name" class="cg-input" required maxlength="120" />
        </div>
        <div>
          <label class="cg-label" for="qa-contact">Contact number</label>
          <input id="qa-contact" v-model="form.contact_number" class="cg-input" required inputmode="tel" placeholder="0917-123-4567" />
        </div>
        <div>
          <label class="cg-label" for="qa-lic">License number</label>
          <input id="qa-lic" v-model="form.license_number" class="cg-input" required />
        </div>
        <div>
          <label class="cg-label" for="qa-exp">License expiry date</label>
          <input id="qa-exp" v-model="form.license_expiry_date" type="date" class="cg-input" required />
        </div>
      </template>

      <template v-else>
        <div>
          <label class="cg-label" for="qa-plate">Plate number</label>
          <input id="qa-plate" v-model="form.plate_number" class="cg-input" required maxlength="20" />
        </div>
        <div>
          <label class="cg-label" for="qa-model">Vehicle model</label>
          <input id="qa-model" v-model="form.vehicle_model" class="cg-input" required maxlength="100" placeholder="Toyota HiAce" />
        </div>
        <div>
          <label class="cg-label" for="qa-cap">Seating capacity (optional)</label>
          <input id="qa-cap" v-model="form.capacity" type="number" min="1" class="cg-input" />
        </div>
      </template>

      <p class="cg-form-error">{{ error }}</p>

      <div class="cg-actions">
        <AppButton variant="secondary" @click="emit('close')">Cancel</AppButton>
        <AppButton type="submit" :disabled="saving">Add</AppButton>
      </div>
    </form>
  </div>
</template>
