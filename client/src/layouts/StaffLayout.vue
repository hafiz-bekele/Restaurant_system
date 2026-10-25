<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  ChefHat,
  UtensilsCrossed,
  CreditCard,
  Truck,
  CalendarCheck,
  LogOut,
  Menu,
  X,
  LayoutDashboard,
  ExternalLink,
  Sun,
  Moon,
  ChevronDown,
  Settings,
  Lock,
  Unlock,
  User
} from 'lucide-vue-next'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import { APP_NAME } from '../config'
import LanguageSwitcher from '../components/common/LanguageSwitcher.vue'

const { t } = useI18n()
const auth   = useAuthStore()
const ui     = useUiStore()
const router = useRouter()
const route  = useRoute()

// ── Responsive & Hover-Expand state ───────────────────────────────────────
const isMobile     = ref(window.innerWidth < 1024)
const sidebarOpen  = ref(window.innerWidth >= 1024) // mobile drawer
const isHovered    = ref(false)                     // desktop hover expand
const isPinned     = ref(false)                     // desktop lock preference
const showUserMenu = ref(false)
const userMenuRef  = ref<HTMLElement | null>(null)

function onSidebarMouseEnter() {
  if (!isMobile.value) {
    isHovered.value = true
  }
}

function onSidebarMouseLeave() {
  if (!isMobile.value) {
    isHovered.value = false
  }
}

function togglePin() {
  isPinned.value = !isPinned.value
  try {
    localStorage.setItem('staff_sidebar_pinned', String(isPinned.value))
  } catch {}
}

function handleResize() {
  const mobile = window.innerWidth < 1024
  if (mobile !== isMobile.value) {
    isMobile.value    = mobile
    sidebarOpen.value = !mobile
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
  try {
    const saved = localStorage.getItem('staff_sidebar_pinned')
    if (saved !== null) {
      isPinned.value = saved === 'true'
    }
  } catch {}
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
  document.removeEventListener('click', handleClickOutside)
})

watch(() => route.path, () => {
  if (isMobile.value) sidebarOpen.value = false
  showUserMenu.value = false
})

const isExpanded = computed(() => {
  if (isMobile.value) return sidebarOpen.value
  return isPinned.value || isHovered.value
})

const showLabels = computed(() => isExpanded.value)

// ── Navigation Tabs with Granular Permission Checks ───────────────────────
const allTabs = computed(() => [
  {
    to: '/staff/kitchen',
    icon: ChefHat,
    label: t('staffLayout.kitchenKds'),
    role: 'kitchen',
    permission: 'kitchen.view_kds',
  },
  {
    to: '/staff/waiter',
    icon: UtensilsCrossed,
    label: t('staffLayout.waiterFloor'),
    role: 'waiter',
    permission: 'orders.view',
  },
  {
    to: '/staff/reception',
    icon: CalendarCheck,
    label: t('staffLayout.reception'),
    role: 'reception',
    permission: 'reception.view_floor',
  },
  {
    to: '/staff/cashier',
    icon: CreditCard,
    label: t('staffLayout.cashierPos'),
    role: 'cashier',
    permission: 'cashier.view_pos',
  },
  {
    to: '/staff/delivery',
    icon: Truck,
    label: t('staffLayout.delivery'),
    role: 'delivery',
    permission: 'orders.view',
  },
])

const visibleTabs = computed(() => {
  return allTabs.value.filter(tab => {
    // Super-admin & admin see everything
    if (auth.hasRole('super-admin') || auth.hasRole('admin')) return true
    // Permission check
    if (tab.permission && auth.hasPermission(tab.permission)) return true
    // Role check fallback
    return auth.hasRole(tab.role)
  })
})

function isActive(to: string) {
  return route.path.startsWith(to)
}

