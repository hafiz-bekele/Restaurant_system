<script setup lang="ts">
import { computed } from 'vue'
import { Clock, Check } from '@lucide/vue'

const props = defineProps<{
  modelValue: number
  durations?: Array<{ duration_hours: number; price?: string | number }>
  surchargePercent?: number
  currency?: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: number): void
}>()

const durationOptions = [
  { hours: 1, title: '1 Hour',  subtitle: 'Quick Dining / Lunch', tag: 'Quick' },
  { hours: 2, title: '2 Hours', subtitle: 'Standard Dining',       tag: 'Recommended' },
  { hours: 3, title: '3 Hours', subtitle: 'Celebration & Leisure', tag: 'Gathering' },
  { hours: 4, title: '4 Hours', subtitle: 'Private Banquet / VIP', tag: 'Event' },
]

function select(hours: number) {
  emit('update:modelValue', hours)
}
</script>

<template>
  <div class="space-y-2.5">
    <label class="block text-xs font-bold uppercase tracking-wider text-warm-600 dark:text-warm-300 flex items-center justify-between">
      <span class="flex items-center gap-1.5">
        <Clock :size="14" class="text-primary-500" />
        Dining Duration
      </span>
      <span class="text-[11px] text-warm-400 font-normal">Table reserved exclusively for your party</span>
    </label>

    <div
      role="radiogroup"
      aria-label="Select dining duration"
      class="grid grid-cols-2 sm:grid-cols-4 gap-2.5"
    >
      <button
        v-for="opt in durationOptions"
        :key="opt.hours"
        type="button"
        role="radio"
        :aria-checked="modelValue === opt.hours"
        @click="select(opt.hours)"
        class="relative flex flex-col items-center justify-between p-3.5 rounded-2xl border text-center transition-all duration-200 focus:outline-hidden focus:ring-2 focus:ring-primary-500"
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
          class="text-sm font-bold transition-colors"
          :class="modelValue === opt.hours ? 'text-primary-600 dark:text-primary-400 font-extrabold' : 'text-warm-900 dark:text-white'"
        >
          {{ opt.title }}
        </span>

        <span class="text-[11px] text-warm-400 line-clamp-1 mt-0.5">
          {{ opt.subtitle }}
        </span>

        <span
          class="mt-2 text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full transition-colors"
          :class="modelValue === opt.hours
            ? 'bg-primary-500 text-white'
            : opt.tag === 'Recommended'
              ? 'bg-primary-50 dark:bg-primary-950/30 text-primary-600 dark:text-primary-400 font-bold'
              : 'bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400'"
        >
          {{ opt.tag }}
        </span>
      </button>
    </div>
  </div>
</template>
