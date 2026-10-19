<script setup>
import { ref } from 'vue'
import { Star, Clock, Plus, Heart, Check, ImageOff } from '@lucide/vue'
import { RouterLink } from 'vue-router'
import { useCartStore } from '../../stores/cart'
import { useAuthStore } from '../../stores/auth'
import { profileApi }   from '../../services/api'
import { useUiStore }   from '../../stores/ui'

const props  = defineProps({ dish: { type: Object, required: true } })
const cart   = useCartStore()
const auth   = useAuthStore()
const ui     = useUiStore()

const adding      = ref(false)
const added       = ref(false)   // brief "✓ Added" feedback state
const liked       = ref(false)
const imgLoaded   = ref(false)
const imgError    = ref(false)

async function addToCart() {
  if (adding.value) return
  adding.value = true
  try {
    await cart.addItem({ dish_id: props.dish.id, quantity: 1 })
    added.value = true
    ui.toast(`${props.dish.name} added to cart`, 'success')
    setTimeout(() => { added.value = false }, 1800)
  } catch {
    ui.toast('Failed to add item', 'error')
  } finally {
    adding.value = false
  }
}

async function toggleFavorite() {
  if (!auth.isAuthenticated) {
    ui.toast('Please sign in to save favourites', 'warning')
    return
  }
  try {
    if (liked.value) {
      await profileApi.removeFavorite(props.dish.id)
    } else {
      await profileApi.addFavorite(props.dish.id)
    }
    liked.value = !liked.value
  } catch {
    ui.toast('Could not update favourites', 'error')
  }
}
</script>

<template>
  <article class="card overflow-hidden group flex flex-col hover:shadow-float hover:-translate-y-1 transition-all duration-300">

    <!-- ── Image ──────────────────────────────────────────────────────── -->
    <RouterLink :to="`/menu/${dish.slug}`" class="block relative overflow-hidden aspect-[4/3] shrink-0 bg-warm-100 dark:bg-warm-800">

      <!-- Skeleton shown until image loads -->
      <div
        v-if="!imgLoaded && !imgError && dish.image"
        class="skeleton absolute inset-0"
        aria-hidden="true"
      ></div>

      <!-- Actual image -->
      <img
        v-if="dish.image && !imgError"
        :src="dish.image"
        :alt="dish.name"
        loading="lazy"
        @load="imgLoaded = true"
        @error="imgError = true"
        :class="[
          'w-full h-full object-cover group-hover:scale-105 transition-transform duration-500',
          imgLoaded ? 'opacity-100' : 'opacity-0'
        ]"
      />

      <!-- Fallback when no image or load error -->
      <div
        v-if="!dish.image || imgError"
        class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-warm-300 dark:text-warm-600"
      >
        <ImageOff :size="32" />
        <span class="text-xs">{{ $t('menu.noImage') }}</span>
      </div>

      <!-- Badges overlay -->
      <div class="absolute top-2.5 left-2.5 flex flex-wrap gap-1 max-w-[calc(100%-3rem)]">
        <span v-if="dish.is_vegetarian" class="badge badge-green shadow-sm">{{ $t('menu.veg') }}</span>
        <span v-if="dish.is_vegan"      class="badge badge-green shadow-sm">{{ $t('menu.vegan') }}</span>
        <span v-if="dish.is_featured"   class="badge badge-orange shadow-sm">{{ $t('menu.featured') }}</span>
      </div>

      <!-- Favourite button -->
      <button
        @click.prevent="toggleFavorite"
        :class="[
          'absolute top-2.5 right-2.5 w-8 h-8 rounded-full flex items-center justify-center',
          'bg-white/85 dark:bg-gray-900/85 backdrop-blur-sm shadow-sm',
          'hover:scale-110 active:scale-95 transition-all duration-150',
          liked ? 'text-red-500' : 'text-warm-400 hover:text-red-400',
        ]"
        :aria-label="liked ? 'Remove from favourites' : 'Add to favourites'"
      >
        <Heart :size="15" :class="{ 'fill-current': liked }" />
      </button>
    </RouterLink>

    <!-- ── Content ────────────────────────────────────────────────────── -->
    <div class="flex flex-col flex-1 p-4 gap-2">

      <!-- Name -->
      <RouterLink :to="`/menu/${dish.slug}`">
        <h3 class="font-heading font-semibold text-base text-warm-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400 transition-colors line-clamp-1 leading-snug">
          {{ dish.name }}
        </h3>
      </RouterLink>

      <!-- Description -->
      <p class="text-xs text-warm-500 dark:text-warm-400 line-clamp-2 flex-1 leading-relaxed">
        {{ dish.description || '&nbsp;' }}
      </p>

      <!-- Meta row: rating + prep time -->
      <div class="flex items-center gap-3 text-xs text-warm-400">
        <span
          v-if="dish.avg_rating > 0"
          class="flex items-center gap-1 font-semibold text-warm-700 dark:text-warm-300"
        >
          <Star :size="12" class="text-amber-400 fill-amber-400" />
          {{ Number(dish.avg_rating).toFixed(1) }}
          <span v-if="dish.reviews_count" class="text-warm-400 font-normal">({{ dish.reviews_count }})</span>
        </span>
        <span v-if="dish.prep_time" class="flex items-center gap-1">
          <Clock :size="12" /> {{ dish.prep_time }}m
        </span>
        <span v-if="dish.calories" class="ml-auto">{{ dish.calories }} kcal</span>
      </div>

      <!-- Price + Add button -->
      <div class="flex items-center justify-between pt-1 mt-auto">
        <div>
          <span class="font-heading font-bold text-lg text-warm-900 dark:text-white leading-none">
            ${{ Number(dish.price).toFixed(2) }}
          </span>
        </div>

        <button
          @click="addToCart"
          :disabled="adding"
          :class="[
            'inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold transition-all duration-200',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50',
            'active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed',
            added
              ? 'bg-green-500 text-white shadow-md shadow-green-500/30'
              : 'bg-primary-500 text-white hover:bg-primary-600 shadow-md shadow-primary-500/20',
          ]"
          :aria-label="`Add ${dish.name} to cart`"
        >
          <Transition
            enter-from-class="scale-0 opacity-0 rotate-90"
            enter-active-class="transition-all duration-200"
            leave-to-class="scale-0 opacity-0 -rotate-90"
            leave-active-class="transition-all duration-150"
            mode="out-in"
          >
            <Check v-if="added" :size="14" key="check" />
            <Plus  v-else       :size="14" key="plus" />
          </Transition>
          <span>{{ adding ? '…' : added ? $t('common.added') : $t('common.add') }}</span>
        </button>
      </div>
    </div>

  </article>
</template>
