<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    variant?: 'circle' | 'ring'
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
    color?: 'blue' | 'primary' | 'white' | 'amber' | 'warm' | 'current'
    label?: string
    labelPosition?: 'right' | 'bottom'
    inline?: boolean
    center?: boolean
  }>(),
  {
    variant: 'circle',
    size: 'md',
    color: 'primary',
    label: '',
    labelPosition: 'right',
    inline: false,
    center: false
  }
)

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'xs':  return 'w-3.5 h-3.5'
    case 'sm':  return 'w-4 h-4'
    case 'md':  return 'w-6 h-6'
    case 'lg':  return 'w-8 h-8'
    case 'xl':  return 'w-12 h-12'
    case '2xl': return 'w-16 h-16'
    default:    return 'w-6 h-6'
  }
})

const colorClasses = computed(() => {
  switch (props.color) {
    case 'blue':    return 'text-blue-600 dark:text-blue-400'
    case 'primary': return 'text-primary-600 dark:text-primary-400'
    case 'white':   return 'text-white'
    case 'amber':   return 'text-amber-500'
    case 'warm':    return 'text-warm-500 dark:text-warm-400'
    case 'current': return 'text-current'
    default:        return 'text-primary-600 dark:text-primary-400'
  }
})

// 12 spokes for radial circular loading style (matching iOS/capsule radial design)
const spokes = [
  { transform: 'rotate(0 50 50)',   delay: '-1.1s' },
  { transform: 'rotate(30 50 50)',  delay: '-1.0s' },
  { transform: 'rotate(60 50 50)',  delay: '-0.9s' },
  { transform: 'rotate(90 50 50)',  delay: '-0.8s' },
  { transform: 'rotate(120 50 50)', delay: '-0.7s' },
  { transform: 'rotate(150 50 50)', delay: '-0.6s' },
  { transform: 'rotate(180 50 50)', delay: '-0.5s' },
  { transform: 'rotate(210 50 50)', delay: '-0.4s' },
  { transform: 'rotate(240 50 50)', delay: '-0.3s' },
  { transform: 'rotate(270 50 50)', delay: '-0.2s' },
  { transform: 'rotate(300 50 50)', delay: '-0.1s' },
  { transform: 'rotate(330 50 50)', delay: '0s' },
]
</script>

<template>
  <div
    :class="[
      center ? 'w-full py-8 flex flex-col items-center justify-center' : (inline ? 'inline-flex' : 'flex'),
      labelPosition === 'bottom' ? 'flex-col items-center justify-center gap-2' : 'items-center justify-center gap-2.5',
      'transition-opacity'
    ]"
    role="status"
    aria-label="Loading..."
  >
    <!-- 12-Pill Radial Circle Loader (Default Style) -->
    <svg
      v-if="variant === 'circle'"
      :class="['circle-loader-svg shrink-0', sizeClasses, colorClasses]"
      viewBox="0 0 100 100"
      xmlns="http://www.w3.org/2000/svg"
    >
      <rect
        v-for="(spoke, index) in spokes"
        :key="index"
        x="46.5"
        y="8"
        width="7"
        height="20"
        rx="3.5"
        ry="3.5"
        fill="currentColor"
        :transform="spoke.transform"
        :style="{
          animation: 'circleSpokeFade 1.2s linear infinite',
          animationDelay: spoke.delay
        }"
      />
    </svg>

    <!-- Ring Spinner Variant -->
    <svg
      v-else
      :class="['animate-spin shrink-0', sizeClasses, colorClasses]"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle
        class="opacity-25"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        stroke-width="3.5"
      ></circle>
      <path
        class="opacity-90"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
      ></path>
    </svg>

    <span
      v-if="label"
      :class="[
        'font-medium select-none',
        props.size === 'xs' || props.size === 'sm' ? 'text-xs' : 'text-sm',
        props.color === 'white' ? 'text-white' : 'text-warm-700 dark:text-warm-200'
      ]"
    >
      {{ label }}
    </span>
  </div>
</template>

<style scoped>
@keyframes circleSpokeFade {
  0% {
    opacity: 1;
  }
  100% {
    opacity: 0.12;
  }
}
</style>
