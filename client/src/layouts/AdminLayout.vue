<script setup>
import { ref, computed } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import {
  LayoutDashboard, UtensilsCrossed, ShoppingBag, BarChart2, Settings,
  Menu, X, LogOut, ChefHat, Users, Grid3x3, Calendar, Tag, Package,
  Shield, AlertTriangle, Truck, BedDouble, Building2, ExternalLink,
  Star, ChevronRight,
} from '@lucide/vue'
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'
import { APP_NAME } from '../config'

const auth   = useAuthStore()
const router = useRouter()
const route  = useRoute()
const sidebarOpen = ref(true)

const isManager    = computed(() => auth.hasRole('manager') && !auth.hasRole('admin') && !auth.hasRole('super-admin'))
const isSuperAdmin = computed(() => auth.hasRole('super-admin'))

// Build nav filtered by what the current role can actually access
const nav = computed(() => {
  const all = [
    {
      group: 'Operations',
      items: [
        { to: '/admin',              icon: LayoutDashboard, label: 'Dashboard',          always: true },
        { to: '/admin/orders',       icon: ShoppingBag,     label: 'Orders',             always: true },
        { to: '/admin/reservations', icon: Calendar,        label: 'Reservations',       always: true },
        { to: '/admin/tables',       icon: Grid3x3,         label: 'Tables',             always: true },
        { to: '/admin/rooms',        icon: BedDouble,       label: 'Rooms',              always: true },
      ],
    },
    {
      group: 'Catalogue',
      items: [
        { to: '/admin/menu',      icon: UtensilsCrossed, label: 'Menu',       always: true },
        { to: '/admin/inventory', icon: Package,         label: 'Inventory',  always: true },
        { to: '/admin/coupons',   icon: Tag,             label: 'Coupons',    always: true },
      ],
    },
    {
      group: 'Guests',
      items: [
        { to: '/admin/reviews',    icon: Star,          label: 'Reviews',       always: true },
        { to: '/admin/complaints', icon: AlertTriangle, label: 'Complaints',    always: true },
      ],
    },
    {
      group: 'Logistics',
      items: [
        { to: '/admin/delivery-zones', icon: Truck, label: 'Delivery Zones', always: true },
      ],
    },
    {
      group: 'Administration',
      items: [
        { to: '/admin/users',   icon: Users,     label: 'Staff & Access',  adminOnly: false },
        { to: '/admin/reports', icon: BarChart2, label: 'Reports',         always: true },
        { to: '/admin/hotels',  icon: Building2, label: 'Properties',      superAdminOnly: true },
        { to: '/admin/audit',   icon: Shield,    label: 'Audit Log',       adminOnly: true },
        { to: '/admin/settings',icon: Settings,  label: 'Settings',        adminOnly: true },
      ],
    },
  ]

  return all.map(group => ({
    group: group.group,
    items: group.items.filter(item => {
      if (item.superAdminOnly) return isSuperAdmin.value
      if (item.adminOnly)      return !isManager.value        // admin + super-admin only
      return true                                              // always visible
    }),
  })).filter(group => group.items.length > 0)
})

