<script setup>
import { ref, computed } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { ShoppingCart, Menu, X, Sun, Moon, User, LogOut, ChefHat, Globe, ShieldCheck } from '@lucide/vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../../stores/auth'
import { useCartStore } from '../../stores/cart'
import { useUiStore }   from '../../stores/ui'

const auth   = useAuthStore()
const cart   = useCartStore()
const ui     = useUiStore()
const router = useRouter()
const { locale } = useI18n()

const mobileOpen  = ref(false)
const userMenuOpen = ref(false)

function toggleLanguage() {
  locale.value = locale.value === 'en' ? 'am' : 'en'
  localStorage.setItem('locale', locale.value)
}

const navLinks = computed(() => [
  { name: locale.value === 'am' ? 'መነሻ' : 'Home',         to: '/' },
  { name: locale.value === 'am' ? 'ምናሌ' : 'Menu',         to: '/menu' },
  { name: locale.value === 'am' ? 'ቦታ ማስያዝ' : 'Reservations', to: '/reservations' },
  { name: locale.value === 'am' ? 'የሆቴል ክፍሎች' : 'Hotel Suites', to: '/rooms' },
  { name: locale.value === 'am' ? 'ስለ እኛ' : 'About',        to: '/about' },
])

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
  router.push('/')
}
</script>

<template>
  <header class="sticky top-0 z-50 glass shadow-sm">
    <nav class="page-container flex items-center justify-between h-16">
      <!-- Logo -->
      <RouterLink to="/" class="flex items-center gap-2 font-heading font-bold text-xl text-primary-600 dark:text-primary-400">
        <ChefHat :size="26" />
        <span>Restaurant</span>
      </RouterLink>

      <!-- Desktop nav -->
      <ul class="hidden md:flex items-center gap-6">
        <li v-for="link in navLinks" :key="link.to">
          <RouterLink
            :to="link.to"
            class="text-sm font-medium text-warm-700 dark:text-warm-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
            active-class="text-primary-600 dark:text-primary-400"
          >{{ link.name }}</RouterLink>
        </li>
      </ul>

      <!-- Actions -->
      <div class="flex items-center gap-2 md:gap-3">
        <!-- Language Switcher (EN / AM per SRS Section 5.4) -->
        <button
          @click="toggleLanguage"
          class="btn-ghost px-2 py-1 rounded-xl text-xs font-bold flex items-center gap-1 border border-warm-200 dark:border-warm-700 hover:border-primary-500 text-warm-700 dark:text-warm-300 transition-colors"
          :title="locale === 'en' ? 'Switch to Amharic (አማርኛ)' : 'Switch to English'"
        >
          <Globe :size="15" class="text-primary-500" />
          <span>{{ locale === 'en' ? 'EN' : 'አማ' }}</span>
        </button>

        <!-- Dark mode -->
        <button @click="ui.toggleDarkMode" class="btn-ghost p-2 rounded-xl" :aria-label="ui.darkMode ? 'Switch to light mode' : 'Switch to dark mode'">
          <Moon v-if="!ui.darkMode" :size="20" />
          <Sun  v-else              :size="20" />
        </button>

        <!-- Cart -->
        <button @click="cart.toggleDrawer" class="btn-ghost p-2 rounded-xl relative" aria-label="Open cart">
          <ShoppingCart :size="20" />
          <span v-if="cart.itemCount" class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-primary-500 text-[10px] font-bold text-white">
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
          <span>Admin Panel</span>
        </RouterLink>

        <!-- User menu -->
        <div v-if="auth.isAuthenticated" class="relative">
          <button @click="userMenuOpen = !userMenuOpen" class="btn-ghost p-2 rounded-xl flex items-center gap-2">
            <User :size="20" />
            <span class="hidden md:block text-sm">{{ auth.user?.name?.split(' ')[0] }}</span>
          </button>
          <div v-if="userMenuOpen" class="absolute right-0 mt-2 w-48 card py-1 shadow-float">
            <RouterLink to="/profile"  @click="userMenuOpen = false" class="flex items-center gap-2 px-4 py-2 text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-warm-800">
              <User :size="16" /> Profile
            </RouterLink>
            <RouterLink to="/orders"   @click="userMenuOpen = false" class="flex items-center gap-2 px-4 py-2 text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-warm-800">
              My Orders
            </RouterLink>
            <RouterLink v-if="auth.isAdmin" to="/admin" @click="userMenuOpen = false" class="flex items-center gap-2 px-4 py-2 text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-warm-800">
              Admin Panel
            </RouterLink>
            <RouterLink v-if="auth.isStaff" :to="staffHomeLink" @click="userMenuOpen = false" class="flex items-center gap-2 px-4 py-2 text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-warm-800">
              Staff Portal
            </RouterLink>
            <hr class="my-1 border-warm-100 dark:border-warm-800" />
            <button @click="handleLogout" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
              <LogOut :size="16" /> Logout
            </button>
          </div>
        </div>
        <template v-else>
          <RouterLink to="/login"    class="btn-ghost text-sm hidden md:flex">Sign in</RouterLink>
          <RouterLink to="/register" class="btn-primary text-sm hidden md:flex">Get Started</RouterLink>
        </template>

        <!-- Mobile menu button -->
        <button @click="mobileOpen = !mobileOpen" class="btn-ghost p-2 rounded-xl md:hidden" aria-label="Toggle menu">
          <X v-if="mobileOpen" :size="20" />
          <Menu v-else         :size="20" />
        </button>
      </div>
    </nav>

    <!-- Mobile menu -->
    <Transition enter-from-class="opacity-0 -translate-y-2" enter-active-class="transition-all duration-200" leave-to-class="opacity-0 -translate-y-2" leave-active-class="transition-all duration-150">
      <div v-if="mobileOpen" class="md:hidden border-t border-warm-100 dark:border-warm-800 py-3 page-container space-y-1">
        <RouterLink v-for="link in navLinks" :key="link.to" :to="link.to" @click="mobileOpen = false"
          class="block px-3 py-2 rounded-xl text-sm font-medium text-warm-700 dark:text-warm-300 hover:bg-warm-100 dark:hover:bg-warm-800"
          active-class="bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-400"
        >{{ link.name }}</RouterLink>
        <template v-if="!auth.isAuthenticated">
          <RouterLink to="/login"    @click="mobileOpen = false" class="block px-3 py-2 rounded-xl text-sm font-medium text-warm-700 dark:text-warm-300 hover:bg-warm-100 dark:hover:bg-warm-800">Sign in</RouterLink>
          <RouterLink to="/register" @click="mobileOpen = false" class="block px-3 py-2 rounded-xl text-sm font-semibold text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20">Get Started</RouterLink>
        </template>
      </div>
    </Transition>
  </header>
</template>
