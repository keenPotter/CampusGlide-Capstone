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
    errors.value.reason = 'Please give a reason for this date change.'
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
        reason: form.reason.trim(),
      })
    } else {
      await api.put(`/trips/${props.trip.id}/edit`, payload)
    }
    emit('saved', isApproved() ? 'Date Change Request submitted.' : 'Trip updated.')
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
    :title="isApproved() ? 'Request Date Change' : 'Edit Trip'"
    :subtitle="trip?.destination || ''"
    @close="emit('close')"
  >
    <div class="flex flex-col gap-4">
      <template v-if="isApproved()">
        <div class="rounded-card border-l-4 border-secondary bg-secondary-50 p-card text-small text-secondary-700">
          <p class="font-medium">Only these fields can be changed</p>
          <p class="mt-1">Travel date and departure time. All other trip details are not editable.</p>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <BaseInput v-model="form.trip_date" label="New travel date" type="date" :error="errors.trip_date" required />
          <BaseInput v-model="form.departure_time" label="New departure time" type="time" :error="errors.departure_time" required />
        </div>
        <div class="rounded-card border border-line bg-surface p-3 text-small">
          <p class="font-medium text-ink">Not editable</p>
          <p class="mt-1 text-ink-muted">
            Destination: {{ trip?.destination || '—' }} · Purpose: {{ trip?.purpose || '—' }}
          </p>
          <p class="mt-1 text-ink-muted">
            Estimated return: {{ trip?.estimated_return_time ? toTimeInput(trip.estimated_return_time) : '—' }} ·
            Passengers: {{ trip?.vehicle_request?.number_of_passengers || '—' }}
          </p>
        </div>
        <BaseTextarea v-model="form.reason" label="Reason for date change" :error="errors.reason" required />
      </template>
      <template v-else>
        <BaseInput v-model="form.trip_date" label="Date" type="date" :error="errors.trip_date" required />
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <BaseInput v-model="form.departure_time" label="Departure time" type="time" :error="errors.departure_time" required />
          <BaseInput v-model="form.estimated_return_time" label="Estimated return time" type="time" :error="errors.estimated_return_time" />
        </div>
        <BaseInput v-model="form.destination" label="Destination" :error="errors.destination" required />
        <BaseInput v-model="form.purpose" label="Purpose" :error="errors.purpose" required />
        <BaseInput v-model="form.number_of_passengers" label="Passenger count" type="number" min="1" :error="errors.number_of_passengers" required />
      </template>
      <p v-if="formError" class="rounded-card bg-red-50 p-3 text-small font-medium text-red-700">{{ formError }}</p>
    </div>
    <template #footer>
      <BaseButton variant="outline" @click="emit('close')">Cancel</BaseButton>
      <BaseButton :loading="saving" @click="submit">{{ isApproved() ? 'Submit Request' : 'Save Changes' }}</BaseButton>
    </template>
  </BaseModal>
</template>
