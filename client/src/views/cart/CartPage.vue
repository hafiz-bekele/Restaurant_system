<script setup>
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import { useCartStore } from '../../stores/cart'
import { ShoppingBag, Trash2, Plus, Minus } from '@lucide/vue'

const cart = useCartStore()
onMounted(() => cart.fetchCart())
</script>
<template>
  <DefaultLayout>
    <div class="page-container py-10">
      <h1 class="font-heading text-3xl font-bold text-warm-900 dark:text-white mb-8">Shopping Cart</h1>
      <!-- Loading skeleton -->
      <div v-if="cart.loading && !cart.cart?.items?.length" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-4">
          <div v-for="i in 3" :key="i" class="card p-4 flex gap-4 border border-warm-100 dark:border-warm-800">
            <div class="skeleton w-20 h-20 rounded-xl shrink-0"></div>
            <div class="flex-1 space-y-2">
              <div class="skeleton h-5 w-40 rounded"></div>
              <div class="skeleton h-4 w-20 rounded"></div>
              <div class="flex items-center gap-2 pt-2">
                <div class="skeleton h-8 w-8 rounded-lg"></div>
                <div class="skeleton h-8 w-8 rounded-lg"></div>
                <div class="skeleton h-8 w-8 rounded-lg"></div>
              </div>
            </div>
          </div>
        </div>
        <div class="card p-6 h-fit space-y-4 border border-warm-100 dark:border-warm-800">
          <div class="skeleton h-6 w-36 rounded-lg"></div>
          <div class="space-y-3">
            <div class="skeleton h-4 w-full rounded"></div>
            <div class="skeleton h-4 w-full rounded"></div>
            <div class="skeleton h-4 w-full rounded"></div>
          </div>
          <div class="skeleton h-11 w-full rounded-xl"></div>
        </div>
      </div>

      <!-- Empty Cart -->
      <div v-else-if="!cart.cart?.items?.length" class="text-center py-20 card border border-warm-100 dark:border-warm-800">
        <ShoppingBag :size="56" class="mx-auto text-warm-300 dark:text-warm-700 mb-4" />
        <h2 class="font-heading text-xl font-bold text-warm-900 dark:text-white mb-2">Your cart is empty</h2>
        <p class="text-warm-500 dark:text-warm-400 mb-6 text-sm">Add some delicious dishes from our restaurant menu to get started.</p>
        <RouterLink to="/menu" class="btn-primary">Browse Menu</RouterLink>
      </div>

      <!-- Cart Items & Summary -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-4">
          <div v-for="item in cart.cart.items" :key="item.id" class="card p-4 flex gap-4 border border-warm-100 dark:border-warm-800">
            <img v-if="item.dish?.image" :src="item.dish.image" :alt="item.dish.name" class="w-20 h-20 rounded-xl object-cover shrink-0" />
            <div v-else class="w-20 h-20 rounded-xl bg-warm-100 dark:bg-warm-800 shrink-0"></div>
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-warm-900 dark:text-white truncate">{{ item.dish?.name }}</p>
              <p class="text-sm text-primary-600 dark:text-primary-400 font-bold">${{ Number(item.line_total).toFixed(2) }}</p>
              <div class="flex items-center gap-2 mt-2">
                <button
                  @click="cart.updateItem(item.id, item.quantity - 1)"
                  class="w-8 h-8 rounded-lg bg-warm-100 dark:bg-warm-800 hover:bg-warm-200 dark:hover:bg-warm-700 flex items-center justify-center transition-colors active:scale-95"
                  :aria-label="`Decrease ${item.dish?.name} quantity`"
                >
                  <Minus :size="14" />
                </button>
                <span class="w-8 text-center text-sm font-semibold select-none">{{ item.quantity }}</span>
                <button
                  @click="cart.updateItem(item.id, item.quantity + 1)"
                  class="w-8 h-8 rounded-lg bg-warm-100 dark:bg-warm-800 hover:bg-warm-200 dark:hover:bg-warm-700 flex items-center justify-center transition-colors active:scale-95"
                  :aria-label="`Increase ${item.dish?.name} quantity`"
                >
                  <Plus :size="14" />
                </button>
                <button
                  @click="cart.removeItem(item.id)"
                  class="ml-auto w-8 h-8 rounded-lg text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center justify-center transition-colors active:scale-95"
                  :aria-label="`Remove ${item.dish?.name}`"
                >
                  <Trash2 :size="16" />
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="card p-6 h-fit space-y-4 border border-warm-100 dark:border-warm-800">
          <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">Order Summary</h2>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between text-warm-600 dark:text-warm-400"><span>Subtotal</span><span>${{ Number(cart.totals.subtotal).toFixed(2) }}</span></div>
            <div v-if="cart.totals.discount > 0" class="flex justify-between text-green-600"><span>Discount</span><span>-${{ Number(cart.totals.discount).toFixed(2) }}</span></div>
            <div class="flex justify-between text-warm-600 dark:text-warm-400"><span>Tax</span><span>${{ Number(cart.totals.tax).toFixed(2) }}</span></div>
            <div class="flex justify-between font-bold text-warm-900 dark:text-white pt-2 border-t border-warm-100 dark:border-warm-800"><span>Total</span><span>${{ Number(cart.totals.total).toFixed(2) }}</span></div>
          </div>
          <RouterLink to="/checkout" class="btn-primary w-full justify-center py-3">Proceed to Checkout</RouterLink>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
