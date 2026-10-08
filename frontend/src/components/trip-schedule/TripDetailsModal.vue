<script setup>
defineProps({
  trip: { type: Object, default: null },
  rows: { type: Array, default: () => [] },
  isNewTrip: { type: Function, required: true },
  tripTypeClass: { type: Function, required: true },
  tripTypeLabel: { type: Function, required: true },
})
const emit = defineEmits(['close'])
</script>

<template>
  <div v-if="trip" class="modal-overlay" @click.self="emit('close')">
    <div class="modal-card">
      <div class="sheet-handle"></div>

      <div class="modal-header">
        <div>
          <div class="badge-row modal-badges">
            <span v-if="isNewTrip(trip)" class="new-indicator" title="New scheduled trip" aria-label="New scheduled trip"></span>
            <span :class="['type-badge', tripTypeClass(trip)]">{{ tripTypeLabel(trip) }}</span>
          </div>
          <span class="small-label">Scheduled trip</span>
          <h2>{{ trip.destination || 'Trip details' }}</h2>
        </div>
        <button type="button" class="icon-button" aria-label="Close" @click="emit('close')">×</button>
      </div>

      <div class="modal-status">
        <span class="status approved">{{ trip.trip_status || 'Scheduled' }}</span>
      </div>

      <div class="detail-group">
        <div v-for="row in rows" :key="row.label" class="detail-row">
          <span>{{ row.label }}</span>
          <strong>{{ row.value }}</strong>
        </div>
      </div>

      <button type="button" class="primary-button" @click="emit('close')">Close</button>
    </div>
  </div>
</template>
