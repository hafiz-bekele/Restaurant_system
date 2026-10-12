import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export interface ToastItem {
  id: number
  message: string
  type: 'success' | 'error' | 'warning'
}

function getInitialDarkMode(): boolean {
  if (typeof window === 'undefined') return false
  const saved = localStorage.getItem('darkMode')
  if (saved !== null) {
    return saved === 'true'
  }
  return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
}

function syncDocumentTheme(isDark: boolean) {
  if (typeof document === 'undefined') return
  const root = document.documentElement
  if (isDark) {
    root.classList.add('dark')
    root.style.colorScheme = 'dark'
  } else {
    root.classList.remove('dark')
    root.style.colorScheme = 'light'
  }
}

export const useUiStore = defineStore('ui', () => {
  const darkMode = ref<boolean>(getInitialDarkMode())
  const toasts   = ref<ToastItem[]>([])

  // Apply immediately on store initialization
  syncDocumentTheme(darkMode.value)

  // Watch for changes and sync to document and localStorage
  watch(darkMode, (newVal) => {
    syncDocumentTheme(newVal)
    localStorage.setItem('darkMode', String(newVal))
  })

  function toggleDarkMode() {
    darkMode.value = !darkMode.value
  }

  function setDarkMode(val: boolean) {
    darkMode.value = val
  }

  function toast(message: string, type: 'success' | 'error' | 'warning' = 'success', duration = 3500) {
    const id = Date.now()
    toasts.value.push({ id, message, type })
    setTimeout(() => removeToast(id), duration)
  }

  function removeToast(id: number) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  return { darkMode, toasts, toggleDarkMode, setDarkMode, toast, removeToast }
}, { persist: { pick: ['darkMode'] } })
