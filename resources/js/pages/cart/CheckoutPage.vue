<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCartStore } from '../../stores/cart'
import { useUiStore }   from '../../stores/ui'
import { orderApi } from '../../services/api'
import DefaultLayout from '../../layouts/DefaultLayout.vue'

const cart    = useCartStore()
const ui      = useUiStore()
const router  = useRouter()
const loading = ref(false)
const form    = ref({ type: 'delivery', payment_method: 'cash', notes: '', address: { line1: '', city: '' } })

async function placeOrder() {
  loading.value = true
  try {
    const { data } = await orderApi.create(form.value)
    ui.toast('Order placed successfully!', 'success')
    router.push(`/orders/${data.data.ulid}/track`)
  } catch (e) {
    ui.toast(e.response?.data?.message ?? 'Failed to place order', 'error')
  } finally {
    loading.value = false
  }
}
</script>
<template>
  <DefaultLayout>
    <div class="page-container py-10 max-w-2xl">
      <h1 class="font-heading text-3xl font-bold text-warm-900 dark:text-white mb-8">Checkout</h1>
      <form @submit.prevent="placeOrder" class="card p-8 space-y-6">
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-2">Order Type</label>
          <div class="flex gap-3">
            <label v-for="type in ['delivery', 'pickup', 'dine_in']" :key="type" :class="['flex-1 flex items-center justify-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-colors text-sm font-medium', form.type === type ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300' : 'border-warm-200 dark:border-warm-700 text-warm-600 dark:text-warm-400']">
              <input type="radio" v-model="form.type" :value="type" class="sr-only" />
              {{ type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) }}
            </label>
          </div>
        </div>
        <div v-if="form.type === 'delivery'" class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Address</label>
            <input v-model="form.address.line1" type="text" required class="input" placeholder="Street address" />
          </div>
          <div>
            <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">City</label>
            <input v-model="form.address.city" type="text" required class="input" placeholder="City" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-2">Payment Method</label>
          <div class="flex gap-3">
            <label v-for="method in ['cash', 'stripe', 'chapa']" :key="method" :class="['flex-1 flex items-center justify-center p-3 rounded-xl border-2 cursor-pointer transition-colors text-sm font-medium', form.payment_method === method ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300' : 'border-warm-200 dark:border-warm-700 text-warm-600 dark:text-warm-400']">
              <input type="radio" v-model="form.payment_method" :value="method" class="sr-only" />
              {{ method.charAt(0).toUpperCase() + method.slice(1) }}
            </label>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Order Notes</label>
          <textarea v-model="form.notes" rows="3" class="input resize-none" placeholder="Any special instructions..."></textarea>
        </div>
        <div class="space-y-2 text-sm border-t border-warm-100 dark:border-warm-800 pt-4">
          <div class="flex justify-between font-bold text-warm-900 dark:text-white text-base"><span>Total</span><span>${{ Number(cart.totals.total).toFixed(2) }}</span></div>
        </div>
        <button type="submit" :disabled="loading" class="btn-primary w-full justify-center py-3 text-base">
          {{ loading ? 'Placing Order...' : 'Place Order' }}
        </button>
      </form>
    </div>
  </DefaultLayout>
</template>
