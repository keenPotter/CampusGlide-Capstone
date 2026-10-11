<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { ApiError, request, UnauthorizedError } from '../api'
import { formatDate } from '../format'
import AppButton from './AppButton.vue'

const props = defineProps({
  allocation: { type: Object, default: null }, 
  isAdmin: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'saved', 'conflict'])

const form = reactive({ newDate: '', reason: '' })
const error = ref('')
const saving = ref(false)

const today = new Date()
const minDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`

const title = computed(() => (props.isAdmin ? 'Change trip date' : 'Request date change'))
const submitLabel = computed(() => (props.isAdmin ? 'Change date' : 'Send request'))

watch(
  () => props.allocation,
  () => {
    form.newDate = ''
    form.reason = ''
    error.value = ''
  },
)

async function submit() {
  error.value = ''
  if (!form.newDate || !form.reason.trim()) {
    error.value = 'Please choose the new date and give a reason.'
    return
  }

  const path = props.isAdmin
    ? `/allocations/${props.allocation.id}/reschedule`
    : `/allocations/${props.allocation.id}/reschedule-requests`

  saving.value = true
  try {
    await request(path, { method: 'POST', body: { new_date: form.newDate, reason: form.reason.trim() } })
    emit('saved', props.isAdmin ? 'Trip date changed.' : 'Date change request sent to the Admin.')
  } catch (e) {
    if (e instanceof UnauthorizedError) return
    if (e instanceof ApiError && e.data?.conflict) emit('conflict', e.data.conflict)
    else error.value = e.message
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-if="allocation" class="cg-overlay">
    <form class="cg-dialog cg-dialog--sm" role="dialog" aria-modal="true" aria-labelledby="resched-title" @submit.prevent="submit">
      <h2 id="resched-title" class="cg-section-title">{{ title }}</h2>

      <div class="cg-box cg-stack" style="gap: 4px">
        <p class="cg-card__title">{{ allocation.trip?.destination ?? '—' }}</p>
        <p class="cg-muted">Current date: {{ formatDate(allocation.trip?.trip_date) }}</p>
      </div>

      <div>
        <label class="cg-label" for="rs-date">New date</label>
        <input id="rs-date" v-model="form.newDate" type="date" class="cg-input" :min="minDate" required />
      </div>

      <div>
        <label class="cg-label" for="rs-reason">Reason</label>
        <textarea id="rs-reason" v-model="form.reason" class="cg-input" rows="3" maxlength="500" required></textarea>
      </div>

      <p v-if="!isAdmin" class="cg-muted">The Admin will review your request. The trip date changes only after the Admin approves it.</p>
      <p class="cg-form-error">{{ error }}</p>

      <div class="cg-actions">
        <AppButton variant="secondary" @click="emit('close')">Close</AppButton>
        <AppButton type="submit" :disabled="saving">{{ submitLabel }}</AppButton>
      </div>
    </form>
  </div>
</template>
