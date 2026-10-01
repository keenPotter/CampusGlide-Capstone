<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useVehicleRequestStore } from '@/stores/vehicleRequests'
import { errorMessage, validationErrors } from '@/lib/api'
import { formatDate, formatDateTime, formatTime } from '@/lib/format'
import { useToast } from '@/composables/useToast'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'

const auth = useAuthStore()
const store = useVehicleRequestStore()
const route = useRoute()
const router = useRouter()
const toast = useToast()

const request = ref(null)
const loading = ref(true)
const submitting = ref(false)
const cancelOpen = ref(false)
const cancelRemarks = ref('')
const cancelError = ref('')

const canEdit = computed(
  () => auth.isFaculty && ['pending', 'approved'].includes(request.value?.status),
)

const rows = computed(() => {
  if (!request.value) return []
  const value = request.value

  return [
    { label: 'Requesting official', value: value.requested_by?.first_name + ' ' + value.requested_by?.last_name },
    { label: 'Destination', value: value.destination },
    { label: 'Purpose', value: value.purpose },
    { label: 'Date of travel', value: formatDate(value.trip_date) },
    { label: 'End of travel', value: formatDate(value.trip_end_date) },
    { label: 'Days of travel', value: value.travel_days ?? '—' },
    { label: 'Inclusive / Exclusive', value: value.trip_type },
    { label: 'Time of departure', value: formatTime(value.departure_time) },
    { label: 'Estimated return', value: formatTime(value.return_time) },
    { label: 'Authorized passengers', value: value.passengers },
    { label: 'Number of passengers', value: value.number_of_passengers ?? '—' },
    { label: 'Date requested', value: formatDateTime(value.created_at) },
    { label: 'Approved on', value: value.approved_date ? formatDateTime(value.approved_date) : '—' },
  ]
})

onMounted(load)

async function load() {
  loading.value = true
  try {
    request.value = await store.find(route.params.id)
  } catch (error) {
    toast.error(errorMessage(error, 'Unable to load this request.'))
    router.push({ name: 'requests.index' })
  } finally {
    loading.value = false
  }
}

async function updateStatus(status) {
  submitting.value = true
  try {
    await store.updateStatus(request.value.id, { status })
    toast.success(`Request ${status}.`)
    await load()
  } catch (error) {
    toast.error(errorMessage(error, 'Unable to update this request.'))
  } finally {
    submitting.value = false
  }
}

async function confirmCancel() {
  submitting.value = true
  cancelError.value = ''

  try {
    await store.cancel(request.value.id, cancelRemarks.value)
    toast.success('Request cancelled.')
    cancelOpen.value = false
    await load()
  } catch (error) {
    cancelError.value = validationErrors(error).cancellation_remarks ?? errorMessage(error)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div v-if="loading" class="flex justify-center p-12">
    <div class="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-primary" />
  </div>

  <div v-else-if="request" class="mx-auto flex max-w-3xl flex-col gap-page">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <button
          type="button"
          class="mb-1 text-small text-ink-muted hover:text-ink"
          @click="router.push({ name: 'requests.index' })"
        >
          ← Back to requests
        </button>
        <h1 class="text-page-title">Request #{{ request.id }}</h1>
        <p class="mt-0.5 text-small text-ink-muted">{{ request.destination }}</p>
      </div>

      <StatusBadge :status="request.status" />
    </div>

    <div
      v-if="request.rejection_reason"
      class="rounded-card border border-red-200 bg-red-50 p-card text-small text-red-800"
    >
      <p class="font-medium">Disapproved</p>
      <p class="mt-1">{{ request.rejection_reason }}</p>
    </div>

    <div
      v-if="request.cancellation_remarks"
      class="rounded-card border border-line bg-neutral-100 p-card text-small text-ink-muted"
    >
      <p class="font-medium text-ink">Cancelled</p>
      <p class="mt-1">{{ request.cancellation_remarks }}</p>
    </div>

    <BaseCard title="Request for use of vehicle" padded="false">
      <dl class="divide-y divide-line">
        <div
          v-for="row in rows"
          :key="row.label"
          class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-3 sm:gap-4"
        >
          <dt class="text-small text-ink-muted">{{ row.label }}</dt>
          <dd class="text-body sm:col-span-2 sm:capitalize">{{ row.value || '—' }}</dd>
        </div>
      </dl>
    </BaseCard>

    <div class="flex flex-wrap justify-end gap-3">
      <template v-if="auth.isAdministrator && request.status === 'pending'">
        <BaseButton variant="outline" :loading="submitting" @click="updateStatus('rejected')">
          Disapprove
        </BaseButton>
        <BaseButton :loading="submitting" @click="updateStatus('approved')">Approve</BaseButton>
      </template>

      <template v-if="canEdit">
        <BaseButton
          variant="outline"
          @click="router.push({ name: 'requests.edit', params: { id: request.id } })"
        >
          Edit
        </BaseButton>
        <BaseButton variant="danger" @click="((cancelOpen = true), (cancelRemarks = ''))">
          Cancel request
        </BaseButton>
      </template>
    </div>

    <BaseModal
      :open="cancelOpen"
      title="Cancel request"
      subtitle="This releases the vehicle slot for other requests."
      @close="cancelOpen = false"
    >
      <BaseTextarea
        v-model="cancelRemarks"
        label="Cancellation remarks"
        placeholder="e.g. Meeting moved to a later date."
        :error="cancelError"
        required
      />

      <template #footer>
        <BaseButton variant="outline" @click="cancelOpen = false">Keep request</BaseButton>
        <BaseButton variant="danger" :loading="submitting" @click="confirmCancel">
          Cancel request
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>