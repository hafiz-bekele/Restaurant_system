<script setup>
import { ref, computed } from 'vue'
import { RouterLink, RouterView } from 'vue-router'
import { LayoutDashboard, UtensilsCrossed, ShoppingBag, BarChart2, Settings, Menu, X, LogOut, ChefHat, Users, Grid3x3, Calendar, Tag, Package, Shield, AlertTriangle, Truck, BedDouble, Building2, ExternalLink, Star } from '@lucide/vue'
import { useAuthStore } from '../stores/auth'
import { useRouter } from 'vue-router'

const auth   = useAuthStore()
const router = useRouter()
const sidebarOpen = ref(true)

const nav = computed(() => {
  const items = [
    { to: '/admin',               icon: LayoutDashboard, label: 'Dashboard' },
    { to: '/admin/menu',          icon: UtensilsCrossed,  label: 'Menu' },
    { to: '/admin/orders',        icon: ShoppingBag,      label: 'Orders' },
    { to: '/admin/reviews',       icon: Star,             label: 'Reviews & Ratings' },
    { to: '/admin/reservations',  icon: Calendar,         label: 'Reservations' },
    { to: '/admin/tables',        icon: Grid3x3,          label: 'Tables' },
    { to: '/admin/rooms',         icon: BedDouble,        label: 'Rooms' },
  ]
  if (auth.hasRole('super-admin')) {
    items.push({ to: '/admin/hotels', icon: Building2, label: 'Properties' })
  }
  items.push(
    { to: '/admin/coupons',       icon: Tag,              label: 'Coupons' },
    { to: '/admin/inventory',     icon: Package,          label: 'Inventory' },
    { to: '/admin/delivery-zones',icon: Truck,            label: 'Delivery Zones' },
    { to: '/admin/complaints',    icon: AlertTriangle,    label: 'Complaints' },
    { to: '/admin/users',         icon: Users,            label: 'Staff & Permissions' },
    { to: '/admin/reports',       icon: BarChart2,        label: 'Reports' },
    { to: '/admin/audit',         icon: Shield,           label: 'Audit Log' },
    { to: '/admin/settings',      icon: Settings,         label: 'Settings' },
  )
  return items
})

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
      <!-- Topbar -->
      <header class="h-16 bg-white dark:bg-gray-900 border-b border-warm-200 dark:border-warm-800 flex items-center justify-between px-6">
        <div class="flex items-center gap-3">
          <button @click="sidebarOpen = !sidebarOpen" class="text-warm-500 hover:text-warm-700">
            <Menu :size="20" />
          </button>
          <!-- Storefront Website Link -->
          <RouterLink
            to="/"
            target="_blank"
            class="flex items-center gap-1.5 btn-secondary text-xs py-1.5 px-3"
            title="Preview customer storefront website in new tab"
          >
            <ExternalLink :size="14" />
            <span>Storefront Website</span>
          </RouterLink>
          <RouterLink
            to="/staff/kitchen"
            class="hidden sm:flex items-center gap-1.5 btn-secondary text-xs py-1.5 px-3"
          >
            <ChefHat :size="15" />
            <span>Staff Portals</span>
          </RouterLink>
        </div>
        <div class="flex items-center gap-4">
          <span class="text-sm font-medium text-warm-700 dark:text-warm-300">{{ auth.user?.name }}</span>
          <span class="badge badge-orange text-xs capitalize">{{ auth.user?.roles?.[0] }}</span>
        </div>
      </header>

      <!-- Content -->
      <main class="flex-1 overflow-y-auto p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
