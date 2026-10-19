<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import { guestApi } from '../../services/api'

const { t } = useI18n()
const form = ref({ name: '', email: '', subject: '', message: '' })
const sent = ref(false)
const loading = ref(false)
const error = ref('')

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await guestApi.contact(form.value)
    sent.value = true
  } catch (e) {
    error.value = e.response?.data?.message ?? t('contactPage.errorMessage')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-16 max-w-2xl">
      <h1 class="font-heading text-4xl font-bold text-warm-900 dark:text-white mb-2">{{ $t('contactPage.title') }}</h1>
      <p class="text-warm-500 dark:text-warm-400 mb-8">{{ $t('contactPage.subtitle') }}</p>

      <div v-if="sent" class="card p-10 text-center space-y-3">
        <div class="w-14 h-14 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mx-auto">
          <svg class="w-7 h-7 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <p class="font-heading font-bold text-lg text-warm-900 dark:text-white">{{ $t('contactPage.messageSent') }}</p>
        <p class="text-warm-500 dark:text-warm-400 text-sm">{{ $t('contactPage.thankYouResponse') }}</p>
        <button @click="sent = false; form = { name: '', email: '', subject: '', message: '' }" class="btn-outline text-sm mt-2">
          {{ $t('contactPage.sendAnother') }}
        </button>
      </div>

      <form v-else @submit.prevent="submit" class="card p-8 space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('contactPage.fullName') }} <span class="text-red-500">*</span></label>
            <input v-model="form.name" type="text" required class="input" :placeholder="$t('contactPage.fullNamePlaceholder')" autocomplete="name" />
          </div>
          <div>
            <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('contactPage.email') }} <span class="text-red-500">*</span></label>
            <input v-model="form.email" type="email" required class="input" :placeholder="$t('contactPage.emailPlaceholder')" autocomplete="email" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('contactPage.subject') }}</label>
          <input v-model="form.subject" type="text" class="input" :placeholder="$t('contactPage.subjectPlaceholder')" />
        </div>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('contactPage.message') }} <span class="text-red-500">*</span></label>
          <textarea v-model="form.message" required rows="5" class="input resize-none" :placeholder="$t('contactPage.messagePlaceholder')"></textarea>
        </div>

        <p v-if="error" class="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-xl px-4 py-2">{{ error }}</p>

        <button type="submit" :disabled="loading" class="btn-primary w-full justify-center py-3 font-semibold">
          {{ loading ? $t('contactPage.sending') : $t('contactPage.sendMessage') }}
        </button>
      </form>
    </div>
  </DefaultLayout>
</template>
