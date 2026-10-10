<script setup>
import { useRoute } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import { menuApi } from '../../services/api'
import { useCartStore } from '../../stores/cart'
import { useUiStore }   from '../../stores/ui'
import { ref } from 'vue'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import { Star, Clock, Leaf, Plus, Minus } from '@lucide/vue'

const route   = useRoute()
const cart    = useCartStore()
const ui      = useUiStore()
const qty     = ref(1)
const loading = ref(false)

const { data: dish, isLoading } = useQuery({
  queryKey: ['dish', route.params.slug],
  queryFn:  () => menuApi.dish(route.params.slug).then(r => r.data.data),
})

async function addToCart() {
  if (!dish.value) return
  loading.value = true
  try {
    await cart.addItem({ dish_id: dish.value.id, quantity: qty.value })
    ui.toast(`${dish.value.name} added to cart`)
  } catch { ui.toast('Failed to add', 'error') }
  finally { loading.value = false }
}
</script>
<template>
  <DefaultLayout>
    <div class="page-container py-10 max-w-4xl">
      <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 gap-10">
        <div class="skeleton aspect-square rounded-2xl"></div>
        <div class="space-y-4">
          <div class="skeleton h-8 w-3/4"></div>
          <div class="skeleton h-4 w-full"></div>
          <div class="skeleton h-4 w-2/3"></div>
        </div>
      </div>
      <div v-else-if="dish" class="grid grid-cols-1 md:grid-cols-2 gap-10">
        <div class="aspect-square rounded-2xl overflow-hidden bg-warm-100 dark:bg-warm-800">
          <img v-if="dish.image" :src="dish.image" :alt="dish.name" class="w-full h-full object-cover" />
        </div>
        <div>
          <div class="flex flex-wrap gap-2 mb-3">
            <span v-if="dish.is_vegetarian" class="badge badge-green">Vegetarian</span>
            <span v-if="dish.is_vegan"      class="badge badge-green">Vegan</span>
          </div>
          <h1 class="font-heading text-3xl font-bold text-warm-900 dark:text-white mb-2">{{ dish.name }}</h1>
          <p class="text-warm-600 dark:text-warm-300 mb-4">{{ dish.description }}</p>
          <div class="flex items-center gap-4 text-sm text-warm-400 mb-4">
            <span v-if="dish.avg_rating > 0" class="flex items-center gap-1"><Star :size="14" class="text-yellow-400 fill-yellow-400" />{{ dish.avg_rating }}</span>
            <span v-if="dish.prep_time" class="flex items-center gap-1"><Clock :size="14" />{{ dish.prep_time }} min</span>
            <span v-if="dish.calories" class="flex items-center gap-1"><Leaf :size="14" />{{ dish.calories }} cal</span>
          </div>
          <p class="font-heading text-3xl font-bold text-primary-600 dark:text-primary-400 mb-6">${{ Number(dish.price).toFixed(2) }}</p>
          <div class="flex items-center gap-4 mb-6">
            <div class="flex items-center gap-2 bg-warm-100 dark:bg-warm-800 rounded-xl p-1">
              <button @click="qty = Math.max(1, qty - 1)" class="p-2 rounded-lg hover:bg-warm-200 dark:hover:bg-warm-700 transition-colors"><Minus :size="16" /></button>
              <span class="w-10 text-center font-semibold text-warm-900 dark:text-white">{{ qty }}</span>
              <button @click="qty++" class="p-2 rounded-lg hover:bg-warm-200 dark:hover:bg-warm-700 transition-colors"><Plus :size="16" /></button>
            </div>
            <button @click="addToCart" :disabled="loading" class="btn-primary flex-1 justify-center py-3">
              {{ loading ? 'Adding...' : `Add to Cart — $${(Number(dish.price) * qty).toFixed(2)}` }}
            </button>
          </div>
          <div v-if="dish.allergens?.length" class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/10 rounded-xl">
            <p class="text-xs font-semibold text-yellow-700 dark:text-yellow-400 mb-1">Allergens</p>
            <p class="text-xs text-yellow-600 dark:text-yellow-500">{{ dish.allergens.map(a => a.name).join(', ') }}</p>
          </div>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
