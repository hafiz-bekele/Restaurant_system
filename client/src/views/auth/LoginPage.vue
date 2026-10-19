<script setup>
import { ref } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../../stores/auth'
import { useUiStore }   from '../../stores/ui'
import { APP_NAME } from '../../config'
import { ChefHat, Mail, Lock, Eye, EyeOff, ArrowLeft, User, Sparkles, LogOut, Check } from '@lucide/vue'
import ThemeToggle from '../../components/ui/ThemeToggle.vue'

const { t } = useI18n()
const auth   = useAuthStore()
const ui     = useUiStore()
const router = useRouter()
const route  = useRoute()

const email    = ref('')
const password = ref('')
const showPw   = ref(false)
const loading  = ref(false)
const error    = ref('')

const demoAccounts = [
  { label: 'Admin',     email: 'admin@restaurant.com',     pass: 'password', role: 'Full Admin' },
  { label: 'Kitchen',   email: 'kitchen@restaurant.com',   pass: 'password', role: 'Head Chef' },
  { label: 'Waiter',    email: 'waiter@restaurant.com',    pass: 'password', role: 'Server' },
  { label: 'Reception', email: 'reception@restaurant.com', pass: 'password', role: 'Front Desk' },
]

function fillDemo(acc) {
  email.value    = acc.email
  password.value = acc.pass
  ui.toast(t('auth.demoSelectedToast', { label: acc.label }), 'info')
}

