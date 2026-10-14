<script setup>
import { ref, computed } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { ShoppingCart, Menu, X, LogOut, ChefHat, ShieldCheck, Sun, Moon } from '@lucide/vue'
import { useAuthStore } from '../../stores/auth'
import { useCartStore } from '../../stores/cart'
import { useUiStore }   from '../../stores/ui'
import { APP_NAME } from '../../config'

const auth   = useAuthStore()
const cart   = useCartStore()
const ui     = useUiStore()
const router = useRouter()

const mobileOpen = ref(false)

// Staff users only see Home + About
// Guests and customers see the full nav — no login required to browse/order
const navLinks = computed(() => {
  if (auth.isStaff) {
    return [
      { label: 'Home',  to: '/' },
      { label: 'About', to: '/about' },
    ]
  }
  return [
    { label: 'Home',         to: '/' },
    { label: 'Menu',         to: '/menu' },
    { label: 'Reservations', to: '/reservations' },
    { label: 'Hotel Suites', to: '/rooms' },
    { label: 'About',        to: '/about' },
  ]
})

const staffHomeLink = computed(() => {
  if (auth.hasRole('kitchen'))   return '/staff/kitchen'
  if (auth.hasRole('waiter'))    return '/staff/waiter'
  if (auth.hasRole('cashier'))   return '/staff/cashier'
  if (auth.hasRole('reception')) return '/staff/reception'
  if (auth.hasRole('delivery'))  return '/staff/delivery'
  return '/staff/kitchen'
})

async function handleLogout() {
  await auth.logout()
  mobileOpen.value = false
  router.push('/')
}
</script>

