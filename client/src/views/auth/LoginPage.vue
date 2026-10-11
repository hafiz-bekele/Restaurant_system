<script setup>
import { ref } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useUiStore }   from '../../stores/ui'
import { ChefHat, Mail, Lock, Eye, EyeOff, ShieldCheck, Users } from '@lucide/vue'

const auth   = useAuthStore()
const ui     = useUiStore()
const router = useRouter()
const route  = useRoute()

const email    = ref('')
const password = ref('')
const showPw   = ref(false)
const loading  = ref(false)
const error    = ref('')

const demoUsers = [
  { role: 'Super Admin', email: 'superadmin@restaurant.com', badge: 'System-wide',  color: 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300' },
  { role: 'Admin',       email: 'admin@restaurant.com',      badge: 'Hotel Admin',  color: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300' },
  { role: 'Manager',     email: 'manager@restaurant.com',    badge: 'Operations',   color: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300' },
  { role: 'Cashier',     email: 'cashier@restaurant.com',    badge: 'Billing/POS',  color: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300' },
  { role: 'Reception',   email: 'reception@restaurant.com',  badge: 'Front Desk',   color: 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' },
  { role: 'Waiter',      email: 'waiter@restaurant.com',     badge: 'Floor/Table',  color: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300' },
  { role: 'Kitchen',     email: 'kitchen@restaurant.com',    badge: 'KDS Station',  color: 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300' },
]

function fillDemo(userEmail) {
  email.value = userEmail
  password.value = 'password'
}

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    const user = await auth.login({ email: email.value, password: password.value })
    ui.toast(`Welcome back, ${user.name}!`, 'success')

    // Intelligent role-based dashboard redirection (REQ-2.3)
    if (route.query.redirect) {
      router.push(route.query.redirect)
    } else if (auth.isAdmin) {
      router.push('/admin')
    } else if (auth.hasRole('kitchen')) {
      router.push('/staff/kitchen')
    } else if (auth.hasRole('waiter')) {
      router.push('/staff/waiter')
    } else if (auth.hasRole('cashier')) {
      router.push('/staff/cashier')
    } else if (auth.hasRole('reception')) {
      router.push('/staff/reception')
    } else if (auth.hasRole('delivery')) {
      router.push('/staff/delivery')
    } else if (auth.hasRole('customer')) {
      router.push('/menu')
    } else {
      router.push('/')
    }
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Invalid credentials'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-warm-50 dark:bg-gray-950 flex items-center justify-center p-4">
    <div class="card w-full max-w-lg p-8">
      <div class="text-center mb-6">
        <RouterLink to="/" class="inline-flex items-center gap-2 text-primary-600 dark:text-primary-400 font-heading font-bold text-2xl">
          <ChefHat :size="28" /> Restaurant &amp; Hotel System
        </RouterLink>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white mt-3">Staff &amp; Guest Sign In</h1>
        <p class="text-warm-500 dark:text-warm-400 mt-1 text-sm">Access your role-specific dashboard or customer account</p>
      </div>

      <!-- 7 System User Roles Demo Switcher (SRS Section 2.3) -->
      <div class="mb-6 p-4 rounded-2xl bg-warm-100/70 dark:bg-warm-900/40 border border-warm-200 dark:border-warm-800">
        <div class="flex items-center justify-between mb-2.5">
          <span class="text-xs font-bold text-warm-700 dark:text-warm-300 flex items-center gap-1.5">
            <ShieldCheck :size="15" class="text-primary-500" />
            7 System Staff Personas (1-Click Fill):
          </span>
          <span class="text-[11px] text-warm-400">Default pw: password</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 mb-2.5">
          <button
            v-for="u in demoUsers"
            :key="u.email"
            type="button"
            @click="fillDemo(u.email)"
            :class="[
              'p-2 rounded-xl text-left border transition-all hover:scale-[1.02] flex flex-col justify-between',
              email === u.email
                ? 'border-primary-500 ring-2 ring-primary-500/30 bg-white dark:bg-warm-800'
                : 'border-warm-200 dark:border-warm-800 bg-white/70 dark:bg-warm-850 hover:border-warm-300'
            ]"
          >
            <span class="text-xs font-semibold text-warm-900 dark:text-white leading-tight">{{ u.role }}</span>
            <span :class="['text-[10px] font-medium px-1.5 py-0.5 rounded-md mt-1 w-fit', u.color]">
              {{ u.badge }}
            </span>
          </button>
        </div>

        <!-- Customer Ordering Persona -->
        <button
          type="button"
          @click="fillDemo('customer@restaurant.com')"
          :class="[
            'w-full p-2.5 rounded-xl text-left border transition-all flex items-center justify-between',
            email === 'customer@restaurant.com'
              ? 'border-rose-500 ring-2 ring-rose-500/30 bg-rose-50/50 dark:bg-rose-950/30'
              : 'border-dashed border-warm-300 dark:border-warm-700 bg-white/50 dark:bg-warm-850 hover:border-rose-400'
          ]"
        >
          <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-warm-900 dark:text-white">Customer / Diner (Ordering User):</span>
            <span class="text-[11px] text-warm-500">customer@restaurant.com</span>
          </div>
          <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300">
            Order Food
          </span>
        </button>
      </div>

      <form @submit.prevent="submit" class="space-y-4" novalidate>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Email Address</label>
          <div class="relative">
            <Mail :size="18" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
            <input v-model="email" type="email" required class="input pl-10" placeholder="user@restaurant.com" autocomplete="email" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Password</label>
          <div class="relative">
            <Lock :size="18" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
            <input v-model="password" :type="showPw ? 'text' : 'password'" required class="input pl-10 pr-10" placeholder="••••••••" autocomplete="current-password" />
            <button type="button" @click="showPw = !showPw" class="absolute right-3 top-1/2 -translate-y-1/2 text-warm-400 hover:text-warm-600">
              <EyeOff v-if="showPw" :size="16" /><Eye v-else :size="16" />
            </button>
          </div>
        </div>

        <p v-if="error" class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-xl px-4 py-2">{{ error }}</p>

        <button type="submit" :disabled="loading" class="btn-primary w-full justify-center py-3 text-base font-semibold shadow-md">
          {{ loading ? 'Signing in...' : 'Sign In to System' }}
        </button>
      </form>

      <p class="text-center text-sm text-warm-500 dark:text-warm-400 mt-6">
        Need a customer account?
        <RouterLink to="/register" class="text-primary-600 dark:text-primary-400 font-semibold hover:underline">Create one</RouterLink>
      </p>
    </div>
  </div>
</template>
