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
  ChevronRight
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

// ── Responsive state ──────────────────────────────────────────────────────
const isMobile    = ref(window.innerWidth < 1024)
const sidebarOpen = ref(window.innerWidth >= 1024)
const collapsed   = ref(false)

function handleResize() {
  const mobile = window.innerWidth < 1024
  if (mobile !== isMobile.value) {
    isMobile.value    = mobile
    sidebarOpen.value = !mobile
    collapsed.value   = false
  }
}
onMounted(()  => window.addEventListener('resize', handleResize))
onUnmounted(() => window.removeEventListener('resize', handleResize))
watch(() => route.path, () => { if (isMobile.value) sidebarOpen.value = false })

const showLabels = computed(() => isMobile.value ? true : !collapsed.value)

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
  <div class="flex h-screen bg-warm-50 dark:bg-[#070709] overflow-hidden">
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
         STAFF SIDEBAR (White in Light, Black in Dark)
    ══════════════════════════════════════════ -->
    <aside
      :class="[
        'flex flex-col z-50 transition-[width,transform] duration-300 ease-in-out will-change-transform',
        'bg-white dark:bg-black border-r border-warm-200/90 dark:border-neutral-850 shadow-xs',
        isMobile
          ? ['fixed inset-y-0 left-0 shadow-2xl', sidebarOpen ? 'w-64 translate-x-0' : 'w-64 -translate-x-full']
          : ['relative shrink-0', collapsed ? 'w-[68px]' : 'w-60'],
      ]"
    >
      <!-- Brand row -->
      <div class="flex items-center h-16 px-4 border-b border-warm-200/90 dark:border-neutral-850 shrink-0 gap-3">
        <!-- Logo mark -->
        <div class="w-8 h-8 rounded-lg bg-primary-500 flex items-center justify-center shrink-0 shadow-lg shadow-primary-500/30">
          <ChefHat :size="16" class="text-white" />
        </div>

        <div v-if="showLabels" class="flex-1 min-w-0">
          <p class="font-heading font-bold text-warm-900 dark:text-white text-sm leading-tight truncate">
            {{ APP_NAME }}
          </p>
          <p class="text-[11px] font-medium text-primary-600 dark:text-primary-400 leading-tight">
            {{ roleLabel }} Portal
          </p>
        </div>

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

      <!-- Nav Items -->
      <nav class="flex-1 overflow-y-auto overflow-x-hidden py-4 space-y-1 scrollbar-hide">
        <p
          v-if="showLabels"
          class="mb-2 px-5 text-[10px] font-bold uppercase tracking-[0.15em] text-warm-400 dark:text-neutral-500 select-none"
        >
          Operations & Floor
        </p>
        <div v-else class="mb-2 mx-4 border-t border-warm-200/80 dark:border-neutral-850"></div>

        <RouterLink
          v-for="tab in visibleTabs"
          :key="tab.to"
          :to="tab.to"
          :title="collapsed && !isMobile ? tab.label : undefined"
          :class="[
            'relative flex items-center gap-3 mx-2 rounded-xl text-sm font-medium transition-all duration-150 group',
            collapsed && !isMobile ? 'px-0 py-2.5 justify-center' : 'px-3 py-2.5',
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

          <span v-if="showLabels" class="truncate">{{ tab.label }}</span>
        </RouterLink>

        <!-- Admin Access Link for managers/admins -->
        <template v-if="auth.isAdmin">
          <div class="pt-4 mt-4 border-t border-warm-200/80 dark:border-neutral-850">
            <p
              v-if="showLabels"
              class="mb-2 px-5 text-[10px] font-bold uppercase tracking-[0.15em] text-warm-400 dark:text-neutral-500 select-none"
            >
              Administration
            </p>
            <RouterLink
              to="/admin"
              :class="[
                'relative flex items-center gap-3 mx-2 rounded-xl text-sm font-medium transition-all text-warm-700 dark:text-neutral-300 hover:bg-warm-100/80 dark:hover:bg-neutral-900 hover:text-warm-900 dark:hover:text-white',
                collapsed && !isMobile ? 'px-0 py-2.5 justify-center' : 'px-3 py-2.5',
              ]"
            >
              <LayoutDashboard :size="18" class="text-warm-400 dark:text-neutral-500" />
              <span v-if="showLabels" class="truncate">Admin Control Panel</span>
            </RouterLink>
          </div>
        </template>
      </nav>

      <!-- ── User + logout row ────────────────────────────────────────── -->
      <div class="border-t border-warm-200/90 dark:border-neutral-850 shrink-0">
        <div v-if="showLabels" class="flex items-center gap-3 px-4 py-3">
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
         MAIN AREA
    ══════════════════════════════════════════ -->
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
      <!-- Top header bar -->
      <header class="h-14 bg-white dark:bg-black border-b border-warm-200/90 dark:border-neutral-850 flex items-center justify-between px-4 sm:px-5 shrink-0 gap-3">
        <!-- Left: Toggle & Storefront -->
        <div class="flex items-center gap-2">
          <button
            @click="isMobile ? (sidebarOpen = !sidebarOpen) : (collapsed = !collapsed)"
            class="p-2 rounded-xl text-warm-500 hover:text-warm-900 dark:text-neutral-400 dark:hover:text-white hover:bg-warm-100 dark:hover:bg-neutral-900 transition-colors"
            :aria-label="isMobile ? 'Toggle Menu' : (collapsed ? 'Expand' : 'Collapse')"
          >
            <Menu :size="19" />
          </button>

          <RouterLink
            to="/"
            target="_blank"
            class="hidden sm:flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg border border-warm-200 dark:border-neutral-800 text-warm-600 dark:text-neutral-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all"
          >
            <ExternalLink :size="12" /> Public Website
          </RouterLink>
        </div>

        <!-- Right: Language & Theme Controls -->
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
          <LanguageSwitcher />

          <button
            @click="ui.toggleDarkMode"
            class="p-2 rounded-xl text-warm-500 hover:text-warm-900 dark:text-neutral-400 dark:hover:text-white hover:bg-warm-100 dark:hover:bg-neutral-900 transition-colors"
            :aria-label="ui.darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
          >
            <Sun v-if="ui.darkMode" :size="18" class="text-amber-400" />
            <Moon v-else :size="18" class="text-gray-400" />
          </button>

          <!-- User Pill -->
          <div class="hidden sm:flex items-center gap-2.5 pl-3 border-l border-warm-200 dark:border-neutral-800">
            <div class="w-7 h-7 rounded-full bg-primary-500/20 border border-primary-500/25 flex items-center justify-center font-bold text-primary-600 dark:text-primary-400 text-xs shrink-0">
              {{ auth.user?.name?.[0]?.toUpperCase() }}
            </div>
            <div>
              <p class="text-xs font-semibold text-warm-800 dark:text-neutral-200 leading-tight">
                {{ auth.user?.name?.split(' ')[0] }}
              </p>
              <p class="text-[10px] text-primary-600 dark:text-primary-400 font-medium leading-tight capitalize">
                {{ roleLabel }}
              </p>
            </div>
          </div>
        </div>
      </header>

      <!-- Main content view -->
      <main class="flex-1 overflow-y-auto p-4 sm:p-6 bg-warm-50/70 dark:bg-[#070709]">
        <RouterView />
      </main>
    </div>
  </div>
</template>
