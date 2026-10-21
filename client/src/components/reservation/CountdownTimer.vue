<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Clock, Timer, CheckCircle2, AlertCircle } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    startAt?: string | null
    endAt?: string | null
    status?: string
    serverOffset?: number
  }>(),
  {
    startAt: null,
    endAt: null,
    status: 'confirmed',
    serverOffset: 0,
  }
)

const currentTimestamp = ref(Date.now() + props.serverOffset)
let timerId: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  timerId = setInterval(() => {
    currentTimestamp.value = Date.now() + (props.serverOffset || 0)
  }, 1000)
})

onUnmounted(() => {
  if (timerId) clearInterval(timerId)
})

const startMs = computed(() => (props.startAt ? new Date(props.startAt).getTime() : 0))
const endMs = computed(() => (props.endAt ? new Date(props.endAt).getTime() : 0))

// Phase: 'upcoming' | 'active' | 'ended'
const phase = computed<'upcoming' | 'active' | 'ended'>(() => {
  if (props.status === 'expired' || props.status === 'cancelled') {
    return 'ended'
  }
  if (!startMs.value || !endMs.value) return 'ended'

  if (currentTimestamp.value < startMs.value) {
    return 'upcoming'
  }
  if (currentTimestamp.value >= endMs.value) {
    return 'ended'
  }
  return 'active'
})

// Remaining seconds until start (if upcoming) or until end (if active)
const diffSeconds = computed(() => {
  if (phase.value === 'upcoming') {
    return Math.max(0, Math.floor((startMs.value - currentTimestamp.value) / 1000))
  }
  if (phase.value === 'active') {
    return Math.max(0, Math.floor((endMs.value - currentTimestamp.value) / 1000))
  }
  return 0
})

const days = computed(() => Math.floor(diffSeconds.value / 86400))
const hours = computed(() => Math.floor((diffSeconds.value % 86400) / 3600))
const minutes = computed(() => Math.floor((diffSeconds.value % 3600) / 60))
const seconds = computed(() => diffSeconds.value % 60)

const formattedParts = computed(() => ({
  days: String(days.value).padStart(2, '0'),
  hours: String(hours.value).padStart(2, '0'),
  minutes: String(minutes.value).padStart(2, '0'),
  seconds: String(seconds.value).padStart(2, '0'),
}))

// Percentage of the reservation window spent (0 to 100)
const progressPercent = computed(() => {
  if (phase.value === 'upcoming') return 0
  if (phase.value === 'ended') return 100
  const total = endMs.value - startMs.value
  if (total <= 0) return 100
  const elapsed = currentTimestamp.value - startMs.value
  return Math.min(100, Math.max(0, Math.round((elapsed / total) * 100)))
})
</script>

