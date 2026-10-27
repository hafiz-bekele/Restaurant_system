<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  Menu,
  ChevronRight,
  ChevronDown,
  Sun,
  Moon,
  ExternalLink,
  ChefHat,
  User,
  Settings,
  LogOut,
  Search,
  SlidersHorizontal
} from 'lucide-vue-next'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import { APP_NAME } from '../config'
import LanguageSwitcher from '../components/common/LanguageSwitcher.vue'
import AppSidebar from '../components/layout/AppSidebar.vue'

const { t } = useI18n()
const auth = useAuthStore()
const ui = useUiStore()
const router = useRouter()
const route = useRoute()

const isMobile = ref(window.innerWidth < 1024)
const mobileOpen = ref(false)
const showUserMenu = ref(false)
const userMenuRef = ref<HTMLElement | null>(null)
const showPortals = ref(false)

function handleResize() {
  const mobile = window.innerWidth < 1024
  if (mobile !== isMobile.value) {
    isMobile.value = mobile
    if (!mobile) {
      mobileOpen.value = false
    }
  }
}

function handleClickOutside(event: MouseEvent) {
  if (userMenuRef.value && !userMenuRef.value.contains(event.target as Node)) {
    showUserMenu.value = false
  }
}

onMounted(() => {
  window.addEventListener('resize', handleResize)
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
  document.removeEventListener('click', handleClickOutside)
})

watch(() => route.path, () => {
  if (isMobile.value) mobileOpen.value = false
  showUserMenu.value = false
})

const roleLabel = computed(() => {
  const r = auth.user?.roles?.[0] ?? ''
  return r === 'super-admin' ? t('adminLayout.superAdmin') : r.charAt(0).toUpperCase() + r.slice(1)
})

