<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useVehicleRequestStore } from '@/stores/vehicleRequests'
import { errorMessage, validationErrors } from '@/lib/api'
import { formatDate, formatDateTime, formatTime, fullName } from '@/lib/format'
import { printRequest } from '@/lib/printRequest'
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
const printing = ref(false)
const disapproveOpen = ref(false)
const disapproveReason = ref('')
const disapproveError = ref('')

const canEdit = computed(
  () => auth.isFaculty && ['pending', 'approved'].includes(request.value?.status),
)

// Printing is for administrators only, and only after the request is approved.
const canPrint = computed(() => auth.isAdministrator && request.value?.status === 'approved')

const rows = computed(() => {
  if (!request.value) return []
  const value = request.value

  return [
    { label: 'Requesting official', value: fullName(value.requested_by) },
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

async function print() {
  printing.value = true
  try {
    await printRequest(request.value)
  } catch (error) {
    toast.error(errorMessage(error, 'Unable to print this request.'))
  } finally {
    printing.value = false
  }
}

async function approve() {
  submitting.value = true
  try {
    await store.updateStatus(request.value.id, { status: 'approved' })
    toast.success('Request approved.')
    await load()
  } catch (error) {
    toast.error(errorMessage(error, 'Unable to update this request.'))
  } finally {
    submitting.value = false
  }
}

function openDisapprove() {
  disapproveReason.value = ''
  disapproveError.value = ''
  disapproveOpen.value = true
}

async function confirmDisapprove() {
  submitting.value = true
  disapproveError.value = ''

  try {
    await store.updateStatus(request.value.id, {
      status: 'disapproved',
      remarks: disapproveReason.value,
    })
    toast.success('Request disapproved.')
    disapproveOpen.value = false
    await load()
  } catch (error) {
    disapproveError.value = validationErrors(error).remarks ?? errorMessage(error)
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
      v-if="request.disapproval_reason"
      class="rounded-card border border-red-200 bg-red-50 p-card text-small text-red-800"
    >
      <p class="font-medium">Disapproved</p>
      <p class="mt-1">{{ request.disapproval_reason }}</p>
    </div>

    <BaseCard title="Request for use of vehicle" :padded="false">
      <dl class="divide-y divide-line">
        <div
          v-for="row in rows"
          :key="row.label"
          class="grid grid-cols-1 gap-1 px-card py-3 sm:grid-cols-3 sm:gap-4"
        >
          <dt class="text-small text-ink-muted">{{ row.label }}</dt>
          <dd class="text-body sm:col-span-2 sm:capitalize">{{ row.value || '—' }}</dd>
        </div>
      </dl>
    </BaseCard>

    <div class="flex flex-wrap justify-end gap-3">
      <template v-if="auth.isAdministrator && request.status === 'pending'">
        <BaseButton variant="outline" :disabled="submitting" @click="openDisapprove">
          Disapprove
        </BaseButton>
        <BaseButton :loading="submitting" @click="approve">Approve</BaseButton>
      </template>

      <!-- Print is for administrators only, and only after the request is approved -->
      <BaseButton v-if="canPrint" :loading="printing" @click="print">
        Print
      </BaseButton>

      <BaseButton
        v-if="canEdit"
        variant="outline"
        @click="router.push({ name: 'requests.edit', params: { id: request.id } })"
      >
        Edit
      </BaseButton>
    </div>

    <!-- Disapprove -->
    <BaseModal
      :open="disapproveOpen"
      title="Disapprove request"
      :subtitle="`Request #${request.id} — ${request.destination}`"
      @close="disapproveOpen = false"
    >
      <BaseTextarea
        v-model="disapproveReason"
        label="Reason for disapproval"
        placeholder="e.g. Vehicle already assigned for that date."
        :error="disapproveError"
        required
      />

      <template #footer>
        <BaseButton variant="outline" @click="disapproveOpen = false">Cancel</BaseButton>
        <BaseButton variant="danger" :loading="submitting" @click="confirmDisapprove">
          Disapprove request
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>