<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { reservationApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import { Calendar, Users, Clock, DollarSign, ShieldCheck, Sparkles, AlertCircle, Play, Pause, RotateCcw } from '@lucide/vue'

const { t } = useI18n()
const route = useRoute()
const ui = useUiStore()

const loading = ref(false)
const done = ref(false)
const confirmedReservation = ref(null)

const form = ref({
  name: '',
  email: '',
  phone: '',
  party_size: Number(route.query.party_size) || 2,
  reserved_at: typeof route.query.reserved_at === 'string' ? route.query.reserved_at : '',
  duration: 60, // Default 60 minutes (1 hour)
  notes: typeof route.query.notes === 'string' ? route.query.notes : ''
})

// Duration Presets
const durationPresets = [
  { label: '1 Hour', minutes: 60, desc: 'Base 50 ETB' },
  { label: '1.25 Hours', minutes: 75, desc: '75 mins' },
  { label: '1.5 Hours', minutes: 90, desc: '90 mins' },
  { label: '2 Hours (Max)', minutes: 120, desc: 'Auto-Redeems at 2h' },
]

// ── Real-Time Reservation Fee Breakdown ──────────────────────────────────
const baseFee = 50.00 // 50 ETB for up to 1 hour (3600s)

const durationSeconds = computed(() => (Number(form.value.duration) || 60) * 60)

const extraSeconds = computed(() => {
  // Overtime beyond 1 hour (3600 seconds), capped at 2 hours (7200 seconds)
  return Math.max(0, Math.min(7200, durationSeconds.value) - 3600)
})

const overtimeIntervals = computed(() => Math.floor(extraSeconds.value / 10))
const overtimeFee = computed(() => overtimeIntervals.value * 5.00) // +5 ETB every 10 seconds
const totalFee = computed(() => Math.round((baseFee + overtimeFee.value) * 100) / 100)

// ── Interactive Live Simulation Demo Ticker ──────────────────────────────
const isSimulating = ref(false)
const simElapsedSeconds = ref(3590) // Start 10 seconds before 1 hour so the user instantly sees the +5 ETB increase!
let simTimer = null

const simCurrentFee = computed(() => {
  if (simElapsedSeconds.value <= 3600) return 50.00
  const extra = Math.min(7200, simElapsedSeconds.value) - 3600
  const intervals = Math.floor(extra / 10)
  return 50.00 + (intervals * 5.00)
})

const simCountdownToRedeem = computed(() => {
  const rem = Math.max(0, 7200 - simElapsedSeconds.value)
  const m = Math.floor(rem / 60)
  const s = rem % 60
  return `${m}m ${s < 10 ? '0' : ''}${s}s`
})

const simIsRedeemed = computed(() => simElapsedSeconds.value >= 7200)

function toggleSimulation() {
  isSimulating.value = !isSimulating.value
  if (isSimulating.value) {
    simTimer = setInterval(() => {
      if (simElapsedSeconds.value < 7200) {
        simElapsedSeconds.value += 1
      } else {
        isSimulating.value = false
        clearInterval(simTimer)
      }
    }, 1000)
  } else {
    clearInterval(simTimer)
  }
}

function resetSimulation() {
  simElapsedSeconds.value = 3590
}

onUnmounted(() => {
  if (simTimer) clearInterval(simTimer)
})

// ── Form Submission ───────────────────────────────────────────────────────
async function submit() {
  loading.value = true
  try {
    const avail = await reservationApi.availability({
      date: form.value.reserved_at,
      party_size: form.value.party_size,
      duration: form.value.duration,
    })
    const tables = avail.data.data
    if (!tables.length) {
      ui.toast('No tables available for this date and party size.', 'warning')
      return
    }

    const payload = {
      ...form.value,
      table_id: tables[0].id,
      duration: Number(form.value.duration),
    }

    const res = await reservationApi.create(payload)
    confirmedReservation.value = res.data.data
    done.value = true
    ui.toast('Table reserved successfully! Reservation fee: ETB ' + totalFee.value.toFixed(2), 'success')
  } catch (e) {
    ui.toast(e.response?.data?.message ?? 'Failed to reserve table', 'error')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-12 max-w-xl mx-auto">
      <div class="text-center mb-8">
        <h1 class="font-heading text-3xl sm:text-4xl font-bold text-warm-900 dark:text-white mb-2">
          Table Reservation
        </h1>
        <p class="text-warm-500 dark:text-warm-400 text-sm sm:text-base">
          Reserve your table with guaranteed seating & automatic 2-hour fee redemption
        </p>
      </div>

      <!-- SUCCESS CONFIRMATION STATE -->
      <div v-if="done" class="card p-8 sm:p-10 text-center space-y-6 shadow-xl border border-warm-200/80 dark:border-warm-800">
        <div class="w-16 h-16 rounded-full bg-green-100 dark:bg-green-950/50 text-green-600 dark:text-green-400 flex items-center justify-center mx-auto shadow-inner">
          <ShieldCheck :size="36" />
        </div>

        <div>
          <h2 class="font-heading text-2xl font-bold text-warm-900 dark:text-white mb-2">
            Reservation Confirmed!
          </h2>
          <p class="text-warm-600 dark:text-warm-400 text-sm">
            We sent a confirmation to <strong class="text-warm-900 dark:text-white">{{ form.email }}</strong>.
          </p>
        </div>

        <!-- Reservation Receipt Summary -->
        <div class="p-4 rounded-2xl bg-warm-50 dark:bg-warm-850/70 border border-warm-200/70 dark:border-warm-800 text-left space-y-3">
          <div class="flex items-center justify-between text-xs text-warm-500 pb-2 border-b border-warm-200/60 dark:border-warm-700/60">
            <span>Reference ID</span>
            <span class="font-mono font-bold text-warm-900 dark:text-white">#{{ confirmedReservation?.id ?? 'RES-CONFIRMED' }}</span>
          </div>

          <div class="flex items-center justify-between text-xs text-warm-500">
            <span>Party Size & Table</span>
            <span class="font-medium text-warm-900 dark:text-white">
              {{ form.party_size }} Guests • {{ confirmedReservation?.table?.name ?? 'Assigned Table' }}
            </span>
          </div>

          <div class="flex items-center justify-between text-xs text-warm-500">
            <span>Reserved Time & Duration</span>
            <span class="font-medium text-warm-900 dark:text-white">
              {{ new Date(form.reserved_at).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) }} ({{ form.duration }} mins)
            </span>
          </div>

          <div class="flex items-center justify-between text-xs text-warm-500 pt-2 border-t border-warm-200/60 dark:border-warm-700/60">
            <span class="font-semibold text-warm-900 dark:text-white">Total Reservation Fee</span>
            <span class="font-mono text-base font-bold text-primary-600 dark:text-primary-400">
              ETB {{ Number(confirmedReservation?.reservation_fee ?? totalFee).toFixed(2) }}
            </span>
          </div>
        </div>

        <!-- Auto-Redeem Policy Banner -->
        <div class="p-3.5 rounded-xl bg-purple-50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800/60 flex items-start gap-2.5 text-left text-xs text-purple-700 dark:text-purple-300">
          <Sparkles :size="16" class="shrink-0 mt-0.5 text-purple-600 dark:text-purple-400" />
          <div>
            <strong class="font-bold">2-Hour Auto-Redeem Guarantee:</strong>
            <p class="mt-0.5 opacity-90">
              Your table is secured. After 2 hours, your reservation will automatically redeem, and your fee will be credited towards your dining bill!
            </p>
          </div>
        </div>

        <button
          @click="done = false; form.name = ''; form.notes = ''"
          class="btn-outline w-full justify-center"
        >
          Make Another Reservation
        </button>
      </div>

      <!-- RESERVATION BOOKING FORM -->
      <form v-else @submit.prevent="submit" class="card p-6 sm:p-8 space-y-5 shadow-xl border border-warm-200/80 dark:border-warm-800">
        <!-- Guest Details -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-warm-700 dark:text-warm-300 mb-1.5">Full Name</label>
            <input v-model="form.name" type="text" required class="input" placeholder="e.g. Abebe Bikila" />
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-warm-700 dark:text-warm-300 mb-1.5">Phone Number</label>
            <input v-model="form.phone" type="tel" required class="input" placeholder="+251 91 123 4567" />
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-warm-700 dark:text-warm-300 mb-1.5">Email Address</label>
          <input v-model="form.email" type="email" required class="input" placeholder="abebe@example.com" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-warm-700 dark:text-warm-300 mb-1.5 flex items-center gap-1">
              <Users :size="13" class="text-primary-500" /> Party Size
            </label>
            <input v-model.number="form.party_size" type="number" min="1" max="20" required class="input" />
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-warm-700 dark:text-warm-300 mb-1.5 flex items-center gap-1">
              <Calendar :size="13" class="text-primary-500" /> Date & Time
            </label>
            <input v-model="form.reserved_at" type="datetime-local" required class="input" />
          </div>
        </div>

        <!-- Duration Selection with Presets -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-warm-700 dark:text-warm-300 mb-1.5 flex items-center justify-between">
            <span class="flex items-center gap-1"><Clock :size="13" class="text-primary-500" /> Planned Duration</span>
            <span class="text-primary-600 dark:text-primary-400 font-bold font-mono">{{ form.duration }} minutes</span>
          </label>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-2">
            <button
              v-for="p in durationPresets"
              :key="p.minutes"
              type="button"
              @click="form.duration = p.minutes"
              :class="[
                'p-2.5 rounded-xl border text-left transition-all',
                form.duration === p.minutes
                  ? 'border-primary-500 bg-primary-500/10 text-primary-700 dark:text-primary-300 ring-2 ring-primary-500/30'
                  : 'border-warm-200 dark:border-warm-800 bg-warm-50/50 dark:bg-warm-850/50 text-warm-700 dark:text-warm-300 hover:border-warm-300'
              ]"
            >
              <p class="font-bold text-xs">{{ p.label }}</p>
              <p class="text-[10px] text-warm-500 mt-0.5">{{ p.desc }}</p>
            </button>
          </div>
          <p class="text-[11px] text-warm-500">
            Base fee applies for the 1st hour. Overtime adds 5 ETB every 10 seconds. Maximum reservation duration is 2 hours (120 mins) before auto-redemption.
          </p>
        </div>

        <!-- Special Requests -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-warm-700 dark:text-warm-300 mb-1.5">Special Requests (Optional)</label>
          <textarea v-model="form.notes" rows="2" class="input resize-none text-sm" placeholder="Window table, quiet area, anniversary..."></textarea>
        </div>

        <!-- ── DYNAMIC RESERVATION FEE BREAKDOWN CARD ────────────────────────── -->
        <div class="p-4 rounded-2xl bg-gradient-to-br from-warm-50 via-warm-100/50 to-warm-50 dark:from-warm-900/90 dark:via-warm-850/80 dark:to-warm-900/90 border border-warm-200/90 dark:border-warm-800/90 space-y-3">
          <div class="flex items-center justify-between pb-2 border-b border-warm-200/70 dark:border-warm-700/60">
            <span class="text-xs font-bold text-warm-900 dark:text-white flex items-center gap-1.5">
              <DollarSign :size="15" class="text-primary-500" />
              Reservation Fee Breakdown
            </span>
            <span class="badge badge-green text-[10px] font-bold">Transparent Pricing</span>
          </div>

          <div class="space-y-1.5 text-xs text-warm-600 dark:text-warm-400">
            <div class="flex items-center justify-between">
              <span>Base Fee (First 1 Hour / 60 mins):</span>
              <span class="font-mono font-bold text-warm-900 dark:text-white">ETB 50.00</span>
            </div>

            <div class="flex items-center justify-between">
              <span>Overtime (After 1 hr: +5 ETB per 10s):</span>
              <span class="font-mono font-bold" :class="overtimeFee > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-warm-500'">
                +ETB {{ overtimeFee.toFixed(2) }}
                <span v-if="overtimeIntervals > 0" class="text-[10px] opacity-75">({{ overtimeIntervals }} × 10s)</span>
              </span>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-warm-200/60 dark:border-warm-700/60 text-sm">
              <span class="font-bold text-warm-900 dark:text-white">Total Reservation Fee:</span>
              <span class="font-mono text-base font-bold text-primary-600 dark:text-primary-400">
                ETB {{ totalFee.toFixed(2) }}
              </span>
            </div>
          </div>

          <!-- 2-Hour Auto-Redeem Banner -->
          <div class="p-3 rounded-xl bg-purple-50/80 dark:bg-purple-950/40 border border-purple-200/80 dark:border-purple-800/60 flex items-start gap-2 text-xs text-purple-700 dark:text-purple-300">
            <Sparkles :size="14" class="shrink-0 mt-0.5 text-purple-600 dark:text-purple-400" />
            <div>
              <p class="font-bold">2-Hour Auto-Redeem Guarantee</p>
              <p class="text-[11px] opacity-90 mt-0.5">
                After 2 hours, your reservation will automatically redeem. The fee deposit is 100% credited towards your dining order upon arrival!
              </p>
            </div>
          </div>

          <!-- Interactive Live Simulator Toggle -->
          <div class="pt-2 border-t border-warm-200/60 dark:border-warm-700/60">
            <button
              type="button"
              @click="toggleSimulation"
              class="w-full py-1.5 px-3 rounded-xl bg-white dark:bg-warm-800 border border-warm-200 dark:border-warm-700 text-xs font-medium text-warm-700 dark:text-warm-300 flex items-center justify-between hover:bg-warm-50 dark:hover:bg-warm-750 transition-colors"
            >
              <span class="flex items-center gap-1.5">
                <component :is="isSimulating ? Pause : Play" :size="12" class="text-primary-500" />
                {{ isSimulating ? 'Pause Live 10s Increment Demo' : 'Simulate Live 10s Increments (+5 ETB)' }}
              </span>
              <span class="font-mono font-bold text-primary-600 dark:text-primary-400">
                ETB {{ simCurrentFee.toFixed(2) }}
              </span>
            </button>

            <div v-if="isSimulating || simElapsedSeconds !== 3590" class="mt-2 p-2.5 rounded-xl bg-warm-100/70 dark:bg-warm-800/80 text-[11px] space-y-1">
              <div class="flex items-center justify-between text-warm-600 dark:text-warm-300">
                <span>Simulated Elapsed Time:</span>
                <span class="font-mono font-bold">{{ Math.floor(simElapsedSeconds / 60) }}m {{ simElapsedSeconds % 60 }}s</span>
              </div>
              <div class="flex items-center justify-between">
                <span>Auto-Redeem Status:</span>
                <span v-if="simIsRedeemed" class="badge badge-purple text-[10px] font-bold">Auto-Redeemed at 2h!</span>
                <span v-else class="text-amber-600 font-mono">{{ simCountdownToRedeem }}</span>
              </div>
              <button
                type="button"
                @click="resetSimulation"
                class="text-[10px] text-primary-600 dark:text-primary-400 flex items-center gap-1 underline pt-0.5"
              >
                <RotateCcw :size="10" /> Reset to 59m 50s
              </button>
            </div>
          </div>
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="btn-primary w-full justify-center py-3 text-sm font-bold shadow-md"
        >
          {{ loading ? 'Checking availability & securing table…' : `Confirm & Reserve Table (ETB ${totalFee.toFixed(2)})` }}
        </button>
      </form>
    </div>
  </DefaultLayout>
</template>
