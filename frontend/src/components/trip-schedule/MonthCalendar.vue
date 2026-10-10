<script setup>
defineProps({
  tripType: { type: String, required: true },
  tripTypeLabel: { type: String, required: true },
  monthName: { type: String, required: true },
  year: { type: Number, required: true },
  weekdayNames: { type: Array, required: true },
  firstDayOfMonth: { type: Number, required: true },
  daysInMonth: { type: Number, required: true },
  isScheduled: { type: Function, required: true },
  getTripCountForDate: { type: Function, required: true },
  isToday: { type: Function, required: true },
  isSelectedDate: { type: Function, required: true },
  hasTripTypeOnDate: { type: Function, required: true },
  hasNewTripOnDate: { type: Function, required: true },
})
const emit = defineEmits(['change-month', 'open-day'])
</script>

<template>
  <div class="calendar-card">
    <div :class="['calendar-header', tripType]">
      <div class="calendar-title-group">
        <span class="calendar-type-label">{{ tripType === 'inclusive' ? 'Inclusive Trips' : 'Exclusive Trips' }}</span>
        <button type="button" class="icon-button" aria-label="Previous month" @click="emit('change-month', -1)">‹</button>
        <h2>{{ monthName }} {{ year }}</h2>
        <button type="button" class="icon-button" aria-label="Next month" @click="emit('change-month', 1)">›</button>
      </div>
    </div>

    <div class="calendar-weekdays">
      <span v-for="name in weekdayNames" :key="name">{{ name }}</span>
    </div>

    <div class="calendar-grid">
      <div
        v-for="blank in firstDayOfMonth"
        :key="'blank-' + blank"
        class="calendar-day empty"
      ></div>

      <button
        v-for="day in daysInMonth"
        :key="day"
        type="button"
        class="calendar-day"
        :class="{
          scheduled: isScheduled(day),
          today: isToday(day),
          selected: isSelectedDate(day),
          inclusive: hasTripTypeOnDate(day, 'inclusive'),
          exclusive: hasTripTypeOnDate(day, 'exclusive'),
          new: hasNewTripOnDate(day)
        }"
        :disabled="!isScheduled(day)"
        @click="emit('open-day', day)"
      >
        <span class="day-number">{{ day }}</span>
        <span v-if="isScheduled(day)" class="calendar-trip-count" :aria-label="`${getTripCountForDate(day)} scheduled trips`">{{ getTripCountForDate(day) }}</span>
        <span v-if="hasNewTripOnDate(day)" class="new-dot" title="New scheduled trip" aria-label="New scheduled trip"></span>
      </button>
    </div>
  </div>
</template>
