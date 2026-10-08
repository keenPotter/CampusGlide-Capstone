<template>
  <div class="flex min-h-screen flex-col bg-surface">
    <div class="flex flex-1 items-center justify-center p-page">
      <div class="w-full max-w-md">
        <div class="mb-6 flex flex-col items-center gap-2 text-center">
          <div class="flex h-12 w-12 items-center justify-center rounded-card bg-primary text-white">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M5 17h14M5 17a2 2 0 1 0 4 0m-4 0a2 2 0 1 1 4 0m6 0a2 2 0 1 0 4 0m-4 0a2 2 0 1 1 4 0" />
              <path d="M3 17V9l2-4h9l3 4h4v8" />
            </svg>
          </div>
          <h1 class="text-page-title">CampusGlide</h1>
          <p class="text-small text-ink-muted">
            Nueva Vizcaya State University — Vehicle Request System
          </p>
        </div>

        <slot />

        <p class="mt-6 text-center text-small text-ink-muted">
          NVSU-FR-PPS-13-02 · Motorpool &amp; General Services Unit
        </p>
      </div>
    </div>
  </div>
</template>