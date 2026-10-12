<script setup>
import { ref, computed, watch } from 'vue'
import { RouterLink, useRouter, useRoute } from 'vue-router'
import {
  ShoppingCart, Menu, X, Sun, Moon, User, LogOut, ChefHat, Globe,
  ShieldCheck, Home, Utensils, Calendar, BedDouble, Info, Package, Sparkles
} from '@lucide/vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../../stores/auth'
import { useCartStore } from '../../stores/cart'
import { useUiStore }   from '../../stores/ui'
import { APP_NAME } from '../../config'
import ThemeToggle from '../ui/ThemeToggle.vue'

const auth   = useAuthStore()
const cart   = useCartStore()
const ui     = useUiStore()
const router = useRouter()
const route  = useRoute()
const { locale } = useI18n()

const mobileOpen   = ref(false)
const userMenuOpen = ref(false)

// Close mobile menu on route navigation
watch(() => route.path, () => {
  mobileOpen.value = false
  userMenuOpen.value = false
})

function toggleLanguage() {
  locale.value = locale.value === 'en' ? 'am' : 'en'
  localStorage.setItem('locale', locale.value)
}

const navLinks = computed(() => {
  // Staff and admin only see Home and About — they cannot place orders or book rooms
  if (auth.isStaff) {
    return [
      { name: locale.value === 'am' ? 'መነሻ' : 'Home',   to: '/',      icon: Home },
      { name: locale.value === 'am' ? 'ስለ እኛ' : 'About', to: '/about', icon: Info },
    ]
  }
  // Guests and customers see the full customer nav
  return [
    { name: locale.value === 'am' ? 'መነሻ' : 'Home',          to: '/',             icon: Home },
    { name: locale.value === 'am' ? 'ምናሌ' : 'Menu',          to: '/menu',         icon: Utensils },
    { name: locale.value === 'am' ? 'ቦታ ማስያዝ' : 'Reservations', to: '/reservations', icon: Calendar },
    { name: locale.value === 'am' ? 'የሆቴል ክፍሎች' : 'Hotel Suites', to: '/rooms',        icon: BedDouble },
    { name: locale.value === 'am' ? 'ስለ እኛ' : 'About',        to: '/about',        icon: Info },
  ]
})

const staffHomeLink = computed(() => {
  if (auth.hasRole('kitchen')) return '/staff/kitchen'
  if (auth.hasRole('waiter')) return '/staff/waiter'
  if (auth.hasRole('cashier')) return '/staff/cashier'
  if (auth.hasRole('reception')) return '/staff/reception'
  if (auth.hasRole('delivery')) return '/staff/delivery'
  return '/staff/kitchen'
})

async function handleLogout() {
  await auth.logout()
  userMenuOpen.value = false
  mobileOpen.value = false
  router.push('/')
}
</script>

