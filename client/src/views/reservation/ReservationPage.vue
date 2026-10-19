<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { reservationApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import { Calendar, Users, Clock } from '@lucide/vue'

const { t }   = useI18n()
const route   = useRoute()
const ui      = useUiStore()
const loading = ref(false)
const done    = ref(false)
const form    = ref({
  name: '',
  email: '',
  phone: '',
  party_size: Number(route.query.party_size) || 2,
  reserved_at: typeof route.query.reserved_at === 'string' ? route.query.reserved_at : '',
  notes: typeof route.query.notes === 'string' ? route.query.notes : ''
})

async function submit() {
  loading.value = true
  try {
    const avail = await reservationApi.availability({ date: form.value.reserved_at, party_size: form.value.party_size })
    const tables = avail.data.data
    if (!tables.length) { ui.toast(t('reservationPage.noTablesWarning'), 'warning'); return }
    await reservationApi.create({ ...form.value, table_id: tables[0].id })
    done.value = true
    ui.toast(t('reservationPage.successToast'), 'success')
  } catch (e) {
    ui.toast(e.response?.data?.message ?? t('reservationPage.failedToast'), 'error')
  } finally {
    loading.value = false
  }
}
</script>
<template>
  <DefaultLayout>
    <div class="page-container py-12 max-w-lg">
      <h1 class="font-heading text-4xl font-bold text-warm-900 dark:text-white mb-2">{{ $t('reservationPage.title') }}</h1>
      <p class="text-warm-500 dark:text-warm-400 mb-8">{{ $t('reservationPage.subtitle') }}</p>

      <div v-if="done" class="card p-10 text-center">
        <Calendar :size="48" class="mx-auto text-primary-500 mb-4" />
        <h2 class="font-heading text-2xl font-bold text-warm-900 dark:text-white mb-2">{{ $t('reservationPage.confirmedTitle') }}</h2>
        <p class="text-warm-500">{{ $t('reservationPage.confirmationSent', { email: form.email }) }}</p>
        <button @click="done = false" class="btn-outline mt-6">{{ $t('reservationPage.makeAnother') }}</button>
      </div>

      <form v-else @submit.prevent="submit" class="card p-8 space-y-5">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('reservationPage.fullName') }}</label>
            <input v-model="form.name" type="text" required class="input" :placeholder="$t('reservationPage.namePlaceholder')" />
          </div>
          <div>
            <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('reservationPage.phone') }}</label>
            <input v-model="form.phone" type="tel" required class="input" :placeholder="$t('reservationPage.phonePlaceholder')" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('reservationPage.email') }}</label>
          <input v-model="form.email" type="email" required class="input" :placeholder="$t('reservationPage.emailPlaceholder')" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5 flex items-center gap-1"><Users :size="14"/> {{ $t('reservationPage.partySize') }}</label>
            <input v-model.number="form.party_size" type="number" min="1" max="20" required class="input" />
          </div>
          <div>
            <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5 flex items-center gap-1"><Calendar :size="14"/> {{ $t('reservationPage.dateTime') }}</label>
            <input v-model="form.reserved_at" type="datetime-local" required class="input" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('reservationPage.specialRequests') }}</label>
          <textarea v-model="form.notes" rows="3" class="input resize-none" :placeholder="$t('reservationPage.requestsPlaceholder')"></textarea>
        </div>
        <button type="submit" :disabled="loading" class="btn-primary w-full justify-center py-3">
          {{ loading ? $t('reservationPage.checking') : $t('reservationPage.reserveBtn') }}
        </button>
      </form>
    </div>
  </DefaultLayout>
</template>
