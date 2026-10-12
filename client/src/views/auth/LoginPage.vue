<script setup>
import { ref } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useUiStore }   from '../../stores/ui'
import { APP_NAME } from '../../config'
import { ChefHat, Mail, Lock, Eye, EyeOff } from '@lucide/vue'

const auth   = useAuthStore()
const ui     = useUiStore()
const router = useRouter()
const route  = useRoute()

const email    = ref('')
const password = ref('')
const showPw   = ref(false)
const loading  = ref(false)
const error    = ref('')

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    const user = await auth.login({ email: email.value, password: password.value })
    ui.toast(`Welcome back, ${user.name}!`, 'success')

      <!-- Redirect comment removed -->
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
          <ChefHat :size="28" /> {{ APP_NAME }}
        </RouterLink>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white mt-3">Sign In</h1>
        <p class="text-warm-500 dark:text-warm-400 mt-1 text-sm">Access your staff dashboard or customer account</p>
      </div>

      <form @submit.prevent="submit" class="space-y-4" novalidate>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Email Address</label>
          <div class="relative">
            <Mail :size="18" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
            <input v-model="email" type="email" required class="input pl-10" placeholder="your@email.com" autocomplete="email" />
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
