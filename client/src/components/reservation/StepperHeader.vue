<script setup lang="ts">
import { computed } from 'vue'
import { Calendar, Utensils, CreditCard, CheckCircle2, Check } from '@lucide/vue'

const props = defineProps<{
  currentStep: number
  canGoToStep2: boolean
  canGoToStep3: boolean
}>()

const emit = defineEmits<{
  (e: 'step-click', step: number): void
}>()

const steps = [
  { step: 1, title: 'Details & Time', icon: Calendar },
  { step: 2, title: 'Select Table',  icon: Utensils },
  { step: 3, title: 'Review & Pay',  icon: CreditCard },
  { step: 4, title: 'Confirmation',  icon: CheckCircle2 },
]

function handleStepClick(targetStep: number) {
  if (targetStep >= props.currentStep) return
  emit('step-click', targetStep)
}
</script>

<template>
  <nav aria-label="Reservation Progress" class="w-full">
    <ol class="flex items-center justify-between w-full relative">
      <!-- Background Connecting Line -->
      <div class="absolute left-6 right-6 top-5 h-0.5 bg-warm-200 dark:bg-warm-800 -z-0" />
      <div
        class="absolute left-6 top-5 h-0.5 bg-primary-500 transition-all duration-500 -z-0"
        :style="{ width: `${((currentStep - 1) / (steps.length - 1)) * 100}%` }"
      />

      <li
        v-for="s in steps"
        :key="s.step"
        class="relative z-10 flex flex-col items-center group cursor-default"
        :class="s.step < currentStep ? 'cursor-pointer' : ''"
        @click="handleStepClick(s.step)"
      >
        <!-- Step Bubble -->
        <button
          type="button"
          :disabled="s.step > currentStep"
          class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all duration-300 focus:outline-hidden focus:ring-2 focus:ring-primary-400 focus:ring-offset-2 dark:focus:ring-offset-warm-900"
          :class="[
            s.step < currentStep
              ? 'bg-primary-500 text-white shadow-md shadow-primary-500/20'
              : s.step === currentStep
                ? 'bg-primary-500 text-white ring-4 ring-primary-100 dark:ring-primary-950/60 shadow-lg shadow-primary-500/30'
                : 'bg-warm-100 dark:bg-warm-800 text-warm-400 border border-warm-200 dark:border-warm-700'
          ]"
        >
          <Check v-if="s.step < currentStep" :size="18" class="stroke-[2.5]" />
          <component v-else :is="s.icon" :size="18" />
        </button>

        <!-- Step Title -->
        <span
          class="mt-2 text-xs font-semibold tracking-wide transition-colors"
          :class="[
            s.step === currentStep
              ? 'text-primary-600 dark:text-primary-400 font-bold'
              : s.step < currentStep
                ? 'text-warm-800 dark:text-warm-200 group-hover:text-primary-500'
                : 'text-warm-400 dark:text-warm-500'
          ]"
        >
          <span class="hidden sm:inline">Step {{ s.step }}: </span>{{ s.title }}
        </span>
      </li>
    </ol>
  </nav>
</template>