<template>
  <header class="sticky top-0 z-50 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md border-b border-warm-100 dark:border-warm-800 shadow-sm">
    <nav class="page-container flex items-center justify-between h-16">

      <!-- Logo -->
      <RouterLink
        to="/"
        class="flex items-center gap-2 font-heading font-bold text-xl text-primary-600 dark:text-primary-400 shrink-0"
        @click="mobileOpen = false"
      >
        <ChefHat :size="24" />
        <span>{{ APP_NAME }}</span>
      </RouterLink>

      <!-- Desktop nav links -->
      <ul class="hidden lg:flex items-center gap-1">
        <li v-for="link in navLinks" :key="link.to">
          <RouterLink
            :to="link.to"
            class="px-3 py-2 rounded-lg text-sm font-medium text-warm-600 dark:text-warm-400 hover:text-warm-900 dark:hover:text-white hover:bg-warm-50 dark:hover:bg-warm-800 transition-colors"
            active-class="text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20"
          >{{ link.label }}</RouterLink>
        </li>
      </ul>

      <!-- Right side -->
      <div class="flex items-center gap-1.5">

        <!-- Dark mode toggle -->
        <button
          @click="ui.toggleDarkMode"
          class="p-2 rounded-lg text-warm-500 dark:text-warm-400 hover:bg-warm-100 dark:hover:bg-warm-800 transition-colors"
          :aria-label="ui.darkMode ? 'Light mode' : 'Dark mode'"
        >
          <Sun  v-if="ui.darkMode" :size="18" class="text-amber-400" />
          <Moon v-else             :size="18" />
        </button>

        <!-- Cart (guests + customers only, not staff) -->
        <button
          v-if="!auth.isStaff"
          @click="cart.toggleDrawer"
          class="relative p-2 rounded-lg text-warm-500 dark:text-warm-400 hover:bg-warm-100 dark:hover:bg-warm-800 transition-colors"
          aria-label="Cart"
        >
          <ShoppingCart :size="20" />
          <span
            v-if="cart.itemCount"
            class="absolute -top-0.5 -right-0.5 w-5 h-5 rounded-full bg-primary-500 text-[10px] font-bold text-white flex items-center justify-center"
          >{{ cart.itemCount > 9 ? '9+' : cart.itemCount }}</span>
        </button>

        <!-- ── Logged-in staff / admin ── -->
        <template v-if="auth.isAuthenticated && auth.isStaff">
          <!-- Admin panel link -->
          <RouterLink
            v-if="auth.isAdmin"
            to="/admin"
            class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-primary-500 text-white hover:bg-primary-600 transition-colors"
          >
            <ShieldCheck :size="14" /> Admin Panel
          </RouterLink>
          <!-- Staff portal link -->
          <RouterLink
            v-else
            :to="staffHomeLink"
            class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-warm-200 dark:border-warm-700 text-warm-700 dark:text-warm-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all"
          >
            My Portal
          </RouterLink>

          <!-- User avatar + logout (desktop) -->
          <div class="hidden md:flex items-center gap-2 pl-2 border-l border-warm-200 dark:border-warm-700 ml-1">
            <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center text-xs font-bold text-primary-600 dark:text-primary-400">
              {{ auth.user?.name?.[0]?.toUpperCase() }}
            </div>
            <button
              @click="handleLogout"
              class="p-1.5 rounded-lg text-warm-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
              title="Sign out"
            >
              <LogOut :size="16" />
            </button>
          </div>
        </template>

        <!-- Mobile hamburger -->
        <button
          @click="mobileOpen = !mobileOpen"
          class="lg:hidden p-2 rounded-lg text-warm-600 dark:text-warm-300 hover:bg-warm-100 dark:hover:bg-warm-800 transition-colors"
          :aria-label="mobileOpen ? 'Close menu' : 'Open menu'"
        >
          <X    v-if="mobileOpen" :size="22" />
          <Menu v-else            :size="22" />
        </button>
      </div>
    </nav>

    <!-- Mobile menu -->
    <Transition
      enter-from-class="opacity-0 -translate-y-2"
      enter-active-class="transition-all duration-200"
      leave-to-class="opacity-0 -translate-y-2"
      leave-active-class="transition-all duration-150"
    >
      <div
        v-if="mobileOpen"
        class="lg:hidden border-t border-warm-100 dark:border-warm-800 bg-white dark:bg-gray-900 px-4 py-4 space-y-1"
      >
        <RouterLink
          v-for="link in navLinks"
          :key="link.to"
          :to="link.to"
          @click="mobileOpen = false"
          class="flex items-center px-3 py-3 rounded-xl text-sm font-medium text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-warm-800"
          active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400"
        >{{ link.label }}</RouterLink>

        <!-- Staff links on mobile -->
        <template v-if="auth.isAuthenticated && auth.isStaff">
          <div class="border-t border-warm-100 dark:border-warm-800 pt-3 mt-3 space-y-1">
            <div class="flex items-center gap-3 px-3 py-2 mb-2">
              <div class="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center font-bold text-primary-600 dark:text-primary-400 shrink-0">
                {{ auth.user?.name?.[0]?.toUpperCase() }}
              </div>
              <div class="min-w-0">
                <p class="text-sm font-semibold text-warm-900 dark:text-white truncate">{{ auth.user?.name }}</p>
                <p class="text-xs text-warm-400 capitalize">{{ auth.user?.roles?.[0] }}</p>
              </div>
            </div>
            <RouterLink v-if="auth.isAdmin" to="/admin" @click="mobileOpen = false" class="flex items-center gap-2 px-3 py-3 rounded-xl text-sm font-semibold text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20">
              <ShieldCheck :size="16" /> Admin Panel
            </RouterLink>
            <RouterLink v-else :to="staffHomeLink" @click="mobileOpen = false" class="flex items-center gap-2 px-3 py-3 rounded-xl text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-warm-800">
              My Portal
            </RouterLink>
            <button @click="handleLogout" class="w-full flex items-center gap-2 px-3 py-3 rounded-xl text-sm font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
              <LogOut :size="16" /> Sign Out
            </button>
          </div>
        </template>
      </div>
    </Transition>
  </header>
</template>
