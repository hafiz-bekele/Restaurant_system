<script setup>
import { Star, Clock, Plus, Heart } from '@lucide/vue'
import { RouterLink } from 'vue-router'
import { useCartStore } from '../../stores/cart'
import { useAuthStore } from '../../stores/auth'
import { profileApi }   from '../../services/api'
import { useUiStore }   from '../../stores/ui'
import { ref } from 'vue'

const props  = defineProps({ dish: { type: Object, required: true } })
const cart   = useCartStore()
const auth   = useAuthStore()
const ui     = useUiStore()
const adding = ref(false)
const liked  = ref(false)

async function addToCart() {
  adding.value = true
  try {
    await cart.addItem({ dish_id: props.dish.id, quantity: 1 })
    ui.toast(`${props.dish.name} added to cart`)
  } catch {
    ui.toast('Failed to add item', 'error')
  } finally {
    adding.value = false
  }
}

async function toggleFavorite() {
  if (!auth.isAuthenticated) { ui.toast('Please sign in to save favorites', 'warning'); return }
  if (liked.value) {
    await profileApi.removeFavorite(props.dish.id)
  } else {
    await profileApi.addFavorite(props.dish.id)
  }
  liked.value = !liked.value
}
</script>

<template>
  <article class="card overflow-hidden group hover:shadow-float transition-all duration-300 hover:-translate-y-1">
    <!-- Image -->
    <RouterLink :to="`/menu/${dish.slug}`" class="block relative overflow-hidden aspect-[4/3]">
      <img
        v-if="dish.image"
        :src="dish.image"
        :alt="dish.name"
        loading="lazy"
        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
      />
      <div v-else class="w-full h-full bg-warm-100 dark:bg-warm-800 flex items-center justify-center text-warm-400 text-5xl">
        ?
      </div>
      <!-- Badges -->
      <div class="absolute top-3 left-3 flex gap-1.5">
        <span v-if="dish.is_vegetarian" class="badge badge-green">Veg</span>
        <span v-if="dish.is_vegan"      class="badge badge-green">Vegan</span>
        <span v-if="dish.is_featured"   class="badge badge-orange">Featured</span>
      </div>
      <!-- Favorite -->
      <button
        @click.prevent="toggleFavorite"
        :class="['absolute top-3 right-3 p-2 rounded-full bg-white/80 dark:bg-gray-900/80 backdrop-blur hover:scale-110 transition-all', liked ? 'text-red-500' : 'text-warm-400']"
        :aria-label="liked ? 'Remove from favorites' : 'Add to favorites'"
      >
        <Heart :size="16" :class="{ 'fill-current': liked }" />
      </button>
    </RouterLink>

    <!-- Content -->
    <div class="p-4">
      <RouterLink :to="`/menu/${dish.slug}`">
        <h3 class="font-heading font-semibold text-warm-900 dark:text-white mb-1 hover:text-primary-600 dark:hover:text-primary-400 transition-colors line-clamp-1">
          {{ dish.name }}
        </h3>
      </RouterLink>
      <p class="text-sm text-warm-500 dark:text-warm-400 line-clamp-2 mb-3">{{ dish.description }}</p>

      <div class="flex items-center gap-3 text-xs text-warm-400 mb-4">
        <span v-if="dish.avg_rating > 0" class="flex items-center gap-1 font-medium text-warm-700 dark:text-warm-300">
          <Star :size="12" class="text-amber-400 fill-amber-400" />
          {{ Number(dish.avg_rating).toFixed(1) }}
          <span v-if="dish.reviews_count" class="text-warm-400 font-normal">({{ dish.reviews_count }})</span>
        </span>
        <span v-if="dish.prep_time" class="flex items-center gap-1">
          <Clock :size="12" /> {{ dish.prep_time }}m
        </span>
      </div>

      <div class="flex items-center justify-between">
        <span class="font-heading font-bold text-lg text-warm-900 dark:text-white">
          ${{ Number(dish.price).toFixed(2) }}
        </span>
        <button
          @click="addToCart"
          :disabled="adding"
          class="btn-primary py-2 px-3 text-xs gap-1"
          :aria-label="`Add ${dish.name} to cart`"
        >
          <Plus :size="14" />
          {{ adding ? '...' : 'Add' }}
        </button>
      </div>
    </div>
  </article>
</template>