<template>
  <header class="sticky top-0 z-50 glass shadow-sm transition-colors duration-200">
    <nav class="page-container flex items-center justify-between h-16">
      <!-- Logo -->
      <RouterLink to="/" class="flex items-center gap-2 font-heading font-bold text-xl text-primary-600 dark:text-primary-400 group">
        <div class="p-1.5 rounded-xl bg-primary-50 dark:bg-primary-950/40 group-hover:scale-105 transition-transform">
          <ChefHat :size="24" class="text-primary-600 dark:text-primary-400" />
        </div>
        <span class="tracking-tight">{{ APP_NAME }}</span>
      </RouterLink>

      <!-- Desktop nav -->
      <ul class="hidden md:flex items-center gap-6">
        <li v-for="link in navLinks" :key="link.to">
          <RouterLink
            :to="link.to"
            class="text-sm font-medium text-warm-700 dark:text-warm-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
            active-class="text-primary-600 dark:text-primary-400 font-semibold"
          >{{ link.name }}</RouterLink>
        </li>
      </ul>

      <!-- Actions -->
      <div class="flex items-center gap-2 md:gap-3">
        <!-- Language Switcher (EN / AM) -->
        <button
          @click="toggleLanguage"
          class="btn-ghost px-2.5 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 border border-warm-200 dark:border-warm-700 hover:border-primary-500 text-warm-700 dark:text-warm-300 transition-colors"
          :title="locale === 'en' ? 'Switch to Amharic (አማርኛ)' : 'Switch to English'"
        >
          <Globe :size="14" class="text-primary-500" />
          <span>{{ locale === 'en' ? 'EN' : 'አማ' }}</span>
        </button>

        <!-- Dark mode toggle -->
        <ThemeToggle variant="icon" size="md" />

        <!-- Cart — only for guests and customers, not staff/admin -->
        <button
          v-if="!auth.isStaff"
          @click="cart.toggleDrawer"
          class="btn-ghost p-2 rounded-xl relative text-warm-700 dark:text-warm-200 hover:text-primary-600"
          aria-label="Open cart"
        >
          <ShoppingCart :size="20" />
          <span
            v-if="cart.itemCount"
            class="absolute -top-1 -right-1 flex h-5 min-w-5 px-1 items-center justify-center rounded-full bg-primary-500 text-[10px] font-bold text-white shadow-xs"
          >
            {{ cart.itemCount > 9 ? '9+' : cart.itemCount }}
          </span>
        </button>

        <!-- Admin Quick Switcher Link -->
        <RouterLink
          v-if="auth.isAdmin"
          to="/admin"
          class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border border-primary-500/80 bg-primary-50/60 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400 hover:bg-primary-500 hover:text-white transition-all shadow-xs"
        >
          <ShieldCheck :size="15" />
          <span>Admin</span>
        </RouterLink>

        <!-- User menu (Desktop) -->
        <div v-if="auth.isAuthenticated" class="relative hidden sm:block">
          <button @click="userMenuOpen = !userMenuOpen" class="btn-ghost p-1.5 sm:px-2.5 sm:py-1.5 rounded-xl flex items-center gap-2 border border-warm-200/80 dark:border-warm-800">
            <div class="w-6 h-6 rounded-full bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center text-xs font-bold text-primary-600 dark:text-primary-400">
              {{ auth.user?.name?.[0]?.toUpperCase() }}
            </div>
            <span class="text-xs font-medium text-warm-800 dark:text-warm-200 truncate max-w-28">{{ auth.user?.name?.split(' ')[0] }}</span>
          </button>
          <div v-if="userMenuOpen" class="absolute right-0 mt-2 w-52 card py-1.5 shadow-float z-50">
            <div class="px-4 py-2 border-b border-warm-100 dark:border-warm-800">
              <p class="text-xs font-semibold text-warm-900 dark:text-white truncate">{{ auth.user?.name }}</p>
              <p class="text-[11px] text-warm-400 truncate">{{ auth.user?.email }}</p>
            </div>
            <RouterLink to="/profile"  @click="userMenuOpen = false" class="flex items-center gap-2.5 px-4 py-2 text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-warm-800 transition-colors">
              <User :size="15" /> Profile
            </RouterLink>
            <RouterLink v-if="!auth.isStaff" to="/orders" @click="userMenuOpen = false" class="flex items-center gap-2.5 px-4 py-2 text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-warm-800 transition-colors">
              <Package :size="15" /> My Orders
            </RouterLink>
            <RouterLink v-if="auth.isAdmin" to="/admin" @click="userMenuOpen = false" class="flex items-center gap-2.5 px-4 py-2 text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-warm-800 transition-colors">
              <ShieldCheck :size="15" /> Admin Panel
            </RouterLink>
            <RouterLink v-if="auth.isStaff" :to="staffHomeLink" @click="userMenuOpen = false" class="flex items-center gap-2.5 px-4 py-2 text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-warm-800 transition-colors">
              <ChefHat :size="15" /> Staff Portal
            </RouterLink>
            <div class="px-4 py-2 border-t border-warm-100 dark:border-warm-800 flex items-center justify-between">
              <span class="text-xs text-warm-500">Theme</span>
              <ThemeToggle variant="segmented" />
            </div>
            <hr class="my-1 border-warm-100 dark:border-warm-800" />
            <button @click="handleLogout" class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
              <LogOut :size="15" /> Logout
            </button>
          </div>
        </div>

        <template v-else>
          <RouterLink to="/login"    class="btn-ghost text-sm hidden md:flex">Sign in</RouterLink>
          <RouterLink to="/register" class="btn-primary text-sm hidden md:flex">Get Started</RouterLink>
        </template>

        <!-- Mobile hamburger toggle button -->
        <button
          @click="mobileOpen = !mobileOpen"
          class="btn-ghost p-2 rounded-xl md:hidden text-warm-700 dark:text-warm-200"
          :aria-label="mobileOpen ? 'Close menu' : 'Open menu'"
        >
          <X v-if="mobileOpen" :size="22" />
          <Menu v-else         :size="22" />
        </button>
      </div>
    </nav>

    <!-- Mobile Drawer / Sheet -->
    <Transition
      enter-from-class="opacity-0 -translate-y-3"
      enter-active-class="transition-all duration-200 ease-out"
      leave-to-class="opacity-0 -translate-y-3"
      leave-active-class="transition-all duration-150 ease-in"
    >
      <div
        v-if="mobileOpen"
        class="md:hidden border-t border-warm-200/80 dark:border-warm-800 bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl shadow-xl max-h-[calc(100vh-4rem)] overflow-y-auto"
      >
        <div class="page-container py-4 space-y-4">

          <!-- User Card in Mobile Drawer -->
          <div v-if="auth.isAuthenticated" class="p-3.5 rounded-2xl bg-warm-50 dark:bg-warm-800/60 border border-warm-200/70 dark:border-warm-700/60 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center text-sm font-bold text-primary-600 dark:text-primary-400">
                {{ auth.user?.name?.[0]?.toUpperCase() }}
              </div>
              <div class="min-w-0">
                <p class="font-semibold text-sm text-warm-900 dark:text-white truncate">{{ auth.user?.name }}</p>
                <div class="flex items-center gap-1.5 mt-0.5">
                  <span class="text-[10px] text-warm-500 capitalize">{{ auth.user?.roles?.[0] ?? 'Customer' }}</span>
                  <span v-if="auth.user?.loyalty_points" class="badge badge-orange text-[10px] py-0">
                    <Sparkles :size="10" class="mr-0.5" /> {{ auth.user.loyalty_points }} pts
                  </span>
                </div>
              </div>
            </div>
            <RouterLink to="/profile" @click="mobileOpen = false" class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline">
              Profile
            </RouterLink>
          </div>

          <!-- Guest Call to Action in Mobile Drawer -->
          <div v-else class="grid grid-cols-2 gap-2 pt-1">
            <RouterLink to="/login" @click="mobileOpen = false" class="btn-secondary text-sm py-2 text-center justify-center">
              Sign in
            </RouterLink>
            <RouterLink to="/register" @click="mobileOpen = false" class="btn-primary text-sm py-2 text-center justify-center">
              Get Started
            </RouterLink>
          </div>

          <!-- Main Nav Links with Icons -->
          <div class="space-y-1 pt-1">
            <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-warm-400">Navigation</p>
            <RouterLink
              v-for="link in navLinks"
              :key="link.to"
              :to="link.to"
              @click="mobileOpen = false"
              class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-warm-800 dark:text-warm-200 hover:bg-warm-100 dark:hover:bg-warm-800 transition-colors"
              active-class="!bg-primary-50 dark:!bg-primary-900/30 text-primary-600 dark:text-primary-400 font-semibold"
            >
              <component :is="link.icon" :size="18" class="text-warm-400 group-hover:text-primary-500" />
              <span>{{ link.name }}</span>
            </RouterLink>
          </div>

          <!-- Account Shortcuts for logged in users -->
          <div v-if="auth.isAuthenticated" class="space-y-1 pt-2 border-t border-warm-100 dark:border-warm-800">
            <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-warm-400">Account</p>

            <RouterLink v-if="!auth.isStaff" to="/orders" @click="mobileOpen = false" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-warm-800 dark:text-warm-200 hover:bg-warm-100 dark:hover:bg-warm-800">
              <Package :size="17" class="text-warm-400" />
              <span>My Orders</span>
            </RouterLink>

            <RouterLink v-if="auth.isAdmin" to="/admin" @click="mobileOpen = false" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20">
              <ShieldCheck :size="17" />
              <span>Admin Panel</span>
            </RouterLink>

            <RouterLink v-if="auth.isStaff" :to="staffHomeLink" @click="mobileOpen = false" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20">
              <ChefHat :size="17" />
              <span>Staff Portal</span>
            </RouterLink>
          </div>

          <!-- Preferences & Theme Settings -->
          <div class="pt-2 border-t border-warm-100 dark:border-warm-800 space-y-2">
            <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-warm-400">Preferences</p>

            <!-- Dark Mode Switch in Mobile Menu -->
            <div class="px-1">
              <ThemeToggle variant="switch" :showLabel="true" />
            </div>

            <!-- Language Switch in Mobile Menu -->
            <button
              @click="toggleLanguage"
              class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium text-warm-800 dark:text-warm-200 hover:bg-warm-100 dark:hover:bg-warm-800 transition-colors"
            >
              <div class="flex items-center gap-2.5">
                <div class="p-1.5 rounded-lg bg-warm-200/60 dark:bg-warm-700/60 text-warm-700 dark:text-warm-200">
                  <Globe :size="16" class="text-primary-500" />
                </div>
                <span>Language / ቋንቋ</span>
              </div>
              <span class="badge badge-orange font-bold text-xs">{{ locale === 'en' ? 'English' : 'አማርኛ' }}</span>
            </button>
          </div>

          <!-- Mobile Logout Button -->
          <div v-if="auth.isAuthenticated" class="pt-2 border-t border-warm-100 dark:border-warm-800">
            <button
              @click="handleLogout"
              class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm font-semibold text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
            >
              <LogOut :size="16" />
              <span>Log out</span>
            </button>
          </div>

        </div>
      </div>
    </Transition>
  </header>
</template>

