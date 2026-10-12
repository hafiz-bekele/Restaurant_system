<script setup>
import Navbar from '../components/common/Navbar.vue'
import CartDrawer from '../components/cart/CartDrawer.vue'
import { APP_NAME } from '../config'
</script>

<template>
  <div class="flex flex-col min-h-screen">
    <Navbar />
    <main class="flex-1">
      <slot />
    </main>
    <footer class="bg-warm-900 dark:bg-gray-950 text-warm-300 py-12 mt-16">
      <div class="page-container grid grid-cols-1 md:grid-cols-3 gap-8">
        <div>
          <h3 class="font-heading font-bold text-white text-lg mb-3">{{ APP_NAME }}</h3>
          <p class="text-sm leading-relaxed">Crafted with passion, served with love. Experience flavours that tell a story.</p>
        </div>
        <div>
          <h4 class="font-semibold text-white mb-3">Quick Links</h4>
          <ul class="space-y-2 text-sm">
            <li><RouterLink to="/menu"         class="hover:text-primary-400 transition-colors">Menu</RouterLink></li>
            <li><RouterLink to="/reservations" class="hover:text-primary-400 transition-colors">Reservations</RouterLink></li>
            <li><RouterLink to="/about"        class="hover:text-primary-400 transition-colors">About Us</RouterLink></li>
            <li><RouterLink to="/contact"      class="hover:text-primary-400 transition-colors">Contact</RouterLink></li>
          </ul>
        </div>
        <div>
          <h4 class="font-semibold text-white mb-3">Opening Hours</h4>
          <ul class="space-y-1 text-sm">
            <li>Mon - Thu: 11:00 - 22:00</li>
            <li>Fri - Sat: 11:00 - 23:00</li>
            <li>Sunday: 12:00 - 21:00</li>
          </ul>
        </div>
      </div>
      <div class="page-container mt-8 pt-8 border-t border-warm-800 text-center text-xs text-warm-500">
        &copy; {{ new Date().getFullYear() }} {{ APP_NAME }}. All rights reserved.
      </div>
    </footer>
    <CartDrawer />
  </div>
</template>
