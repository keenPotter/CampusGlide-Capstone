<script setup>
import { reactive, ref, watch } from 'vue'
import api, { errorMessage, validationErrors } from '@/lib/api'
import { toTimeInput } from '@/lib/format'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'

const props = defineProps({
  trip: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

const form = reactive({
  trip_date: '',
  departure_time: '',
  estimated_return_time: '',
  destination: '',
  purpose: '',
  number_of_passengers: 1,
  reason: '',
})
const errors = ref({})
const formError = ref('')
const saving = ref(false)

const isApproved = () => props.trip?.vehicle_request?.status === 'approved'

watch(
  () => props.trip,
  (trip) => {
    if (!trip) return
    form.trip_date = trip.trip_date || ''
    form.departure_time = toTimeInput(trip.departure_time)
    form.estimated_return_time = toTimeInput(trip.estimated_return_time)
    form.destination = trip.destination || ''
    form.purpose = trip.purpose || ''
    form.number_of_passengers = trip.vehicle_request?.number_of_passengers || 1
    form.reason = ''
    errors.value = {}
    formError.value = ''
  },
)

async function submit() {
  if (!props.trip) return
  errors.value = {}
  formError.value = ''
  if (isApproved() && !form.reason.trim()) {
    errors.value.reason = 'Please give a reason for this modification.'
    return
  }

  saving.value = true
  try {
    const payload = {
      trip_date: form.trip_date,
      departure_time: form.departure_time,
      estimated_return_time: form.estimated_return_time,
      destination: form.destination,
      purpose: form.purpose,
      number_of_passengers: Number(form.number_of_passengers),
    }
    if (isApproved()) {
      await api.post(`/allocations/${props.trip.allocation_id}/trip-modification-requests`, {
        new_date: payload.trip_date,
        new_departure_time: payload.departure_time,
        new_estimated_return_time: payload.estimated_return_time,
        new_destination: payload.destination,
        new_purpose: payload.purpose,
        new_number_of_passengers: payload.number_of_passengers,
        reason: form.reason.trim(),
      })
    } else {
      await api.put(`/trips/${props.trip.id}/edit`, payload)
    }
    emit('saved', isApproved() ? 'Trip modification request submitted.' : 'Trip updated.')
  } catch (error) {
    errors.value = validationErrors(error)
    if (!Object.keys(errors.value).length) {
      formError.value = errorMessage(error, 'Unable to save the trip.')
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <BaseModal
    :open="Boolean(trip)"
    :title="isApproved() ? 'Request Modification' : 'Edit Trip'"
    :subtitle="trip?.destination || ''"
    @close="emit('close')"
  >
    <div class="flex flex-col gap-4">
      <BaseInput v-model="form.trip_date" label="Date" type="date" :error="errors.trip_date" required />
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <BaseInput v-model="form.departure_time" label="Departure time" type="time" :error="errors.departure_time" required />
        <BaseInput v-model="form.estimated_return_time" label="Estimated return time" type="time" :error="errors.estimated_return_time" />
      </div>
      <BaseInput v-model="form.destination" label="Destination" :error="errors.destination" required />
      <BaseInput v-model="form.purpose" label="Purpose" :error="errors.purpose" required />
      <BaseInput v-model="form.number_of_passengers" label="Passenger count" type="number" min="1" :error="errors.number_of_passengers" required />
      <BaseTextarea v-if="isApproved()" v-model="form.reason" label="Reason for change" :error="errors.reason" required />
      <p v-if="formError" class="rounded-card bg-red-50 p-3 text-small font-medium text-red-700">{{ formError }}</p>
    </div>
    <template #footer>
      <BaseButton variant="outline" @click="emit('close')">Cancel</BaseButton>
      <BaseButton :loading="saving" @click="submit">{{ isApproved() ? 'Submit Request' : 'Save Changes' }}</BaseButton>
    </template>
  </BaseModal>
</template>
