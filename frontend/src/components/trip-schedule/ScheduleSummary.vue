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
      <span
        v-if="newCount"
        class="new-summary-indicator"
        title="New scheduled trip"
        aria-label="New scheduled trip"
      ></span>
    </div>

    <div class="summary-grid">
      <div class="summary-item">
        <strong>{{ total }}</strong>
        <span>Total scheduled</span>
      </div>
      <div class="summary-item">
        <strong>{{ upcoming }}</strong>
        <span>Upcoming</span>
      </div>
      <div class="summary-item new-summary-item">
        <span
          class="new-summary-indicator large"
          title="Unviewed scheduled trips"
          aria-label="Unviewed scheduled trips"
        ></span>
        <span>Unviewed scheduled trips</span>
      </div>
    </div>
  </section>
</template>
