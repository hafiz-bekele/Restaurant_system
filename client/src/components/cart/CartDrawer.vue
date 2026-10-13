<script setup>
import { X, Trash2, Plus, Minus, ShoppingBag } from '@lucide/vue'
import { RouterLink } from 'vue-router'
import { useCartStore } from '../../stores/cart'
import { useUiStore }   from '../../stores/ui'

const cart = useCartStore()
const ui   = useUiStore()

async function updateQty(item, delta) {
  const newQty = item.quantity + delta
  try {
    if (newQty < 1) {
      await cart.removeItem(item.id)
    } else {
      await cart.updateItem(item.id, newQty)
    }
  } catch {
    ui.toast('Failed to update cart', 'error')
  }
}
</script>

<template>
  <Teleport to="body">
    <!-- Overlay -->
    <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-300" leave-to-class="opacity-0" leave-active-class="transition-opacity duration-200">
      <div v-if="cart.open" class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm" @click="cart.closeDrawer" aria-hidden="true"></div>
    </Transition>

      <!-- Drawer -->
    <Transition enter-from-class="translate-x-full" enter-active-class="transition-transform duration-300 ease-out" leave-to-class="translate-x-full" leave-active-class="transition-transform duration-200 ease-in">
      <aside v-if="cart.open" class="fixed right-0 top-0 bottom-0 z-50 w-full max-w-md bg-white dark:bg-gray-900 flex flex-col shadow-2xl" role="dialog" aria-label="Shopping cart">
        <!-- Mobile drag handle pill -->
        <div class="w-12 h-1.5 bg-warm-200 dark:bg-warm-700 rounded-full mx-auto mt-2.5 mb-0.5 md:hidden" aria-hidden="true"></div>

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-warm-100 dark:border-warm-800">
          <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white flex items-center gap-2">
            <ShoppingBag :size="20" /> Your Cart
            <span v-if="cart.itemCount" class="badge badge-orange">{{ cart.itemCount }}</span>
          </h2>
          <button @click="cart.closeDrawer" class="btn-ghost p-2 rounded-xl" aria-label="Close cart">
            <X :size="20" />
          </button>
        </div>

        <!-- Items -->
        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
          <div v-if="!cart.cart?.items?.length" class="flex flex-col items-center justify-center h-full text-center gap-4">
            <ShoppingBag :size="48" class="text-warm-300" />
            <p class="text-warm-500 dark:text-warm-400">Your cart is empty</p>
            <RouterLink to="/menu" @click="cart.closeDrawer" class="btn-primary">Browse Menu</RouterLink>
          </div>

          <div v-for="item in (cart.cart?.items ?? [])" :key="item.id" class="flex gap-4">
            <img
              v-if="item.dish?.image"
              :src="item.dish.image"
              :alt="item.dish.name"
              class="w-16 h-16 rounded-xl object-cover shrink-0"
            />
            <div v-else class="w-16 h-16 rounded-xl bg-warm-100 dark:bg-warm-800 shrink-0"></div>
            <div class="flex-1 min-w-0">
              <p class="font-medium text-sm text-warm-900 dark:text-white truncate">{{ item.dish?.name }}</p>
              <p v-if="item.variant" class="text-xs text-warm-400">{{ item.variant.name }}</p>
              <p class="text-sm font-semibold text-primary-600 dark:text-primary-400">${{ Number(item.line_total).toFixed(2) }}</p>
              <div class="flex items-center gap-2 mt-2">
                <button
                  @click="updateQty(item, -1)"
                  class="w-8 h-8 rounded-lg bg-warm-100 dark:bg-warm-800 hover:bg-warm-200 dark:hover:bg-warm-700 flex items-center justify-center transition-colors active:scale-95"
                  :aria-label="`Decrease ${item.dish?.name} quantity`"
                >
                  <Minus :size="14" />
                </button>
                <span class="text-sm font-semibold w-7 text-center select-none">{{ item.quantity }}</span>
                <button
                  @click="updateQty(item, 1)"
                  class="w-8 h-8 rounded-lg bg-warm-100 dark:bg-warm-800 hover:bg-warm-200 dark:hover:bg-warm-700 flex items-center justify-center transition-colors active:scale-95"
                  :aria-label="`Increase ${item.dish?.name} quantity`"
                >
                  <Plus :size="14" />
                </button>
                <button
                  @click="cart.removeItem(item.id)"
                  class="ml-auto w-8 h-8 rounded-lg text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center justify-center transition-colors active:scale-95"
                  :aria-label="`Remove ${item.dish?.name}`"
                >
                  <Trash2 :size="15" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Summary -->
        <div v-if="cart.cart?.items?.length" class="px-6 py-4 pb-8 md:pb-4 border-t border-warm-100 dark:border-warm-800 space-y-3 bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm">
          <div class="space-y-1 text-sm">
            <div class="flex justify-between text-warm-600 dark:text-warm-400"><span>Subtotal</span><span>${{ Number(cart.totals.subtotal).toFixed(2) }}</span></div>
            <div v-if="cart.totals.discount > 0" class="flex justify-between text-green-600"><span>Discount</span><span>-${{ Number(cart.totals.discount).toFixed(2) }}</span></div>
            <div class="flex justify-between text-warm-600 dark:text-warm-400"><span>Tax</span><span>${{ Number(cart.totals.tax).toFixed(2) }}</span></div>
            <div class="flex justify-between font-bold text-warm-900 dark:text-white pt-1 border-t border-warm-100 dark:border-warm-800"><span>Total</span><span>${{ Number(cart.totals.total).toFixed(2) }}</span></div>
          </div>
          <RouterLink to="/checkout" @click="cart.closeDrawer" class="btn-primary w-full justify-center py-3">
            Proceed to Checkout
          </RouterLink>
        </div>
      </aside>
    </Transition>
  </Teleport>
</template>
