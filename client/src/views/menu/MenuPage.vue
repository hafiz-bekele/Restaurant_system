<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import { Search, SlidersHorizontal, X, UtensilsCrossed, Leaf, ChevronDown } from '@lucide/vue'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import DishCard from '../../components/menu/DishCard.vue'
import DishCardSkeleton from '../../components/ui/DishCardSkeleton.vue'
import { menuApi } from '../../services/api'

const route  = useRoute()
const router = useRouter()

const search   = ref(route.query.search   ?? '')
const category = ref(route.query.category ?? '')
const veg      = ref(false)
const vegan    = ref(false)
const sort     = ref('popular')
const showFilters = ref(false)  // mobile filter panel toggle

// Sync URL on filter changes
watch([search, category], ([s, c]) => {
  router.replace({ query: { ...(s ? { search: s } : {}), ...(c ? { category: c } : {}) } })
})

const { data: categories } = useQuery({
  queryKey: ['categories'],
  queryFn:  () => menuApi.categories().then(r => r.data.data),
  staleTime: 3600000,
})

const queryParams = computed(() => ({
  search:     search.value   || undefined,
  category:   category.value || undefined,
  vegetarian: veg.value      || undefined,
  vegan:      vegan.value    || undefined,
  sort:       sort.value,
}))

const { data: dishData, isLoading } = useQuery({
  queryKey: ['dishes', queryParams],
  queryFn:  () => menuApi.dishes(queryParams.value).then(r => r.data),
  keepPreviousData: true,
})

const dishes = computed(() => dishData.value?.data ?? [])

const hasActiveFilters = computed(() =>
  !!search.value || !!category.value || veg.value || vegan.value || sort.value !== 'popular'
)