const staffPortals = computed(() => [
  { to: '/staff/kitchen', label: t('staffLayout.kitchenKds') },
  { to: '/staff/waiter', label: t('staffLayout.waiterFloor') },
  { to: '/staff/cashier', label: t('staffLayout.cashierPos') },
  { to: '/staff/reception', label: t('staffLayout.reception') },
  { to: '/staff/delivery', label: t('staffLayout.delivery') },
])

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="flex h-screen bg-warm-50 dark:bg-[#070709] overflow-hidden relative">
    <!-- Skip to Main Content Accessibility Landmark -->
    <a
      href="#main-content"
      class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[999] focus:px-4 focus:py-2 focus:bg-primary-500 focus:text-white focus:rounded-xl focus:shadow-2xl focus:font-semibold text-xs focus:outline-none"
    >
      Skip to main content
    </a>

    <!-- Ambient Subtle Background Depth Glows -->
    <div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden" aria-hidden="true">
      <div class="absolute -top-[15%] right-[10%] w-[45vw] max-w-[450px] aspect-square rounded-full bg-gradient-to-br from-primary-400/10 via-amber-300/5 to-transparent dark:from-primary-500/8 dark:via-orange-600/4 dark:to-transparent blur-3xl opacity-20" />
      <div class="absolute top-[40%] -left-[10%] w-[40vw] max-w-[400px] aspect-square rounded-full bg-gradient-to-tr from-indigo-300/6 via-primary-300/5 to-transparent dark:from-indigo-600/6 dark:via-primary-600/4 dark:to-transparent blur-3xl opacity-15" />
    </div>

    <!-- ══════════════════════════════════════════
         MASTER APP SIDEBAR COMPONENT
    ══════════════════════════════════════════ -->
    <AppSidebar
      v-model:mobileOpen="mobileOpen"
    />

    <!-- ══════════════════════════════════════════
         MAIN CONTENT AREA
    ══════════════════════════════════════════ -->
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

      <!-- Top bar -->
      <header class="relative z-30 h-14 bg-white/95 dark:bg-[#0c0d12]/95 backdrop-blur-md border-b border-warm-200/80 dark:border-white/10 flex items-center justify-between px-4 sm:px-6 shrink-0 gap-3 shadow-xs">

        <!-- Left Header Controls -->
        <div class="flex items-center gap-2.5">
          <!-- Mobile Hamburger Toggle -->
          <button
            v-if="isMobile"
            @click="mobileOpen = true"
            class="min-h-[44px] min-w-[44px] p-2 rounded-xl text-warm-500 hover:text-warm-900 dark:text-neutral-400 dark:hover:text-white hover:bg-warm-100 dark:hover:bg-neutral-900 transition-colors inline-flex items-center justify-center focus:ring-2 focus:ring-primary-500 focus:outline-none cursor-pointer"
            :aria-label="$t('adminLayout.toggleMenu')"
            title="Open navigation menu"
          >
            <Menu :size="19" />
          </button>

          <!-- Storefront Public Site Link -->
          <RouterLink
            to="/"
            target="_blank"
            class="hidden sm:flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg border border-warm-200 dark:border-neutral-800 text-warm-600 dark:text-neutral-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all"
          >
            <ExternalLink :size="12" /> {{ $t('adminLayout.storefront') }}
          </RouterLink>

          <!-- Staff portals dropdown -->
          <div v-if="auth.isStaff" class="relative z-50">
            <button
              @click="showPortals = !showPortals"
              class="hidden sm:flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg border border-warm-200 dark:border-neutral-800 text-warm-600 dark:text-neutral-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all cursor-pointer"
            >
              <ChefHat :size="12" />
              {{ $t('adminLayout.staffPortals') }}
              <ChevronRight :size="11" :class="['transition-transform duration-200', showPortals ? 'rotate-90' : '']" />
            </button>

            <Transition
              enter-from-class="opacity-0 scale-95 -translate-y-1"
              enter-active-class="transition-all duration-150 origin-top-left"
              leave-to-class="opacity-0 scale-95 -translate-y-1"
              leave-active-class="transition-all duration-100 origin-top-left"
            >
              <div
                v-if="showPortals"
                class="absolute top-full left-0 mt-2 w-48 bg-white/95 dark:bg-[#151722]/95 backdrop-blur-lg rounded-xl border border-warm-200/90 dark:border-white/10 shadow-2xl z-50 py-1.5 overflow-hidden"
              >
                <RouterLink
                  v-for="p in staffPortals"
                  :key="p.to"
                  :to="p.to"
                  @click="showPortals = false"
                  class="flex items-center gap-2.5 px-4 py-2 text-sm text-warm-700 dark:text-neutral-300 hover:bg-primary-50 dark:hover:bg-neutral-900 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                >
                  <ChevronRight :size="11" class="text-primary-400 shrink-0" /> {{ p.label }}
                </RouterLink>
              </div>
            </Transition>
          </div>
        </div>

        <!-- Right Side Corner: Language, Theme, Settings, Profile -->
        <div class="flex items-center gap-1.5 sm:gap-2.5 shrink-0">

          <!-- Language Switcher -->
          <LanguageSwitcher />

          <!-- Theme Toggle -->
          <button
            @click="ui.toggleDarkMode"
            class="min-h-[44px] min-w-[44px] p-2 rounded-xl text-warm-500 hover:text-warm-900 dark:text-neutral-400 dark:hover:text-white hover:bg-warm-100 dark:hover:bg-neutral-900 transition-colors inline-flex items-center justify-center focus:ring-2 focus:ring-primary-500 focus:outline-none cursor-pointer"
            :aria-label="ui.darkMode ? $t('adminLayout.lightMode') : $t('adminLayout.darkMode')"
            :title="ui.darkMode ? $t('adminLayout.lightMode') : $t('adminLayout.darkMode')"
          >
            <Sun v-if="ui.darkMode" :size="18" class="text-amber-400" />
            <Moon v-else :size="18" class="text-gray-400" />
          </button>

          <!-- System Settings Link -->
          <RouterLink
            to="/admin/settings"
            class="min-h-[44px] min-w-[44px] p-2 rounded-xl text-warm-500 hover:text-warm-900 dark:text-neutral-400 dark:hover:text-white hover:bg-warm-100 dark:hover:bg-neutral-900 transition-colors inline-flex items-center justify-center focus:ring-2 focus:ring-primary-500 focus:outline-none"
            title="System Settings"
            aria-label="System Settings"
          >
            <Settings :size="18" />
          </RouterLink>

          <!-- User Profile Dropdown Menu in Right Corner -->
          <div class="relative z-50" ref="userMenuRef">
            <button
              @click="showUserMenu = !showUserMenu"
              class="flex items-center gap-2 p-1 pl-1.5 pr-2.5 rounded-full border border-warm-200 dark:border-neutral-800 hover:border-primary-400 dark:hover:border-neutral-700 bg-warm-50/50 dark:bg-neutral-900 transition-all cursor-pointer"
              title="User Account Menu"
              aria-label="User Account"
            >
              <div class="w-7 h-7 rounded-full bg-primary-500/20 border border-primary-500/30 flex items-center justify-center font-bold text-primary-600 dark:text-primary-400 text-xs shrink-0">
                {{ auth.user?.name?.[0]?.toUpperCase() }}
              </div>
              <div class="hidden sm:block text-left">
                <p class="text-xs font-semibold text-warm-800 dark:text-neutral-200 leading-tight">
                  {{ auth.user?.name?.split(' ')[0] }}
                </p>
                <p class="text-[10px] text-primary-600 dark:text-primary-400 font-medium leading-tight capitalize">
                  {{ roleLabel }}
                </p>
              </div>
              <ChevronDown :size="13" class="text-warm-400 dark:text-neutral-500 transition-transform duration-200" :class="showUserMenu ? 'rotate-180' : ''" />
            </button>

            <!-- Floating Profile Menu -->
            <Transition
              enter-from-class="opacity-0 scale-95 -translate-y-1"
              enter-active-class="transition-all duration-150 origin-top-right"
              leave-to-class="opacity-0 scale-95 -translate-y-1"
              leave-active-class="transition-all duration-100 origin-top-right"
            >
              <div
                v-if="showUserMenu"
                class="absolute right-0 mt-2 w-56 bg-white/95 dark:bg-[#151722]/95 backdrop-blur-lg rounded-2xl border border-warm-200/90 dark:border-white/10 shadow-2xl z-50 py-2 overflow-hidden"
              >
                <!-- User Info Header -->
                <div class="px-4 py-2.5 border-b border-warm-100 dark:border-neutral-850">
                  <p class="text-xs font-bold text-warm-900 dark:text-white truncate">{{ auth.user?.name }}</p>
                  <p class="text-[11px] text-warm-400 truncate">{{ auth.user?.email }}</p>
                  <div class="mt-1.5 flex items-center gap-1.5">
                    <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-full bg-primary-500/10 text-primary-600 dark:text-primary-400 border border-primary-500/20">
                      {{ roleLabel }}
                    </span>
                    <span v-if="auth.user?.on_duty" class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> On Duty
                    </span>
                  </div>
                </div>

                <!-- Navigation Links -->
                <div class="p-1.5 space-y-0.5">
                  <RouterLink
                    to="/profile"
                    @click="showUserMenu = false"
                    class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium rounded-xl text-warm-700 dark:text-neutral-300 hover:bg-warm-100 dark:hover:bg-neutral-900 hover:text-warm-900 dark:hover:text-white transition-colors"
                  >
                    <User :size="15" class="text-primary-500 shrink-0" />
                    <span>My Profile</span>
                  </RouterLink>

                  <RouterLink
                    to="/admin/settings"
                    @click="showUserMenu = false"
                    class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium rounded-xl text-warm-700 dark:text-neutral-300 hover:bg-warm-100 dark:hover:bg-neutral-900 hover:text-warm-900 dark:hover:text-white transition-colors"
                  >
                    <Settings :size="15" class="text-warm-400 dark:text-neutral-500 shrink-0" />
                    <span>System Settings</span>
                  </RouterLink>

                  <RouterLink
                    to="/"
                    target="_blank"
                    @click="showUserMenu = false"
                    class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium rounded-xl text-warm-700 dark:text-neutral-300 hover:bg-warm-100 dark:hover:bg-neutral-900 hover:text-warm-900 dark:hover:text-white transition-colors"
                  >
                    <ExternalLink :size="15" class="text-warm-400 dark:text-neutral-500 shrink-0" />
                    <span>Storefront Website</span>
                  </RouterLink>
                </div>

                <!-- Sign Out inside dropdown -->
                <div class="pt-1 mt-1 border-t border-warm-100 dark:border-neutral-850 px-1.5">
                  <button
                    @click="() => { showUserMenu = false; logout(); }"
                    class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold rounded-xl text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors text-left cursor-pointer"
                  >
                    <LogOut :size="15" class="shrink-0" />
                    <span>Sign Out</span>
                  </button>
                </div>
              </div>
            </Transition>
          </div>
        </div>
      </header>

      <!-- Main Page Content Landmark with aria-live support -->
      <main id="main-content" class="flex-1 overflow-y-auto p-4 sm:p-6 bg-warm-50 dark:bg-[#070709] focus:outline-none">
        <RouterView />
      </main>
    </div>

  </div>
</template>