const roleLabel = computed(() => {
  const r = auth.user?.roles?.[0] ?? ''
  if (!r) return 'Staff'
  return r.charAt(0).toUpperCase() + r.slice(1)
})

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="flex h-screen bg-warm-50 dark:bg-[#070709] overflow-hidden relative">
    <!-- Ambient Background Depth Accents -->
    <div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden" aria-hidden="true">
      <div class="absolute -top-[15%] right-[10%] w-[45vw] max-w-[450px] aspect-square rounded-full bg-gradient-to-br from-primary-400/12 via-amber-300/6 to-transparent dark:from-primary-500/8 dark:via-orange-600/4 dark:to-transparent blur-3xl opacity-20"></div>
      <div class="absolute top-[40%] -left-[10%] w-[40vw] max-w-[400px] aspect-square rounded-full bg-gradient-to-tr from-indigo-300/8 via-primary-300/6 to-transparent dark:from-indigo-600/8 dark:via-primary-600/4 dark:to-transparent blur-3xl opacity-15"></div>
    </div>
    <!-- Mobile backdrop -->
    <Transition
      enter-from-class="opacity-0"
      enter-active-class="transition-opacity duration-200"
      leave-to-class="opacity-0"
      leave-active-class="transition-opacity duration-150"
    >
      <div
        v-if="isMobile && sidebarOpen"
        class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
        @click="sidebarOpen = false"
      />
    </Transition>

    <!-- ══════════════════════════════════════════
         STAFF SIDEBAR (Hover-Expandable, White / Black)
    ══════════════════════════════════════════ -->
    <aside
      @mouseenter="onSidebarMouseEnter"
      @mouseleave="onSidebarMouseLeave"
      :class="[
        'flex flex-col z-50 transition-[width,transform] duration-300 ease-in-out will-change-transform select-none',
        'bg-white/95 dark:bg-[#0c0d12]/95 backdrop-blur-md border-r border-warm-200/80 dark:border-white/10 shadow-[4px_0_24px_rgba(0,0,0,0.03)]',
        isMobile
          ? ['fixed inset-y-0 left-0 shadow-2xl', sidebarOpen ? 'w-64 translate-x-0' : 'w-64 -translate-x-full']
          : ['relative shrink-0', isExpanded ? 'w-64 shadow-lg' : 'w-[70px]'],
      ]"
    >
      <!-- Brand row -->
      <div class="flex items-center h-16 px-4 border-b border-white/50 dark:border-white/10 shrink-0 gap-3 justify-between">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-8 h-8 rounded-lg bg-primary-500 flex items-center justify-center shrink-0 shadow-lg shadow-primary-500/30">
            <ChefHat :size="16" class="text-white" />
          </div>

          <div v-if="showLabels" class="flex-1 min-w-0 transition-opacity duration-200">
            <p class="font-heading font-bold text-warm-900 dark:text-white text-sm leading-tight truncate">
              {{ APP_NAME }}
            </p>
            <p class="text-[11px] font-medium text-primary-600 dark:text-primary-400 leading-tight">
              {{ roleLabel }} Portal
            </p>
          </div>
        </div>

        <!-- Desktop Pin Toggle -->
        <button
          v-if="!isMobile && isExpanded"
          @click.stop="togglePin"
          :class="[
            'p-1.5 rounded-lg transition-colors text-xs flex items-center justify-center shrink-0',
            isPinned
              ? 'text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-neutral-900 border border-primary-200 dark:border-neutral-800'
              : 'text-warm-400 dark:text-neutral-500 hover:text-warm-700 dark:hover:text-neutral-300 hover:bg-warm-100 dark:hover:bg-neutral-900'
          ]"
          :title="isPinned ? 'Sidebar pinned open (Click to enable auto-collapse on mouse leave)' : 'Pin sidebar open (Keep open permanently)'"
          aria-label="Pin Sidebar"
        >
          <Lock v-if="isPinned" :size="13" />
          <Unlock v-else :size="13" />
        </button>

        <!-- Close button on mobile -->
        <button
          v-if="isMobile"
          @click="sidebarOpen = false"
          class="p-1.5 rounded-lg text-warm-400 hover:text-warm-900 dark:text-neutral-400 dark:hover:text-white hover:bg-warm-100 dark:hover:bg-neutral-900 transition-colors"
          aria-label="Close Sidebar"
        >
          <X :size="17" />
        </button>
      </div>

      <!-- Nav Items with custom auto-fading scrollbar -->
      <nav class="flex-1 overflow-y-auto overflow-x-hidden py-4 space-y-1 sidebar-scroll">
        <p
          v-if="showLabels"
          class="mb-2 px-5 text-[10px] font-bold uppercase tracking-[0.15em] text-warm-400 dark:text-neutral-500 select-none transition-opacity duration-200"
        >
          Operations & Floor
        </p>
        <div v-else class="mb-2 mx-4 border-t border-warm-200/80 dark:border-neutral-850"></div>

        <RouterLink
          v-for="tab in visibleTabs"
          :key="tab.to"
          :to="tab.to"
          :title="!isExpanded && !isMobile ? tab.label : undefined"
          :class="[
            'relative flex items-center gap-3 mx-2 rounded-xl text-sm font-medium transition-all duration-150 group',
            !isExpanded && !isMobile ? 'px-0 py-2.5 justify-center' : 'px-3 py-2.5',
            isActive(tab.to)
              ? 'bg-primary-500/10 text-primary-600 dark:text-primary-400 font-semibold shadow-xs'
              : 'text-warm-700 dark:text-neutral-300 hover:bg-warm-100/80 dark:hover:bg-neutral-900 hover:text-warm-900 dark:hover:text-white',
          ]"
        >
          <!-- Active bar -->
          <span
            v-if="isActive(tab.to)"
            class="absolute left-0 inset-y-1.5 w-[3px] bg-primary-500 rounded-r-full"
          />

          <component
            :is="tab.icon"
            :size="18"
            :class="[
              'shrink-0 transition-colors',
              isActive(tab.to) ? 'text-primary-500 dark:text-primary-400' : 'text-warm-400 dark:text-neutral-500 group-hover:text-warm-800 dark:group-hover:text-neutral-200',
            ]"
          />

          <span v-if="showLabels" class="truncate transition-opacity duration-200">{{ tab.label }}</span>
        </RouterLink>

        <!-- Admin Access Link for managers/admins -->
        <template v-if="auth.isAdmin">
          <div class="pt-4 mt-4 border-t border-warm-200/80 dark:border-neutral-850">
            <p
              v-if="showLabels"
              class="mb-2 px-5 text-[10px] font-bold uppercase tracking-[0.15em] text-warm-400 dark:text-neutral-500 select-none transition-opacity duration-200"
            >
              Administration
            </p>
            <RouterLink
              to="/admin"
              :class="[
                'relative flex items-center gap-3 mx-2 rounded-xl text-sm font-medium transition-all text-warm-700 dark:text-neutral-300 hover:bg-warm-100/80 dark:hover:bg-neutral-900 hover:text-warm-900 dark:hover:text-white',
                !isExpanded && !isMobile ? 'px-0 py-2.5 justify-center' : 'px-3 py-2.5',
              ]"
            >
              <LayoutDashboard :size="18" class="text-warm-400 dark:text-neutral-500" />
              <span v-if="showLabels" class="truncate transition-opacity duration-200">Admin Control Panel</span>
            </RouterLink>
          </div>
        </template>
      </nav>

      <!-- ── Sidebar Bottom User Card ─────────────────────────────────── -->
      <div class="border-t border-white/50 dark:border-white/10 shrink-0">
        <div v-if="showLabels" class="flex items-center gap-3 px-4 py-3">
          <RouterLink to="/profile" class="flex items-center gap-3 flex-1 min-w-0 hover:opacity-85 transition-opacity">
            <div class="w-8 h-8 rounded-full bg-primary-500/15 border border-primary-500/30 flex items-center justify-center font-bold text-primary-600 dark:text-primary-400 text-sm shrink-0">
              {{ auth.user?.name?.[0]?.toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-semibold text-warm-900 dark:text-white truncate leading-tight">
                {{ auth.user?.name }}
              </p>
              <p class="text-[10px] text-warm-500 dark:text-neutral-400 truncate leading-tight">
                {{ auth.user?.section || auth.user?.email }}
              </p>
            </div>
          </RouterLink>
          <button
            @click="handleLogout"
            class="p-1.5 rounded-lg text-warm-400 dark:text-neutral-500 hover:text-red-500 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/20 transition-colors shrink-0"
            title="Log Out"
            aria-label="Log Out"
          >
            <LogOut :size="15" />
          </button>
        </div>
        <div v-else class="flex justify-center py-3">
          <button
            @click="handleLogout"
            class="p-2 rounded-xl text-warm-400 dark:text-neutral-500 hover:text-red-500 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/20 transition-colors"
            title="Log Out"
            aria-label="Log Out"
          >
            <LogOut :size="16" />
          </button>
        </div>
      </div>
    </aside>

    <!-- ══════════════════════════════════════════
         MAIN CONTENT AREA
    ══════════════════════════════════════════ -->
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
      <!-- Top header bar -->
      <header class="relative z-30 h-14 bg-white/95 dark:bg-[#0c0d12]/95 backdrop-blur-md border-b border-warm-200/80 dark:border-white/10 flex items-center justify-between px-4 sm:px-6 shrink-0 gap-3 shadow-xs">
        <!-- Left: Toggle & Storefront -->
        <div class="flex items-center gap-2">
          <!-- Mobile Hamburger Toggle -->
          <button
            v-if="isMobile"
            @click="sidebarOpen = !sidebarOpen"
            class="min-h-[44px] min-w-[44px] p-2 rounded-xl text-warm-500 hover:text-warm-900 dark:text-neutral-400 dark:hover:text-white hover:bg-warm-100 dark:hover:bg-neutral-900 transition-colors inline-flex items-center justify-center focus:ring-2 focus:ring-primary-500 focus:outline-none"
            aria-label="Toggle Navigation Menu"
            title="Toggle Menu"
          >
            <Menu :size="19" />
          </button>

          <!-- Desktop Sidebar Pin Status Indicator -->
          <button
            v-else
            @click="togglePin"
            class="min-h-[44px] min-w-[44px] p-2 rounded-xl text-warm-500 hover:text-warm-900 dark:text-neutral-400 dark:hover:text-white hover:bg-warm-100 dark:hover:bg-neutral-900 transition-colors inline-flex items-center justify-center focus:ring-2 focus:ring-primary-500 focus:outline-none"
            :title="isPinned ? 'Sidebar is pinned open' : 'Sidebar expands on mouse hover (Click to pin)'"
            :aria-label="isPinned ? 'Unpin Sidebar' : 'Pin Sidebar'"
          >
            <Lock v-if="isPinned" :size="17" class="text-primary-500" />
            <Unlock v-else :size="17" class="text-warm-400" />
          </button>

          <RouterLink
            to="/"
            target="_blank"
            class="hidden sm:inline-flex items-center gap-1.5 text-xs font-medium px-3 py-2 rounded-lg border border-warm-200 dark:border-neutral-800 text-warm-600 dark:text-neutral-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all focus:ring-2 focus:ring-primary-500 focus:outline-none"
            aria-label="Open Public Storefront Website in new tab"
            title="Public Website"
          >
            <ExternalLink :size="13" /> Public Website
          </RouterLink>
        </div>

        <!-- Right Side Corner: Language, Theme, Settings, Profile, Logout -->
        <div class="flex items-center gap-1 sm:gap-2 shrink-0">
          <LanguageSwitcher />

          <button
            @click="ui.toggleDarkMode"
            class="min-h-[44px] min-w-[44px] p-2 rounded-xl text-warm-500 hover:text-warm-900 dark:text-neutral-400 dark:hover:text-white hover:bg-warm-100 dark:hover:bg-neutral-900 transition-colors inline-flex items-center justify-center focus:ring-2 focus:ring-primary-500 focus:outline-none"
            :aria-label="ui.darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
            :title="ui.darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
          >
            <Sun v-if="ui.darkMode" :size="18" class="text-amber-400" />
            <Moon v-else :size="18" class="text-gray-400" />
          </button>

          <!-- Settings Button in Right Corner -->
          <RouterLink
            :to="auth.isAdmin ? '/admin/settings' : '/profile'"
            class="min-h-[44px] min-w-[44px] p-2 rounded-xl text-warm-500 hover:text-warm-900 dark:text-neutral-400 dark:hover:text-white hover:bg-warm-100 dark:hover:bg-neutral-900 transition-colors inline-flex items-center justify-center focus:ring-2 focus:ring-primary-500 focus:outline-none"
            title="Settings & Profile"
            aria-label="Settings and Profile"
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
                    v-if="auth.isAdmin"
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

                <!-- Divider & Logout inside dropdown -->
                <div class="pt-1 mt-1 border-t border-warm-100 dark:border-neutral-850 px-1.5">
                  <button
                    @click="() => { showUserMenu = false; handleLogout(); }"
                    class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-semibold rounded-xl text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors text-left"
                  >
                    <LogOut :size="15" class="shrink-0" />
                    <span>Sign Out</span>
                  </button>
                </div>
              </div>
            </Transition>
          </div>

          <!-- Direct Logout Button in Far Right Corner -->
          <button
            @click="handleLogout"
            class="min-h-[44px] min-w-[44px] p-2 rounded-xl text-warm-500 hover:text-red-500 dark:text-neutral-400 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors shrink-0 inline-flex items-center justify-center focus:ring-2 focus:ring-primary-500 focus:outline-none"
            title="Log Out"
            aria-label="Log Out"
          >
            <LogOut :size="18" />
          </button>
        </div>
      </header>

      <!-- Main content view -->
      <main class="flex-1 overflow-y-auto p-4 sm:p-6 bg-warm-50 dark:bg-[#070709]">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<style scoped>
/* ── Custom auto-fading scrollbar ("close in / close out") ────────────── */
.sidebar-scroll {
  scrollbar-width: thin;
  scrollbar-color: transparent transparent;
  transition: scrollbar-color 0.3s ease;
}

.sidebar-scroll:hover {
  scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
}

:global(.dark) .sidebar-scroll:hover {
  scrollbar-color: rgba(115, 115, 115, 0.5) transparent;
}

/* WebKit custom scrollbar */
.sidebar-scroll::-webkit-scrollbar {
  width: 5px;
}

.sidebar-scroll::-webkit-scrollbar-track {
  background: transparent;
}

.sidebar-scroll::-webkit-scrollbar-thumb {
  background-color: transparent;
  border-radius: 9999px;
  transition: background-color 0.3s ease;
}

.sidebar-scroll:hover::-webkit-scrollbar-thumb {
  background-color: rgba(156, 163, 175, 0.4);
}

:global(.dark) .sidebar-scroll:hover::-webkit-scrollbar-thumb {
  background-color: rgba(115, 115, 115, 0.5);
}

.sidebar-scroll::-webkit-scrollbar-thumb:hover {
  background-color: rgba(249, 115, 22, 0.7);
}
</style>