<template>
  <div class="rounded-2xl border border-warm-200 dark:border-warm-800 bg-white/80 dark:bg-warm-900/80 p-5 shadow-sm space-y-4">
    <!-- Header status banner -->
    <div class="flex items-center justify-between pb-3 border-b border-warm-100 dark:border-warm-800/80">
      <div class="flex items-center gap-2">
        <div
          :class="[
            'w-8 h-8 rounded-xl flex items-center justify-center',
            phase === 'active'
              ? 'bg-primary-500/10 text-primary-600 dark:text-primary-400'
              : phase === 'upcoming'
                ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                : 'bg-warm-100 dark:bg-warm-800 text-warm-500'
          ]"
        >
          <Timer v-if="phase === 'active'" class="w-4 h-4 animate-pulse" />
          <Clock v-else-if="phase === 'upcoming'" class="w-4 h-4" />
          <CheckCircle2 v-else class="w-4 h-4" />
        </div>
        <div>
          <h4 class="text-xs font-bold uppercase tracking-wider text-warm-900 dark:text-white">
            <template v-if="phase === 'active'">Live Dining Window Active</template>
            <template v-else-if="phase === 'upcoming'">Upcoming Reservation Window</template>
            <template v-else>Reservation Concluded</template>
          </h4>
          <p class="text-[11px] text-warm-500 dark:text-warm-400">
            <template v-if="phase === 'active'">Counting down your remaining paid duration</template>
            <template v-else-if="phase === 'upcoming'">Timer activates automatically at your reserved start time</template>
            <template v-else>Table has been released automatically</template>
          </p>
        </div>
      </div>

      <span
        :class="[
          'px-2.5 py-1 rounded-full text-[11px] font-semibold border flex items-center gap-1.5',
          phase === 'active'
            ? 'bg-primary-500/10 border-primary-500/30 text-primary-700 dark:text-primary-300'
            : phase === 'upcoming'
              ? 'bg-blue-500/10 border-blue-500/30 text-blue-700 dark:text-blue-300'
              : 'bg-warm-100 dark:bg-warm-800 border-warm-200 dark:border-warm-700 text-warm-600 dark:text-warm-400'
        ]"
      >
        <span
          v-if="phase === 'active'"
          class="w-1.5 h-1.5 rounded-full bg-primary-500 animate-ping"
        />
        {{ phase === 'active' ? 'Live Remaining' : phase === 'upcoming' ? 'Starts In' : 'Ended' }}
      </span>
    </div>

    <!-- Active/Upcoming Digital Countdown Grid -->
    <div v-if="phase !== 'ended'" class="grid grid-cols-4 gap-2 sm:gap-3 text-center">
      <!-- Days -->
      <div class="rounded-xl bg-warm-50 dark:bg-warm-800/60 border border-warm-200/60 dark:border-warm-700/60 p-2.5 sm:p-3">
        <div class="text-xl sm:text-2xl font-black font-mono tracking-tight text-warm-900 dark:text-white">
          {{ formattedParts.days }}
        </div>
        <div class="text-[10px] uppercase font-bold tracking-wider text-warm-500 dark:text-warm-400 mt-0.5">
          Days
        </div>
      </div>

      <!-- Hours -->
      <div class="rounded-xl bg-warm-50 dark:bg-warm-800/60 border border-warm-200/60 dark:border-warm-700/60 p-2.5 sm:p-3">
        <div class="text-xl sm:text-2xl font-black font-mono tracking-tight text-warm-900 dark:text-white">
          {{ formattedParts.hours }}
        </div>
        <div class="text-[10px] uppercase font-bold tracking-wider text-warm-500 dark:text-warm-400 mt-0.5">
          Hours
        </div>
      </div>

      <!-- Minutes -->
      <div class="rounded-xl bg-warm-50 dark:bg-warm-800/60 border border-warm-200/60 dark:border-warm-700/60 p-2.5 sm:p-3">
        <div class="text-xl sm:text-2xl font-black font-mono tracking-tight text-warm-900 dark:text-white">
          {{ formattedParts.minutes }}
        </div>
        <div class="text-[10px] uppercase font-bold tracking-wider text-warm-500 dark:text-warm-400 mt-0.5">
          Mins
        </div>
      </div>

      <!-- Seconds -->
      <div class="rounded-xl bg-primary-500/10 dark:bg-primary-500/20 border border-primary-500/30 p-2.5 sm:p-3">
        <div class="text-xl sm:text-2xl font-black font-mono tracking-tight text-primary-600 dark:text-primary-400">
          {{ formattedParts.seconds }}
        </div>
        <div class="text-[10px] uppercase font-bold tracking-wider text-primary-700 dark:text-primary-300 mt-0.5">
          Secs
        </div>
      </div>
    </div>

    <!-- Ended State UI -->
    <div
      v-else
      class="rounded-xl bg-warm-50 dark:bg-warm-800/50 border border-warm-200 dark:border-warm-700 p-4 text-center space-y-1"
    >
      <div class="text-xs font-semibold text-warm-700 dark:text-warm-300">
        This reservation window has reached its end time.
      </div>
      <p class="text-[11px] text-warm-500 dark:text-warm-400">
        Thank you for dining with us! The table has been automatically made available for other guests.
      </p>
    </div>

    <!-- Progress bar for active window -->
    <div v-if="phase === 'active'" class="space-y-1.5 pt-1">
      <div class="flex items-center justify-between text-[11px] text-warm-500 dark:text-warm-400">
        <span>Elapsed Dining Time</span>
        <span class="font-mono font-medium">{{ progressPercent }}% completed</span>
      </div>
      <div class="h-2 w-full bg-warm-100 dark:bg-warm-800 rounded-full overflow-hidden">
        <div
          class="h-full bg-gradient-to-r from-primary-500 to-amber-500 transition-all duration-1000 ease-linear rounded-full"
          :style="{ width: `${progressPercent}%` }"
        />
      </div>
    </div>

    <div class="text-[11px] text-warm-400 dark:text-warm-500 text-center flex items-center justify-center gap-1.5">
      <Clock class="w-3.5 h-3.5" />
      <span>Accurately synchronized with restaurant server time (anti-clock-drift).</span>
    </div>
  </div>
</template>
