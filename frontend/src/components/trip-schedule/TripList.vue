<script setup>
defineProps({
  tripTypeLabel: { type: String, required: true },
  trips: { type: Array, required: true },
  isNewTrip: { type: Function, required: true },
  tripTypeClass: { type: Function, required: true },
  tripTypeLabelForTrip: { type: Function, required: true },
  dayNumber: { type: Function, required: true },
  shortMonth: { type: Function, required: true },
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
        <p class="section-note">All allocated trips of this type</p>
      </div>
      <span class="trip-count">{{ trips.length }}</span>
    </div>

    <div v-if="trips.length" class="trip-list">
      <button
        v-for="trip in trips"
        :key="trip.id"
        type="button"
        class="list-trip-card"
        @click="emit('open-trip', trip)"
      >
        <div class="list-trip-date">
          <strong>{{ dayNumber(trip.trip_date) }}</strong>
          <span>{{ shortMonth(trip.trip_date) }}</span>
        </div>

        <div class="list-trip-content">
          <div class="list-title-row">
            <h3>{{ trip.destination || 'Trip' }}</h3>
            <div class="badge-row">
              <span v-if="isNewTrip(trip)" class="new-indicator" title="New scheduled trip" aria-label="New scheduled trip"></span>
              <span :class="['type-badge', tripTypeClass(trip)]">{{ tripTypeLabelForTrip(trip) }}</span>
            </div>
          </div>

          <p class="list-purpose">{{ trip.purpose || '—' }}</p>

          <div class="list-details">
            <span>{{ formatDateRange(trip) }}</span>
            <span>{{ formatTime(trip.departure_time) }}</span>
            <span>{{ vehicleName(trip) }}</span>
            <span>{{ driverName(trip) }}</span>
          </div>
        </div>

        <span class="arrow">›</span>
      </button>
    </div>

    <div v-else class="no-trips">
      <p>No {{ tripTypeLabel.toLowerCase() }} scheduled trips found.</p>
    </div>
  </div>
</template>
