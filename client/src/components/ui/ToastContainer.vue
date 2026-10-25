<script setup>
import { useUiStore } from '../../stores/ui'
import { CheckCircle, XCircle, AlertCircle, X } from '@lucide/vue'

const ui = useUiStore()

const icons = { success: CheckCircle, error: XCircle, warning: AlertCircle }
const colors = {
  success: 'bg-white/95 dark:bg-[#0c1810]/95 border-emerald-500/30 text-emerald-950 dark:text-emerald-100 backdrop-blur-md shadow-[0_12px_36px_rgba(16,185,129,0.12)]',
  error:   'bg-white/95 dark:bg-[#1a0a0a]/95 border-red-500/30 text-red-950 dark:text-red-100 backdrop-blur-md shadow-[0_12px_36px_rgba(239,68,68,0.12)]',
  warning: 'bg-white/95 dark:bg-[#1a1405]/95 border-amber-500/30 text-amber-950 dark:text-amber-100 backdrop-blur-md shadow-[0_12px_36px_rgba(245,158,11,0.12)]',
  info:    'bg-white/95 dark:bg-[#0a121a]/95 border-blue-500/30 text-blue-950 dark:text-blue-100 backdrop-blur-md shadow-[0_12px_36px_rgba(59,130,246,0.12)]',
}
</script>

<template>
  <div class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-3 max-w-sm w-full pointer-events-none">
    <TransitionGroup
      enter-from-class="opacity-0 translate-y-4 scale-95"
      enter-active-class="transition-all duration-300"
      leave-to-class="opacity-0 translate-y-4 scale-95"
      leave-active-class="transition-all duration-200"
    >
      <div
        v-for="toast in ui.toasts"
        :key="toast.id"
        :class="['flex items-start gap-3 p-4 rounded-2xl border shadow-float pointer-events-auto', colors[toast.type]]"
      >
        <component :is="icons[toast.type] ?? CheckCircle" :size="20" class="shrink-0 mt-0.5" />
        <p class="text-sm font-medium flex-1">{{ toast.message }}</p>
        <button @click="ui.removeToast(toast.id)" class="shrink-0 opacity-60 hover:opacity-100">
          <X :size="16" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
