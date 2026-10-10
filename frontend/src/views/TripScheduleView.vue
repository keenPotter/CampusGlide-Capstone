<script setup>
import TripScheduleHeader from '@/components/trip-schedule/TripScheduleHeader.vue'
import ScheduleTypeSwitcher from '@/components/trip-schedule/ScheduleTypeSwitcher.vue'
import ScheduleViewSwitcher from '@/components/trip-schedule/ScheduleViewSwitcher.vue'
import ScheduleSummary from '@/components/trip-schedule/ScheduleSummary.vue'
import LoadError from '@/components/trip-schedule/LoadError.vue'
import MonthCalendar from '@/components/trip-schedule/MonthCalendar.vue'
import MonthTripList from '@/components/trip-schedule/MonthTripList.vue'
import WeekCalendar from '@/components/trip-schedule/WeekCalendar.vue'
import TripList from '@/components/trip-schedule/TripList.vue'
import TripDetailsModal from '@/components/trip-schedule/TripDetailsModal.vue'
import { useTripSchedule } from '@/composables/useTripSchedule'

import '@/assets/trip-schedule.css'

const schedule = useTripSchedule()
</script>

<template>
  <div class="app">
    <main class="page">
      <TripScheduleHeader />

      <ScheduleTypeSwitcher v-model="schedule.currentTripType.value" />

      <ScheduleViewSwitcher
        v-model="schedule.currentView.value"
        :views="schedule.views"
      />

      <LoadError :message="schedule.loadError.value" @retry="schedule.fetchTrips" />

      <ScheduleSummary
        :trip-type-label="schedule.currentTripTypeLabel.value"
        :total="schedule.filteredTrips.value.length"
        :upcoming="schedule.upcomingTripCount.value"
        :new-count="schedule.newTripCount.value"
      />

      <section v-if="schedule.currentView.value === 'month'" class="schedule-section">
        <MonthCalendar
          :trip-type="schedule.currentTripType.value"
          :trip-type-label="schedule.currentTripTypeLabel.value"
          :month-name="schedule.monthName.value"
          :year="schedule.currentDate.value.getFullYear()"
          :weekday-names="schedule.weekdayNames"
          :first-day-of-month="schedule.firstDayOfMonth.value"
          :days-in-month="schedule.daysInMonth.value"
          :is-scheduled="schedule.isScheduled"
          :get-trip-count-for-date="schedule.getTripCountForDate"
          :is-today="schedule.isToday"
          :is-selected-date="schedule.isSelectedDate"
          :has-trip-type-on-date="schedule.hasTripTypeOnDate"
          :has-new-trip-on-date="schedule.hasNewTripOnDate"
          @change-month="schedule.changeMonth"
          @open-day="schedule.openDateTrips"
        />

        <MonthTripList
          :trip-type-label="schedule.currentTripTypeLabel.value"
          :month-name="schedule.monthName.value"
          :year="schedule.currentDate.value.getFullYear()"
          :trips="schedule.monthTrips.value"
          :is-new-trip="schedule.isNewTrip"
          :trip-type-class="schedule.tripTypeClass"
          :trip-type-label-for-trip="schedule.tripTypeLabel"
          :requester-name="schedule.requesterName"
          :format-date-range="schedule.formatDateRange"
          :format-time="schedule.formatTime"
          :vehicle-name="schedule.vehicleName"
          :driver-name="schedule.driverName"
          @open-trip="schedule.openTripModal"
        />
      </section>

      <section v-if="schedule.currentView.value === 'week'" class="schedule-section">
        <WeekCalendar
          :trip-type="schedule.currentTripType.value"
          :week-range="schedule.weekRange.value"
          :week-days="schedule.weekDays.value"
          :get-trips-for-date="schedule.getTripsForDate"
          :trip-type-class="schedule.tripTypeClass"
          :is-new-trip="schedule.isNewTrip"
          :format-time="schedule.formatTime"
          @change-week="schedule.changeWeek"
          @open-date="schedule.openDateString"
          @open-trip="schedule.openTripModal"
        />
      </section>

      <section v-if="schedule.currentView.value === 'list'" class="schedule-section">
        <TripList
          :trip-type-label="schedule.currentTripTypeLabel.value"
          :trips="schedule.scheduledTripList.value"
          :is-new-trip="schedule.isNewTrip"
          :trip-type-class="schedule.tripTypeClass"
          :trip-type-label-for-trip="schedule.tripTypeLabel"
          :day-number="schedule.dayNumber"
          :short-month="schedule.shortMonth"
          :format-date-range="schedule.formatDateRange"
          :format-time="schedule.formatTime"
          :vehicle-name="schedule.vehicleName"
          :driver-name="schedule.driverName"
          @open-trip="schedule.openTripModal"
        />
      </section>
    </main>

    <TripDetailsModal
      :trip="schedule.activeTrip.value"
      :trips="schedule.activeTrips.value"
      :date-label="schedule.activeDateLabel.value"
      :rows="schedule.tripDetailRows.value"
      :is-new-trip="schedule.isNewTrip"
      :trip-type-class="schedule.tripTypeClass"
      :trip-type-label="schedule.tripTypeLabel"
      :vehicle-name="schedule.vehicleName"
      :driver-name="schedule.driverName"
      @select-trip="schedule.selectModalTrip"
      @close="schedule.closeTripModal"
    />
  </div>
</template>
