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
      <div v-if="!cart.cart?.items?.length" class="text-center py-20">
        <ShoppingBag :size="56" class="mx-auto text-warm-300 mb-4" />
        <p class="text-warm-500 mb-6">Your cart is empty</p>
        <RouterLink to="/menu" class="btn-primary">Browse Menu</RouterLink>
      </div>
      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-4">
          <div v-for="item in cart.cart.items" :key="item.id" class="card p-4 flex gap-4">
            <img v-if="item.dish?.image" :src="item.dish.image" :alt="item.dish.name" class="w-20 h-20 rounded-xl object-cover shrink-0" />
            <div class="flex-1">
              <p class="font-semibold text-warm-900 dark:text-white">{{ item.dish?.name }}</p>
              <p class="text-sm text-primary-600 dark:text-primary-400 font-bold">${{ Number(item.line_total).toFixed(2) }}</p>
              <div class="flex items-center gap-2 mt-2">
                <button @click="cart.updateItem(item.id, item.quantity - 1)" class="p-1 rounded-lg bg-warm-100 dark:bg-warm-800"><Minus :size="14" /></button>
                <span class="w-8 text-center text-sm font-semibold">{{ item.quantity }}</span>
                <button @click="cart.updateItem(item.id, item.quantity + 1)" class="p-1 rounded-lg bg-warm-100 dark:bg-warm-800"><Plus :size="14" /></button>
                <button @click="cart.removeItem(item.id)" class="ml-auto text-red-400 hover:text-red-600 p-1"><Trash2 :size="16" /></button>
              </div>
            </div>
          </div>
        </div>
        <div class="card p-6 h-fit space-y-4">
          <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">Order Summary</h2>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between text-warm-600 dark:text-warm-400"><span>Subtotal</span><span>${{ Number(cart.totals.subtotal).toFixed(2) }}</span></div>
            <div v-if="cart.totals.discount > 0" class="flex justify-between text-green-600"><span>Discount</span><span>-${{ Number(cart.totals.discount).toFixed(2) }}</span></div>
            <div class="flex justify-between text-warm-600 dark:text-warm-400"><span>Tax</span><span>${{ Number(cart.totals.tax).toFixed(2) }}</span></div>
            <div class="flex justify-between font-bold text-warm-900 dark:text-white pt-2 border-t border-warm-100 dark:border-warm-800"><span>Total</span><span>${{ Number(cart.totals.total).toFixed(2) }}</span></div>
          </div>
          <RouterLink to="/checkout" class="btn-primary w-full justify-center">Checkout</RouterLink>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
