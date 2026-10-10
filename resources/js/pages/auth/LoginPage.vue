<script setup>
import { ref } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useUiStore }   from '../../stores/ui'
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
    await auth.login({ email: email.value, password: password.value })
    ui.toast('Welcome back!', 'success')
    router.push(route.query.redirect ?? '/')
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Invalid credentials'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-warm-50 dark:bg-gray-950 flex items-center justify-center p-4">
    <div class="card w-full max-w-md p-8">
      <div class="text-center mb-8">
        <RouterLink to="/" class="inline-flex items-center gap-2 text-primary-600 dark:text-primary-400 font-heading font-bold text-2xl">
          <ChefHat :size="28" /> Restaurant
        </RouterLink>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white mt-4">Welcome back</h1>
        <p class="text-warm-500 dark:text-warm-400 mt-1 text-sm">Sign in to your account</p>
      </div>

      <form @submit.prevent="submit" class="space-y-5" novalidate>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Email</label>
          <div class="relative">
            <Mail :size="18" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
            <input v-model="email" type="email" required class="input pl-10" placeholder="you@example.com" autocomplete="email" />
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

        <button type="submit" :disabled="loading" class="btn-primary w-full justify-center py-3">
          {{ loading ? 'Signing in...' : 'Sign In' }}
        </button>
      </form>

      <p class="text-center text-sm text-warm-500 dark:text-warm-400 mt-6">
        Don't have an account?
        <RouterLink to="/register" class="text-primary-600 dark:text-primary-400 font-semibold hover:underline">Create one</RouterLink>
      </p>
    </div>
  </div>
</template>
