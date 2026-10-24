<script setup lang="ts">
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import ReservationWizard from '../../components/reservation/ReservationWizard.vue'
import { UtensilsCrossed, Sparkles } from 'lucide-vue-next'
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-8 sm:py-12 max-w-4xl mx-auto">
      <!-- Page Hero Header -->
      <div class="text-center mb-8 sm:mb-10 space-y-3">
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-500/20">
          <UtensilsCrossed class="w-3.5 h-3.5" />
          <span>Real-Time Table Seating & Guaranteed Reservation</span>
        </div>

        <h1 class="font-heading text-3xl sm:text-4xl md:text-5xl font-black text-warm-900 dark:text-white tracking-tight">
          Reserve Your Table
        </h1>

        <p class="text-warm-600 dark:text-warm-400 text-sm sm:text-base max-w-xl mx-auto">
          Select your dining time, choose your preferred table in real time, and enjoy instant booking confirmation.
        </p>
      </div>

      <!-- Main Multi-Step Wizard -->
      <ReservationWizard />
    </div>
  </DefaultLayout>
</template>
