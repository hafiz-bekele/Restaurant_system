<script setup lang="ts">
import { computed } from 'vue'
import {
  Calendar,
  Clock,
  Users,
  ShieldCheck,
  CreditCard,
  ArrowLeft,
  Lock,
  Sparkles,
  MapPin,
  FileText,
} from '@lucide/vue'
import { useReservationStore } from '../../stores/reservation'

const store = useReservationStore()

const emit = defineEmits<{
  (e: 'back'): void
  (e: 'pay'): void
}>()

const surchargeLabel = computed(() => {
  if (store.daysAhead === 0) return 'Same-Day Booking (+0%)'
  if (store.daysAhead === 1) return 'Tomorrow Advance (+25%)'
  return `${store.daysAhead} Days Ahead (+40%)`
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="card p-6 border border-warm-100 dark:border-warm-800 bg-gradient-to-br from-warm-50 via-white to-primary-50/20 dark:from-warm-900 dark:via-warm-900 dark:to-primary-950/20">
      <div class="flex items-center gap-2 pb-3 border-b border-warm-100 dark:border-warm-800">
        <CreditCard :size="20" class="text-primary-500" />
        <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">
          Review & Settle Reservation
        </h2>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
        <!-- Guest Details -->
        <div class="space-y-3">
          <h3 class="text-xs font-bold uppercase tracking-wider text-warm-400">
            Guest Information
          </h3>
          <div class="p-4 rounded-xl bg-white dark:bg-warm-800/60 border border-warm-100 dark:border-warm-800 space-y-1.5 text-sm">
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
          <div class="p-4 rounded-xl bg-white dark:bg-warm-800/60 border border-warm-100 dark:border-warm-800 space-y-1.5 text-sm">
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
              <span class="text-xs text-warm-400">Notes: {{ store.form.special_requests }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Official Price Breakdown Card -->
    <div class="card p-6 border border-warm-100 dark:border-warm-800 space-y-4">
      <h3 class="text-xs font-bold uppercase tracking-wider text-warm-400 pb-2 border-b border-warm-100 dark:border-warm-800">
        Price Breakdown (ETB)
      </h3>

      <div class="space-y-2.5 text-sm">
        <!-- Base Duration -->
        <div class="flex items-center justify-between text-warm-700 dark:text-warm-300">
          <span>Base Fee ({{ store.form.duration_hours }} Hours @ 12.50 ETB/hr)</span>
          <span class="font-mono font-semibold">ETB {{ store.basePrice.toFixed(2) }}</span>
        </div>

        <!-- Day Surcharge -->
        <div class="flex items-center justify-between text-warm-700 dark:text-warm-300">
          <span class="flex items-center gap-1.5">
            <span>{{ surchargeLabel }}</span>
            <span v-if="store.surchargePercent > 0" class="text-[10px] font-bold px-1.5 py-0.2 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
              +{{ store.surchargePercent }}%
            </span>
          </span>
          <span class="font-mono font-semibold" :class="store.surchargeAmount > 0 ? 'text-amber-600 dark:text-amber-400' : ''">
            +ETB {{ store.surchargeAmount.toFixed(2) }}
          </span>
        </div>

        <!-- Divider -->
        <div class="h-px bg-warm-200 dark:bg-warm-800 my-2" />

        <!-- Total -->
        <div class="flex items-baseline justify-between text-base">
          <div>
            <strong class="font-heading font-bold text-lg text-warm-900 dark:text-white">
              Total Payable Deposit
            </strong>
            <p class="text-xs text-warm-400">Fully credited towards your dining bill or reservation session</p>
          </div>
          <span class="font-heading font-extrabold text-2xl text-primary-600 dark:text-primary-400 font-mono">
            ETB {{ store.totalPrice.toFixed(2) }}
          </span>
        </div>
      </div>

      <!-- 10-Minute Hold Guarantee Banner -->
      <div class="p-4 rounded-xl bg-primary-50/70 dark:bg-primary-950/30 border border-primary-200/60 dark:border-primary-900/40 flex items-start gap-3">
        <div class="p-1.5 rounded-lg bg-primary-500 text-white shrink-0 mt-0.5">
          <ShieldCheck :size="16" />
        </div>
        <div class="text-xs text-warm-700 dark:text-warm-300 leading-relaxed">
          <strong class="text-primary-700 dark:text-primary-300">10-Minute Table Hold Guarantee:</strong>
          Upon clicking <em>Pay with Chapa</em>, <strong>{{ store.selectedTable?.name }}</strong> will be instantly locked in the database exclusively for you. You will have 10 minutes to complete payment via Telebirr, CBE Birr, or debit card.
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
        @click="emit('pay')"
        class="btn-primary gap-2.5 text-base px-8 py-4 shadow-xl shadow-primary-500/30 font-bold"
      >
        <Lock v-if="!store.isSubmitting" :size="18" />
        <span v-if="store.isSubmitting" class="flex items-center gap-2">
          <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          <span>Connecting to Chapa…</span>
        </span>
        <span v-else>
          Pay with Chapa (ETB {{ store.totalPrice.toFixed(2) }})
        </span>
      </button>
    </div>
  </div>
</template>