async function handleSignOut() {
  await auth.logout()
  ui.toast(t('auth.signedOutToast'), 'success')
}

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    const user = await auth.login({ email: email.value, password: password.value })
    ui.toast(t('auth.welcomeBackToast', { name: user.name }), 'success')

    // Honour an explicit redirect first (e.g. from a protected route)
    if (route.query.redirect) {
      router.push(String(route.query.redirect))
      return
    }

    // Role-based home destination
    if (auth.isAdmin) {
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
    } else {
      router.push('/menu')
    }
  } catch (e) {
    error.value = e.response?.data?.message ?? t('auth.invalidCredentials')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-warm-50 dark:bg-gray-950 flex flex-col justify-between p-4 sm:p-6 transition-colors duration-200">
    <!-- Top Header Bar with Storefront Link & Theme Toggle -->
    <header class="w-full max-w-4xl mx-auto flex items-center justify-between py-2 shrink-0">
      <RouterLink
        to="/"
        class="inline-flex items-center gap-1.5 text-xs font-semibold text-warm-600 dark:text-warm-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
      >
        <ArrowLeft :size="16" />
        <span>{{ $t('auth.returnTo', { name: APP_NAME }) }}</span>
      </RouterLink>

      <div class="flex items-center gap-2">
        <ThemeToggle variant="segmented" />
      </div>
    </header>

    <div class="card w-full max-w-md mx-auto p-6 sm:p-8 my-auto shadow-card border border-warm-100 dark:border-warm-800">

      <!-- Already logged in notification banner -->
      <div v-if="auth.isAuthenticated" class="mb-6 p-4 rounded-2xl bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800/60">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="font-bold text-xs uppercase tracking-wider text-primary-600 dark:text-primary-400">{{ $t('auth.currentlyLoggedIn') }}</p>
            <p class="text-sm font-semibold text-warm-900 dark:text-white mt-0.5">{{ auth.user?.name }}</p>
            <p class="text-xs text-warm-500 dark:text-warm-400 capitalize">{{ auth.user?.roles?.[0] ?? $t('auth.customer') }} ({{ auth.user?.email }})</p>
          </div>
          <button
            @click="handleSignOut"
            class="text-xs text-red-600 dark:text-red-400 hover:underline flex items-center gap-1 shrink-0 font-medium"
          >
            <LogOut :size="13" /> {{ $t('auth.signOut') }}
          </button>
        </div>
        <div class="mt-3 pt-3 border-t border-primary-100 dark:border-primary-900/50 flex gap-2">
          <RouterLink
            :to="auth.isAdmin ? '/admin' : (auth.isStaff ? '/staff/kitchen' : '/menu')"
            class="btn-primary !py-1.5 !px-3.5 text-xs font-semibold w-full justify-center"
          >
            {{ $t('auth.goTo', { destination: auth.isAdmin ? $t('auth.adminDashboard') : (auth.isStaff ? $t('auth.staffPortal') : $t('auth.mainMenu')) }) }}
          </RouterLink>
        </div>
      </div>

      <div class="text-center mb-6">
        <RouterLink to="/" class="inline-flex items-center gap-2 text-primary-600 dark:text-primary-400 font-heading font-bold text-2xl group">
          <div class="p-2 rounded-xl bg-primary-50 dark:bg-primary-950/40 group-hover:scale-105 transition-transform">
            <ChefHat :size="26" />
          </div>
          <span>{{ APP_NAME }}</span>
        </RouterLink>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white mt-3">{{ $t('auth.signInTitle') }}</h1>
        <p class="text-warm-500 dark:text-warm-400 mt-1 text-sm">{{ $t('auth.signInSubtitle') }}</p>
      </div>

      <!-- Quick Demo Account Fillers -->
      <div class="mb-5 p-3 rounded-2xl bg-warm-100/70 dark:bg-white/5 border border-warm-200/80 dark:border-white/10">
        <div class="flex items-center gap-1.5 text-xs font-bold text-warm-700 dark:text-warm-300 mb-2">
          <Sparkles :size="13" class="text-primary-500" />
          <span>{{ $t('auth.quickDemoTitle') }}</span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5">
          <button
            v-for="acc in demoAccounts"
            :key="acc.label"
            type="button"
            @click="fillDemo(acc)"
            class="text-[11px] font-semibold px-2 py-1.5 rounded-lg bg-white dark:bg-warm-800 text-warm-800 dark:text-warm-200 border border-warm-200 dark:border-warm-700 hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400 transition-colors shadow-2xs text-center"
          >
            {{ acc.label }}
          </button>
        </div>
      </div>

      <form @submit.prevent="submit" class="space-y-4" novalidate>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('auth.email') }}</label>
          <div class="relative">
            <Mail :size="18" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
            <input v-model="email" type="email" required class="input pl-10" :placeholder="$t('auth.emailPlaceholder')" autocomplete="email" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('auth.password') }}</label>
          <div class="relative">
            <Lock :size="18" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
            <input v-model="password" :type="showPw ? 'text' : 'password'" required class="input pl-10 pr-10" :placeholder="$t('auth.passwordPlaceholder')" autocomplete="current-password" />
            <button type="button" @click="showPw = !showPw" class="absolute right-3 top-1/2 -translate-y-1/2 text-warm-400 hover:text-warm-600">
              <EyeOff v-if="showPw" :size="16" /><Eye v-else :size="16" />
            </button>
          </div>
        </div>

        <p v-if="error" class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-xl px-4 py-2">{{ error }}</p>

        <button type="submit" :disabled="loading" class="btn-primary w-full justify-center py-3 text-base font-semibold shadow-md">
          {{ loading ? $t('auth.signingIn') : $t('auth.signInBtn') }}
        </button>
      </form>

      <p class="text-center text-sm text-warm-500 dark:text-warm-400 mt-6">
        {{ $t('auth.needAccount') }}
        <RouterLink to="/register" class="text-primary-600 dark:text-primary-400 font-semibold hover:underline">{{ $t('auth.createOne') }}</RouterLink>
      </p>
    </div>

    <!-- Bottom subfooter -->
    <div class="py-2 text-center text-xs text-warm-400 shrink-0">
      &copy; {{ new Date().getFullYear() }} {{ APP_NAME }}. {{ $t('auth.allRightsReserved') }}
    </div>
  </div>
</template>
