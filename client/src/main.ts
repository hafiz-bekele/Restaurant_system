import './bootstrap'
import './assets/app.css'

// Apply dark mode before Vue mounts — prevents white flash on dark mode reload
;(function () {
  const saved = localStorage.getItem('darkMode')
  const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches
  const isDark = saved !== null ? saved === 'true' : prefersDark
  if (isDark) {
    document.documentElement.classList.add('dark')
    document.documentElement.style.colorScheme = 'dark'
    document.body.style.backgroundColor = '#030712'
  } else {
    document.body.style.backgroundColor = '#fafaf9'
  }
})()

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import piniaPersistedState from 'pinia-plugin-persistedstate'
import { VueQueryPlugin } from '@tanstack/vue-query'
import { createI18n } from 'vue-i18n'

import App from './App.vue'
import router from './router'
import en from './i18n/locales/en'
import am from './i18n/locales/am'

const pinia = createPinia()
pinia.use(piniaPersistedState)

const currentLocale = localStorage.getItem('locale') || 'am'
document.documentElement.lang = currentLocale

const i18n = createI18n({
  legacy: false,
  locale: currentLocale,
  fallbackLocale: 'am',
  messages: { en, am },
})

import { useAuthStore } from './stores/auth'

const app = createApp(App)
app.use(pinia)
app.use(router)
app.use(VueQueryPlugin)
app.use(i18n)

// Global permission directive: v-permission="'users.create'" or v-permission="['orders.view', 'orders.create']"
app.directive('permission', {
  mounted(el, binding) {
    const auth = useAuthStore()
    const { value } = binding
    if (value) {
      const allowed = auth.hasPermission(value)
      if (!allowed) {
        el.style.display = 'none'
        el.setAttribute('aria-hidden', 'true')
      }
    }
  },
  updated(el, binding) {
    const auth = useAuthStore()
    const { value } = binding
    if (value) {
      const allowed = auth.hasPermission(value)
      if (!allowed) {
        el.style.display = 'none'
        el.setAttribute('aria-hidden', 'true')
      } else {
        el.style.removeProperty('display')
        el.removeAttribute('aria-hidden')
      }
    }
  },
})

app.mount('#app')
