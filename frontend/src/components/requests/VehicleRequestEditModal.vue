<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useVehicleRequestStore } from '@/stores/vehicleRequests'
import { errorMessage, validationErrors } from '@/lib/api'
import { todayIso, toTimeInput } from '@/lib/format'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import VehicleRequestFields from '@/components/requests/VehicleRequestFields.vue'

const props = defineProps({
  request: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])
const store = useVehicleRequestStore()
const toast = useToast()
const submitting = ref(false)
const confirmOpen = ref(false)
const errors = ref({})
const originalStatus = ref('')
const needsReason = computed(() => originalStatus.value === 'approved')

function emptyForm() {
  const today = todayIso()
  return {
    destination: '',
    purpose: '',
    trip_date: today,
    trip_end_date: today,
    min_trip_date: today,
    trip_type: 'inclusive',
    departure_time: '07:30',
    estimated_return_time: '17:00',
    passengers: '',
    number_of_passengers: 1,
    edit_reason: '',
  }
}

const form = reactive(emptyForm())

watch(
  () => props.request,
  (request) => {
    if (!request) return

    originalStatus.value = request.status
    Object.assign(form, emptyForm(), {
      destination: request.destination ?? '',
      purpose: request.purpose ?? '',
      trip_date: request.trip_date ?? todayIso(),
      trip_end_date: request.trip_end_date ?? todayIso(),
      trip_type: request.trip_type ?? 'inclusive',
      departure_time: toTimeInput(request.departure_time),
      estimated_return_time: toTimeInput(request.return_time),
      passengers: request.passengers ?? '',
      number_of_passengers: request.number_of_passengers ?? 1,
    })
    errors.value = {}
    confirmOpen.value = false
  },
)

function submit() {
  errors.value = {}

  if (needsReason.value) {
    if (!form.edit_reason.trim()) {
      errors.value = { edit_reason: 'Please give a reason for editing this approved request.' }
      return
    }

    confirmOpen.value = true
    return
  }

  save()
}

async function save() {
  if (!props.request || submitting.value) return

  submitting.value = true
  errors.value = {}
  const payload = needsReason.value
    ? {
        trip_date: form.trip_date,
        departure_time: form.departure_time,
        edit_reason: form.edit_reason.trim(),
      }
    : {
        ...form,
        number_of_passengers: Number(form.number_of_passengers),
      }

  delete payload.min_trip_date
  if (!needsReason.value) delete payload.edit_reason

  try {
    const updated = await store.update(props.request.id, payload)
    toast.success(
      needsReason.value
        ? 'Date Change Request submitted for administrator review.'
        : 'Request updated.',
    )
    confirmOpen.value = false
    emit('saved', updated)
    emit('close')
  } catch (error) {
    confirmOpen.value = false
    errors.value = validationErrors(error)
    if (!Object.keys(errors.value).length) {
      toast.error(errorMessage(error, 'Unable to save this request.'))
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <BaseModal
    :open="Boolean(request)"
    :title="needsReason ? 'Request Date Change' : 'Edit vehicle request'"
    :subtitle="request?.destination ?? ''"
    @close="!submitting && emit('close')"
  >
    <form class="flex flex-col gap-5" @submit.prevent="submit">
      <div
        v-if="needsReason"
        class="rounded-card border-l-4 border-secondary bg-secondary-50 p-card text-small text-secondary-700"
      >
        <p class="font-medium">This request is already approved.</p>
        <p class="mt-1">
          Only the travel date and departure time can be changed. The approved trip stays
          unchanged until an administrator approves the request.
        </p>
      </div>

      <template v-if="needsReason">
        <div class="rounded-card border border-line bg-surface p-card text-small">
          <p class="mb-3 font-medium text-ink">Not editable</p>
          <dl class="grid gap-2 sm:grid-cols-2">
            <div>
              <dt class="text-ink-muted">Destination</dt>
              <dd class="mt-1 text-ink">{{ form.destination || '—' }}</dd>
            </div>
            <div>
              <dt class="text-ink-muted">Purpose</dt>
              <dd class="mt-1 text-ink">{{ form.purpose || '—' }}</dd>
            </div>
            <div>
              <dt class="text-ink-muted">Estimated return</dt>
              <dd class="mt-1 text-ink">{{ form.estimated_return_time || '—' }}</dd>
            </div>
            <div>
              <dt class="text-ink-muted">Passengers</dt>
              <dd class="mt-1 text-ink">{{ form.number_of_passengers || '—' }}</dd>
            </div>
          </dl>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput
            v-model="form.trip_date"
            label="New travel date"
            type="date"
            :min="form.min_trip_date"
            :error="errors.trip_date"
            required
          />
          <BaseInput
            v-model="form.departure_time"
            label="New departure time"
            type="time"
            :error="errors.departure_time"
            required
          />
        </div>
      </template>

      <VehicleRequestFields v-else :form="form" :errors="errors" />

      <BaseTextarea
        v-if="needsReason"
        v-model="form.edit_reason"
        label="Why are you changing this approved request?"
        placeholder="e.g. The meeting was moved to a later date."
        :error="errors.edit_reason"
        required
      />

      <div class="flex justify-end gap-2">
        <BaseButton type="button" variant="outline" :disabled="submitting" @click="emit('close')">
          Cancel
        </BaseButton>
        <BaseButton type="submit" :loading="submitting">
          {{ needsReason ? 'Submit Date Change Request' : 'Save changes' }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>

  <BaseModal
    :open="confirmOpen"
    :title="needsReason ? 'Confirm Date Change Request?' : 'Change an approved request?'"
    subtitle="Please read before you continue"
    @close="!submitting && (confirmOpen = false)"
  >
    <p class="text-body">If you continue:</p>
    <ul class="mt-2 list-disc space-y-1 pl-5 text-body">
      <li>The original approved trip will remain unchanged.</li>
      <li>Only the travel date and departure time are requested to change.</li>
      <li>A pending Date Change Request will be created.</li>
      <li>The administrator must approve the requested changes.</li>
    </ul>

    <template #footer>
      <BaseButton variant="outline" :disabled="submitting" @click="confirmOpen = false">
        Go back
      </BaseButton>
      <BaseButton variant="danger" :loading="submitting" @click="save">
        Yes, save changes
      </BaseButton>
    </template>
  </BaseModal>
</template>
