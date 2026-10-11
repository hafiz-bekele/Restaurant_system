<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { guestApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import { QrCode, Loader, ChefHat, BedDouble } from '@lucide/vue'

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
    error.value = 'QR code not recognised or has expired.'
    loading.value = false
  }
})
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-32 flex flex-col items-center justify-center text-center gap-6">
      <!-- Loading spinner -->
      <template v-if="loading">
        <Loader :size="48" class="text-primary-500 animate-spin" />
        <p class="text-warm-600 dark:text-warm-300 text-lg">Scanning QR code…</p>
      </template>

      <!-- Error state -->
      <template v-else-if="error">
        <QrCode :size="56" class="text-warm-300" />
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white">QR Code Not Found</h1>
        <p class="text-warm-500 dark:text-warm-400 max-w-sm">{{ error }}</p>
        <RouterLink to="/" class="btn-primary">Go to Homepage</RouterLink>
      </template>

      <!-- Disambiguation (only shown if auto-redirect was not possible) -->
      <template v-else-if="context">
        <div class="card p-8 max-w-sm w-full space-y-4">
          <img v-if="context.logo_url" :src="context.logo_url" :alt="context.hotel_name" class="h-12 mx-auto object-contain" />
          <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white">{{ context.hotel_name }}</h1>
          <p class="text-warm-500 dark:text-warm-400 text-sm">What would you like to do?</p>
          <RouterLink :to="{ name: 'menu', query: { hotel_id: context.hotel_id } }" class="btn-primary w-full justify-center gap-2">
            <ChefHat :size="18" /> Browse Menu
          </RouterLink>
          <RouterLink :to="{ name: 'rooms', query: { hotel_id: context.hotel_id } }" class="btn-outline w-full justify-center gap-2">
            <BedDouble :size="18" /> Book a Room
          </RouterLink>
        </div>
      </template>
    </div>
  </DefaultLayout>
</template>
