<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
    color?: 'blue' | 'primary' | 'white' | 'amber' | 'warm' | 'current'
    label?: string
    labelPosition?: 'right' | 'bottom'
    center?: boolean
    inline?: boolean
  }>(),
  {
    size: 'md',
    color: 'primary',
    label: '',
    labelPosition: 'right',
    center: false,
    inline: false
  }
)

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'xs':  return 'w-4 h-4'
    case 'sm':  return 'w-5 h-5'
    case 'md':  return 'w-7 h-7'
    case 'lg':  return 'w-10 h-10'
    case 'xl':  return 'w-14 h-14'
    case '2xl': return 'w-20 h-20'
    default:    return 'w-7 h-7'
  }
})

const colorClasses = computed(() => {
  switch (props.color) {
    case 'blue':    return 'text-blue-600 dark:text-blue-400'
    case 'primary': return 'text-primary-600 dark:text-primary-400'
    case 'amber':   return 'text-amber-500'
    case 'white':   return 'text-white'
    case 'warm':    return 'text-warm-500 dark:text-warm-400'
    case 'current': return 'text-current'
    default:        return 'text-primary-600 dark:text-primary-400'
  }
})

const spokes = [
  { transform: 'rotate(0 50 50)',   delay: '-1.1s', opacity: 1.0 },
  { transform: 'rotate(30 50 50)',  delay: '-1.0s', opacity: 0.91 },
  { transform: 'rotate(60 50 50)',  delay: '-0.9s', opacity: 0.83 },
  { transform: 'rotate(90 50 50)',  delay: '-0.8s', opacity: 0.75 },
  { transform: 'rotate(120 50 50)', delay: '-0.7s', opacity: 0.67 },
  { transform: 'rotate(150 50 50)', delay: '-0.6s', opacity: 0.58 },
  { transform: 'rotate(180 50 50)', delay: '-0.5s', opacity: 0.50 },
  { transform: 'rotate(210 50 50)', delay: '-0.4s', opacity: 0.41 },
  { transform: 'rotate(240 50 50)', delay: '-0.3s', opacity: 0.33 },
  { transform: 'rotate(270 50 50)', delay: '-0.2s', opacity: 0.25 },
  { transform: 'rotate(300 50 50)', delay: '-0.1s', opacity: 0.16 },
  { transform: 'rotate(330 50 50)', delay: '0s',    opacity: 0.08 },
]
</script>

<template>
  <div
    :class="[
      center ? 'w-full py-8 flex flex-col items-center justify-center' : (inline ? 'inline-flex' : 'flex'),
      labelPosition === 'bottom' ? 'flex-col items-center justify-center gap-2.5' : 'items-center justify-center gap-3',
      'transition-opacity'
    ]"
    role="status"
    aria-label="Loading..."
  >
    <svg
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