// Staff portals the manager can switch into
const staffPortals = [
  { to: '/staff/kitchen',   label: 'Kitchen KDS' },
  { to: '/staff/waiter',    label: 'Waiter Floor' },
  { to: '/staff/cashier',   label: 'Cashier POS' },
  { to: '/staff/reception', label: 'Reception' },
  { to: '/staff/delivery',  label: 'Delivery' },
]
const showPortals = ref(false)

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="flex h-screen bg-warm-50 dark:bg-gray-950 overflow-hidden">

    <!-- ── Sidebar ──────────────────────────────────────────────────────── -->
    <aside
      :class="['transition-all duration-300 bg-warm-900 dark:bg-gray-900 flex flex-col shrink-0 z-30', sidebarOpen ? 'w-60' : 'w-16']"
    >
      <!-- Logo -->
      <div class="flex items-center gap-3 px-4 py-5 border-b border-warm-800 shrink-0">
        <ChefHat :size="22" class="text-primary-400 shrink-0" />
        <div v-if="sidebarOpen" class="min-w-0">
          <p class="font-heading font-bold text-white text-sm leading-tight truncate">{{ APP_NAME }}</p>
          <p class="text-[10px] text-warm-500 capitalize leading-tight">{{ auth.user?.roles?.[0] }} panel</p>
        </div>
      </div>

      <!-- Nav groups -->
      <nav class="flex-1 py-3 overflow-y-auto space-y-4 px-2">
        <div v-for="group in nav" :key="group.group">
          <!-- Group label -->
          <p v-if="sidebarOpen" class="px-3 mb-1 text-[10px] font-bold uppercase tracking-[0.14em] text-warm-600">
            {{ group.group }}
          </p>
          <div v-else class="my-1 mx-3 h-px bg-warm-800"></div>

          <RouterLink
            v-for="item in group.items"
            :key="item.to"
            :to="item.to"
            :title="!sidebarOpen ? item.label : ''"
            :class="[
              'flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-all duration-150',
              (item.to === '/admin' ? route.path === '/admin' : route.path.startsWith(item.to + '/') || route.path === item.to)
                ? 'bg-primary-600 text-white shadow-md shadow-primary-900/40'
                : 'text-warm-400 hover:bg-warm-800 hover:text-white'
            ]"
          >
            <component :is="item.icon" :size="18" class="shrink-0" />
            <span v-if="sidebarOpen" class="truncate">{{ item.label }}</span>
          </RouterLink>
        </div>
      </nav>

      <!-- Logout -->
      <button
        @click="logout"
        :title="!sidebarOpen ? 'Logout' : ''"
        class="flex items-center gap-3 px-5 py-4 text-sm text-warm-500 hover:text-red-400 border-t border-warm-800 transition-colors shrink-0"
      >
        <LogOut :size="17" class="shrink-0" />
        <span v-if="sidebarOpen">Logout</span>
      </button>
    </aside>

    <!-- ── Main area ─────────────────────────────────────────────────────── -->
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

      <!-- Topbar -->
      <header class="h-14 bg-white dark:bg-gray-900 border-b border-warm-200 dark:border-warm-800 flex items-center justify-between px-5 shrink-0 gap-3">
        <!-- Left side -->
        <div class="flex items-center gap-2">
          <button @click="sidebarOpen = !sidebarOpen" class="text-warm-400 hover:text-warm-700 dark:hover:text-warm-200 p-1 rounded-lg transition-colors" aria-label="Toggle sidebar">
            <Menu :size="19" />
          </button>

          <!-- Storefront link -->
          <RouterLink
            to="/"
            target="_blank"
            class="hidden sm:flex items-center gap-1.5 text-xs font-medium px-2.5 py-1.5 rounded-lg border border-warm-200 dark:border-warm-700 text-warm-600 dark:text-warm-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all"
          >
            <ExternalLink :size="13" /> Storefront
          </RouterLink>

          <!-- Staff portals dropdown (manager + admin) -->
          <div v-if="auth.isStaff" class="relative">
            <button
              @click="showPortals = !showPortals"
              class="hidden sm:flex items-center gap-1.5 text-xs font-medium px-2.5 py-1.5 rounded-lg border border-warm-200 dark:border-warm-700 text-warm-600 dark:text-warm-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all"
            >
              <ChefHat :size="13" />
              Staff Portals
              <ChevronRight :size="12" :class="['transition-transform duration-200', showPortals ? 'rotate-90' : '']" />
            </button>
            <Transition
              enter-from-class="opacity-0 translate-y-1"
              enter-active-class="transition-all duration-150"
              leave-to-class="opacity-0 translate-y-1"
              leave-active-class="transition-all duration-100"
            >
              <div v-if="showPortals" class="absolute top-full left-0 mt-1.5 w-44 bg-white dark:bg-gray-900 rounded-xl border border-warm-200 dark:border-warm-700 shadow-float z-50 py-1.5 overflow-hidden">
                <RouterLink
                  v-for="p in staffPortals"
                  :key="p.to"
                  :to="p.to"
                  @click="showPortals = false"
                  class="flex items-center gap-2 px-3.5 py-2 text-xs font-medium text-warm-700 dark:text-warm-300 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                >
                  <ChevronRight :size="12" class="text-primary-400" /> {{ p.label }}
                </RouterLink>
              </div>
            </Transition>
          </div>
        </div>

        <!-- Right side: user info -->
        <div class="flex items-center gap-3 shrink-0">
          <div class="text-right hidden sm:block">
            <p class="text-xs font-semibold text-warm-800 dark:text-warm-200 leading-tight">{{ auth.user?.name }}</p>
            <p class="text-[10px] text-warm-400 leading-tight capitalize">{{ auth.user?.roles?.[0] }}</p>
          </div>
          <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center font-bold text-primary-600 dark:text-primary-400 text-sm shrink-0">
            {{ auth.user?.name?.[0]?.toUpperCase() }}
          </div>
        </div>
      </header>

      <!-- Page content -->
      <main class="flex-1 overflow-y-auto p-6">
        <RouterView />
      </main>
    </div>

  </div>
</template>
