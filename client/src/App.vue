<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, RouterView } from 'vue-router'
import { useUiStore } from './stores/ui'
import { useAuthStore } from './stores/auth'
import ToastContainer from './components/ui/ToastContainer.vue'
import GlobalProgressBar from './components/ui/GlobalProgressBar.vue'
import GlobalProcessModal from './components/ui/GlobalProcessModal.vue'
import InitialAppLoader from './components/ui/InitialAppLoader.vue'
import CircleLoader from './components/ui/CircleLoader.vue'

const router = useRouter()
const ui = useUiStore()
const auth = useAuthStore()

const appLoaderRef = ref<InstanceType<typeof InitialAppLoader> | null>(null)
const initError = ref<string | null>(null)

async function initializeApplication() {
  initError.value = null
  try {
    // 1. Wait for router to resolve initial matched route, chunk, and beforeEach guards
    await router.isReady()

    // 2. If token exists, verify auth resolution without blocking permanently
    if (auth.token && !auth.user) {
      try {
        await Promise.race([
          auth.fetchUser(),
          new Promise((_, reject) => setTimeout(() => reject(new Error('Auth verification timeout')), 4500))
        ])
      } catch (err) {
        console.warn('Initial auth verification resolved with note:', err)
      }
    }

    // 3. Dismiss full-screen preloader smoothly
    appLoaderRef.value?.dismiss()
  } catch (err: any) {
    console.error('App initialization error:', err)
    initError.value = err?.message || 'Unable to connect to service. Check your connection.'
    // Safety auto-dismiss after 4s so user is never trapped
    setTimeout(() => {
      appLoaderRef.value?.dismiss()
    }, 4000)
  }
}

onMounted(() => {
  initializeApplication()
})
</script>

<template>
  <!-- Screen Reader Live Region for Page Navigation & Status announcements -->
  <div class="sr-only" role="status" aria-live="polite">
    {{ ui.isNavigating ? 'Navigating to page...' : (ui.isGlobalLoading ? 'Processing request...' : '') }}
  </div>

  <!-- Initial Mount App Loader with smooth fade-out and error recovery -->
  <InitialAppLoader
    ref="appLoaderRef"
    :error="initError"
    @retry="initializeApplication"
  />

  <!-- Global Top Progress Bar for all API and Page transitions -->
  <GlobalProgressBar />

  <!-- Full-Screen Glassmorphic Process Modal for Heavy Operations -->
  <GlobalProcessModal />

  <RouterView v-slot="{ Component, route }">
    <Transition name="page" mode="out-in">
      <Suspense>
        <template #default>
          <component :is="Component" :key="route.path" />
        </template>
        <template #fallback>
          <!-- Elegant Glassmorphic Route Transition Loader -->
          <div
            class="min-h-[60vh] flex flex-col items-center justify-center p-8 select-none"
            role="status"
            aria-label="Loading page..."
          >
            <div class="glass-card p-8 flex flex-col items-center justify-center gap-3.5 text-center max-w-xs w-full shadow-lg border border-white/40 dark:border-white/10">
              <CircleLoader size="lg" color="primary" />
              <div class="space-y-1">
                <p class="text-xs font-semibold text-warm-800 dark:text-warm-100">
                  {{ ui.isNavigating ? 'Navigating to page…' : 'Loading content…' }}
                </p>
                <p class="text-[11px] text-warm-400">Please wait a moment</p>
              </div>
            </div>
          </div>
        </template>
      </Suspense>
    </Transition>
  </RouterView>

  <ToastContainer />
</template>
