<script setup>
import { ref } from 'vue'
import { RouterLink, RouterView } from 'vue-router'
import { LayoutDashboard, UtensilsCrossed, ShoppingBag, BarChart2, Settings, Menu, X, LogOut, ChefHat, Users, Grid3x3, Calendar, Tag, Package, Shield, AlertTriangle, Truck, BedDouble } from '@lucide/vue'
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'

const auth   = useAuthStore()
const router = useRouter()
const sidebarOpen = ref(true)

const nav = [
  { to: '/admin',               icon: LayoutDashboard, label: 'Dashboard' },
  { to: '/admin/menu',          icon: UtensilsCrossed,  label: 'Menu' },
  { to: '/admin/orders',        icon: ShoppingBag,      label: 'Orders' },
  { to: '/admin/reservations',  icon: Calendar,         label: 'Reservations' },
  { to: '/admin/tables',        icon: Grid3x3,          label: 'Tables' },
  { to: '/admin/rooms',         icon: BedDouble,        label: 'Rooms' },
  { to: '/admin/coupons',       icon: Tag,              label: 'Coupons' },
  { to: '/admin/inventory',     icon: Package,          label: 'Inventory' },
  { to: '/admin/delivery-zones',icon: Truck,            label: 'Delivery Zones' },
  { to: '/admin/complaints',    icon: AlertTriangle,    label: 'Complaints' },
  { to: '/admin/users',         icon: Users,            label: 'Users' },
  { to: '/admin/reports',       icon: BarChart2,        label: 'Reports' },
  { to: '/admin/audit',         icon: Shield,           label: 'Audit Log' },
  { to: '/admin/settings',      icon: Settings,         label: 'Settings' },
]

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="flex h-screen bg-warm-50 dark:bg-gray-950">
    <!-- Sidebar -->
    <aside :class="['transition-all duration-300 bg-warm-900 dark:bg-gray-900 flex flex-col', sidebarOpen ? 'w-60' : 'w-16']">
      <div class="flex items-center gap-3 px-4 py-5 border-b border-warm-800">
        <ChefHat :size="24" class="text-primary-400 shrink-0" />
        <span v-if="sidebarOpen" class="font-heading font-bold text-white">Admin</span>
      </div>
      <nav class="flex-1 py-4 space-y-1 px-2 overflow-y-auto">
        <RouterLink
          v-for="item in nav" :key="item.to" :to="item.to"
          :class="['flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors text-warm-400 hover:bg-warm-800 hover:text-white', { '!bg-primary-600 !text-white': item.to === '/admin' ? $route.path === '/admin' : $route.path.startsWith(item.to) }]"
          :title="!sidebarOpen ? item.label : ''"
        >
          <component :is="item.icon" :size="20" class="shrink-0" />
          <span v-if="sidebarOpen">{{ item.label }}</span>
        </RouterLink>
      </nav>
      <button @click="logout" class="flex items-center gap-3 px-5 py-4 text-sm text-warm-400 hover:text-red-400 border-t border-warm-800">
        <LogOut :size="18" class="shrink-0" />
        <span v-if="sidebarOpen">Logout</span>
      </button>
    </aside>

    <!-- Main -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <header class="glass border-b border-warm-200 dark:border-warm-800 h-14 flex items-center px-6 gap-4">
        <button @click="sidebarOpen = !sidebarOpen" class="btn-ghost p-2 rounded-xl">
          <Menu :size="20" />
        </button>
        <h1 class="font-heading font-semibold text-warm-900 dark:text-white">Admin Panel</h1>
      </header>
      <main class="flex-1 overflow-auto p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
