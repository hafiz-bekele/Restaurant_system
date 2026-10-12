<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'

const ui      = useUiStore()
const loading = ref(true)
const error   = ref(false)
const saving  = ref(false)
const settings = ref({ restaurant_name: '', tax_rate: 0.15, service_charge_rate: 0.10, currency: 'USD', currency_symbol: '$' })

onMounted(async () => {
  try {
    const { data } = await adminApi.settings()
    Object.assign(settings.value, data.data)
  } catch {
    error.value = true
    ui.toast('Failed to load settings', 'error')
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  try {
    await adminApi.saveSettings(settings.value)
    ui.toast('Settings saved successfully', 'success')
  } catch {
    ui.toast('Failed to save settings', 'error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white">Settings</h1>
      <p class="text-sm text-warm-500 dark:text-warm-400 mt-0.5">Configure restaurant name, tax rates, and currency</p>
    </div>

    <div v-if="loading" class="space-y-4">
      <div v-for="i in 4" :key="i" class="skeleton h-16 rounded-2xl"></div>
    </div>

    <div v-else-if="error" class="card p-10 text-center">
      <p class="text-warm-500 dark:text-warm-400 mb-4">Failed to load settings. Check your connection and try again.</p>
      <button @click="error = false; loading = true; onMounted()" class="btn-outline">Retry</button>
    </div>

    <form v-else @submit.prevent="save" class="card p-8 max-w-xl space-y-5">
      <div>
        <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">Restaurant / Brand Name</label>
        <input v-model="settings.restaurant_name" type="text" class="input" placeholder="e.g. Taste & Stay" />
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">Tax Rate</label>
          <div class="relative">
            <input v-model.number="settings.tax_rate" type="number" step="0.01" min="0" max="1" class="input pr-8" placeholder="0.15" />
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-warm-400 pointer-events-none">= {{ (settings.tax_rate * 100).toFixed(0) }}%</span>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">Service Charge</label>
          <div class="relative">
            <input v-model.number="settings.service_charge_rate" type="number" step="0.01" min="0" max="1" class="input pr-8" placeholder="0.10" />
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-warm-400 pointer-events-none">= {{ (settings.service_charge_rate * 100).toFixed(0) }}%</span>
          </div>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">Currency Code</label>
          <input v-model="settings.currency" type="text" class="input" placeholder="USD" maxlength="4" />
        </div>
        <div>
          <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">Currency Symbol</label>
          <input v-model="settings.currency_symbol" type="text" class="input" placeholder="$" maxlength="3" />
        </div>
      </div>
      <div class="pt-2 border-t border-warm-100 dark:border-warm-800">
        <button type="submit" :disabled="saving" class="btn-primary">
          {{ saving ? 'Saving…' : 'Save Settings' }}
        </button>
      </div>
    </form>
  </div>
</template>
