<script setup>
defineProps({
  tripTypeLabel: { type: String, required: true },
  total: { type: Number, required: true },
  upcoming: { type: Number, required: true },
  newCount: { type: Number, required: true },
})
</script>

<template>
  <section class="summary-card">
    <div class="summary-heading">
      <div>
        <span class="small-label">Schedule summary</span>
        <h2>{{ tripTypeLabel }} trips</h2>
      </div>
    </div>

    <div class="summary-grid">
      <div class="summary-item">
        <svg class="summary-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="17" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M7.5 3.5v4M16.5 3.5v4M3.5 9.5h17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="m8 14 2.2 2.2L16 11" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <strong>{{ total }}</strong>
        <span>Total scheduled</span>
      </div>
      <div class="summary-item">
        <svg class="summary-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3.5 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <strong>{{ upcoming }}</strong>
        <span>Upcoming</span>
      </div>
      <div class="summary-item">
        <svg class="summary-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <strong>{{ newCount }}</strong>
        <span>Unviewed scheduled trips</span>
      </div>
    </div>
  </section>
</template>
