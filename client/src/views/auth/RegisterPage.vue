<script setup>
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useUiStore }   from '../../stores/ui'
import { ChefHat, User, Mail, Lock } from '@lucide/vue'
import { APP_NAME } from '../../config'

const auth   = useAuthStore()
const ui     = useUiStore()
const router = useRouter()

const form    = ref({ name: '', email: '', phone: '', password: '', password_confirmation: '' })
const loading = ref(false)
const error   = ref('')

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    await auth.register(form.value)
    ui.toast('Account created! Welcome.', 'success')
    router.push('/')
  } catch (e) {
    const errors = e.response?.data?.errors
    error.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message ?? 'Registration failed')
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
          <ChefHat :size="28" /> {{ APP_NAME }}
        </RouterLink>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white mt-4">Create Account</h1>
        <p class="text-warm-500 dark:text-warm-400 mt-1 text-sm">Join us for the best dining experience</p>
      </div>

      <form @submit.prevent="submit" class="space-y-4" novalidate>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Full Name</label>
          <input v-model="form.name" type="text" required class="input" placeholder="John Doe" autocomplete="name" />
        </div>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Email</label>
          <input v-model="form.email" type="email" required class="input" placeholder="you@example.com" autocomplete="email" />
        </div>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Phone (optional)</label>
          <input v-model="form.phone" type="tel" class="input" placeholder="+251 911 000 000" autocomplete="tel" />
        </div>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Password</label>
          <input v-model="form.password" type="password" required class="input" placeholder="Min. 8 characters" autocomplete="new-password" />
        </div>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Confirm Password</label>
          <input v-model="form.password_confirmation" type="password" required class="input" placeholder="Repeat password" autocomplete="new-password" />
        </div>

        <p v-if="error" class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-xl px-4 py-2">{{ error }}</p>

        <button type="submit" :disabled="loading" class="btn-primary w-full justify-center py-3 mt-2">
          {{ loading ? 'Creating...' : 'Create Account' }}
        </button>
      </form>

      <p class="text-center text-sm text-warm-500 dark:text-warm-400 mt-6">
        Already have an account?
        <RouterLink to="/login" class="text-primary-600 dark:text-primary-400 font-semibold hover:underline">Sign in</RouterLink>
      </p>
    </div>
  </div>
</template>
