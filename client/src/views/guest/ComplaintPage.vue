<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { guestApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import { AlertTriangle, CheckCircle } from '@lucide/vue'

const route = useRoute()
const ui    = useUiStore()

const loading = ref(false)
const done    = ref(false)
const ticket  = ref(null)

const form = ref({
  hotel_id:    Number(localStorage.getItem('hotel_id') || 1),
  order_id:    route.query.order_id ? Number(route.query.order_id) : undefined,
  category:    'other',
  subject:     '',
  description: '',
})

const categories = [
  { value: 'late_food',      label: 'Late Food' },
  { value: 'cold_dish',      label: 'Cold Dish' },
  { value: 'missing_item',   label: 'Missing Item' },
  { value: 'staff_behavior', label: 'Staff Behavior' },
  { value: 'other',          label: 'Other' },
]

async function submit() {
  loading.value = true
  try {
    const { data } = await guestApi.submitComplaint(form.value)
    ticket.value = data.data
    done.value   = true
    ui.toast('Complaint submitted', 'success')
  } catch (e) {
    ui.toast(e.response?.data?.message ?? 'Failed to submit complaint', 'error')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-12 max-w-lg">
      <h1 class="font-heading text-3xl font-bold text-warm-900 dark:text-white mb-2 flex items-center gap-2">
        <AlertTriangle :size="28" class="text-orange-500" /> Submit a Complaint
      </h1>
      <p class="text-warm-500 dark:text-warm-400 mb-8">We're sorry to hear you had an issue. We'll look into it right away.</p>

      <!-- Success state -->
      <div v-if="done" class="card p-10 text-center space-y-4">
        <CheckCircle :size="52" class="mx-auto text-green-500" />
        <h2 class="font-heading text-xl font-bold text-warm-900 dark:text-white">Complaint Received</h2>
        <p class="text-warm-500">Your ticket number is:</p>
        <p class="font-mono text-2xl font-bold text-primary-600 dark:text-primary-400">{{ ticket?.ticket_number }}</p>
        <p class="text-sm text-warm-400">You can use this number to track your complaint status.</p>
        <button @click="done = false" class="btn-outline">Submit Another</button>
      </div>

      <!-- Form -->
      <form v-else @submit.prevent="submit" class="card p-8 space-y-5">
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Category</label>
          <div class="grid grid-cols-2 gap-2">
            <label
              v-for="cat in categories" :key="cat.value"
              :class="['flex items-center gap-2 p-3 rounded-xl border-2 cursor-pointer text-sm font-medium transition-colors', form.category === cat.value ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300' : 'border-warm-200 dark:border-warm-700 text-warm-600 dark:text-warm-400 hover:border-warm-300']"
            >
              <input type="radio" v-model="form.category" :value="cat.value" class="sr-only" />
              {{ cat.label }}
            </label>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Subject</label>
          <input v-model="form.subject" type="text" required maxlength="255" class="input" placeholder="Brief description of the issue" />
        </div>

        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Details (optional)</label>
          <textarea v-model="form.description" rows="4" maxlength="1000" class="input resize-none" placeholder="Tell us more about what happened…"></textarea>
        </div>

        <button type="submit" :disabled="loading" class="btn-primary w-full justify-center py-3">
          {{ loading ? 'Submitting…' : 'Submit Complaint' }}
        </button>
      </form>
    </div>
  </DefaultLayout>
</template>
