<script setup>
import { ref, computed } from 'vue'
import { RouterLink, useRouter, useRoute } from 'vue-router'
import {
  ChefHat, ShoppingCart, Sun, Moon, Menu, X,
  LayoutDashboard, LogOut, ChevronDown,
  UtensilsCrossed, CalendarDays, BedDouble, Info,
  Home, ShieldCheck, Gauge, User, LogIn, Package, Heart,
} from '@lucide/vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../../stores/auth'
import { useCartStore } from '../../stores/cart'
import { useUiStore }   from '../../stores/ui'
import { APP_NAME } from '../../config'
import LanguageSwitcher from './LanguageSwitcher.vue'

const { t }  = useI18n()
const auth   = useAuthStore()
const cart   = useCartStore()
const ui     = useUiStore()
const router = useRouter()
const route  = useRoute()

const mobileOpen  = ref(false)
const userMenuOpen = ref(false)

// Icon map for nav links
const navLinks = computed(() => {
  if (auth.isStaff) {
    return [
      { labelKey: 'nav.home',  to: '/',      icon: Home  },
      { labelKey: 'nav.about', to: '/about', icon: Info  },
    ]
  }
  return [
    { labelKey: 'nav.home',         to: '/',             icon: Home           },
    { labelKey: 'nav.menu',         to: '/menu',         icon: UtensilsCrossed },
    { labelKey: 'nav.reservations', to: '/reservations', icon: CalendarDays   },
    { labelKey: 'nav.suites',       to: '/rooms',        icon: BedDouble      },
    { labelKey: 'nav.about',        to: '/about',        icon: Info           },
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

function isActive(to) {
  return to === '/' ? route.path === '/' : route.path.startsWith(to)
}

async function handleLogout() {
  await auth.logout()
  mobileOpen.value   = false
  userMenuOpen.value = false
  router.push('/')
}
</script>

<template>
  <header class="sticky top-0 z-50 bg-white/92 dark:bg-[#0c0d12]/92 backdrop-blur-md border-b border-warm-200/80 dark:border-white/10 shadow-xs">
    <nav class="page-container flex items-center justify-between h-16 gap-4">

      <!-- ── Logo ─────────────────────────────────────── -->
      <RouterLink
        to="/"
        class="flex items-center gap-2.5 shrink-0 group"
        @click="mobileOpen = false"
      >
        <div class="w-8 h-8 rounded-xl bg-primary-500 flex items-center justify-center shadow-md shadow-primary-500/30 group-hover:shadow-primary-500/50 transition-shadow">
          <ChefHat :size="16" class="text-white" />
        </div>
        <span class="font-heading font-bold text-base text-warm-900 dark:text-white tracking-tight">
          {{ t('brandName') }}
        </span>
      </RouterLink>

      <!-- ── Desktop nav ───────────────────────────────── -->
      <nav class="hidden lg:flex items-center gap-0.5">
        <RouterLink
          v-for="link in navLinks"
          :key="link.to"
          :to="link.to"
          :class="[
            'flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-sm font-medium transition-all duration-150',
            isActive(link.to)
              ? 'text-primary-600 dark:text-primary-400 bg-primary-500/10 dark:bg-primary-500/15 border border-primary-500/20 shadow-2xs backdrop-blur-xs'
              : 'text-warm-600 dark:text-warm-400 hover:text-warm-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-white/5 border border-transparent',
          ]"
        >
          <component :is="link.icon" :size="15" :class="isActive(link.to) ? 'text-primary-500' : 'text-warm-400 dark:text-warm-600'" />
          {{ t(link.labelKey) }}
        </RouterLink>
      </nav>

      <!-- ── Right actions ─────────────────────────────── -->
      <div class="flex items-center gap-1.5">

        <!-- Language switcher (Amharic / English) -->
        <LanguageSwitcher />

        <!-- Dark / light toggle -->
        <button
          @click="ui.toggleDarkMode"
          :class="[
            'w-9 h-9 rounded-xl flex items-center justify-center transition-all',
            'text-warm-500 dark:text-warm-400 hover:bg-warm-100 dark:hover:bg-white/8',
          ]"
          :aria-label="ui.darkMode ? 'Light mode' : 'Dark mode'"
        >
          <Transition
            enter-from-class="scale-0 opacity-0 rotate-90"
            enter-active-class="transition-all duration-200"
            leave-to-class="scale-0 opacity-0 -rotate-90"
            leave-active-class="transition-all duration-150"
            mode="out-in"
          >
            <Sun  v-if="ui.darkMode" :size="18" class="text-amber-400" key="sun" />
            <Moon v-else             :size="18" key="moon" />
          </Transition>
        </button>

        <!-- Cart badge -->
        <button
          v-if="!auth.isStaff"
          @click="cart.toggleDrawer"
          class="relative w-9 h-9 rounded-xl flex items-center justify-center text-warm-500 dark:text-warm-400 hover:bg-warm-100 dark:hover:bg-white/8 transition-colors"
          aria-label="Open cart"
        >
          <ShoppingCart :size="19" />
          <Transition
            enter-from-class="scale-0 opacity-0"
            enter-active-class="transition-all duration-200"
            leave-to-class="scale-0 opacity-0"
            leave-active-class="transition-all duration-100"
          >
            <span
              v-if="cart.itemCount"
              key="badge"
              class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-primary-500 text-[10px] font-bold text-white flex items-center justify-center shadow-sm"
            >{{ cart.itemCount > 9 ? '9+' : cart.itemCount }}</span>
          </Transition>
        </button>

        <!-- Staff / admin logged in ─────────────────────── -->
        <template v-if="auth.isAuthenticated && auth.isStaff">

          <!-- Quick access pill -->
          <RouterLink
            :to="auth.isAdmin ? '/admin' : staffHomeLink"
            class="hidden sm:flex items-center gap-2 pl-2.5 pr-3.5 py-1.5 rounded-xl text-xs font-semibold bg-primary-500/10 dark:bg-primary-500/15 text-primary-700 dark:text-primary-400 hover:bg-primary-500/20 dark:hover:bg-primary-500/25 border border-primary-500/20 transition-all"
          >
            <Gauge v-if="auth.isAdmin" :size="14" />
            <LayoutDashboard v-else    :size="14" />
            {{ auth.isAdmin ? t('nav.dashboard') : t('nav.myPortal') }}
          </RouterLink>

          <!-- User menu -->
          <div class="relative hidden md:block">
            <button
              @click="userMenuOpen = !userMenuOpen"
              :class="[
                'flex items-center gap-2 pl-2 pr-2.5 py-1.5 rounded-xl transition-all',
                userMenuOpen ? 'bg-warm-100 dark:bg-white/10' : 'hover:bg-warm-100 dark:hover:bg-white/8',
              ]"
            >
              <!-- Avatar -->
              <div class="w-7 h-7 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-xs font-bold text-white shadow-sm">
                {{ auth.user?.name?.[0]?.toUpperCase() }}
              </div>
              <span class="text-sm font-medium text-warm-800 dark:text-warm-200 max-w-[80px] truncate">
                {{ auth.user?.name?.split(' ')[0] }}
              </span>
              <ChevronDown :size="14" :class="['text-warm-400 transition-transform duration-200', userMenuOpen ? 'rotate-180' : '']" />
            </button>

            <!-- Dropdown -->
            <Transition
              enter-from-class="opacity-0 scale-95 -translate-y-1"
              enter-active-class="transition-all duration-150 origin-top-right"
              leave-to-class="opacity-0 scale-95 -translate-y-1"
              leave-active-class="transition-all duration-100 origin-top-right"
            >
              <div
                v-if="userMenuOpen"
                class="absolute right-0 mt-2 w-56 rounded-2xl bg-white/95 dark:bg-[#161824]/95 backdrop-blur-lg border border-warm-200/90 dark:border-white/10 shadow-[0_16px_48px_rgba(0,0,0,0.12)] dark:shadow-[0_16px_48px_rgba(0,0,0,0.5)] z-50 overflow-hidden"
              >
                <!-- User info -->
                <div class="px-4 pt-4 pb-3 border-b border-warm-100 dark:border-white/6">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-sm font-bold text-white shrink-0">
                      {{ auth.user?.name?.[0]?.toUpperCase() }}
                    </div>
                    <div class="min-w-0">
                      <p class="text-sm font-semibold text-warm-900 dark:text-white truncate leading-tight">{{ auth.user?.name }}</p>
                      <p class="text-xs text-primary-500 dark:text-primary-400 font-medium capitalize leading-tight mt-0.5">{{ auth.user?.roles?.[0] }}</p>
                    </div>
                  </div>
                </div>

                <!-- Menu items -->
                <div class="py-1.5">
                  <RouterLink
                    :to="auth.isAdmin ? '/admin' : staffHomeLink"
                    @click="userMenuOpen = false"
                    class="flex items-center gap-3 px-4 py-2.5 text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-white/5 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                  >
                    <Gauge v-if="auth.isAdmin" :size="16" class="text-warm-400" />
                    <LayoutDashboard v-else    :size="16" class="text-warm-400" />
                    {{ auth.isAdmin ? t('nav.dashboard') : t('nav.myPortal') }}
                  </RouterLink>
                </div>

                <!-- Logout -->
                <div class="border-t border-warm-100 dark:border-white/6 py-1.5">
                  <button
                    @click="handleLogout"
                    class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                  >
                    <LogOut :size="16" class="text-red-500" />
                    {{ t('nav.signOut') }}
                  </button>
                </div>
              </div>
            </Transition>
          </div>
        </template>

        <!-- Customer logged in ──────────────────────────────── -->
        <template v-else-if="auth.isAuthenticated">
          <div class="relative hidden sm:block">
            <button
              @click="userMenuOpen = !userMenuOpen"
              :class="[
                'flex items-center gap-2 pl-2 pr-2.5 py-1.5 rounded-xl transition-all',
                userMenuOpen ? 'bg-warm-100 dark:bg-white/10' : 'hover:bg-warm-100 dark:hover:bg-white/8',
              ]"
            >
              <div class="w-7 h-7 rounded-full bg-primary-500 flex items-center justify-center text-xs font-bold text-white shadow-sm">
                {{ auth.user?.name?.[0]?.toUpperCase() ?? 'U' }}
              </div>
              <span class="text-sm font-medium text-warm-800 dark:text-warm-200 max-w-[80px] truncate">
                {{ auth.user?.name?.split(' ')[0] ?? 'Account' }}
              </span>
              <ChevronDown :size="14" :class="['text-warm-400 transition-transform duration-200', userMenuOpen ? 'rotate-180' : '']" />
            </button>

            <!-- Dropdown -->
            <Transition
              enter-from-class="opacity-0 scale-95 -translate-y-1"
              enter-active-class="transition-all duration-150 origin-top-right"
              leave-to-class="opacity-0 scale-95 -translate-y-1"
              leave-active-class="transition-all duration-100 origin-top-right"
            >
              <div
                v-if="userMenuOpen"
                class="absolute right-0 mt-2 w-52 rounded-2xl bg-white/95 dark:bg-[#161824]/95 backdrop-blur-lg border border-warm-200/90 dark:border-white/10 shadow-[0_16px_48px_rgba(0,0,0,0.12)] dark:shadow-[0_16px_48px_rgba(0,0,0,0.5)] z-50 overflow-hidden"
              >
                <div class="px-4 py-3 border-b border-warm-100 dark:border-white/6">
                  <p class="text-sm font-semibold text-warm-900 dark:text-white truncate">{{ auth.user?.name }}</p>
                  <p class="text-xs text-warm-500 dark:text-warm-400 truncate">{{ auth.user?.email }}</p>
                </div>
                <div class="py-1.5">
                  <RouterLink
                    to="/profile"
                    @click="userMenuOpen = false"
                    class="flex items-center gap-3 px-4 py-2 text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-white/5"
                  >
                    <User :size="15" class="text-warm-400" /> {{ t('nav.profile') }}
                  </RouterLink>
                  <RouterLink
                    to="/orders"
                    @click="userMenuOpen = false"
                    class="flex items-center gap-3 px-4 py-2 text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-white/5"
                  >
                    <Package :size="15" class="text-warm-400" /> {{ t('nav.myOrders') }}
                  </RouterLink>
                  <RouterLink
                    to="/favorites"
                    @click="userMenuOpen = false"
                    class="flex items-center gap-3 px-4 py-2 text-sm text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-white/5"
                  >
                    <Heart :size="15" class="text-warm-400" /> {{ t('nav.favorites') }}
                  </RouterLink>
                </div>
                <div class="border-t border-warm-100 dark:border-white/6 py-1.5">
                  <button
                    @click="handleLogout"
                    class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10"
                  >
                    <LogOut :size="15" class="text-red-500" /> {{ t('nav.signOut') }}
                  </button>
                </div>
              </div>
            </Transition>
          </div>
        </template>

        <!-- Guest: Not Authenticated ────────────────────────── -->
        <template v-else>
          <RouterLink
            to="/login"
            class="hidden sm:inline-flex items-center gap-1.5 btn-primary !py-1.5 !px-3.5 text-xs font-semibold shadow-sm ml-1"
          >
            <LogIn :size="14" />
            <span>{{ t('nav.login') }}</span>
          </RouterLink>
        </template>

        <!-- Mobile hamburger -->
        <button
          @click="mobileOpen = !mobileOpen"
          class="lg:hidden w-9 h-9 rounded-xl flex items-center justify-center text-warm-600 dark:text-warm-300 hover:bg-warm-100 dark:hover:bg-white/8 transition-colors"
          :aria-label="mobileOpen ? 'Close' : 'Menu'"
        >
          <Transition
            enter-from-class="scale-0 opacity-0 rotate-90"
            enter-active-class="transition-all duration-150"
            leave-to-class="scale-0 opacity-0 -rotate-90"
            leave-active-class="transition-all duration-100"
            mode="out-in"
          >
            <X    v-if="mobileOpen" :size="20" key="x" />
            <Menu v-else            :size="20" key="menu" />
          </Transition>
        </button>
      </div>
    </nav>

    <!-- ── Mobile menu ──────────────────────────────────── -->
    <Transition
      enter-from-class="opacity-0 -translate-y-2"
      enter-active-class="transition-all duration-200 ease-out"
      leave-to-class="opacity-0 -translate-y-2"
      leave-active-class="transition-all duration-150 ease-in"
    >
      <div
        v-if="mobileOpen"
        class="lg:hidden border-t border-warm-200/80 dark:border-white/10 bg-white/95 dark:bg-[#0c0d12]/95 backdrop-blur-lg px-4 py-3 shadow-lg"
      >
        <!-- Nav links with icons -->
        <div class="space-y-0.5 mb-3">
          <RouterLink
            v-for="link in navLinks"
            :key="link.to"
            :to="link.to"
            @click="mobileOpen = false"
            :class="[
              'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors',
              isActive(link.to)
                ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400'
                : 'text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-white/5',
            ]"
          >
            <component
              :is="link.icon"
              :size="17"
              :class="isActive(link.to) ? 'text-primary-500' : 'text-warm-400 dark:text-warm-600'"
            />
            {{ t(link.labelKey) }}
          </RouterLink>
        </div>

        <!-- Language Switcher in Mobile Menu -->
        <div class="flex items-center justify-between px-3 py-2.5 rounded-xl bg-warm-50 dark:bg-white/4 mb-3">
          <span class="text-xs font-medium text-warm-600 dark:text-warm-400">ቋንቋ / Language</span>
          <LanguageSwitcher />
        </div>

        <!-- Staff section -->
        <template v-if="auth.isAuthenticated && auth.isStaff">
          <div class="border-t border-warm-100 dark:border-white/6 pt-3 space-y-0.5">
            <!-- User info row -->
            <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-warm-50 dark:bg-white/4 mb-2">
              <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-xs font-bold text-white shrink-0">
                {{ auth.user?.name?.[0]?.toUpperCase() }}
              </div>
              <div class="min-w-0">
                <p class="text-sm font-semibold text-warm-900 dark:text-white truncate leading-tight">{{ auth.user?.name }}</p>
                <p class="text-xs text-primary-500 dark:text-primary-400 capitalize font-medium">{{ auth.user?.roles?.[0] }}</p>
              </div>
            </div>

            <RouterLink
              :to="auth.isAdmin ? '/admin' : staffHomeLink"
              @click="mobileOpen = false"
              class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-warm-700 dark:text-warm-300 hover:bg-warm-50 dark:hover:bg-white/5 transition-colors"
            >
              <Gauge v-if="auth.isAdmin" :size="17" class="text-warm-400" />
              <LayoutDashboard v-else    :size="17" class="text-warm-400" />
              {{ auth.isAdmin ? t('nav.dashboard') : t('nav.myPortal') }}
            </RouterLink>

            <button
              @click="handleLogout"
              class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
            >
              <LogOut :size="17" class="text-red-500" />
              {{ t('nav.signOut') }}
            </button>
          </div>
        </template>

        <!-- Customer mobile section -->
        <template v-else-if="auth.isAuthenticated">
          <div class="border-t border-warm-100 dark:border-white/6 pt-3 space-y-1">
            <div class="px-3 py-2 flex items-center gap-3 bg-warm-50 dark:bg-white/4 rounded-xl">
              <div class="w-8 h-8 rounded-full bg-primary-500 text-white flex items-center justify-center font-bold text-xs">
                {{ auth.user?.name?.[0]?.toUpperCase() ?? 'U' }}
              </div>
              <div class="min-w-0">
                <p class="text-sm font-semibold text-warm-900 dark:text-white truncate">{{ auth.user?.name }}</p>
                <p class="text-xs text-warm-500 truncate">{{ auth.user?.email }}</p>
              </div>
            </div>
            <RouterLink to="/profile" @click="mobileOpen = false" class="flex items-center gap-3 px-3 py-2 text-sm text-warm-700 dark:text-warm-300">
              <User :size="17" class="text-warm-400" /> {{ t('nav.profile') }}
            </RouterLink>
            <RouterLink to="/orders" @click="mobileOpen = false" class="flex items-center gap-3 px-3 py-2 text-sm text-warm-700 dark:text-warm-300">
              <Package :size="17" class="text-warm-400" /> {{ t('nav.myOrders') }}
            </RouterLink>
            <button @click="handleLogout" class="w-full flex items-center gap-3 px-3 py-2 text-sm text-red-600 dark:text-red-400">
              <LogOut :size="17" class="text-red-500" /> {{ t('nav.signOut') }}
            </button>
          </div>
        </template>

        <!-- Guest mobile sign in -->
        <template v-else>
          <div class="border-t border-warm-100 dark:border-white/6 pt-3">
            <RouterLink
              to="/login"
              @click="mobileOpen = false"
              class="w-full btn-primary justify-center py-2.5 text-sm font-semibold gap-2 shadow-sm"
            >
              <LogIn :size="16" />
              <span>{{ t('nav.login') }}</span>
            </RouterLink>
          </div>
        </template>
      </div>
    </Transition>
  </header>
</template>
