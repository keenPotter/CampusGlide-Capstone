<script setup>
defineProps({
  trip: { type: Object, default: null },
  trips: { type: Array, default: () => [] },
  dateLabel: { type: String, default: '' },
  rows: { type: Array, default: () => [] },
  isNewTrip: { type: Function, required: true },
  tripTypeClass: { type: Function, required: true },
  tripTypeLabel: { type: Function, required: true },
  vehicleName: { type: Function, required: true },
  driverName: { type: Function, required: true },
})
const emit = defineEmits(['close', 'select-trip'])
</script>

<template>
  <div v-if="trip" class="modal-overlay" @click.self="emit('close')">
    <div class="modal-card trip-modal-card">
      <div class="sheet-handle"></div>

      <div class="modal-header">
        <div>
          <span class="small-label">{{ trips.length > 1 ? 'Scheduled trips' : 'Scheduled trip' }}</span>
          <h2>{{ dateLabel || trip.destination || 'Trip details' }}</h2>
          <p v-if="trips.length > 1" class="modal-subtitle">{{ trips.length }} trips scheduled for this date</p>
        </div>
        <button type="button" class="icon-button" aria-label="Close" @click="emit('close')">×</button>
      </div>

      <div v-if="trips.length > 1" class="date-trip-list" aria-label="Trips on selected date">
        <button
          v-for="item in trips"
          :key="item.id"
          type="button"
          :class="['date-trip-item', { selected: item.id === trip.id }]"
          @click="emit('select-trip', item)"
        >
          <span :class="['type-badge', tripTypeClass(item)]">{{ tripTypeLabel(item) }}</span>
          <span class="date-trip-main">
            <strong>{{ item.destination || 'Destination not specified' }}</strong>
            <small>{{ item.departure_time ? item.departure_time.slice(0, 5) : 'Time not set' }} · {{ vehicleName(item) }} · {{ driverName(item) }}</small>
          </span>
          <span v-if="isNewTrip(item)" class="modal-new-dot" aria-label="Unviewed trip"></span>
          <span class="date-trip-chevron">›</span>
        </button>
      </div>

      <div class="selected-trip-details">
        <div class="modal-badges">
          <span :class="['type-badge', tripTypeClass(trip)]">{{ tripTypeLabel(trip) }}</span>
        </div>
        <h3 class="selected-trip-title">{{ trip.destination || 'Trip details' }}</h3>
        <div class="modal-status">
          <span class="status approved">{{ trip.trip_status || 'Scheduled' }}</span>
        </div>
        <div class="detail-group">
          <div v-for="row in rows" :key="row.label" class="detail-row">
            <span>{{ row.label }}</span>
            <strong>{{ row.value }}</strong>
          </div>
        </div>
      </div>

      <button type="button" class="primary-button" @click="emit('close')">Close</button>
    </div>
  </div>
</template>
