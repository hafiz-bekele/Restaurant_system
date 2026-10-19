<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  LayoutDashboard, UtensilsCrossed, ShoppingBag, BarChart2, Settings,
  Menu, X, LogOut, ChefHat, Users, Grid3x3, Calendar, Tag, Package,
  Shield, AlertTriangle, Truck, BedDouble, Building2, ExternalLink,
  Star, ChevronRight, Sun, Moon, CreditCard,
} from '@lucide/vue'
import { useAuthStore } from '../stores/auth'
import { useUiStore }   from '../stores/ui'
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

// ── Role checks ───────────────────────────────────────────────────────────
const isManager    = computed(() => auth.hasRole('manager') && !auth.hasRole('admin') && !auth.hasRole('super-admin'))
const isSuperAdmin = computed(() => auth.hasRole('super-admin'))

const roleLabel = computed(() => {
  const r = auth.user?.roles?.[0] ?? ''
  return r === 'super-admin' ? t('adminLayout.superAdmin') : r.charAt(0).toUpperCase() + r.slice(1)
})

// ── Nav ───────────────────────────────────────────────────────────────────
const nav = computed(() => {
  const all = [
    {
      group: t('adminLayout.groups.operations'),
      items: [
        { to: '/admin',              icon: LayoutDashboard, label: t('adminLayout.nav.dashboard') },
        { to: '/admin/orders',       icon: ShoppingBag,     label: t('adminLayout.nav.orders') },
        { to: '/staff/cashier',      icon: CreditCard,      label: t('staffLayout.cashierPos') },
        { to: '/admin/reservations', icon: Calendar,        label: t('adminLayout.nav.reservations') },
        { to: '/admin/tables',       icon: Grid3x3,         label: t('adminLayout.nav.tables') },
        { to: '/admin/rooms',        icon: BedDouble,       label: t('adminLayout.nav.rooms') },
      ],
    },
    {
      group: t('adminLayout.groups.catalogue'),
      items: [
        { to: '/admin/menu',      icon: UtensilsCrossed, label: t('adminLayout.nav.menu') },
        { to: '/admin/inventory', icon: Package,         label: t('adminLayout.nav.inventory') },
        { to: '/admin/coupons',   icon: Tag,             label: t('adminLayout.nav.coupons') },
      ],
    },
    {
      group: t('adminLayout.groups.guests'),
      items: [
        { to: '/admin/reviews',    icon: Star,          label: t('adminLayout.nav.reviews') },
        { to: '/admin/complaints', icon: AlertTriangle, label: t('adminLayout.nav.complaints') },
      ],
    },
    {
      group: t('adminLayout.groups.logistics'),
      items: [
        { to: '/admin/delivery-zones', icon: Truck, label: t('adminLayout.nav.deliveryZones') },
      ],
    },
    {
      group: t('adminLayout.groups.administration'),
      items: [
        { to: '/admin/users',    icon: Users,     label: t('adminLayout.nav.users') },
        { to: '/admin/reports',  icon: BarChart2, label: t('adminLayout.nav.reports') },
        { to: '/admin/hotels',   icon: Building2, label: t('adminLayout.nav.hotels'),  superAdminOnly: true },
        { to: '/admin/audit',    icon: Shield,    label: t('adminLayout.nav.audit'),   adminOnly: true },
        { to: '/admin/settings', icon: Settings,  label: t('adminLayout.nav.settings'), adminOnly: true },
      ],
    },
  ]
  return all.map(g => ({
    ...g,
    items: g.items.filter(item => {
      if (item.superAdminOnly) return isSuperAdmin.value
      if (item.adminOnly)      return !isManager.value
      return true
    }),
  })).filter(g => g.items.length)
})

function isActive(to) {
  return to === '/admin'
    ? route.path === '/admin'
    : route.path === to || route.path.startsWith(to + '/')
}

