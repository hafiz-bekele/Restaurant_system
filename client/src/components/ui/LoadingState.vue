<script setup lang="ts">
import { computed } from 'vue'
import CircleLoader from './CircleLoader.vue'
import TableSkeleton from './TableSkeleton.vue'
import CardSkeleton from './CardSkeleton.vue'
import DishCardSkeleton from './DishCardSkeleton.vue'
import RoomCardSkeleton from './RoomCardSkeleton.vue'
import { AlertCircle, RefreshCw, Inbox } from '@lucide/vue'

interface Props {
  loading?: boolean
  error?: string | Error | null
  empty?: boolean
  emptyTitle?: string
  emptyDescription?: string
  emptyIcon?: any
  skeletonType?: 'cards' | 'table' | 'dishes' | 'rooms' | 'spinner' | 'custom'
  skeletonCount?: number
  tableColumns?: number
  loadingText?: string
  retryLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  error: null,
  empty: false,
  emptyTitle: 'No items found',
  emptyDescription: 'There is no data to display right now.',
  emptyIcon: null,
  skeletonType: 'spinner',
  skeletonCount: 6,
  tableColumns: 5,
  loadingText: 'Loading content...',
  retryLabel: 'Try Again',
})

const emit = defineEmits<{
  (e: 'retry'): void
}>()

const errorMessage = computed(() => {
  if (!props.error) return ''
  if (typeof props.error === 'string') return props.error
  return props.error.message || 'An unexpected error occurred while loading data.'
})
</script>

<template>
  <div class="w-full">
    <!-- 1. LOADING STATE -->
    <div v-if="loading" role="status" aria-live="polite">
      <span class="sr-only">{{ loadingText }}</span>

      <!-- Custom skeleton slot -->
      <slot v-if="skeletonType === 'custom'" name="skeleton">
        <div class="glass-card p-12 flex flex-col items-center justify-center gap-3 text-center border border-white/50 dark:border-white/10 shadow-md">
          <CircleLoader size="lg" color="primary" />
          <p class="text-xs font-semibold text-warm-500 dark:text-warm-400">{{ loadingText }}</p>
        </div>
      </slot>

      <!-- Dishes skeleton grid -->
      <div
        v-else-if="skeletonType === 'dishes'"
        class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6"
      >
        <DishCardSkeleton v-for="i in skeletonCount" :key="i" />
      </div>

      <!-- Rooms skeleton grid -->
      <div
        v-else-if="skeletonType === 'rooms'"
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
      >
        <RoomCardSkeleton v-for="i in skeletonCount" :key="i" />
      </div>

      <!-- Generic Cards skeleton grid -->
      <div
        v-else-if="skeletonType === 'cards'"
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5"
      >
        <CardSkeleton v-for="i in skeletonCount" :key="i" />
      </div>

      <!-- Table skeleton -->
      <TableSkeleton
        v-else-if="skeletonType === 'table'"
        :rows="skeletonCount"
        :cols="tableColumns"
        :label="loadingText"
      />

      <!-- Spinner centered container -->
      <div
        v-else
        class="glass-card p-12 flex flex-col items-center justify-center gap-3.5 text-center border border-white/50 dark:border-white/10 shadow-lg max-w-sm mx-auto"
      >
        <CircleLoader size="lg" color="primary" />
        <p class="text-xs font-semibold text-warm-600 dark:text-warm-300">{{ loadingText }}</p>
      </div>
    </div>

    <!-- 2. ERROR STATE -->
    <div
      v-else-if="error"
      class="glass-card p-8 md:p-12 text-center max-w-lg mx-auto border border-red-300 dark:border-red-900/60 bg-red-50/50 dark:bg-red-950/25 shadow-lg backdrop-blur-md"
      role="alert"
    >
      <div class="w-12 h-12 rounded-2xl bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 flex items-center justify-center mx-auto mb-3 shadow-xs">
        <AlertCircle :size="24" />
      </div>
      <h3 class="font-heading font-bold text-base text-red-900 dark:text-red-200 mb-1">
        Unable to Load Data
      </h3>
      <p class="text-xs text-red-700 dark:text-red-300/80 mb-5 leading-relaxed">
        {{ errorMessage }}
      </p>
      <button
        type="button"
        @click="emit('retry')"
        class="btn-primary inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold shadow-md active:scale-95 transition-all cursor-pointer mx-auto"
      >
        <RefreshCw :size="13" />
        <span>{{ retryLabel }}</span>
      </button>
    </div>

    <!-- 3. EMPTY STATE -->
    <div
      v-else-if="empty"
      class="glass-card p-12 text-center max-w-md mx-auto border border-white/50 dark:border-white/10 shadow-md backdrop-blur-md"
    >
      <slot name="empty">
        <div class="w-14 h-14 rounded-2xl bg-warm-100 dark:bg-warm-800 text-warm-400 dark:text-warm-500 flex items-center justify-center mx-auto mb-3">
          <component :is="emptyIcon || Inbox" :size="28" />
        </div>
        <h3 class="font-heading font-bold text-base text-warm-900 dark:text-white mb-1">
          {{ emptyTitle }}
        </h3>
        <p class="text-xs text-warm-500 dark:text-warm-400 leading-relaxed mb-4">
          {{ emptyDescription }}
        </p>
        <slot name="empty-action" />
      </slot>
    </div>

    <!-- 4. DATA LOADED / DEFAULT SLOT -->
    <slot v-else />
  </div>
</template>
