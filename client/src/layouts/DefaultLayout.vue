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
  <div class="flex flex-col min-h-screen">
    <Navbar />

    <main class="flex-1 pb-20 md:pb-0">
      <slot />
    </main>

    <!-- Footer -->
    <footer class="bg-warm-900 dark:bg-gray-950 text-warm-300 py-12 mt-16 border-t border-warm-800/60">
      <div class="page-container grid grid-cols-1 md:grid-cols-3 gap-8">
        <div>
          <h3 class="font-heading font-bold text-white text-lg mb-3">{{ APP_NAME }}</h3>
          <p class="text-sm leading-relaxed text-warm-400">Crafted with passion, served with love. Experience flavours that tell a story.</p>
        </div>
        <div>
          <h4 class="font-semibold text-white mb-3">Quick Links</h4>
          <ul class="space-y-2 text-sm">
            <li><RouterLink to="/menu"         class="hover:text-primary-400 transition-colors">Menu</RouterLink></li>
            <li><RouterLink to="/reservations" class="hover:text-primary-400 transition-colors">Reservations</RouterLink></li>
            <li><RouterLink to="/rooms"        class="hover:text-primary-400 transition-colors">Hotel Suites</RouterLink></li>
            <li><RouterLink to="/about"        class="hover:text-primary-400 transition-colors">About Us</RouterLink></li>
            <li><RouterLink to="/contact"      class="hover:text-primary-400 transition-colors">Contact</RouterLink></li>
          </ul>
        </div>
        <div>
          <h4 class="font-semibold text-white mb-3">Opening Hours</h4>
          <ul class="space-y-1 text-sm text-warm-400">
            <li>Mon - Thu: 11:00 - 22:00</li>
            <li>Fri - Sat: 11:00 - 23:00</li>
            <li>Sunday: 12:00 - 21:00</li>
          </ul>
        </div>
      </div>

      <!-- Footer bottom bar -->
      <div class="page-container mt-10 pt-6 border-t border-warm-800 flex items-center justify-center text-xs text-warm-500">
        &copy; {{ new Date().getFullYear() }} {{ APP_NAME }}. All rights reserved.
      </div>
    </footer>

    <!-- Mobile Bottom Navigation Bar (Hidden on desktop) -->
    <nav
      v-if="!auth.isStaff"
      class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl border-t border-warm-200/80 dark:border-warm-800 shadow-xl px-2 py-1.5 flex items-center justify-around"
      aria-label="Mobile navigation"
    >
      <RouterLink
        to="/"
        class="flex flex-col items-center justify-center py-1 px-3 rounded-xl text-xs font-medium text-warm-600 dark:text-warm-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
        :class="{ '!text-primary-600 dark:!text-primary-400 font-bold': route.path === '/' }"
      >
        <Home :size="20" />
        <span class="text-[10px] mt-0.5">Home</span>
      </RouterLink>

      <RouterLink
        to="/menu"
        class="flex flex-col items-center justify-center py-1 px-3 rounded-xl text-xs font-medium text-warm-600 dark:text-warm-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
        :class="{ '!text-primary-600 dark:!text-primary-400 font-bold': route.path.startsWith('/menu') }"
      >
        <Utensils :size="20" />
        <span class="text-[10px] mt-0.5">Menu</span>
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
        <span class="text-[10px] mt-0.5">Cart</span>
      </button>

      <RouterLink
        to="/reservations"
        class="flex flex-col items-center justify-center py-1 px-3 rounded-xl text-xs font-medium text-warm-600 dark:text-warm-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
        :class="{ '!text-primary-600 dark:!text-primary-400 font-bold': route.path === '/reservations' }"
      >
        <Calendar :size="20" />
        <span class="text-[10px] mt-0.5">Reserve</span>
      </RouterLink>

      <RouterLink
        :to="auth.isAuthenticated ? '/profile' : '/login'"
        class="flex flex-col items-center justify-center py-1 px-3 rounded-xl text-xs font-medium text-warm-600 dark:text-warm-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
        :class="{ '!text-primary-600 dark:!text-primary-400 font-bold': route.path === '/profile' || route.path === '/login' }"
      >
        <User :size="20" />
        <span class="text-[10px] mt-0.5">{{ auth.isAuthenticated ? 'Account' : 'Sign in' }}</span>
      </RouterLink>
    </nav>

    <CartDrawer />
  </div>
</template>

