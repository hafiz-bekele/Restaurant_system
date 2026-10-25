<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useUiStore } from '../../stores/ui'
import CircleLoader from './CircleLoader.vue'

const ui = useUiStore()

const visible = computed(() => ui.progress > 0)
const widthStyle = computed(() => ({
  width: `${Math.min(100, Math.max(0, ui.progress))}%`
}))

// Debounce badge so sub-150ms requests don't cause flicker
const showBadge = ref(false)
let badgeTimer: any = null

watch(
  () => ui.isGlobalLoading && !ui.processLoading,
  (loading) => {
    if (loading) {
      if (!badgeTimer) {
        badgeTimer = setTimeout(() => {
          showBadge.value = true
        }, 140)
      }
    } else {
      if (badgeTimer) {
        clearTimeout(badgeTimer)
        badgeTimer = null
      }
      showBadge.value = false
    }
  },
  { immediate: true }
)
</script>

<template>
  <div>
    <!-- Top Progress Line -->
    <div
      v-show="visible"
      class="fixed top-0 left-0 right-0 z-[10000] pointer-events-none h-[3.5px] overflow-hidden"
      aria-hidden="true"
    >
      <div
        class="h-full bg-gradient-to-r from-amber-500 via-primary-500 to-rose-500 shadow-[0_0_12px_rgba(249,115,22,0.8),0_0_4px_rgba(234,108,10,0.6)] transition-all duration-200 ease-out relative"
        :style="widthStyle"
      >
        <!-- Leading light pulse dot at the tip of the progress bar -->
        <div
          class="absolute right-0 top-1/2 -translate-y-1/2 w-4 h-4 rounded-full bg-white/90 blur-[2px] shadow-[0_0_8px_#ffffff]"
        ></div>

        <!-- Moving Shimmer Highlight -->
        <div
          class="absolute inset-0 bg-gradient-to-r from-transparent via-white/40 to-transparent animate-progress-shimmer"
        ></div>
      </div>
    </div>

    <!-- Floating Circular Activity Badge (top right, matching iOS 12-pill circle style) -->
    <Transition
      enter-from-class="opacity-0 translate-y-[-10px] scale-90"
      enter-active-class="transition-all duration-200 ease-out"
      leave-to-class="opacity-0 translate-y-[-10px] scale-90"
      leave-active-class="transition-all duration-150 ease-in"
    >
      <div
        v-if="showBadge"
        class="fixed top-4 right-5 z-[9999] pointer-events-none flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/95 dark:bg-gray-900/95 border border-warm-200/90 dark:border-warm-800 shadow-xl backdrop-blur-md"
        role="status"
        aria-label="Loading in progress"
      >
        <CircleLoader size="sm" color="primary" />
        <span class="text-[11px] font-semibold text-warm-700 dark:text-warm-300 tracking-tight">
          {{ ui.isNavigating ? 'Navigating…' : 'Syncing…' }}
        </span>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
@keyframes progressShimmer {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(200%); }
}

.animate-progress-shimmer {
  animation: progressShimmer 1.4s infinite cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
