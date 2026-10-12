<script setup>
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { ChefHat, UtensilsCrossed, CreditCard, Truck, CalendarCheck, LogOut, User } from '@lucide/vue'
import { useAuthStore } from '../stores/auth'
import { APP_NAME } from '../config'
import ThemeToggle from '../components/ui/ThemeToggle.vue'

const auth   = useAuthStore()
const router = useRouter()

const allTabs = [
  { to: '/staff/kitchen',   icon: ChefHat,          label: 'Kitchen KDS', role: 'kitchen' },
  { to: '/staff/waiter',    icon: UtensilsCrossed,   label: 'Waiter Floor', role: 'waiter' },
  { to: '/staff/reception', icon: CalendarCheck,     label: 'Reception',   role: 'reception' },
  { to: '/staff/cashier',   icon: CreditCard,        label: 'Cashier POS', role: 'cashier' },
  { to: '/staff/delivery',  label: 'Delivery',    role: 'delivery' },
]

const visibleTabs = allTabs.filter(t =>
  auth.hasRole(t.role) ||
  auth.hasRole('super-admin') ||
  auth.hasRole('admin') ||
  auth.hasRole('manager') ||
  (t.role === 'reception' && auth.hasRole('waiter'))
)

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-warm-50 dark:bg-gray-950 flex flex-col">
    <!-- Staff Header -->
    <header class="glass border-b border-warm-200 dark:border-warm-800 h-16 flex items-center justify-between px-4 sm:px-6 sticky top-0 z-40">
      <div class="flex items-center gap-4 sm:gap-6">
        <RouterLink to="/" class="flex items-center gap-2 font-heading font-bold text-lg text-primary-600 dark:text-primary-400">
          <ChefHat :size="24" />
          <span class="inline">{{ APP_NAME }}</span>
        </RouterLink>

        <!-- Navigation Tabs (Desktop) -->
        <nav class="hidden sm:flex items-center gap-1.5">
          <RouterLink
            v-for="tab in visibleTabs"
            :key="tab.to"
            :to="tab.to"
            :class="[
              'flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all text-warm-600 dark:text-warm-400 hover:bg-warm-100 dark:hover:bg-warm-800',
              { '!bg-primary-500 !text-white shadow-sm': $route.path.startsWith(tab.to) }
            ]"
          >
            <component :is="tab.icon" :size="15" />
            {{ tab.label }}
          </RouterLink>
        </nav>
      </div>

      <!-- Right User Controls -->
      <div class="flex items-center gap-2 sm:gap-4">
        <!-- Dark Mode Toggle for Staff -->
        <ThemeToggle variant="icon" size="md" />

        <div class="text-right hidden sm:block">
          <p class="text-xs font-bold text-warm-900 dark:text-white leading-tight">{{ auth.user?.name }}</p>
          <span class="badge badge-orange text-[10px] capitalize">{{ auth.user?.roles?.[0] ?? 'Staff' }}</span>
        </div>

        <RouterLink v-if="auth.isAdmin" to="/admin" class="btn-ghost text-xs py-1.5 px-2.5 hidden sm:inline-flex">
          Admin Panel
        </RouterLink>

        <button
          @click="handleLogout"
          class="p-2 rounded-xl text-red-500 hover:bg-red-50 dark:hover:bg-red-950/20 transition-colors"
          title="Log Out"
          aria-label="Log out"
        >
          <LogOut :size="18" />
        </button>
      </div>
    </header>

    <!-- Mobile Staff Navigation Bar (Sub-header) -->
    <div
      v-if="visibleTabs.length > 1"
      class="sm:hidden overflow-x-auto bg-white/95 dark:bg-gray-900/95 backdrop-blur border-b border-warm-200 dark:border-warm-800 px-3 py-2 flex items-center gap-2 shrink-0 z-30"
    >
      <RouterLink
        v-for="tab in visibleTabs"
        :key="tab.to"
        :to="tab.to"
        :class="[
          'flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all text-warm-700 dark:text-warm-300 bg-warm-100 dark:bg-warm-800',
          { '!bg-primary-500 !text-white shadow-xs': $route.path.startsWith(tab.to) }
        ]"
      >
        <component :is="tab.icon" :size="14" />
        <span>{{ tab.label }}</span>
      </RouterLink>
    </div>

    <main class="flex-1 p-4 sm:p-6 max-w-7xl w-full mx-auto">
      <RouterView />
    </main>
  </div>
</template>