function clearAll() {
  search.value   = ''
  category.value = ''
  veg.value      = false
  vegan.value    = false
  sort.value     = 'popular'
}
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-8 md:py-12">

      <!-- ── Page header ─────────────────────────────────────────── -->
      <div class="flex items-end justify-between mb-6 md:mb-8">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary-500 mb-1">{{ $t('menu.whatWeServe') }}</p>
          <h1 class="font-heading text-3xl md:text-4xl font-bold text-warm-900 dark:text-white">{{ $t('menu.ourMenu') }}</h1>
        </div>
        <p v-if="!isLoading" class="text-sm text-warm-400 hidden sm:block">
          {{ $t('menu.dishesCount', { count: dishes.length }) }}
        </p>
      </div>

      <!-- ── Sticky search + filter bar ─────────────────────────── -->
      <div class="sticky top-16 z-30 bg-warm-50/95 dark:bg-gray-950/95 backdrop-blur-sm -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 pb-3 pt-2 mb-6 border-b border-warm-100 dark:border-warm-800">

        <div class="flex gap-2 items-center">
          <!-- Search input -->
          <div class="relative flex-1">
            <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
            <input
              v-model="search"
              type="search"
              :placeholder="$t('menu.searchPlaceholder')"
              class="input pl-9 pr-9 text-sm h-10"
              :aria-label="$t('menu.searchPlaceholder')"
            />
            <button
              v-if="search"
              @click="search = ''"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-warm-400 hover:text-warm-600 dark:hover:text-warm-200"
              aria-label="Clear search"
            >
              <X :size="14" />
            </button>
          </div>

          <!-- Sort (desktop only) -->
          <select v-model="sort" class="hidden sm:block input w-auto text-sm h-10 py-0 cursor-pointer">
            <option value="popular">{{ $t('menu.sortPopular') }}</option>
            <option value="price_asc">{{ $t('menu.sortPriceAsc') }}</option>
            <option value="price_desc">{{ $t('menu.sortPriceDesc') }}</option>
            <option value="rating">{{ $t('menu.sortRating') }}</option>
          </select>

          <!-- Filter toggle (mobile) -->
          <button
            @click="showFilters = !showFilters"
            :class="[
              'sm:hidden flex items-center gap-1.5 px-3 h-10 rounded-xl text-sm font-semibold border transition-all shrink-0',
              showFilters || hasActiveFilters
                ? 'bg-primary-500 text-white border-primary-500'
                : 'border-warm-300 dark:border-warm-700 text-warm-600 dark:text-warm-300'
            ]"
            aria-label="Toggle filters"
          >
            <SlidersHorizontal :size="16" />
            <span>{{ $t('menu.filter') }}</span>
            <span v-if="hasActiveFilters" class="bg-white/30 text-white rounded-full w-4 h-4 text-[10px] flex items-center justify-center font-bold">!</span>
          </button>

          <!-- Clear all (when filters active) -->
          <button
            v-if="hasActiveFilters"
            @click="clearAll"
            class="hidden sm:flex items-center gap-1 text-xs text-warm-500 hover:text-red-500 transition-colors px-2 shrink-0"
            aria-label="Clear all filters"
          >
            <X :size="13" /> {{ $t('menu.clear') }}
          </button>
        </div>

        <!-- Mobile filter panel -->
        <Transition
          enter-from-class="opacity-0 -translate-y-2"
          enter-active-class="transition-all duration-200"
          leave-to-class="opacity-0 -translate-y-2"
          leave-active-class="transition-all duration-150"
        >
          <div v-if="showFilters" class="sm:hidden mt-3 space-y-3">
            <select v-model="sort" class="input text-sm h-10 py-0 w-full">
              <option value="popular">{{ $t('menu.sortPopular') }}</option>
              <option value="price_asc">{{ $t('menu.sortPriceAsc') }}</option>
              <option value="price_desc">{{ $t('menu.sortPriceDesc') }}</option>
              <option value="rating">{{ $t('menu.sortRating') }}</option>
            </select>
            <div class="flex gap-2">
              <button
                @click="veg = !veg"
                :class="['flex-1 flex items-center justify-center gap-2 py-2 rounded-xl text-sm font-semibold border transition-all', veg ? 'bg-green-500 text-white border-green-500' : 'border-warm-300 dark:border-warm-700 text-warm-600 dark:text-warm-300']"
              >
                <Leaf :size="15" /> {{ $t('menu.veg') }}
              </button>
              <button
                @click="vegan = !vegan"
                :class="['flex-1 flex items-center justify-center gap-2 py-2 rounded-xl text-sm font-semibold border transition-all', vegan ? 'bg-green-600 text-white border-green-600' : 'border-warm-300 dark:border-warm-700 text-warm-600 dark:text-warm-300']"
              >
                <Leaf :size="15" /> {{ $t('menu.vegan') }}
              </button>
            </div>
            <button v-if="hasActiveFilters" @click="clearAll" class="w-full py-2 text-sm text-red-500 font-semibold border border-red-200 dark:border-red-900/40 rounded-xl hover:bg-red-50 dark:hover:bg-red-950/20 transition-colors">
              {{ $t('menu.clearAll') }}
            </button>
          </div>
        </Transition>
      </div>

      <!-- ── Category chips — horizontally scrollable ─────────────── -->
      <div class="flex gap-2 overflow-x-auto pb-2 mb-6 -mx-4 sm:-mx-0 px-4 sm:px-0 scrollbar-hide snap-x">
        <button
          @click="category = ''"
          :class="[
            'shrink-0 snap-start px-4 py-2 rounded-full text-sm font-semibold transition-all border',
            !category
              ? 'bg-primary-500 text-white border-primary-500 shadow-md shadow-primary-500/25'
              : 'bg-white dark:bg-gray-900 border-warm-200 dark:border-warm-700 text-warm-700 dark:text-warm-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400'
          ]"
        >
          <span class="flex items-center gap-1.5">
            <UtensilsCrossed :size="13" /> {{ $t('menu.all') }}
          </span>
        </button>

        <button
          v-for="cat in (categories ?? [])"
          :key="cat.id"
          @click="category = category === cat.slug ? '' : cat.slug"
          :class="[
            'shrink-0 snap-start px-4 py-2 rounded-full text-sm font-semibold transition-all border whitespace-nowrap',
            category === cat.slug
              ? 'bg-primary-500 text-white border-primary-500 shadow-md shadow-primary-500/25'
              : 'bg-white dark:bg-gray-900 border-warm-200 dark:border-warm-700 text-warm-700 dark:text-warm-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400'
          ]"
        >{{ cat.name }}</button>
      </div>

      <!-- Dietary toggles (desktop) -->
      <div class="hidden sm:flex items-center gap-2 mb-8">
        <button
          @click="veg = !veg"
          :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all', veg ? 'bg-green-500 text-white border-green-500 shadow-sm' : 'border-warm-300 dark:border-warm-700 text-warm-600 dark:text-warm-300 hover:border-green-400']"
        >
          <Leaf :size="12" /> {{ $t('menu.veg') }}
        </button>
        <button
          @click="vegan = !vegan"
          :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all', vegan ? 'bg-green-600 text-white border-green-600 shadow-sm' : 'border-warm-300 dark:border-warm-700 text-warm-600 dark:text-warm-300 hover:border-green-400']"
        >
          <Leaf :size="12" /> {{ $t('menu.vegan') }}
        </button>

        <!-- Active filter chips -->
        <template v-if="hasActiveFilters">
          <span class="text-warm-300 dark:text-warm-700 text-xs">|</span>
          <button @click="clearAll" class="flex items-center gap-1 text-xs text-warm-500 hover:text-red-500 transition-colors font-medium">
            <X :size="12" /> {{ $t('menu.clearAll') }}
          </button>
        </template>
      </div>

      <!-- ── Loading skeletons ───────────────────────────────────── -->
      <div v-if="isLoading" class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6">
        <DishCardSkeleton v-for="i in 8" :key="i" />
      </div>

      <!-- ── Dish grid ───────────────────────────────────────────── -->
      <TransitionGroup
        v-else-if="dishes.length"
        tag="div"
        class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6"
        enter-from-class="opacity-0 translate-y-4"
        enter-active-class="transition-all duration-300"
        leave-to-class="opacity-0 scale-95"
        leave-active-class="transition-all duration-200 absolute"
      >
        <DishCard v-for="dish in dishes" :key="dish.id" :dish="dish" />
      </TransitionGroup>

      <!-- ── Empty state ─────────────────────────────────────────── -->
      <div v-else class="flex flex-col items-center justify-center py-20 text-center">
        <div class="w-20 h-20 rounded-2xl bg-warm-100 dark:bg-warm-800 flex items-center justify-center mb-5">
          <Search :size="36" class="text-warm-300 dark:text-warm-600" />
        </div>
        <h3 class="font-heading font-bold text-lg text-warm-800 dark:text-warm-200 mb-2">{{ $t('menu.noDishesFound') }}</h3>
        <p class="text-sm text-warm-500 dark:text-warm-400 mb-6 max-w-xs">
          {{ search ? $t('menu.noResultsFor', { search }) : $t('menu.noDishesMatch') }}
          {{ $t('menu.adjustFilters') }}
        </p>
        <button @click="clearAll" class="btn-outline text-sm">
          <X :size="15" /> {{ $t('menu.clearAll') }}
        </button>
      </div>

    </div>
  </DefaultLayout>
</template>
