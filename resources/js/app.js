import './bootstrap'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import piniaPersistedState from 'pinia-plugin-persistedstate'
import { VueQueryPlugin } from '@tanstack/vue-query'
import { createI18n } from 'vue-i18n'
import router from './router'
import App from './App.vue'
import en from './i18n/locales/en.js'
import am from './i18n/locales/am.js'

const pinia = createPinia()
pinia.use(piniaPersistedState)

const i18n = createI18n({
  legacy: false,
  locale: localStorage.getItem('locale') || 'en',
  fallbackLocale: 'en',
  messages: { en, am },
})

const app = createApp(App)
app.use(pinia)
app.use(router)
app.use(VueQueryPlugin)
app.use(i18n)
app.mount('#app')
