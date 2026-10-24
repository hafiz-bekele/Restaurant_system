<script setup lang="ts">
import {
  Calendar,
  Clock,
  Users,
  ShieldCheck,
  CheckCircle2,
  ArrowLeft,
  Sparkles,
  MapPin,
  Check,
} from '@lucide/vue'
import { useReservationStore } from '../../stores/reservation'

const store = useReservationStore()

const emit = defineEmits<{
  (e: 'back'): void
  (e: 'confirm'): void
  (e: 'pay'): void
}>()

function handleConfirm() {
  emit('confirm')
  emit('pay')
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header & Summary Card -->
    <div class="card p-6 border border-warm-100 dark:border-warm-800 bg-gradient-to-br from-warm-50 via-white to-primary-50/20 dark:from-warm-900 dark:via-warm-900 dark:to-primary-950/20">
      <div class="flex items-center gap-2 pb-3 border-b border-warm-100 dark:border-warm-800">
        <CheckCircle2 :size="20" class="text-primary-500" />
        <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">
          Review & Confirm Reservation
        </h2>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
        <!-- Guest Details -->
        <div class="space-y-3">
          <h3 class="text-xs font-bold uppercase tracking-wider text-warm-400">
            Guest Information
          </h3>
          <div class="p-4 rounded-xl bg-white dark:bg-warm-800/60 border border-warm-100 dark:border-warm-800 space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-warm-500">Guest Name:</span>
              <strong class="text-warm-900 dark:text-white">{{ store.form.customer_name }}</strong>
            </div>
            <div class="flex justify-between">
              <span class="text-warm-500">Phone:</span>
              <span class="font-mono text-warm-900 dark:text-white">{{ store.form.phone }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-warm-500">Email:</span>
              <span class="text-warm-900 dark:text-white">{{ store.form.email }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-warm-500">Party Size:</span>
              <span class="font-bold text-primary-600 dark:text-primary-400">
                {{ store.form.party_size }} {{ store.form.party_size === 1 ? 'Guest' : 'Guests' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Booking Details -->
        <div class="space-y-3">
          <h3 class="text-xs font-bold uppercase tracking-wider text-warm-400">
            Reservation Window & Table
          </h3>
          <div class="p-4 rounded-xl bg-white dark:bg-warm-800/60 border border-warm-100 dark:border-warm-800 space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-warm-500">Date:</span>
              <strong class="text-warm-900 dark:text-white">{{ store.form.date }}</strong>
            </div>
            <div class="flex justify-between">
              <span class="text-warm-500">Dining Window:</span>
              <span class="font-mono font-bold text-warm-900 dark:text-white">
                {{ store.form.start_time }} &mdash; {{ store.formattedEndTime }} ({{ store.form.duration_hours }}h)
              </span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-warm-500">Table:</span>
              <span class="font-bold text-warm-900 dark:text-white flex items-center gap-1.5">
                <MapPin :size="13" class="text-primary-500" />
                {{ store.selectedTable?.name }}
                <span class="text-[10px] uppercase font-bold px-1.5 py-0.2 rounded bg-warm-100 dark:bg-warm-700 text-warm-600 dark:text-warm-300">
                  {{ store.selectedTable?.area }}
                </span>
              </span>
            </div>
            <div v-if="store.form.special_requests" class="pt-1.5 border-t border-warm-100 dark:border-warm-700/60">
              <span class="text-xs text-warm-500">Notes: {{ store.form.special_requests }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Complimentary Policy & Hospitality Guarantee -->
    <div class="card p-6 border border-warm-100 dark:border-warm-800 space-y-4">
      <div class="flex items-center justify-between pb-2 border-b border-warm-100 dark:border-warm-800">
        <h3 class="text-xs font-bold uppercase tracking-wider text-warm-400">
          Booking Terms & Table Policy
        </h3>
        <span class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
          <Sparkles :size="12" /> 100% Complimentary
        </span>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
        <div class="p-3.5 rounded-xl bg-warm-50 dark:bg-warm-800/40 border border-warm-100 dark:border-warm-800 space-y-1">
          <p class="font-bold text-warm-900 dark:text-white flex items-center gap-1.5">
            <Check :size="14" class="text-emerald-500" /> No Booking Fee
          </p>
          <p class="text-warm-500 leading-relaxed">Table reservations are free of charge. You only pay for what you dine and drink.</p>
        </div>

        <div class="p-3.5 rounded-xl bg-warm-50 dark:bg-warm-800/40 border border-warm-100 dark:border-warm-800 space-y-1">
          <p class="font-bold text-warm-900 dark:text-white flex items-center gap-1.5">
            <Clock :size="14" class="text-primary-500" /> 15-Minute Grace Period
          </p>
          <p class="text-warm-500 leading-relaxed">Your table is held for up to 15 minutes past your reserved time.</p>
        </div>

        <div class="p-3.5 rounded-xl bg-warm-50 dark:bg-warm-800/40 border border-warm-100 dark:border-warm-800 space-y-1">
          <p class="font-bold text-warm-900 dark:text-white flex items-center gap-1.5">
            <ShieldCheck :size="14" class="text-sky-500" /> Instant Confirmation
          </p>
          <p class="text-warm-500 leading-relaxed">Receive instant QR check-in pass and Add to Calendar reminder.</p>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between pt-2">
      <button
        type="button"
        @click="emit('back')"
        class="btn-outline gap-2 text-sm"
      >
        <ArrowLeft :size="16" />
        <span>Change Table</span>
      </button>

      <button
        type="button"
        :disabled="store.isSubmitting"
        @click="handleConfirm"
        class="btn-primary gap-2.5 text-base px-8 py-4 shadow-xl shadow-primary-500/30 font-bold"
      >
        <CheckCircle2 v-if="!store.isSubmitting" :size="18" />
        <span v-if="store.isSubmitting" class="flex items-center gap-2">
          <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          <span>Confirming Reservation…</span>
        </span>
        <span v-else>
          Confirm Table Reservation
        </span>
      </button>
    </div>
  </div>
</template>
