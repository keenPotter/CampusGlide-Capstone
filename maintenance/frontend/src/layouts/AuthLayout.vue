<script setup>
import nvsuLogo from '@/assets/nvsu-logo.png'
import nvsuBuilding from '@/assets/nvsu-building.jpg'

const features = [
  {
    text: 'Online vehicle request and approval, no paper forms',
    icon: 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 5h6M9 14l2 2 4-4',
  },
  {
    text: 'Trip scheduling with driver and vehicle assignment in one place',
    icon: 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z',
  },
  {
    text: 'Maintenance records and service dates for every motor pool vehicle',
    icon: 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94z',
  },
  {
    text: 'Dashboard with request status and pending items for administrators',
    icon: 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
  },
]
</script>

<template>
  <div class="grid min-h-screen bg-white lg:grid-cols-[1.35fr_1fr]">
    <!-- Left brand panel (laptop and up) -->
    <section
      class="relative hidden overflow-hidden lg:flex lg:items-center lg:justify-center"
      style="background-image: linear-gradient(135deg, #1a5c20 0%, #2f9a2f 55%, #8a9a2a 100%)"
    >
      <!-- Building photo, see-through over the gradient -->
      <img
        :src="nvsuBuilding"
        alt=""
        class="absolute inset-0 h-full w-full object-cover opacity-25"
      />

      <!-- Soft decorative circles -->
      <div class="absolute -left-20 -top-20 h-80 w-80 rounded-full bg-white/10" />
      <div class="absolute -bottom-24 -right-16 h-72 w-72 rounded-full bg-white/10" />

      <div class="relative z-10 flex w-full max-w-md flex-col items-center px-page text-center text-white">
        <div class="flex h-16 w-16 items-center justify-center rounded-2xl border-2 border-white/60 bg-white/15">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 17h14M5 17a2 2 0 1 0 4 0m-4 0a2 2 0 1 1 4 0m6 0a2 2 0 1 0 4 0m-4 0a2 2 0 1 1 4 0" />
            <path d="M3 17V9l2-4h9l3 4h4v8" />
          </svg>
        </div>

        <h1 class="mt-4 !text-4xl font-bold">CampusGlide</h1>
        <p class="mt-2 text-small text-white/85">
          Nueva Vizcaya State University<br />
          Vehicle Request System
        </p>

        <ul class="mt-10 flex w-full flex-col gap-3 text-left">
          <li
            v-for="feature in features"
            :key="feature.text"
            class="flex items-center gap-3 rounded-card border border-white/40 bg-white/15 px-4 py-3 backdrop-blur-sm"
          >
            <svg
              class="shrink-0"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path :d="feature.icon" />
            </svg>
            <span class="text-small">{{ feature.text }}</span>
          </li>
        </ul>
      </div>
    </section>

    <!-- Right form panel -->
    <section class="flex min-h-screen flex-col items-center justify-center bg-white p-page">
      <div class="w-full max-w-sm">
        <div class="mb-8 flex items-center justify-center gap-3">
          <img :src="nvsuLogo" alt="Nueva Vizcaya State University seal" class="h-14 w-14 object-contain" />
          <p class="text-left text-body font-bold uppercase leading-tight text-primary-800">
            Nueva Vizcaya<br />State University
          </p>
        </div>

        <!-- Product name for phones and tablets, where the left panel is hidden -->
        <p class="mb-6 text-center text-small font-medium text-ink-muted lg:hidden">
          CampusGlide · Vehicle Request System
        </p>

        <slot />
      </div>

      <p class="mt-10 text-center text-small text-ink-muted">
        NVSU-FR-PPS-13-02 · Motorpool &amp; General Services Unit
      </p>
    </section>
  </div>
</template>