// ── Staff portals ─────────────────────────────────────────────────────────
const staffPortals = computed(() => [
  { to: '/staff/kitchen',   label: t('staffLayout.kitchenKds') },
  { to: '/staff/waiter',    label: t('staffLayout.waiterFloor') },
  { to: '/staff/cashier',   label: t('staffLayout.cashierPos') },
  { to: '/staff/reception', label: t('staffLayout.reception') },
  { to: '/staff/delivery',  label: t('staffLayout.delivery') },
])
const showPortals = ref(false)

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="flex h-screen bg-warm-50 dark:bg-[#0f1117] overflow-hidden">

    <!-- Mobile backdrop -->
    <Transition
      enter-from-class="opacity-0"  enter-active-class="transition-opacity duration-200"
      leave-to-class="opacity-0"    leave-active-class="transition-opacity duration-150"
    >
      <div
        v-if="isMobile && sidebarOpen"
        class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
        @click="sidebarOpen = false"
      />
    </Transition>

    <!-- ══════════════════════════════════════════
         SIDEBAR
    ══════════════════════════════════════════ -->
    <aside
      :class="[
        'flex flex-col z-50 transition-[width,transform] duration-300 ease-in-out will-change-transform',
        'bg-[#111318] border-r border-white/[0.06]',
        isMobile
          ? ['fixed inset-y-0 left-0 shadow-2xl', sidebarOpen ? 'w-64 translate-x-0' : 'w-64 -translate-x-full']
          : ['relative shrink-0',                  collapsed   ? 'w-[68px]'          : 'w-60'],
      ]"
    >

      <!-- Brand row -->
      <div class="flex items-center h-16 px-4 border-b border-white/[0.06] shrink-0 gap-3">
        <!-- Logo mark -->
        <div class="w-8 h-8 rounded-lg bg-primary-500 flex items-center justify-center shrink-0 shadow-lg shadow-primary-500/30">
          <ChefHat :size="16" class="text-white" />
        </div>

        <div v-if="showLabels" class="flex-1 min-w-0">
          <p class="font-heading font-bold text-white text-sm leading-tight truncate">{{ APP_NAME }}</p>
          <p class="text-[11px] font-medium text-primary-400 leading-tight">{{ roleLabel }}</p>
        </div>

        <!-- Close on mobile -->
        <button
          v-if="isMobile"
          @click="sidebarOpen = false"
          class="p-1.5 rounded-lg text-gray-500 hover:text-white hover:bg-white/8 transition-colors"
          :aria-label="$t('adminLayout.close')"
        >
          <X :size="17" />
        </button>
      </div>

      <!-- Nav scroll area -->
      <nav class="flex-1 overflow-y-auto overflow-x-hidden py-3 scrollbar-hide">
        <template v-for="group in nav" :key="group.group">
          <!-- Group label -->
          <p
            v-if="showLabels"
            class="mt-3 mb-1 px-5 text-[10px] font-bold uppercase tracking-[0.15em] text-gray-600 select-none"
          >{{ group.group }}</p>
          <div v-else class="mt-3 mb-1 mx-4 border-t border-white/[0.06]"></div>

          <!-- Nav items -->
          <RouterLink
            v-for="item in group.items"
            :key="item.to"
            :to="item.to"
            :title="collapsed && !isMobile ? item.label : undefined"
            :class="[
              'relative flex items-center gap-3 mx-2 rounded-xl text-sm font-medium transition-all duration-150 group',
              collapsed && !isMobile ? 'px-0 py-2.5 justify-center' : 'px-3 py-2.5',
              isActive(item.to)
                ? 'bg-primary-500/12 text-primary-400'
                : 'text-gray-400 hover:bg-white/5 hover:text-gray-100',
            ]"
          >
            <!-- Left accent line on active -->
            <span
              v-if="isActive(item.to)"
              class="absolute left-0 inset-y-1.5 w-[3px] bg-primary-500 rounded-r-full"
            />

            <component
              :is="item.icon"
              :size="18"
              :class="[
                'shrink-0 transition-colors',
                isActive(item.to) ? 'text-primary-400' : 'text-gray-500 group-hover:text-gray-200',
              ]"
            />

            <span v-if="showLabels" class="truncate">{{ item.label }}</span>

            <!-- Tiny active dot in collapsed mode -->
            <span
              v-if="!showLabels && isActive(item.to)"
              class="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-primary-500"
            />
          </RouterLink>
        </template>
      </nav>

      <!-- ── User + logout row ────────────────────────────────────────── -->
      <div class="border-t border-white/[0.06] shrink-0">
        <div v-if="showLabels" class="flex items-center gap-3 px-4 py-3">
          <div class="w-8 h-8 rounded-full bg-primary-500/20 border border-primary-500/30 flex items-center justify-center font-bold text-primary-400 text-sm shrink-0">
            {{ auth.user?.name?.[0]?.toUpperCase() }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-white truncate leading-tight">{{ auth.user?.name }}</p>
            <p class="text-[10px] text-gray-500 truncate leading-tight">{{ auth.user?.email }}</p>
          </div>
          <button
            @click="logout"
            class="p-1.5 rounded-lg text-gray-600 hover:text-red-400 hover:bg-red-500/10 transition-colors shrink-0"
            :title="$t('adminLayout.signOut')" :aria-label="$t('adminLayout.signOut')"
          >
            <LogOut :size="15" />
          </button>
        </div>
        <div v-else class="flex justify-center py-3">
          <button
            @click="logout"
            class="p-2 rounded-xl text-gray-600 hover:text-red-400 hover:bg-red-500/10 transition-colors"
            :title="$t('adminLayout.signOut')" :aria-label="$t('adminLayout.signOut')"
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

      <!-- Top bar -->
      <header class="h-14 bg-white dark:bg-[#161921] border-b border-warm-200 dark:border-white/[0.06] flex items-center justify-between px-4 sm:px-5 shrink-0 gap-3">

        <!-- Left -->
        <div class="flex items-center gap-2">

          <!-- Hamburger (mobile) / Collapse toggle (desktop) -->
          <button
            @click="isMobile ? (sidebarOpen = !sidebarOpen) : (collapsed = !collapsed)"
            class="p-2 rounded-xl text-warm-500 hover:text-warm-900 dark:text-gray-500 dark:hover:text-white hover:bg-warm-100 dark:hover:bg-white/8 transition-colors"
            :aria-label="isMobile ? $t('adminLayout.toggleMenu') : (collapsed ? $t('adminLayout.expandSidebar') : $t('adminLayout.collapseSidebar'))"
          >
            <Menu :size="19" />
          </button>

          <!-- Storefront -->
          <RouterLink
            to="/"
            target="_blank"
            class="hidden sm:flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg border border-warm-200 dark:border-white/10 text-warm-600 dark:text-gray-400 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all"
          >
            <ExternalLink :size="12" /> {{ $t('adminLayout.storefront') }}
          </RouterLink>

          <!-- Staff portals dropdown -->
          <div v-if="auth.isStaff" class="relative">
            <button
              @click="showPortals = !showPortals"
              class="hidden sm:flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg border border-warm-200 dark:border-white/10 text-warm-600 dark:text-gray-400 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all"
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
                class="absolute top-full left-0 mt-2 w-44 bg-white dark:bg-[#1c1f28] rounded-xl border border-warm-100 dark:border-white/8 shadow-float z-50 py-1.5 overflow-hidden"
              >
                <RouterLink
                  v-for="p in staffPortals"
                  :key="p.to"
                  :to="p.to"
                  @click="showPortals = false"
                  class="flex items-center gap-2.5 px-4 py-2 text-sm text-warm-700 dark:text-gray-300 hover:bg-primary-50 dark:hover:bg-primary-500/10 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                >
                  <ChevronRight :size="11" class="text-primary-400 shrink-0" /> {{ p.label }}
                </RouterLink>
              </div>
            </Transition>
          </div>
        </div>

        <!-- Right -->
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">

          <!-- Language switcher in admin topbar -->
          <LanguageSwitcher />

          <!-- Inline theme toggle in topbar -->
          <button
            @click="ui.toggleDarkMode"
            class="p-2 rounded-xl text-warm-500 hover:text-warm-900 dark:text-gray-400 dark:hover:text-white hover:bg-warm-100 dark:hover:bg-white/8 transition-colors"
            :aria-label="ui.darkMode ? $t('adminLayout.lightMode') : $t('adminLayout.darkMode')"
            :title="ui.darkMode ? $t('adminLayout.lightMode') : $t('adminLayout.darkMode')"
          >
            <Sun v-if="ui.darkMode"  :size="18" class="text-amber-400" />
            <Moon v-else             :size="18" class="text-gray-400" />
          </button>

          <!-- User pill -->
          <div class="hidden sm:flex items-center gap-2.5 pl-3 border-l border-warm-200 dark:border-white/10">
            <div class="w-7 h-7 rounded-full bg-primary-500/20 border border-primary-500/25 flex items-center justify-center font-bold text-primary-600 dark:text-primary-400 text-xs shrink-0">
              {{ auth.user?.name?.[0]?.toUpperCase() }}
            </div>
            <div>
              <p class="text-xs font-semibold text-warm-800 dark:text-gray-200 leading-tight">{{ auth.user?.name?.split(' ')[0] }}</p>
              <p class="text-[10px] text-primary-600 dark:text-primary-400 font-medium leading-tight capitalize">{{ roleLabel }}</p>
            </div>
          </div>
        </div>
      </header>

      <!-- Page content -->
      <main class="flex-1 overflow-y-auto p-4 sm:p-6 bg-warm-50 dark:bg-[#0f1117]">
        <RouterView />
      </main>
    </div>

  </div>
</template>
