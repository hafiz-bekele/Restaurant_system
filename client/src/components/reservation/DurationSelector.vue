<script setup lang="ts">
import { computed } from 'vue'
import { Clock, Check } from '@lucide/vue'

const props = defineProps<{
  modelValue: number
  durations: Array<{ duration_hours: number; price: string | number }>
  surchargePercent: number
  currency?: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: number): void
}>()

const durationLabels: Record<number, { title: string; subtitle: string }> = {
  1:  { title: '1 Hour',   subtitle: 'Quick Dining' },
  2:  { title: '2 Hours',  subtitle: 'Classic Experience' },
  3:  { title: '3 Hours',  subtitle: 'Extended Gathering' },
  6:  { title: '6 Hours',  subtitle: 'Half-Day Event' },
  12: { title: '12 Hours', subtitle: 'Day-Long Celebration' },
  18: { title: '18 Hours', subtitle: 'Overnight Lounge' },
  24: { title: '24 Hours', subtitle: 'Full 24h Private Session' },
}

const options = computed(() => {
  const allowed = [1, 2, 3, 6, 12, 18, 24]
  return allowed.map(hours => {
    const found = props.durations.find(d => Number(d.duration_hours) === hours)
    const base = found ? Number(found.price) : hours * 12.50
    const surcharge = Math.round(base * (props.surchargePercent / 100) * 100) / 100
    const total = base + surcharge

    return {
      hours,
      title: durationLabels[hours]?.title ?? `${hours} Hours`,
      subtitle: durationLabels[hours]?.subtitle ?? '',
      basePrice: base,
      totalPrice: total,
    }
  })
})

function select(hours: number) {
  emit('update:modelValue', hours)
}
</script>

<template>
  <div class="space-y-2.5">
    <label class="block text-xs font-bold uppercase tracking-wider text-warm-600 dark:text-warm-300 flex items-center justify-between">
      <span class="flex items-center gap-1.5">
        <Clock :size="14" class="text-primary-500" />
        Reservation Duration
      </span>
      <span class="text-[11px] text-warm-400 font-normal">Max 24 hours</span>
    </label>

    <div
      role="radiogroup"
      aria-label="Select reservation duration"
      class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2"
    >
      <button
        v-for="opt in options"
        :key="opt.hours"
        type="button"
        role="radio"
        :aria-checked="modelValue === opt.hours"
        @click="select(opt.hours)"
        class="relative flex flex-col items-center justify-between p-3 rounded-2xl border text-center transition-all duration-200 focus:outline-hidden focus:ring-2 focus:ring-primary-500"
        :class="[
          modelValue === opt.hours
            ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-950/20 shadow-sm ring-1 ring-primary-500'
            : 'border-warm-200 dark:border-warm-800 bg-white dark:bg-warm-900 hover:border-warm-300 dark:hover:border-warm-700 hover:bg-warm-50/50 dark:hover:bg-warm-800/40'
        ]"
      >
        <!-- Selection Check Badge -->
        <span
          v-if="modelValue === opt.hours"
          class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-primary-500 text-white flex items-center justify-center shadow-xs"
        >
          <Check :size="12" class="stroke-[3]" />
        </span>

        <span
          class="text-xs font-bold transition-colors"
          :class="modelValue === opt.hours ? 'text-primary-600 dark:text-primary-400 font-extrabold' : 'text-warm-900 dark:text-white'"
        >
          {{ opt.title }}
        </span>

        <span class="text-[10px] text-warm-400 line-clamp-1 mt-0.5">
          {{ opt.subtitle }}
        </span>

        <span
          class="mt-2 text-xs font-mono font-bold px-2 py-0.5 rounded-full"
          :class="modelValue === opt.hours ? 'bg-primary-500 text-white' : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300'"
        >
          {{ (currency || 'ETB') }} {{ opt.totalPrice.toFixed(0) }}
        </span>
      </button>
    </div>
  </div>
</template>
