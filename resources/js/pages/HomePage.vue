<script setup>
import { useQuery } from '@tanstack/vue-query'
import { RouterLink } from 'vue-router'
import { ArrowRight, Star, Clock, MapPin } from '@lucide/vue'
import DefaultLayout from '../layouts/DefaultLayout.vue'
import { menuApi } from '../services/api'
import DishCard from '../components/menu/DishCard.vue'

const { data: categoriesData } = useQuery({
  queryKey: ['categories'],
  queryFn:  () => menuApi.categories().then(r => r.data.data),
  staleTime: 3600000,
})

const { data: featuredData, isLoading } = useQuery({
  queryKey: ['dishes', 'featured'],
  queryFn:  () => menuApi.featured().then(r => r.data.data),
  staleTime: 900000,
})
</script>

<template>
  <DefaultLayout>
    <!-- Hero -->
    <section class="relative overflow-hidden bg-gradient-to-br from-warm-900 via-warm-800 to-primary-900 text-white min-h-[90vh] flex items-center">
      <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=1600')] bg-cover bg-center opacity-20"></div>
      <div class="page-container relative z-10 py-24">
        <div class="max-w-2xl">
          <span class="badge badge-orange mb-4">Fresh &amp; Delicious</span>
          <h1 class="font-heading text-5xl md:text-7xl font-bold leading-tight mb-6">
            Every Bite<br><span class="text-primary-400">Tells a Story</span>
          </h1>
          <p class="text-lg text-warm-300 mb-10 max-w-lg leading-relaxed">
            From our kitchen to your table — crafted with the finest ingredients, served with love.
          </p>
          <div class="flex flex-wrap gap-4">
            <RouterLink to="/menu" class="btn-primary text-base px-8 py-3">
              Order Now <ArrowRight :size="18" />
            </RouterLink>
            <RouterLink to="/reservations" class="btn-outline text-base px-8 py-3 border-white text-white hover:bg-white/10">
              Reserve a Table
            </RouterLink>
          </div>
          <div class="mt-12 flex gap-8">
            <div class="flex items-center gap-2 text-sm text-warm-300">
              <Star :size="18" class="text-yellow-400 fill-yellow-400" />
              <span class="font-semibold text-white">4.9</span> Rating
            </div>
            <div class="flex items-center gap-2 text-sm text-warm-300">
              <Clock :size="18" class="text-primary-400" />
              15-30 min delivery
            </div>
            <div class="flex items-center gap-2 text-sm text-warm-300">
              <MapPin :size="18" class="text-primary-400" />
              Free delivery over $30
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Categories -->
    <section class="py-16 page-container">
      <h2 class="font-heading text-3xl font-bold text-warm-900 dark:text-white mb-8">Explore Categories</h2>
      <div class="flex gap-4 overflow-x-auto pb-4 scrollbar-hide">
        <RouterLink
          v-for="cat in (categoriesData ?? [])" :key="cat.id"
          :to="`/menu?category=${cat.slug}`"
          class="flex flex-col items-center gap-2 shrink-0 group"
        >
          <div class="w-20 h-20 rounded-2xl overflow-hidden bg-warm-100 dark:bg-warm-800 group-hover:ring-2 ring-primary-500 transition-all">
            <img v-if="cat.image" :src="cat.image" :alt="cat.name" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300" />
            <div v-else class="w-full h-full flex items-center justify-center text-2xl text-warm-400">?</div>
          </div>
          <span class="text-xs font-medium text-warm-700 dark:text-warm-300">{{ cat.name }}</span>
        </RouterLink>
      </div>
    </section>

    <!-- Featured dishes -->
    <section class="py-16 bg-warm-50 dark:bg-gray-900/50">
      <div class="page-container">
        <div class="flex items-center justify-between mb-8">
          <h2 class="font-heading text-3xl font-bold text-warm-900 dark:text-white">Featured Dishes</h2>
          <RouterLink to="/menu" class="btn-ghost text-sm text-primary-600 dark:text-primary-400 flex items-center gap-1">
            View all <ArrowRight :size="16" />
          </RouterLink>
        </div>

        <!-- Skeleton -->
        <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div v-for="i in 4" :key="i" class="card overflow-hidden">
            <div class="skeleton h-48 rounded-none"></div>
            <div class="p-4 space-y-2">
              <div class="skeleton h-5 w-3/4"></div>
              <div class="skeleton h-4 w-full"></div>
              <div class="skeleton h-4 w-1/2"></div>
            </div>
          </div>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <DishCard v-for="dish in (featuredData ?? [])" :key="dish.id" :dish="dish" />
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-primary-600 text-white">
      <div class="page-container text-center">
        <h2 class="font-heading text-4xl font-bold mb-4">Ready to Book Your Table?</h2>
        <p class="text-primary-100 mb-8 text-lg">Reserve your spot and enjoy a memorable dining experience.</p>
        <RouterLink to="/reservations" class="inline-flex items-center gap-2 bg-white text-primary-600 font-semibold px-8 py-3 rounded-xl hover:bg-primary-50 transition-colors shadow-float">
          Make a Reservation <ArrowRight :size="18" />
        </RouterLink>
      </div>
    </section>
  </DefaultLayout>
</template>
