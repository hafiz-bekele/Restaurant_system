import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'

export interface ToastItem {
  id: number
  message: string
  type: 'success' | 'error' | 'warning' | 'info'
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
    document.body.style.backgroundColor = '#030712' // gray-950
  } else {
    root.classList.remove('dark')
    root.style.colorScheme = 'light'
    document.body.style.backgroundColor = '#fafaf9' // warm-50
  }
}

export const useUiStore = defineStore('ui', () => {
  const darkMode = ref<boolean>(getInitialDarkMode())
  const toasts   = ref<ToastItem[]>([])

  // ── Process & Loading States ──────────────────────────────────────────────
  const activeRequests  = ref<number>(0)
  const isNavigating    = ref<boolean>(false)
  const processLoading  = ref<boolean>(false)
  const processMessage  = ref<string>('')
  const progress        = ref<number>(0)
  let trickleTimer: any = null
  let processSafetyTimer: any = null
  let requestWatchdogTimer: any = null
  let navigationWatchdogTimer: any = null

  const isGlobalLoading = computed(() => activeRequests.value > 0 || isNavigating.value || processLoading.value)

  // Apply immediately on store initialization
  syncDocumentTheme(darkMode.value)

  // Watch for changes and sync to document and localStorage
  watch(darkMode, (newVal) => {
    syncDocumentTheme(newVal)
    localStorage.setItem('darkMode', String(newVal))
  })

  // ── Top Progress Bar Trickle Animation ────────────────────────────────────
  function startProgress() {
    if (progress.value === 0) {
      progress.value = 22
    }
    if (!trickleTimer) {
      trickleTimer = setInterval(() => {
        if (progress.value < 88) {
          // Asymptotically slow down as it approaches 90%
          const step = Math.max(1, (90 - progress.value) * 0.12)
          progress.value = Math.min(88, progress.value + step)
        }
      }, 150)
    }
  }

  function finishProgress() {
    if (activeRequests.value > 0 || isNavigating.value) {
      return // Still other requests running
    }
    if (trickleTimer) {
      clearInterval(trickleTimer)
      trickleTimer = null
    }
    progress.value = 100
    setTimeout(() => {
      if (activeRequests.value === 0 && !isNavigating.value) {
        progress.value = 0
      }
    }, 280)
  }

  // ── Request Handlers with Watchdog ────────────────────────────────────────
  function resetRequestWatchdog() {
    if (requestWatchdogTimer) clearTimeout(requestWatchdogTimer)
    if (activeRequests.value > 0) {
      requestWatchdogTimer = setTimeout(() => {
        // If requests hung or were dropped without response, auto-clear
        activeRequests.value = 0
        finishProgress()
      }, 15000)
    }
  }

  function startRequest() {
    activeRequests.value++
    startProgress()
    resetRequestWatchdog()
  }

  function finishRequest() {
    if (activeRequests.value > 0) {
      activeRequests.value--
    }
    resetRequestWatchdog()
    if (activeRequests.value === 0 && !isNavigating.value) {
      finishProgress()
    }
  }

  // ── Router Navigation Handlers with Safety ────────────────────────────────
  function startNavigation() {
    isNavigating.value = true
    // If a modal process was open when user navigates, safely close it
    if (processLoading.value) {
      stopProcess()
    }
    startProgress()

    // Safety watchdog: never allow stuck navigation indicator
    if (navigationWatchdogTimer) clearTimeout(navigationWatchdogTimer)
    navigationWatchdogTimer = setTimeout(() => {
      if (isNavigating.value) {
        finishNavigation()
      }
    }, 10000)
  }

  function finishNavigation() {
    isNavigating.value = false
    if (navigationWatchdogTimer) {
      clearTimeout(navigationWatchdogTimer)
      navigationWatchdogTimer = null
    }
    if (activeRequests.value === 0) {
      finishProgress()
    }
  }

  // ── Full-Screen Blocking Process Modal ────────────────────────────────────
  function startProcess(message = 'Processing...') {
    processLoading.value = true
    processMessage.value = message
    startProgress()

    // Safety fallback: auto-clear after 20s if network hung
    if (processSafetyTimer) clearTimeout(processSafetyTimer)
    processSafetyTimer = setTimeout(() => {
      stopProcess()
    }, 20000)
  }

  function stopProcess() {
    processLoading.value = false
    processMessage.value = ''
    if (processSafetyTimer) {
      clearTimeout(processSafetyTimer)
      processSafetyTimer = null
    }
    if (activeRequests.value === 0 && !isNavigating.value) {
      finishProgress()
    }
  }

  function resetAllLoading() {
    activeRequests.value = 0
    isNavigating.value = false
    processLoading.value = false
    processMessage.value = ''
    if (trickleTimer) clearInterval(trickleTimer)
    trickleTimer = null
    progress.value = 0
  }

  function toggleDarkMode() {
    darkMode.value = !darkMode.value
  }

  function setDarkMode(val: boolean) {
    darkMode.value = val
  }

  function toast(message: string, type: 'success' | 'error' | 'warning' | 'info' = 'success', duration = 3500) {
    const id = Date.now()
    toasts.value.push({ id, message, type })
    setTimeout(() => removeToast(id), duration)
  }

  function removeToast(id: number) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  return {
    darkMode,
    toasts,
    activeRequests,
    isNavigating,
    processLoading,
    processMessage,
    progress,
    isGlobalLoading,
    startProgress,
    finishProgress,
    startRequest,
    finishRequest,
    startNavigation,
    finishNavigation,
    startProcess,
    stopProcess,
    resetAllLoading,
    toggleDarkMode,
    setDarkMode,
    toast,
    removeToast
  }
}, { persist: { pick: ['darkMode'] } })
