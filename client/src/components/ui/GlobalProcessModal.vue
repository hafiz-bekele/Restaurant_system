<script setup lang="ts">
import { useUiStore } from '../../stores/ui'
import CircleLoader from './CircleLoader.vue'
import { ChefHat } from '@lucide/vue'

const ui = useUiStore()
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-250 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="ui.processLoading"
        class="fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-black/40 dark:bg-black/60 backdrop-blur-md"
        role="dialog"
        aria-modal="true"
        aria-label="Process loading"
      >
        <div
          class="relative w-full max-w-sm rounded-3xl p-8 bg-white/95 dark:bg-[#0c0d12]/95 backdrop-blur-lg border border-warm-200/90 dark:border-white/10 shadow-[0_24px_64px_rgba(0,0,0,0.2)] flex flex-col items-center text-center overflow-hidden"
        >
          <!-- Background Ambient Glow -->
          <div
            class="absolute -top-12 -right-12 w-36 h-36 bg-blue-500/20 rounded-full blur-2xl pointer-events-none"
          ></div>
          <div
            class="absolute -bottom-12 -left-12 w-36 h-36 bg-primary-500/20 rounded-full blur-2xl pointer-events-none"
          ></div>

          <!-- 12-Pill Radial Circle Loader with Center Logo -->
          <div class="relative w-24 h-24 flex items-center justify-center mb-5">
            <CircleLoader size="2xl" color="blue" />
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
              <div
                class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shadow-xs"
              >
                <ChefHat :size="18" class="animate-bounce-gentle" />
              </div>
            </div>
          </div>

          <!-- Process Message -->
          <h3
            class="font-heading font-bold text-lg text-warm-900 dark:text-white mb-1.5 tracking-tight"
          >
            {{ ui.processMessage || 'Processing Request' }}
          </h3>

          <p class="text-xs text-warm-500 dark:text-warm-400 max-w-[240px] leading-relaxed mb-6">
            Please wait while we complete this action. Do not refresh or navigate away.
          </p>

          <!-- Indeterminate micro progress line -->
          <div
            class="w-full h-1.5 bg-warm-100 dark:bg-warm-800 rounded-full overflow-hidden relative"
          >
            <div
              class="absolute top-0 bottom-0 left-0 bg-gradient-to-r from-blue-500 via-primary-500 to-primary-600 rounded-full w-1/3 animate-indeterminate-bar shadow-[0_0_8px_rgba(37,99,235,0.6)]"
            ></div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
@keyframes bounceGentle {
  0%, 100% { transform: translateY(0); }
  50%      { transform: translateY(-3px); }
}

@keyframes indeterminateBar {
  0% {
    left: -35%;
    width: 35%;
  }
  50% {
    left: 40%;
    width: 45%;
  }
  100% {
    left: 105%;
    width: 25%;
  }
}

.animate-bounce-gentle {
  animation: bounceGentle 2s ease-in-out infinite;
}

.animate-indeterminate-bar {
  animation: indeterminateBar 1.6s infinite ease-in-out;
}
</style>
