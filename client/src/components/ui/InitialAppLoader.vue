<script setup lang="ts">
import { ref } from 'vue'
import CircleLoader from './CircleLoader.vue'

const props = withDefaults(
  defineProps<{
    brandName?: string
    subtitle?: string
    error?: string | null
  }>(),
  {
    brandName: 'Taste & Stay',
    subtitle: 'Culinary Excellence & Boutique Living',
    error: null,
  }
)

const emit = defineEmits<{
  (e: 'retry'): void
  (e: 'dismiss'): void
}>()

const visible = ref(true)

function dismiss() {
  visible.value = false
  emit('dismiss')
}

defineExpose({ dismiss })
</script>

<template>
  <Transition
    enter-active-class="transition-opacity duration-200"
    leave-active-class="transition-all duration-350 ease-out"
    leave-from-class="opacity-100 scale-100"
    leave-to-class="opacity-0 scale-[1.02] pointer-events-none"
  >
    <div
      v-if="visible"
      class="fixed inset-0 z-[99999] flex flex-col items-center justify-center bg-warm-50/95 dark:bg-[#030712]/95 backdrop-blur-xl select-none"
      role="status"
      aria-label="Application loading"
    >
      <!-- Ambient Backlight Glow -->
      <div
        class="absolute w-80 h-80 rounded-full bg-gradient-to-tr from-amber-500/20 via-primary-500/20 to-orange-600/15 blur-3xl pointer-events-none"
        aria-hidden="true"
      ></div>

      <!-- Center Brand Content -->
      <div class="relative z-10 flex flex-col items-center text-center px-6 max-w-sm">
        <!-- Brand Icon with Glow -->
        <div class="relative mb-5">
          <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-amber-500 via-primary-500 to-orange-600 flex items-center justify-center shadow-xl shadow-primary-500/30">
            <!-- Chef Hat SVG -->
            <svg class="w-9 h-9 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"/>
              <line x1="6" y1="17" x2="18" y2="17"/>
            </svg>
          </div>

          <!-- Pulsing Ring -->
          <div class="absolute -inset-2 rounded-3xl border border-primary-500/30 animate-ping opacity-30 pointer-events-none"></div>
        </div>

        <!-- Brand Name -->
        <h1 class="font-heading font-extrabold text-2xl md:text-3xl tracking-tight text-warm-900 dark:text-white mb-1.5">
          {{ brandName }}
        </h1>
        <p class="text-xs font-medium text-warm-500 dark:text-warm-400 mb-6">
          {{ subtitle }}
        </p>

        <!-- Normal State: Circular Spinner & Progress Bar -->
        <template v-if="!error">
          <div class="mb-4">
            <CircleLoader size="lg" color="primary" />
          </div>

          <!-- Shimmering Progress Indicator -->
          <div class="w-36 h-1 rounded-full bg-warm-200 dark:bg-warm-800 overflow-hidden relative">
            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-primary-500 to-transparent animate-loader-sweep"></div>
          </div>
        </template>

        <!-- Error Recovery Card: Never Traps the User -->
        <template v-else>
          <div class="glass-card p-4 rounded-2xl border border-red-300 dark:border-red-900/60 bg-red-50/40 dark:bg-red-950/20 text-center w-full mb-3">
            <p class="text-xs font-semibold text-red-700 dark:text-red-300 mb-1">
              Initialization Notice
            </p>
            <p class="text-[11px] text-warm-600 dark:text-warm-400 leading-relaxed mb-3">
              {{ error }}
            </p>
            <div class="flex items-center justify-center gap-2">
              <button
                type="button"
                @click="emit('retry')"
                class="btn-primary text-xs py-1.5 px-3 rounded-lg shadow-sm font-semibold"
              >
                Retry
              </button>
              <button
                type="button"
                @click="dismiss"
                class="btn-outline text-xs py-1.5 px-3 rounded-lg font-semibold"
              >
                Continue Anyway
              </button>
            </div>
          </div>
        </template>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
@keyframes loaderSweep {
  0%   { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

.animate-loader-sweep {
  animation: loaderSweep 1.2s infinite ease-in-out;
}

@media (prefers-reduced-motion: reduce) {
  .animate-loader-sweep {
    animation: none;
  }
}
</style>
