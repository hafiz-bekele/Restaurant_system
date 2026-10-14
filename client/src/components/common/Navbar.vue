<script setup>
import { ref, computed } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { ShoppingCart, Menu, X, User, LogOut, ChefHat, Globe, ShieldCheck, Sun, Moon } from '@lucide/vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../../stores/auth'
import { useCartStore } from '../../stores/cart'
import { useUiStore }   from '../../stores/ui'
import { APP_NAME } from '../../config'

const auth   = useAuthStore()
const cart   = useCartStore()
const ui     = useUiStore()
const router = useRouter()
const { locale } = useI18n()

const mobileOpen   = ref(false)
const userMenuOpen = ref(false)

function toggleLanguage() {
  locale.value = locale.value === 'en' ? 'am' : 'en'
  localStorage.setItem('locale', locale.value)
}

const navLinks = computed(() => {
  if (auth.isStaff) {
    return [
      { name: locale.value === 'am' ? 'መነሻ'   : 'Home',  to: '/' },
      { name: locale.value === 'am' ? 'ስለ እኛ' : 'About', to: '/about' },
    ]
  }
  return [
    { name: locale.value === 'am' ? 'መነሻ'       : 'Home',         to: '/' },
    { name: locale.value === 'am' ? 'ምናሌ'       : 'Menu',         to: '/menu' },
    { name: locale.value === 'am' ? 'ቦታ ማስያዝ'  : 'Reservations', to: '/reservations' },
    { name: locale.value === 'am' ? 'የሆቴል ክፍሎች': 'Hotel Suites',  to: '/rooms' },
    { name: locale.value === 'am' ? 'ስለ እኛ'     : 'About',        to: '/about' },
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
  userMenuOpen.value = false
  mobileOpen.value   = false
  router.push('/')
}

function closeMobile() { mobileOpen.value = false }
</script>

<template>
  <header class="sticky top-0 z-50 glass shadow-sm">
    <nav class="page-container flex items-center justify-between h-16 gap-3">

      <!-- ── Logo ─────────────────────────────────────────────────── -->
      <RouterLink
        to="/"
        class="flex items-center gap-2 font-heading font-bold text-xl text-primary-600 dark:text-primary-400 shrink-0"
        @click="closeMobile"
      >
        <ChefHat :size="26" />
        <span class="hidden sm:inline">{{ APP_NAME }}</span>
      </RouterLink>

      <!-- ── Desktop nav links ─────────────────────────────────────── -->
      <ul class="hidden md:flex items-center gap-1">
        <li v-for="link in navLinks" :key="link.to">
          <RouterLink
            :to="link.to"
            class="px-3 py-2 rounded-xl text-sm font-medium text-warm-700 dark:text-warm-300 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-warm-100 dark:hover:bg-warm-800 transition-colors"
            active-class="text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20"
          >{{ link.name }}</RouterLink>
        </li>
      </ul>

      <!-- ── Right actions ─────────────────────────────────────────── -->
      <div class="flex items-center gap-1 sm:gap-2">

        <!-- Language switcher -->
        <button
          @click="toggleLanguage"
          class="hidden sm:flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-bold border border-warm-200 dark:border-warm-700 text-warm-700 dark:text-warm-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400"
          :title="locale === 'en' ? 'Switch to Amharic' : 'Switch to English'"
        >
          <Globe :size="14" class="text-primary-500" />
          {{ locale === 'en' ? 'EN' : 'አማ' }}
        </button>

        <!-- Theme toggle — single icon -->
        <button
          @click="ui.toggleDarkMode"
          class="p-2 rounded-xl text-warm-700 dark:text-warm-300 hover:bg-warm-100 dark:hover:bg-warm-800 transition-colors"
          :aria-label="ui.darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
          :title="ui.darkMode ? 'Light mode' : 'Dark mode'"
        >
          <Sun  v-if="ui.darkMode" :size="19" class="text-amber-400" />
          <Moon v-else             :size="19" />
        </button>

        <!-- Cart (customers & guests only) -->
        <button
          v-if="!auth.isStaff"
          @click="cart.toggleDrawer"
          class="relative p-2 rounded-xl text-warm-700 dark:text-warm-300 hover:bg-warm-100 dark:hover:bg-warm-800"
          aria-label="Open cart"
        >
          <ShoppingCart :size="20" />
          <Transition
            enter-from-class="scale-0 opacity-0"
            enter-active-class="transition-all duration-200"
            leave-to-class="scale-0 opacity-0"
            leave-active-class="transition-all duration-150"
          >
            <span
              v-if="cart.itemCount"
              class="absolute -top-0.5 -right-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-primary-500 text-[10px] font-bold text-white shadow-sm"
            >
              {{ cart.itemCount > 9 ? '9+' : cart.itemCount }}
            </span>
          </Transition>
        </button>

        <!-- Admin badge -->
        <RouterLink
          v-if="auth.isAdmin"
          to="/admin"
          class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border border-primary-500/70 bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400 hover:bg-primary-500 hover:text-white hover:border-primary-500 transition-all"
        >
          <ShieldCheck :size="14" /> Admin
        </RouterLink>

        <!-- User menu (desktop) -->
        <div v-if="auth.isAuthenticated" class="relative hidden md:block">
          <button
            @click="userMenuOpen = !userMenuOpen"
            class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl hover:bg-warm-100 dark:hover:bg-warm-800 text-warm-700 dark:text-warm-300"
          >
            <div class="w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center text-xs font-bold text-primary-600 dark:text-primary-400">
              {{ auth.user?.name?.[0]?.toUpperCase() }}
            </div>
            <span class="text-sm font-medium">{{ auth.user?.name?.split(' ')[0] }}</span>
          </button>

          <Transition
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-active-class="transition-all duration-150 origin-top-right"
            leave-to-class="opacity-0 scale-95 -translate-y-1"
            leave-active-class="transition-all duration-100 origin-top-right"
          >
            <div
              v-if="userMenuOpen"
              v-click-outside="() => userMenuOpen = false"
              class="absolute right-0 mt-2 w-52 card py-2 shadow-float z-50"
            >
              <!-- User info header -->
              <div class="px-4 py-2 border-b border-warm-100 dark:border-warm-800 mb-1">
                <p class="text-xs font-bold text-warm-900 dark:text-white truncate">{{ auth.user?.name }}</p>
                <p class="text-[11px] text-warm-400 truncate">{{ auth.user?.email }}</p>
              </div>

              <RouterLink to="/profile"      @click="userMenuOpen = false" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-warm-800 hover:text-primary-600 dark:hover:text-primary-400">
                <User :size="15" /> Profile
              </RouterLink>
              <RouterLink v-if="!auth.isStaff" to="/orders" @click="userMenuOpen = false" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-warm-800 hover:text-primary-600 dark:hover:text-primary-400">
                My Orders
              </RouterLink>
              <RouterLink v-if="auth.isAdmin" to="/admin" @click="userMenuOpen = false" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-warm-800 hover:text-primary-600 dark:hover:text-primary-400">
                <ShieldCheck :size="15" /> Admin Panel
              </RouterLink>
              <RouterLink v-if="auth.isStaff && !auth.isAdmin" :to="staffHomeLink" @click="userMenuOpen = false" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-warm-800 hover:text-primary-600 dark:hover:text-primary-400">
                Staff Portal
              </RouterLink>

              <button @click="handleLogout" class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                <LogOut :size="15" /> Sign Out
              </button>
            </div>
          </Transition>
        </div>

        <!-- Auth buttons (guest, desktop) -->
        <template v-if="!auth.isAuthenticated">
          <RouterLink to="/login"    class="hidden md:inline-flex btn-ghost text-sm py-2 px-3">Sign in</RouterLink>
          <RouterLink to="/register" class="hidden md:inline-flex btn-primary text-sm py-2 px-4">Get Started</RouterLink>
        </template>

        <!-- Mobile hamburger -->
        <button
          @click="mobileOpen = !mobileOpen"
          class="md:hidden p-2 rounded-xl text-warm-700 dark:text-warm-300 hover:bg-warm-100 dark:hover:bg-warm-800"
          :aria-label="mobileOpen ? 'Close menu' : 'Open menu'"
          :aria-expanded="mobileOpen"
        >
          <X    v-if="mobileOpen" :size="22" />
          <Menu v-else            :size="22" />
        </button>
      </div>
    </nav>

    <!-- ── Mobile menu ───────────────────────────────────────────── -->
    <Transition
      enter-from-class="opacity-0 -translate-y-3"
      enter-active-class="transition-all duration-200 ease-out"
      leave-to-class="opacity-0 -translate-y-3"
      leave-active-class="transition-all duration-150 ease-in"
    >
      <div
        v-if="mobileOpen"
        class="md:hidden border-t border-warm-100 dark:border-warm-800 bg-white/98 dark:bg-gray-900/98 backdrop-blur-sm"
      >
        <!-- Nav links -->
        <div class="px-4 pt-3 pb-2 space-y-1">
          <RouterLink
            v-for="link in navLinks"
            :key="link.to"
            :to="link.to"
            @click="closeMobile"
            class="flex items-center px-3 py-3 rounded-xl text-sm font-medium text-warm-700 dark:text-warm-300 hover:bg-warm-100 dark:hover:bg-warm-800"
            active-class="bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400"
          >{{ link.name }}</RouterLink>
        </div>

        <!-- Divider -->
        <div class="mx-4 border-t border-warm-100 dark:border-warm-800"></div>

        <!-- Auth section -->
        <div class="px-4 py-3 space-y-2">
          <template v-if="auth.isAuthenticated">
            <!-- User info -->
            <div class="flex items-center gap-3 px-3 py-2 rounded-xl bg-warm-50 dark:bg-warm-800/50 mb-3">
              <div class="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center text-sm font-bold text-primary-600 dark:text-primary-400 shrink-0">
                {{ auth.user?.name?.[0]?.toUpperCase() }}
              </div>
              <div class="min-w-0">
                <p class="text-sm font-bold text-warm-900 dark:text-white truncate">{{ auth.user?.name }}</p>
                <p class="text-xs text-warm-400 truncate capitalize">{{ auth.user?.roles?.[0] }}</p>
              </div>
            </div>

            <RouterLink to="/profile"      @click="closeMobile" class="flex items-center gap-2.5 px-3 py-3 rounded-xl text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-100 dark:hover:bg-warm-800">
              <User :size="16" /> Profile
            </RouterLink>
            <RouterLink v-if="!auth.isStaff" to="/orders" @click="closeMobile" class="flex items-center gap-2.5 px-3 py-3 rounded-xl text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-100 dark:hover:bg-warm-800">
              My Orders
            </RouterLink>
            <RouterLink v-if="auth.isAdmin" to="/admin" @click="closeMobile" class="flex items-center gap-2.5 px-3 py-3 rounded-xl text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-100 dark:hover:bg-warm-800">
              <ShieldCheck :size="16" /> Admin Panel
            </RouterLink>
          </template>

          <template v-else>
            <RouterLink to="/login"    @click="closeMobile" class="flex items-center justify-center px-3 py-3 rounded-xl text-sm font-semibold border-2 border-warm-200 dark:border-warm-700 text-warm-700 dark:text-warm-300">Sign In</RouterLink>
            <RouterLink to="/register" @click="closeMobile" class="btn-primary w-full justify-center py-3">Get Started</RouterLink>
          </template>
        </div>

        <!-- Bottom bar: language + logout -->
        <div class="px-4 pb-4 pt-1 flex items-center justify-between gap-3 border-t border-warm-100 dark:border-warm-800">
          <button
            @click="toggleLanguage"
            class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold border border-warm-200 dark:border-warm-700 text-warm-600 dark:text-warm-400"
          >
            <Globe :size="14" class="text-primary-500" />
            {{ locale === 'en' ? 'EN / አማ' : 'አማ / EN' }}
          </button>

          <button
            v-if="auth.isAuthenticated"
            @click="handleLogout"
            class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-red-600 dark:text-red-400 border border-red-200 dark:border-red-900/40 hover:bg-red-50 dark:hover:bg-red-950/20"
          >
            <LogOut :size="14" /> Sign Out
          </button>
        </div>
      </div>
    </Transition>
  </header>
</template>
