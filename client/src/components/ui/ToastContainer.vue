<script setup>
import { useUiStore } from '../../stores/ui'
import { CheckCircle, XCircle, AlertCircle, X } from '@lucide/vue'

const ui = useUiStore()

const icons = { success: CheckCircle, error: XCircle, warning: AlertCircle }
const colors = {
  success: 'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-200',
  error:   'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-200',
  warning: 'bg-yellow-50 border-yellow-200 text-yellow-800 dark:bg-yellow-900/30 dark:border-yellow-700 dark:text-yellow-200',
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
