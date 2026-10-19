<script setup>
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { guestApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import { AlertTriangle, CheckCircle } from '@lucide/vue'

const { t } = useI18n()
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

const categories = computed(() => [
  { value: 'late_food',      label: t('guestComplaint.catLateFood') },
  { value: 'cold_dish',      label: t('guestComplaint.catColdDish') },
  { value: 'missing_item',   label: t('guestComplaint.catMissingItem') },
  { value: 'staff_behavior', label: t('guestComplaint.catStaffBehavior') },
  { value: 'other',          label: t('guestComplaint.catOther') },
])

async function submit() {
  loading.value = true
  try {
    const { data } = await guestApi.submitComplaint(form.value)
    ticket.value = data.data
    done.value   = true
    ui.toast(t('guestComplaint.toastSubmitted'), 'success')
  } catch (e) {
    ui.toast(e.response?.data?.message ?? t('guestComplaint.toastFailed'), 'error')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-12 max-w-lg">
      <h1 class="font-heading text-3xl font-bold text-warm-900 dark:text-white mb-2 flex items-center gap-2">
        <AlertTriangle :size="28" class="text-orange-500" /> {{ $t('guestComplaint.title') }}
      </h1>
      <p class="text-warm-500 dark:text-warm-400 mb-8">{{ $t('guestComplaint.subtitle') }}</p>

      <!-- Success state -->
      <div v-if="done" class="card p-10 text-center space-y-4">
        <CheckCircle :size="52" class="mx-auto text-green-500" />
        <h2 class="font-heading text-xl font-bold text-warm-900 dark:text-white">{{ $t('guestComplaint.receivedTitle') }}</h2>
        <p class="text-warm-500">{{ $t('guestComplaint.ticketNumberText') }}</p>
        <p class="font-mono text-2xl font-bold text-primary-600 dark:text-primary-400">{{ ticket?.ticket_number }}</p>
        <p class="text-sm text-warm-400">{{ $t('guestComplaint.trackStatusText') }}</p>
        <button @click="done = false" class="btn-outline">{{ $t('guestComplaint.submitAnother') }}</button>
      </div>

      <!-- Form -->
      <form v-else @submit.prevent="submit" class="card p-8 space-y-5">
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('guestComplaint.categoryLabel') }}</label>
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
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('guestComplaint.subjectLabel') }}</label>
          <input v-model="form.subject" type="text" required maxlength="255" class="input" :placeholder="$t('guestComplaint.subjectPlaceholder')" />
        </div>

        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('guestComplaint.detailsLabel') }}</label>
          <textarea v-model="form.description" rows="4" maxlength="1000" class="input resize-none" :placeholder="$t('guestComplaint.detailsPlaceholder')"></textarea>
        </div>

        <button type="submit" :disabled="loading" class="btn-primary w-full justify-center py-3">
          {{ loading ? $t('guestComplaint.submittingBtn') : $t('guestComplaint.submitBtn') }}
        </button>
      </form>
    </div>
  </DefaultLayout>
</template>
