<script setup>
import { RouterLink, RouterView } from 'vue-router'
import { ChefHat, UtensilsCrossed, CreditCard, Truck, ChevronRight, CalendarCheck } from '@lucide/vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()

const tabs = [
  { to: '/staff/kitchen',   icon: ChefHat,          label: 'Kitchen',    role: 'kitchen' },
  { to: '/staff/waiter',    icon: UtensilsCrossed,   label: 'Waiter',     role: 'waiter' },
  { to: '/staff/reception', icon: CalendarCheck,     label: 'Reception',  role: 'waiter' },
  { to: '/staff/cashier',   icon: CreditCard,        label: 'Cashier',    role: 'cashier' },
  { to: '/staff/delivery',  icon: Truck,             label: 'Delivery',   role: 'delivery' },
].filter(t => auth.hasRole(t.role) || auth.hasRole('super-admin') || auth.hasRole('manager'))
</script>

<template>
  <div class="min-h-screen bg-warm-50 dark:bg-gray-950 flex flex-col">
    <header class="glass border-b border-warm-200 dark:border-warm-800 h-14 flex items-center px-6 gap-4">
      <ChefHat :size="22" class="text-primary-500" />
      <span class="font-heading font-bold text-warm-900 dark:text-white">Staff Portal</span>
      <nav class="ml-4 flex items-center gap-2">
        <RouterLink
          v-for="tab in tabs" :key="tab.to" :to="tab.to"
          :class="['flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors text-warm-600 dark:text-warm-400 hover:bg-warm-100 dark:hover:bg-warm-800', { '!bg-primary-500 !text-white': $route.path.startsWith(tab.to) }]"
        >
          <component :is="tab.icon" :size="16" />
          {{ tab.label }}
        </RouterLink>
      </nav>
    </header>
    <main class="flex-1 p-6">
      <RouterView />
    </main>
  </div>
</template>
