<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import QRCode from 'qrcode'
import {
  CheckCircle2,
  AlertCircle,
  Clock,
  Calendar,
  Users,
  MapPin,
  RefreshCw,
  Copy,
  Check,
  CalendarPlus,
  ArrowRight,
  ShieldCheck,
  AlertTriangle,
  RotateCcw
} from 'lucide-vue-next'
import { useReservationStore, type ReservationData } from '../../stores/reservation'
import CountdownTimer from './CountdownTimer.vue'

const props = defineProps<{
  ulid?: string
}>()

const emit = defineEmits<{
  (e: 'restart'): void
  (e: 'retryPayment'): void
}>()

const router = useRouter()
const reservationStore = useReservationStore()

const loading = ref(true)
const errorMessage = ref<string | null>(null)
const qrCodeDataUrl = ref<string | null>(null)
const copied = ref(false)

const reservation = computed<ReservationData | null>(() => {
  return reservationStore.currentReservation
})

const effectiveUlid = computed(() => {
  return props.ulid || reservation.value?.ulid || ''
})

const paymentState = computed<'paid' | 'pending' | 'failed'>(() => {
  const status = reservation.value?.status
  if (status === 'confirmed' || status === 'active' || reservation.value?.is_paid) {
    return 'paid'
  }
  if (status === 'pending_payment') {
    return 'pending'
  }
  if (status === 'cancelled' || status === 'expired' || status === 'refunded') {
    return 'failed'
  }
  return 'pending'
})

async function generateQr(text: string) {
  try {
    qrCodeDataUrl.value = await QRCode.toDataURL(text, {
      width: 220,
      margin: 1,
      color: {
        dark: '#1c1917',
        light: '#ffffff',
      },
    })
  } catch (e) {
    console.error('Failed to generate QR code', e)
  }
}

async function verifyAndLoad() {
  if (!effectiveUlid.value) {
    loading.value = false
    return
  }

  loading.value = true
  errorMessage.value = null

  try {
    // 1. Call verification endpoint
    const data = await reservationStore.verifyPayment(effectiveUlid.value)
    if (data?.reservation) {
      await generateQr(data.reservation.ulid || effectiveUlid.value)
    }
  } catch (e: any) {
    // Fallback: try standard get if verify fails
    try {
      const res = await reservationStore.fetchReservation(effectiveUlid.value)
      if (res?.ulid) {
        await generateQr(res.ulid)
      }
    } catch (err: any) {
      errorMessage.value = err?.response?.data?.message || 'Could not verify reservation status.'
    }
  } finally {
    loading.value = false
  }
}

function copyCode() {
  if (!reservation.value?.ulid) return
  navigator.clipboard.writeText(reservation.value.ulid)
  copied.value = true
  setTimeout(() => {
    copied.value = false
  }, 2000)
}

