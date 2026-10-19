<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import ThemeToggle from '../../components/ui/ThemeToggle.vue'
import { Palette, Sun, Moon } from '@lucide/vue'

const { t } = useI18n()
const ui      = useUiStore()
const loading = ref(true)
const error   = ref(false)
const saving  = ref(false)
const settings = ref({ restaurant_name: '', tax_rate: 0.15, service_charge_rate: 0.10, currency: 'ETB', currency_symbol: 'ETB' })

async function loadSettings() {
  loading.value = true
  error.value = false
  try {
    const { data } = await adminApi.settings()
    Object.assign(settings.value, data.data)
  } catch {
    error.value = true
    ui.toast(t('adminSettings.toastLoadFailed'), 'error')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadSettings()
})

async function save() {
  saving.value = true
  try {
    await adminApi.saveSettings(settings.value)
    ui.toast(t('adminSettings.toastSaved'), 'success')
  } catch {
    ui.toast(t('adminSettings.toastFailed'), 'error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-6 max-w-2xl">
    <div>
      <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white">{{ $t('adminSettings.title') }}</h1>
      <p class="text-sm text-warm-500 dark:text-warm-400 mt-0.5">{{ $t('adminSettings.subtitle') }}</p>
    </div>

    <!-- Appearance Card -->
    <div class="card p-6 space-y-4">
      <div class="flex items-center gap-2.5">
        <div class="p-2 rounded-xl bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400">
          <Palette :size="18" />
        </div>
        <div>
          <h2 class="font-heading font-bold text-base text-warm-900 dark:text-white">{{ $t('adminSettings.themeCardTitle') }}</h2>
          <p class="text-xs text-warm-500 dark:text-warm-400">{{ $t('adminSettings.themeCardSubtitle') }}</p>
        </div>
      </div>

      <div class="flex items-center justify-between p-4 rounded-xl bg-warm-50 dark:bg-warm-800/50 border border-warm-100 dark:border-warm-700/60">
        <div class="space-y-0.5">
          <p class="text-sm font-semibold text-warm-900 dark:text-white flex items-center gap-2">
            <Sun v-if="!ui.darkMode" :size="15" class="text-amber-500" />
            <Moon v-else :size="15" class="text-primary-400" />
            {{ $t('adminSettings.appearanceTitle') }}
          </p>
          <p class="text-xs text-warm-500 dark:text-warm-400">
            {{ ui.darkMode ? $t('adminSettings.darkModeEnabled') : $t('adminSettings.lightModeEnabled') }}
          </p>
        </div>
        <ThemeToggle variant="segmented" />
      </div>
    </div>

    <div v-if="loading" class="space-y-4">
      <div v-for="i in 4" :key="i" class="skeleton h-16 rounded-2xl"></div>
    </div>

    <div v-else-if="error" class="card p-10 text-center">
      <p class="text-warm-500 dark:text-warm-400 mb-4">{{ $t('adminSettings.loadingFailed') }}</p>
      <button @click="loadSettings" class="btn-outline">{{ $t('adminSettings.retryBtn') }}</button>
    </div>

    <form v-else @submit.prevent="save" class="card p-6 sm:p-8 space-y-5">
      <div>
        <h2 class="font-heading font-bold text-base text-warm-900 dark:text-white mb-1">{{ $t('adminSettings.financialCardTitle') }}</h2>
        <p class="text-xs text-warm-500 dark:text-warm-400 mb-4">{{ $t('adminSettings.financialCardSubtitle') }}</p>
      </div>

      <div>
        <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminSettings.restaurantName') }}</label>
        <input v-model="settings.restaurant_name" type="text" class="input" placeholder="e.g. Taste & Stay" />
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminSettings.taxRate') }}</label>
          <div class="relative">
            <input v-model.number="settings.tax_rate" type="number" step="0.01" min="0" max="1" class="input pr-8" placeholder="0.15" />
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-warm-400 pointer-events-none">= {{ (settings.tax_rate * 100).toFixed(0) }}%</span>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminSettings.serviceCharge') }}</label>
          <div class="relative">
            <input v-model.number="settings.service_charge_rate" type="number" step="0.01" min="0" max="1" class="input pr-8" placeholder="0.10" />
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-warm-400 pointer-events-none">= {{ (settings.service_charge_rate * 100).toFixed(0) }}%</span>
          </div>
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminSettings.currencyCode') }}</label>
          <input v-model="settings.currency" type="text" class="input" placeholder="ETB" maxlength="6" />
        </div>
        <div>
          <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminSettings.currencySymbol') }}</label>
          <input v-model="settings.currency_symbol" type="text" class="input" placeholder="ETB" maxlength="6" />
        </div>
      </div>
      <div class="pt-2 border-t border-warm-100 dark:border-warm-800">
        <button type="submit" :disabled="saving" class="btn-primary">
          {{ saving ? $t('adminSettings.savingBtn') : $t('adminSettings.saveBtn') }}
        </button>
      </div>
    </form>
  </div>
</template>
