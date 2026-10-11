<script setup>
defineProps({
  tripTypeLabel: { type: String, required: true },
  monthName: { type: String, required: true },
  year: { type: Number, required: true },
  trips: { type: Array, required: true },
  isNewTrip: { type: Function, required: true },
  tripTypeClass: { type: Function, required: true },
  tripTypeLabelForTrip: { type: Function, required: true },
  requesterName: { type: Function, required: true },
  formatDateRange: { type: Function, required: true },
  formatTime: { type: Function, required: true },
  vehicleName: { type: Function, required: true },
  driverName: { type: Function, required: true },
})
const emit = defineEmits(['open-trip'])
</script>

<template>
  <div>
    <div class="section-heading">
      <div>
        <h2>{{ tripTypeLabel }} scheduled trips</h2>
        <p class="section-note">{{ monthName }} {{ year }}</p>
      </div>
      <span class="trip-count">{{ trips.length }}</span>
    </div>

    <div v-if="trips.length" class="approved-list">
      <button
        v-for="trip in trips"
        :key="trip.id"
        type="button"
        class="approved-trip"
        @click="emit('open-trip', trip)"
      >
        <div class="approved-main">
          <div class="approved-title-row">
            <strong>{{ trip.request_code || `TRIP-${trip.id}` }}</strong>
            <div class="badge-row">
              <span v-if="isNewTrip(trip)" class="new-indicator" title="New scheduled trip" aria-label="New scheduled trip"></span>
              <span :class="['type-badge', tripTypeClass(trip)]">{{ tripTypeLabelForTrip(trip) }}</span>
            </div>
          </div>

          <p class="requester">{{ requesterName(trip) }}</p>

          <div class="approved-info">
            <span>{{ formatDateRange(trip) }}</span>
            <span>{{ formatTime(trip.departure_time) }}</span>
          </div>

          <div class="approved-info">
            <span>{{ vehicleName(trip) }}</span>
            <span>{{ driverName(trip) }}</span>
          </div>

          <p class="approved-purpose">{{ trip.purpose || '—' }}</p>
        </div>
        <span class="arrow">›</span>
      </button>
    </div>

    <div v-else class="no-trips">
      <p>No {{ tripTypeLabel.toLowerCase() }} scheduled trips for this month.</p>
    </div>
  </div>
</template>
