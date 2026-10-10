<script setup>
import { useQuery } from '@tanstack/vue-query'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import DishCard from '../../components/menu/DishCard.vue'
import { profileApi } from '../../services/api'
import { Heart } from '@lucide/vue'

const { data, isLoading } = useQuery({
  queryKey: ['favorites'],
  queryFn:  () => profileApi.favorites().then(r => r.data),
})
const dishes = () => data.value?.data ?? []
</script>
<template>
  <DefaultLayout>
    <div class="page-container py-10">
      <h1 class="font-heading text-3xl font-bold text-warm-900 dark:text-white mb-8 flex items-center gap-3">
        <Heart :size="28" class="text-red-500 fill-red-500" /> Favorites
      </h1>
      <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div v-for="i in 4" :key="i" class="skeleton h-72 rounded-2xl"></div>
      </div>
      <div v-else-if="dishes().length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <DishCard v-for="dish in dishes()" :key="dish.id" :dish="dish" />
      </div>
      <div v-else class="text-center py-20">
        <Heart :size="48" class="mx-auto text-warm-300 mb-4" />
        <p class="text-warm-500">No favorites yet. Start adding dishes!</p>
      </div>
    </div>
  </DefaultLayout>
</template>
