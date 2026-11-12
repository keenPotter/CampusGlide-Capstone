<script setup>
import { computed } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import RefreshButton from '@/components/ui/RefreshButton.vue'
import SegmentedControl from '@/components/ui/SegmentedControl.vue'
import LoadError from '@/components/ui/LoadError.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import { MONTH_OPTIONS, STATUS_LABELS, YEAR_OPTIONS } from '@/lib/format'
import ScheduleSummary from '@/components/trip-schedule/ScheduleSummary.vue'
import MonthCalendar from '@/components/trip-schedule/MonthCalendar.vue'
import WeekCalendar from '@/components/trip-schedule/WeekCalendar.vue'
import TripList from '@/components/trip-schedule/TripList.vue'
import TripDetailsModal from '@/components/trip-schedule/TripDetailsModal.vue'
import { useTripSchedule } from '@/composables/useTripSchedule'

const typeOptions = computed(() => [
  { value: 'inclusive', label: `Inclusive (${inclusiveTripCount.value})`, dot: 'bg-primary' },
  { value: 'exclusive', label: `Exclusive (${exclusiveTripCount.value})`, dot: 'bg-secondary' },
])

const viewOptions = [
  { value: 'month', label: 'Month' },
  { value: 'week', label: 'Week' },
  { value: 'list', label: 'List' },
]

const listStatusOptions = ['scheduled', 'in_progress'].map((value) => ({
  value,
  label: STATUS_LABELS[value],
}))

const {
  currentView,
  currentTripType,
  currentDate,
  activeTrip,
  activeTrips,
  activeDateLabel,
  loading,
  loadError,
  currentTripTypeLabel,
  filteredTrips,
  listSearch,
  listStatus,
  listMonth,
  listYear,
  tripListItems,
  tripListMeta,
  tripListLoading,
  tripListError,
  fetchTripList,
  changeTripListPage,
  exclusiveTripCount,
  inclusiveTripCount,
  upcomingTripCount,
  newTripCount,
  monthName,
  daysInMonth,
  firstDayOfMonth,
  weekDays,
  weekRange,
  monthTrips,
  tripDetailRows,
  fetchTrips,
  changeMonth,
  changeWeek,
  isToday,
  isSelectedDate,
  isScheduled,
  getTripCountForDate,
  hasNewTripOnDate,
  getTripsForDate,
  openDay,
  openDate,
  openTripModal,
  selectModalTrip,
  closeTripModal,
  isNewTrip,
} = useTripSchedule()
</script>

<template>
  <div class="flex flex-col gap-page">
    <div class="page-header">
      <div class="page-header-copy">
        <h1 class="text-page-title">Trip Schedule</h1>
        <p class="mt-1 text-small text-ink-muted">View scheduled trips and allocation details</p>
      </div>
      <div class="page-actions">
        <RefreshButton :loading="loading" label="Refresh trip schedule" @click="fetchTrips" />
      </div>
    </div>

    <SegmentedControl v-model="currentTripType" :options="typeOptions" label="Trip type" />
    <SegmentedControl v-model="currentView" :options="viewOptions" label="Schedule view" />

    <div v-if="currentView === 'list'" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <BaseInput v-model="listSearch" type="search" placeholder="Search trips..." />
      <BaseSelect v-model="listStatus" :options="listStatusOptions" placeholder="All statuses" allow-empty />
      <BaseSelect v-model="listMonth" :options="MONTH_OPTIONS" placeholder="All months" allow-empty />
      <BaseSelect v-model="listYear" :options="YEAR_OPTIONS" placeholder="All years" allow-empty />
    </div>

    <LoadError :message="loadError" @retry="fetchTrips" />

    <ScheduleSummary
      :trip-type-label="currentTripTypeLabel"
      :total="filteredTrips.length"
      :upcoming="upcomingTripCount"
      :new-count="newTripCount"
    />

    <div v-if="loading" class="flex justify-center p-12" aria-label="Loading trip schedule">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-primary" />
    </div>

    <template v-else-if="currentView === 'month'">
      <MonthCalendar
        :trip-type="currentTripType"
        :month-name="monthName"
        :year="currentDate.getFullYear()"
        :first-day-of-month="firstDayOfMonth"
        :days-in-month="daysInMonth"
        :is-scheduled="isScheduled"
        :get-trip-count-for-date="getTripCountForDate"
        :is-today="isToday"
        :is-selected-date="isSelectedDate"
        :has-new-trip-on-date="hasNewTripOnDate"
        @change-month="changeMonth"
        @open-day="openDay"
      />

      <TripList
        :title="`${currentTripTypeLabel} scheduled trips`"
        :subtitle="`${monthName} ${currentDate.getFullYear()}`"
        :empty-message="`No ${currentTripTypeLabel.toLowerCase()} scheduled trips for this month.`"
        :trips="monthTrips"
        :is-new-trip="isNewTrip"
        @open-trip="openTripModal"
      />
    </template>

    <WeekCalendar
      v-else-if="currentView === 'week'"
      :trip-type="currentTripType"
      :week-range="weekRange"
      :week-days="weekDays"
      :get-trips-for-date="getTripsForDate"
      :is-new-trip="isNewTrip"
      @change-week="changeWeek"
      @open-date="openDate"
      @open-trip="openTripModal"
    />

    <template v-else>
      <div v-if="tripListLoading" class="flex justify-center p-12" aria-label="Loading trip batch">
        <div class="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-primary" />
      </div>
      <LoadError v-else-if="tripListError" :message="tripListError" @retry="fetchTripList" />
      <template v-else>
        <TripList
          :title="`${currentTripTypeLabel} scheduled trips`"
          subtitle="All allocated trips of this type"
          :empty-message="`No ${currentTripTypeLabel.toLowerCase()} scheduled trips found.`"
          :trips="tripListItems"
          :is-new-trip="isNewTrip"
          @open-trip="openTripModal"
        />
        <PaginationBar :meta="tripListMeta" @change="changeTripListPage" />
      </template>
    </template>

    <TripDetailsModal
      :trip="activeTrip"
      :trips="activeTrips"
      :date-label="activeDateLabel"
      :rows="tripDetailRows"
      :is-new-trip="isNewTrip"
      @select-trip="selectModalTrip"
      @close="closeTripModal"
    />
  </div>
</template>