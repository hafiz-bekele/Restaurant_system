<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { guestApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import { QrCode, Loader, ChefHat, BedDouble } from '@lucide/vue'

const { t } = useI18n()
const route  = useRoute()
const router = useRouter()
const ui     = useUiStore()

const loading = ref(true)
const error   = ref('')
const context = ref(null)

onMounted(async () => {
  try {
    const { data } = await guestApi.resolveQr(route.params.token)
    context.value = data.data

    // Store hotel context for subsequent requests
    if (context.value?.hotel_id) {
      localStorage.setItem('hotel_id', context.value.hotel_id)
    }

    // Auto-redirect based on action
    if (context.value?.type === 'table') {
      // Dine-in: go straight to menu with table context
      router.replace({ name: 'menu', query: { table_id: context.value.table?.id, hotel_id: context.value.hotel_id } })
      return
    }

    if (context.value?.type === 'room') {
      if (context.value.action === 'book_room') {
        // No active reservation — redirect to room booking
        router.replace({ name: 'rooms', query: { hotel_id: context.value.hotel_id } })
        return
      }
      // Active reservation — go to menu for room service
      router.replace({ name: 'menu', query: { hotel_id: context.value.hotel_id, room: context.value.room_number } })
      return
    }

    loading.value = false
  } catch {
    error.value = t('qrLanding.notFoundDesc')
    loading.value = false
  }
})
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-32 flex flex-col items-center justify-center text-center gap-6">
      <!-- Loading Skeleton -->
      <template v-if="loading">
        <div class="card p-8 max-w-sm w-full space-y-4 text-center">
          <div class="skeleton w-24 h-24 mx-auto !rounded-2xl"></div>
          <div class="skeleton h-5 w-44 mx-auto rounded-lg"></div>
          <div class="skeleton h-3.5 w-32 mx-auto rounded"></div>
        </div>
      </template>

      <!-- Error state -->
      <template v-else-if="error">
        <QrCode :size="56" class="text-warm-300" />
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white">{{ $t('qrLanding.notFoundTitle') }}</h1>
        <p class="text-warm-500 dark:text-warm-400 max-w-sm">{{ error }}</p>
        <RouterLink to="/" class="btn-primary">{{ $t('qrLanding.goHome') }}</RouterLink>
      </template>

      <!-- Disambiguation (only shown if auto-redirect was not possible) -->
      <template v-else-if="context">
        <div class="card p-8 max-w-sm w-full space-y-4">
          <img v-if="context.logo_url" :src="context.logo_url" :alt="context.hotel_name" class="h-12 mx-auto object-contain" />
          <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white">{{ context.hotel_name }}</h1>
          <p class="text-warm-500 dark:text-warm-400 text-sm">{{ $t('qrLanding.whatToDo') }}</p>
          <RouterLink :to="{ name: 'menu', query: { hotel_id: context.hotel_id } }" class="btn-primary w-full justify-center gap-2">
            <ChefHat :size="18" /> {{ $t('qrLanding.browseMenu') }}
          </RouterLink>
          <RouterLink :to="{ name: 'rooms', query: { hotel_id: context.hotel_id } }" class="btn-outline w-full justify-center gap-2">
            <BedDouble :size="18" /> {{ $t('qrLanding.bookRoom') }}
          </RouterLink>
        </div>
      </template>
    </div>
  </DefaultLayout>
</template>
