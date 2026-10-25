<script setup lang="ts">
import { ref, computed } from 'vue'
import { Star } from '@lucide/vue'

const props = withDefaults(
  defineProps<{
    rating?: number
    reviewsCount?: number
    interactive?: boolean
    size?: 'xs' | 'sm' | 'md' | 'lg'
    showValue?: boolean
    showCount?: boolean
    compact?: boolean
  }>(),
  {
    rating: 0,
    reviewsCount: 0,
    interactive: false,
    size: 'sm',
    showValue: true,
    showCount: true,
    compact: false,
  }
)

const emit = defineEmits<{
  (e: 'rate', stars: number): void
}>()

const hoverRating = ref(0)
const justRated = ref<number | null>(null)

const starSize = computed(() => {
  switch (props.size) {
    case 'xs': return 12
    case 'sm': return 14
    case 'md': return 18
    case 'lg': return 24
    default:   return 14
  }
})

const effectiveRating = computed(() => {
  if (hoverRating.value > 0) return hoverRating.value
  if (justRated.value !== null) return justRated.value
  return Number(props.rating) || 0
})

function onStarHover(stars: number) {
  if (!props.interactive) return
  hoverRating.value = stars
}

function onMouseLeave() {
  if (!props.interactive) return
  hoverRating.value = 0
}

function onStarClick(stars: number) {
  if (!props.interactive) return
  justRated.value = stars
  emit('rate', stars)
}
</script>

<template>
  <div
    class="inline-flex items-center gap-1.5 select-none"
    @mouseleave="onMouseLeave"
    :title="interactive ? 'Click to rate (1–5 stars)' : undefined"
  >
    <!-- 5 Stars Group -->
    <div class="flex items-center gap-0.5">
      <button
        v-for="s in 5"
        :key="s"
        type="button"
        :disabled="!interactive"
        @mouseenter="onStarHover(s)"
        @click.stop.prevent="onStarClick(s)"
        :class="[
          'transition-all duration-150 relative p-0.5 rounded-sm',
          interactive ? 'cursor-pointer hover:scale-125 focus:outline-none' : 'cursor-default pointer-events-none',
        ]"
        :aria-label="interactive ? `Rate ${s} stars` : `${s} of 5 stars`"
      >
        <Star
          :size="starSize"
          :class="[
            'transition-colors duration-150',
            s <= Math.round(effectiveRating)
              ? 'text-amber-400 fill-amber-400 drop-shadow-[0_1px_2px_rgba(251,191,36,0.4)]'
              : 'text-warm-300 dark:text-warm-700 hover:text-amber-300',
          ]"
        />
      </button>
    </div>

    <!-- Rating Value & Count -->
    <div
      v-if="!compact && (showValue || showCount)"
      class="flex items-center gap-1 text-xs"
    >
      <span
        v-if="showValue && (effectiveRating > 0 || interactive)"
        :class="[
          'font-semibold transition-colors',
          effectiveRating > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-warm-400'
        ]"
      >
        {{ effectiveRating > 0 ? effectiveRating.toFixed(1) : (interactive ? 'Rate' : '') }}
      </span>

      <span
        v-if="showCount && reviewsCount > 0"
        class="text-warm-400 text-[11px] font-normal"
      >
        ({{ reviewsCount }})
      </span>
    </div>
  </div>
</template>
