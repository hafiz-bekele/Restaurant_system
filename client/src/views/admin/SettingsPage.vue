<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'

const ui      = useUiStore()
const loading = ref(true)
const saving  = ref(false)
const settings = ref({ restaurant_name: '', tax_rate: 0.15, currency: 'USD' })

onMounted(async () => {
  const { data } = await adminApi.settings()
  Object.assign(settings.value, data.data)
  loading.value = false
})

async function save() {
  saving.value = true
  try {
    await adminApi.saveSettings(settings.value)
    ui.toast('Settings saved', 'success')
  } catch { ui.toast('Failed to save', 'error') }
  finally { saving.value = false }
}
</script>
<template>
  <div>
    <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white mb-6">Settings</h1>
    <div v-if="loading" class="skeleton h-40 rounded-2xl"></div>
    <form v-else @submit.prevent="save" class="card p-8 max-w-xl space-y-5">
      <div>
        <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Restaurant Name</label>
        <input v-model="settings.restaurant_name" type="text" class="input" />
      </div>
      <div>
        <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Tax Rate (decimal, e.g. 0.15 = 15%)</label>
        <input v-model.number="settings.tax_rate" type="number" step="0.01" min="0" max="1" class="input" />
      </div>
      <div>
        <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Currency</label>
        <input v-model="settings.currency" type="text" class="input" placeholder="USD" />
      </div>
      <button type="submit" :disabled="saving" class="btn-primary">{{ saving ? 'Saving...' : 'Save Settings' }}</button>
    </form>
  </div>
</template>
