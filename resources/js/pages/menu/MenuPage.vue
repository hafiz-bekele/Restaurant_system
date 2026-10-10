<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import { Search, SlidersHorizontal } from '@lucide/vue'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import DishCard from '../../components/menu/DishCard.vue'
import { menuApi } from '../../services/api'

const route  = useRoute()
const router = useRouter()

const search   = ref(route.query.search ?? '')
const category = ref(route.query.category ?? '')
const veg      = ref(false)
const vegan    = ref(false)
const sort     = ref('popular')

const { data: categories } = useQuery({
  queryKey: ['categories'],
  queryFn: () => menuApi.categories().then(r => r.data.data),
  staleTime: 3600000,
})

const queryParams = computed(() => ({
  search:    search.value || undefined,
  category:  category.value || undefined,
  vegetarian: veg.value || undefined,
  vegan:      vegan.value || undefined,
  sort:       sort.value,
}))

const { data: dishData, isLoading } = useQuery({
  queryKey: ['dishes', queryParams],
  queryFn:  () => menuApi.dishes(queryParams.value).then(r => r.data),
  keepPreviousData: true,
})

const dishes = computed(() => dishData.value?.data ?? [])
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-10">
      <h1 class="font-heading text-4xl font-bold text-warm-900 dark:text-white mb-8">Our Menu</h1>

      <!-- Search + Filters -->
      <div class="flex flex-wrap gap-4 mb-8">
        <div class="relative flex-1 min-w-64">
          <Search :size="18" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
          <input v-model="search" type="search" placeholder="Search dishes..." class="input pl-10" />
        </div>
        <select v-model="sort" class="input w-auto">
          <option value="popular">Most Popular</option>
          <option value="price_asc">Price: Low to High</option>
          <option value="price_desc">Price: High to Low</option>
          <option value="rating">Top Rated</option>
        </select>
      </div>

      <!-- Category chips -->
      <div class="flex flex-wrap gap-2 mb-8">
        <button @click="category = ''" :class="['px-4 py-1.5 rounded-full text-sm font-medium transition-colors', !category ? 'bg-primary-500 text-white' : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-warm-200 dark:hover:bg-warm-700']">
          All
        </button>
        <button v-for="cat in (categories ?? [])" :key="cat.id"
          @click="category = category === cat.slug ? '' : cat.slug"
          :class="['px-4 py-1.5 rounded-full text-sm font-medium transition-colors', category === cat.slug ? 'bg-primary-500 text-white' : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-warm-200 dark:hover:bg-warm-700']"
        >{{ cat.name }}</button>
      </div>

      <!-- Toggle filters -->
      <div class="flex gap-3 mb-8">
        <button @click="veg = !veg"   :class="['px-3 py-1 rounded-full text-xs font-semibold transition-colors', veg   ? 'bg-green-500 text-white' : 'badge-green']">Vegetarian</button>
        <button @click="vegan = !vegan" :class="['px-3 py-1 rounded-full text-xs font-semibold transition-colors', vegan ? 'bg-green-600 text-white' : 'badge-green']">Vegan</button>
      </div>

      <!-- Skeleton -->
      <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <div v-for="i in 8" :key="i" class="card overflow-hidden">
          <div class="skeleton aspect-[4/3]"></div>
          <div class="p-4 space-y-2">
            <div class="skeleton h-5 w-3/4"></div>
            <div class="skeleton h-4 w-full"></div>
          </div>
        </div>
      </div>

      <!-- Grid -->
      <div v-else-if="dishes.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <DishCard v-for="dish in dishes" :key="dish.id" :dish="dish" />
      </div>

      <!-- Empty -->
      <div v-else class="text-center py-20 text-warm-400">
        <Search :size="48" class="mx-auto mb-4 opacity-40" />
        <p class="text-lg font-medium">No dishes found</p>
        <p class="text-sm mt-1">Try adjusting your filters</p>
      </div>
    </div>
  </DefaultLayout>
</template>
