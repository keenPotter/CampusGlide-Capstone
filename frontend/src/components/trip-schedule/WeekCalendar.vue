<script setup>
defineProps({
  tripType: { type: String, required: true },
  weekRange: { type: String, required: true },
  weekDays: { type: Array, required: true },
  getTripsForDate: { type: Function, required: true },
  tripTypeClass: { type: Function, required: true },
  isNewTrip: { type: Function, required: true },
  formatTime: { type: Function, required: true },
})
const emit = defineEmits(['change-week', 'open-date', 'open-trip'])
</script>

<template>
  <div class="calendar-card week-card">
    <div :class="['calendar-header', tripType]">
      <div class="calendar-title-group">
        <span class="calendar-type-label">{{ tripType === 'inclusive' ? 'Inclusive Trips' : 'Exclusive Trips' }}</span>
        <button type="button" class="icon-button" aria-label="Previous week" @click="emit('change-week', -1)">‹</button>
        <h2>{{ weekRange }}</h2>
        <button type="button" class="icon-button" aria-label="Next week" @click="emit('change-week', 1)">›</button>
      </div>
    </div>

    <div class="week-calendar">
      <div v-for="day in weekDays" :key="day.date" class="week-day">
        <button
          type="button"
          class="week-day-header"
          :class="{ active: getTripsForDate(day.date).length }"
          :disabled="!getTripsForDate(day.date).length"
          @click="emit('open-date', day.date)"
        >
          <span>{{ day.name }}</span>
          <strong>{{ day.number }}</strong>
        </button>

        <div class="week-events">
          <button
            v-for="trip in getTripsForDate(day.date)"
            :key="trip.id"
            type="button"
            :class="['week-event', tripTypeClass(trip)]"
            @click="emit('open-trip', trip)"
          >
            <div class="week-event-title">
              <strong>{{ formatTime(trip.departure_time) }}</strong>
              <span v-if="isNewTrip(trip)" class="new-indicator small" title="New scheduled trip" aria-label="New scheduled trip"></span>
            </div>
            <span>{{ trip.destination }}</span>
          </button>
          <span v-if="!getTripsForDate(day.date).length" class="no-event">—</span>
        </div>
      </div>
    </div>
  </div>
</template>