function downloadCalendarInvite() {
  if (!reservation.value) return
  const r = reservation.value

  const startDate = new Date(r.start_at)
  const endDate = new Date(r.end_at)

  const formatIcsDate = (date: Date) => {
    return date.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z'
  }

  const icsContent = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Restaurant Reservation System//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    `UID:rsv-${r.ulid}@restaurant.local`,
    `DTSTAMP:${formatIcsDate(new Date())}`,
    `DTSTART:${formatIcsDate(startDate)}`,
    `DTEND:${formatIcsDate(endDate)}`,
    `SUMMARY:Dining Reservation - Table ${r.table?.name ?? 'Reserved'}`,
    `DESCRIPTION:Table reservation for ${r.customer_name} (${r.party_size} guests). Reservation code: ${r.ulid}. Duration: ${r.duration_hours}h.`,
    'LOCATION:Restaurant Main Dining, Addis Ababa, Ethiopia',
    'STATUS:CONFIRMED',
    'END:VEVENT',
    'END:VCALENDAR',
  ].join('\r\n')

  const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.setAttribute('download', `reservation-${r.ulid}.ics`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

function formatDateTime(isoStr?: string | null) {
  if (!isoStr) return '—'
  const d = new Date(isoStr)
  return d.toLocaleString([], {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function retryPayment() {
  if (reservationStore.checkoutUrl) {
    window.location.href = reservationStore.checkoutUrl
  } else {
    emit('retryPayment')
  }
}

onMounted(() => {
  verifyAndLoad()
})
</script>

<template>
  <div class="space-y-6 max-w-2xl mx-auto">
    <!-- Loading skeleton -->
    <div
      v-if="loading"
      class="card p-8 rounded-3xl border border-warm-200 dark:border-warm-800 bg-white dark:bg-warm-900 shadow-xl text-center space-y-6"
    >
      <div class="w-16 h-16 rounded-full bg-primary-500/10 text-primary-500 flex items-center justify-center mx-auto animate-pulse">
        <RefreshCw class="w-8 h-8 animate-spin" />
      </div>
      <div class="space-y-2">
        <h3 class="text-xl font-bold text-warm-900 dark:text-white">
          Verifying Payment & Reservation...
        </h3>
        <p class="text-sm text-warm-500 dark:text-warm-400">
          Communicating with Chapa payment gateway and confirming your table hold.
        </p>
      </div>
      <div class="h-32 bg-warm-100 dark:bg-warm-800 rounded-2xl animate-pulse" />
    </div>

    <!-- Error state with retry -->
    <div
      v-else-if="errorMessage && !reservation"
      class="card p-8 rounded-3xl border border-red-200 dark:border-red-900/60 bg-red-50/50 dark:bg-red-950/20 shadow-xl text-center space-y-5"
    >
      <div class="w-14 h-14 rounded-2xl bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400 flex items-center justify-center mx-auto">
        <AlertCircle class="w-8 h-8" />
      </div>
      <div class="space-y-1">
        <h3 class="text-xl font-bold text-red-900 dark:text-red-200">
          Verification Issue
        </h3>
        <p class="text-sm text-red-600 dark:text-red-400">
          {{ errorMessage }}
        </p>
      </div>
      <div class="flex items-center justify-center gap-3 pt-2">
        <button
          type="button"
          @click="verifyAndLoad"
          class="btn-primary text-sm flex items-center gap-2"
        >
          <RefreshCw class="w-4 h-4" />
          Retry Verification
        </button>
        <button
          type="button"
          @click="emit('restart')"
          class="btn-outline text-sm"
        >
          Start New Reservation
        </button>
      </div>
    </div>

    <!-- MAIN CONFIRMED / PAID STATE -->
    <div
      v-else-if="paymentState === 'paid' && reservation"
      class="card p-6 sm:p-8 rounded-3xl border border-warm-200 dark:border-warm-800 bg-white dark:bg-warm-900 shadow-xl space-y-6"
    >
      <!-- Top banner -->
      <div class="text-center space-y-3">
        <div class="w-16 h-16 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto shadow-inner ring-4 ring-emerald-500/10">
          <ShieldCheck class="w-9 h-9" />
        </div>
        <div>
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20 mb-2">
            <CheckCircle2 class="w-3.5 h-3.5" />
            Payment Verified & Table Guaranteed
          </span>
          <h2 class="text-2xl sm:text-3xl font-black font-heading text-warm-900 dark:text-white">
            Reservation Confirmed!
          </h2>
          <p class="text-sm text-warm-500 dark:text-warm-400 mt-1">
            We have confirmed your booking and sent a receipt to
            <strong class="text-warm-800 dark:text-warm-200">{{ reservation.email }}</strong>.
          </p>
        </div>
      </div>

      <!-- Live Countdown Timer -->
      <CountdownTimer
        :start-at="reservation.start_at"
        :end-at="reservation.end_at"
        :status="reservation.status"
        :server-offset="reservationStore.serverTimeOffset"
      />

      <!-- Reservation Code & QR Code Card -->
      <div class="p-5 rounded-2xl bg-warm-50 dark:bg-warm-850/80 border border-warm-200/80 dark:border-warm-800 grid grid-cols-1 md:grid-cols-3 gap-5 items-center">
        <!-- QR Code Canvas -->
        <div class="flex flex-col items-center justify-center text-center">
          <div class="p-2.5 bg-white rounded-2xl shadow-sm border border-warm-200 dark:border-warm-700">
            <img
              v-if="qrCodeDataUrl"
              :src="qrCodeDataUrl"
              alt="Reservation QR Code"
              class="w-32 h-32 object-contain rounded-lg"
            />
            <div
              v-else
              class="w-32 h-32 bg-warm-100 flex items-center justify-center text-xs text-warm-400"
            >
              Generating QR...
            </div>
          </div>
          <span class="text-[10px] font-semibold uppercase tracking-wider text-warm-500 dark:text-warm-400 mt-2">
            Scan at host stand
          </span>
        </div>

        <!-- Reservation Details -->
        <div class="md:col-span-2 space-y-3">
          <div>
            <span class="text-[11px] font-semibold uppercase tracking-wider text-warm-500 dark:text-warm-400">
              Reservation Code (ULID)
            </span>
            <div class="flex items-center gap-2 mt-1">
              <span class="font-mono text-base sm:text-lg font-black text-warm-900 dark:text-white tracking-wider bg-white dark:bg-warm-800 px-3 py-1.5 rounded-xl border border-warm-200 dark:border-warm-700 select-all">
                {{ reservation.ulid }}
              </span>
              <button
                type="button"
                @click="copyCode"
                class="p-2 rounded-xl border border-warm-200 dark:border-warm-700 bg-white dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-warm-100 transition-colors"
                title="Copy Code"
              >
                <Check v-if="copied" class="w-4 h-4 text-emerald-600" />
                <Copy v-else class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2 text-xs">
            <div class="p-2.5 rounded-xl bg-white dark:bg-warm-800 border border-warm-200/70 dark:border-warm-700/70">
              <span class="text-[10px] uppercase font-bold text-warm-400 block">Table</span>
              <span class="font-bold text-warm-900 dark:text-white text-sm">
                {{ reservation.table?.name ?? 'Assigned' }}
              </span>
              <span class="text-[11px] text-primary-600 dark:text-primary-400 capitalize block mt-0.5">
                {{ reservation.table?.area ?? 'Main Area' }}
              </span>
            </div>

            <div class="p-2.5 rounded-xl bg-white dark:bg-warm-800 border border-warm-200/70 dark:border-warm-700/70">
              <span class="text-[10px] uppercase font-bold text-warm-400 block">Party & Duration</span>
              <span class="font-bold text-warm-900 dark:text-white text-sm">
                {{ reservation.party_size }} Guests
              </span>
              <span class="text-[11px] text-warm-600 dark:text-warm-400 block mt-0.5">
                {{ reservation.duration_hours }} Hour{{ reservation.duration_hours > 1 ? 's' : '' }} Window
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Schedule Details Card -->
      <div class="p-4 rounded-2xl bg-warm-50/70 dark:bg-warm-850/60 border border-warm-200/80 dark:border-warm-800 space-y-2 text-xs">
        <div class="flex items-center justify-between py-1 border-b border-warm-200/60 dark:border-warm-700/60">
          <span class="text-warm-500 flex items-center gap-1.5">
            <Clock class="w-3.5 h-3.5 text-primary-500" /> Start Window:
          </span>
          <span class="font-bold text-warm-900 dark:text-white font-mono">
            {{ formatDateTime(reservation.start_at) }}
          </span>
        </div>

        <div class="flex items-center justify-between py-1 border-b border-warm-200/60 dark:border-warm-700/60">
          <span class="text-warm-500 flex items-center gap-1.5">
            <Clock class="w-3.5 h-3.5 text-amber-500" /> End Window:
          </span>
          <span class="font-bold text-warm-900 dark:text-white font-mono">
            {{ formatDateTime(reservation.end_at) }}
          </span>
        </div>

        <div class="flex items-center justify-between py-1">
          <span class="text-warm-500">Amount Paid via Chapa:</span>
          <span class="font-mono font-bold text-primary-600 dark:text-primary-400 text-sm">
            ETB {{ Number(reservation.total_price).toFixed(2) }}
          </span>
        </div>
      </div>

      <!-- Action buttons -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
        <button
          type="button"
          @click="downloadCalendarInvite"
          class="btn-outline justify-center text-sm py-2.5 flex items-center gap-2"
        >
          <CalendarPlus class="w-4 h-4 text-primary-500" />
          Add to Calendar (.ics)
        </button>

        <button
          type="button"
          @click="emit('restart')"
          class="btn-primary justify-center text-sm py-2.5 flex items-center gap-2"
        >
          Book Another Table
          <ArrowRight class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- PENDING PAYMENT STATE (Awaiting Chapa / Hold Active) -->
    <div
      v-else-if="paymentState === 'pending' && reservation"
      class="card p-6 sm:p-8 rounded-3xl border border-amber-200 dark:border-amber-800/80 bg-amber-50/40 dark:bg-amber-950/20 shadow-xl space-y-6"
    >
      <div class="text-center space-y-3">
        <div class="w-14 h-14 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto">
          <Clock class="w-7 h-7 animate-pulse" />
        </div>
        <div>
          <span class="badge badge-amber text-xs font-bold uppercase tracking-wider mb-2">
            Payment Hold Active
          </span>
          <h3 class="text-xl sm:text-2xl font-bold text-warm-900 dark:text-white">
            Awaiting Payment Confirmation
          </h3>
          <p class="text-xs sm:text-sm text-warm-600 dark:text-warm-400 max-w-md mx-auto mt-1">
            Your table <strong class="text-warm-900 dark:text-white">{{ reservation.table?.name }}</strong> is held for 10 minutes while we await payment confirmation from Chapa.
          </p>
        </div>
      </div>

      <div class="p-4 rounded-2xl bg-white dark:bg-warm-850 border border-amber-200 dark:border-amber-800 space-y-2 text-xs">
        <div class="flex items-center justify-between">
          <span class="text-warm-500">Hold Expires At:</span>
          <span class="font-mono font-bold text-amber-600 dark:text-amber-400">
            {{ formatDateTime(reservation.hold_expires_at) }}
          </span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-warm-500">Amount to Pay:</span>
          <span class="font-mono font-bold text-primary-600 dark:text-primary-400 text-sm">
            ETB {{ Number(reservation.total_price).toFixed(2) }}
          </span>
        </div>
      </div>

      <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <button
          type="button"
          @click="verifyAndLoad"
          class="btn-primary w-full sm:w-auto text-sm flex items-center justify-center gap-2"
        >
          <RefreshCw class="w-4 h-4" />
          Refresh Payment Status
        </button>

        <button
          v-if="reservationStore.checkoutUrl"
          type="button"
          @click="retryPayment"
          class="btn-outline w-full sm:w-auto text-sm flex items-center justify-center gap-2"
        >
          Continue to Chapa
          <ArrowRight class="w-4 h-4" />
        </button>

        <button
          type="button"
          @click="emit('restart')"
          class="text-xs text-warm-500 hover:text-warm-700 dark:hover:text-warm-300 underline"
        >
          Cancel & choose another table
        </button>
      </div>
    </div>

    <!-- FAILED / EXPIRED STATE -->
    <div
      v-else
      class="card p-6 sm:p-8 rounded-3xl border border-warm-200 dark:border-warm-800 bg-white dark:bg-warm-900 shadow-xl text-center space-y-5"
    >
      <div class="w-14 h-14 rounded-2xl bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400 flex items-center justify-center mx-auto">
        <AlertTriangle class="w-7 h-7 text-amber-500" />
      </div>

      <div class="space-y-1">
        <h3 class="text-xl font-bold text-warm-900 dark:text-white">
          Payment Not Completed
        </h3>
        <p class="text-xs sm:text-sm text-warm-500 dark:text-warm-400 max-w-md mx-auto">
          The payment was not completed or the 10-minute table hold expired. The table has been released for other diners.
        </p>
      </div>

      <div class="flex items-center justify-center gap-3 pt-2">
        <button
          type="button"
          @click="emit('retryPayment')"
          class="btn-primary text-sm flex items-center gap-2"
        >
          <RotateCcw class="w-4 h-4" />
          Try Reserving Again
        </button>
        <button
          type="button"
          @click="emit('restart')"
          class="btn-outline text-sm"
        >
          Back to Step 1
        </button>
      </div>
    </div>
  </div>
</template>
