import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface ToastItem {
  id: number
  message: string
  type: 'success' | 'error' | 'warning'
}

export const useUiStore = defineStore('ui', () => {
  const darkMode = ref<boolean>(localStorage.getItem('darkMode') === 'true')
  const toasts   = ref<ToastItem[]>([])

  function toggleDarkMode() {
    darkMode.value = !darkMode.value
    localStorage.setItem('darkMode', String(darkMode.value))
  }

  function toast(message: string, type: 'success' | 'error' | 'warning' = 'success', duration = 3500) {
    const id = Date.now()
    toasts.value.push({ id, message, type })
    setTimeout(() => removeToast(id), duration)
  }

  function removeToast(id: number) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  return { darkMode, toasts, toggleDarkMode, toast, removeToast }
}, { persist: { pick: ['darkMode'] } })
