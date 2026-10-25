<script setup>
import { RouterLink, useRoute } from 'vue-router'
import { Home, Utensils, ShoppingBag, Calendar, User } from '@lucide/vue'
import Navbar from '../components/common/Navbar.vue'
import CartDrawer from '../components/cart/CartDrawer.vue'
import { useCartStore } from '../stores/cart'
import { useAuthStore } from '../stores/auth'
import { APP_NAME } from '../config'

const cart  = useCartStore()
const auth  = useAuthStore()
const route = useRoute()
</script>

<template>
  <div class="flex flex-col min-h-screen relative overflow-x-hidden bg-warm-50 dark:bg-gray-950">
    <!-- Subtle Ambient Glow Accents (Restricted, low-opacity background depth) -->
    <div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden" aria-hidden="true">
      <!-- Warm Amber/Orange Brand Glow (Top-Right) -->
      <div class="absolute -top-[12%] -right-[10%] w-[45vw] max-w-[500px] aspect-square rounded-full bg-gradient-to-br from-primary-400/15 via-amber-300/10 to-transparent dark:from-primary-500/10 dark:via-orange-600/5 dark:to-transparent blur-3xl opacity-25"></div>

      <!-- Cool Indigo/Violet Accent Glow (Middle-Left) -->
      <div class="absolute top-[35%] -left-[12%] w-[40vw] max-w-[450px] aspect-square rounded-full bg-gradient-to-tr from-indigo-300/10 via-primary-300/8 to-transparent dark:from-indigo-600/8 dark:via-primary-600/5 dark:to-transparent blur-3xl opacity-20"></div>

      <!-- Subtle Rose/Amber Warm Glow (Bottom-Right) -->
      <div class="absolute -bottom-[8%] right-[10%] w-[35vw] max-w-[400px] aspect-square rounded-full bg-gradient-to-tl from-rose-300/10 via-amber-200/8 to-transparent dark:from-rose-600/6 dark:via-primary-700/5 dark:to-transparent blur-3xl opacity-20"></div>
    </div>

    <Navbar />

    <main class="flex-1 pb-20 md:pb-0">
      <slot />
    </main>

    <!-- Footer -->
    <footer class="bg-warm-900/95 dark:bg-gray-950/95 backdrop-blur-xl text-warm-300 py-12 mt-16 border-t border-white/10">
      <div class="page-container grid grid-cols-1 md:grid-cols-3 gap-8">
        <div>
          <h3 class="font-heading font-bold text-white text-lg mb-3">{{ $t('brandName') }}</h3>
          <p class="text-sm leading-relaxed text-warm-400">{{ $t('footer.tagline') }}</p>
        </div>
        <div>
          <h4 class="font-semibold text-white mb-3">{{ $t('footer.quickLinks') }}</h4>
          <ul class="space-y-2 text-sm">
            <li><RouterLink to="/menu"         class="hover:text-primary-400 transition-colors">{{ $t('nav.menu') }}</RouterLink></li>
            <li><RouterLink to="/reservations" class="hover:text-primary-400 transition-colors">{{ $t('nav.reservations') }}</RouterLink></li>
            <li><RouterLink to="/rooms"        class="hover:text-primary-400 transition-colors">{{ $t('nav.suites') }}</RouterLink></li>
            <li><RouterLink to="/about"        class="hover:text-primary-400 transition-colors">{{ $t('nav.about') }}</RouterLink></li>
            <li><RouterLink to="/contact"      class="hover:text-primary-400 transition-colors">{{ $t('nav.contact') }}</RouterLink></li>
            <li><RouterLink to="/login"        class="hover:text-primary-400 transition-colors text-primary-400 font-medium">{{ $t('footer.staffSignIn') }}</RouterLink></li>
          </ul>
        </div>
        <div>
          <h4 class="font-semibold text-white mb-3">{{ $t('footer.openingHours') }}</h4>
          <ul class="space-y-1 text-sm text-warm-400">
            <li>{{ $t('footer.monThu') }}</li>
            <li>{{ $t('footer.friSat') }}</li>
            <li>{{ $t('footer.sunday') }}</li>
          </ul>
        </div>
      </div>

      <!-- Footer bottom bar -->
      <div class="page-container mt-10 pt-6 border-t border-warm-800 flex items-center justify-center text-xs text-warm-500">
        &copy; {{ new Date().getFullYear() }} {{ $t('brandName') }}. {{ $t('footer.allRightsReserved') }}
      </div>
    </footer>

    <!-- Mobile Bottom Navigation Bar (Hidden on desktop) -->
    <nav
      v-if="!auth.isStaff"
      class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 dark:bg-gray-950/95 backdrop-blur-md border-t border-warm-200/80 dark:border-white/10 shadow-[0_-8px_30px_rgba(0,0,0,0.08)] px-2 py-1.5 flex items-center justify-around"
      aria-label="Mobile navigation"
    >
      <RouterLink
        to="/"
        class="flex flex-col items-center justify-center py-1 px-3 rounded-xl text-xs font-medium text-warm-600 dark:text-warm-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
        :class="{ '!text-primary-600 dark:!text-primary-400 font-bold': route.path === '/' }"
      >
        <Home :size="20" />
        <span class="text-[10px] mt-0.5">{{ $t('nav.home') }}</span>
      </RouterLink>

      <RouterLink
        to="/menu"
        class="flex flex-col items-center justify-center py-1 px-3 rounded-xl text-xs font-medium text-warm-600 dark:text-warm-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
        :class="{ '!text-primary-600 dark:!text-primary-400 font-bold': route.path.startsWith('/menu') }"
      >
        <Utensils :size="20" />
        <span class="text-[10px] mt-0.5">{{ $t('nav.menu') }}</span>
      </RouterLink>

      <!-- Cart Button with Dynamic Badge -->
      <button
        type="button"
        @click="cart.toggleDrawer"
        class="flex flex-col items-center justify-center py-1 px-3 rounded-xl text-xs font-medium text-warm-600 dark:text-warm-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors relative"
      >
        <div class="relative">
          <ShoppingBag :size="20" />
          <span
            v-if="cart.itemCount"
            class="absolute -top-1.5 -right-2 flex h-4 min-w-4 px-1 items-center justify-center rounded-full bg-primary-500 text-[9px] font-bold text-white shadow-xs"
          >
            {{ cart.itemCount > 9 ? '9+' : cart.itemCount }}
          </span>
        </div>
        <span class="text-[10px] mt-0.5">{{ $t('cart.cart') }}</span>
      </button>

      <RouterLink
        to="/reservations"
        class="flex flex-col items-center justify-center py-1 px-3 rounded-xl text-xs font-medium text-warm-600 dark:text-warm-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
        :class="{ '!text-primary-600 dark:!text-primary-400 font-bold': route.path === '/reservations' }"
      >
        <Calendar :size="20" />
        <span class="text-[10px] mt-0.5">{{ $t('nav.reservations') }}</span>
      </RouterLink>

      <RouterLink
        :to="auth.isAuthenticated ? '/profile' : '/login'"
        class="flex flex-col items-center justify-center py-1 px-3 rounded-xl text-xs font-medium text-warm-600 dark:text-warm-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
        :class="{ '!text-primary-600 dark:!text-primary-400 font-bold': route.path === '/profile' || route.path === '/login' }"
      >
        <User :size="20" />
        <span class="text-[10px] mt-0.5">{{ auth.isAuthenticated ? $t('nav.account') : $t('nav.login') }}</span>
      </RouterLink>
    </nav>

    <CartDrawer />
  </div>
</template>

