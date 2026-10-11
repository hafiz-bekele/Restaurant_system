<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useCartStore } from '../../stores/cart'
import { useAuthStore } from '../../stores/auth'
import { useUiStore }   from '../../stores/ui'
import { orderApi, guestApi, paymentApi } from '../../services/api'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import { MapPin, CreditCard, ShoppingBag, AlertCircle, CheckCircle, Truck, Utensils, Smartphone } from '@lucide/vue'

const cart    = useCartStore()
const auth    = useAuthStore()
const ui      = useUiStore()
const router  = useRouter()

const loading       = ref(false)
const zonesLoading  = ref(false)
const deliveryZones = ref([])

const form = ref({
  type: 'delivery',
  payment_method: 'chapa',
  delivery_zone_id: null,
  table_id: null,
  notes: '',
  address: {
    line1: '',
    city: 'Downtown',
  },
})

const selectedZone = computed(() => {
  return deliveryZones.value.find(z => z.id === form.value.delivery_zone_id)
})

async function loadZones() {
  zonesLoading.value = true
  try {
    const { data } = await guestApi.deliveryZones()
    deliveryZones.value = data.data ?? []
    if (deliveryZones.value.length > 0 && !form.value.delivery_zone_id) {
      form.value.delivery_zone_id = deliveryZones.value[0].id
    }
  } catch (err) {
    console.error('Failed to load delivery zones', err)
  } finally {
    zonesLoading.value = false
  }
}

onMounted(async () => {
  await cart.fetchCart()
  await loadZones()
})

async function placeOrder() {
  if (!auth.isAuthenticated) {
    ui.toast('Please sign in or register to place your order', 'warning')
    router.push({ name: 'login', query: { redirect: '/checkout' } })
    return
  }

  if (!cart.cart?.items?.length) {
    ui.toast('Your cart is empty', 'warning')
    router.push('/menu')
    return
  }

  loading.value = true
  try {
    const payload = {
      type: form.value.type,
      payment_method: form.value.payment_method,
      notes: form.value.notes || undefined,
    }

    if (form.value.type === 'delivery') {
      if (!form.value.delivery_zone_id) {
        ui.toast('Please select a delivery zone', 'warning')
        loading.value = false
        return
      }
      payload.delivery_zone_id = Number(form.value.delivery_zone_id)
      payload.address = {
        line1: form.value.address.line1,
        city: form.value.address.city,
      }
    } else if (form.value.type === 'dine_in') {
      if (!form.value.table_id) {
        ui.toast('Please specify a table number', 'warning')
        loading.value = false
        return
      }
      payload.table_id = Number(form.value.table_id)
    }

    const { data } = await orderApi.create(payload)
    const newOrder = data.data
    ui.toast('Order placed successfully!', 'success')
    await cart.fetchCart()

    // Handle Chapa Gateway Payment (Telebirr, CBE Birr, Card)
    if (form.value.payment_method === 'chapa') {
      ui.toast('Connecting to Chapa Gateway (Telebirr / CBE Birr / Cards)...', 'info')
      try {
        const { data: chapaRes } = await paymentApi.createChapaIntent(newOrder.ulid)
        const checkoutUrl = chapaRes.data?.checkout_url
        if (checkoutUrl) {
          window.location.href = checkoutUrl
          return
        }
      } catch (err) {
        console.error('Failed to initialize Chapa gateway', err)
        ui.toast('Order created. Proceeding to order tracking for payment settlement.', 'warning')
      }
    }

    router.push(`/orders/${newOrder.ulid}/track`)
  } catch (e) {
    const errorMsg = e.response?.data?.message || 'Failed to place order'
    const fieldErrors = e.response?.data?.errors
    if (fieldErrors) {
      const firstError = Object.values(fieldErrors).flat()[0]
      ui.toast(firstError, 'error')
    } else {
      ui.toast(errorMsg, 'error')
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-10 max-w-2xl">
      <h1 class="font-heading text-3xl font-bold text-warm-900 dark:text-white mb-2">Checkout</h1>
      <p class="text-warm-500 dark:text-warm-400 mb-8">Review your delivery details and payment preference.</p>

      <!-- Auth warning banner if guest -->
      <div v-if="!auth.isAuthenticated" class="card p-5 mb-6 border-amber-300 bg-amber-50 dark:bg-amber-950/30 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 text-amber-800 dark:text-amber-200">
          <AlertCircle :size="24" class="shrink-0 text-amber-600" />
          <p class="text-sm">You need an account to place and track orders.</p>
        </div>
        <RouterLink to="/login?redirect=/checkout" class="btn-primary text-xs px-4 py-2 shrink-0">Sign In to Continue</RouterLink>
      </div>

      <!-- Empty Cart Alert -->
      <div v-if="!cart.cart?.items?.length" class="card p-10 text-center">
        <ShoppingBag :size="48" class="mx-auto text-warm-300 mb-4" />
        <h2 class="font-heading text-xl font-bold text-warm-900 dark:text-white mb-2">Your Cart is Empty</h2>
        <p class="text-warm-500 mb-6">Add delicious dishes from our menu before checking out.</p>
        <RouterLink to="/menu" class="btn-primary">Browse Menu</RouterLink>
      </div>

      <form v-else @submit.prevent="placeOrder" class="card p-8 space-y-6">
        <!-- Order Type -->
        <div>
          <label class="block text-sm font-semibold text-warm-800 dark:text-warm-200 mb-2">Order Type</label>
          <div class="grid grid-cols-3 gap-3">
            <label
              v-for="type in ['delivery', 'pickup', 'dine_in']"
              :key="type"
              :class="[
                'flex items-center justify-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all text-sm font-medium text-center',
                form.type === type
                  ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300 shadow-sm'
                  : 'border-warm-200 dark:border-warm-700 text-warm-600 dark:text-warm-400 hover:border-warm-300'
              ]"
            >
              <input type="radio" v-model="form.type" :value="type" class="sr-only" />
              {{ type === 'delivery' ? 'Delivery' : type === 'pickup' ? 'Takeout' : 'Dine In' }}
            </label>
          </div>
        </div>

        <!-- Delivery Zone & Address -->
        <div v-if="form.type === 'delivery'" class="space-y-4 pt-2 border-t border-warm-100 dark:border-warm-800">
          <div>
            <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5 flex items-center gap-1.5">
              <Truck :size="16" class="text-primary-500" /> Delivery Zone
            </label>
            <select v-model="form.delivery_zone_id" required class="input">
              <option v-for="zone in deliveryZones" :key="zone.id" :value="zone.id">
                {{ zone.name }} — ${{ Number(zone.fee).toFixed(2) }} (ETA ~{{ zone.eta_minutes }} min)
              </option>
            </select>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Street Address</label>
              <input v-model="form.address.line1" type="text" required class="input" placeholder="e.g. 123 Main St, Apt 4B" />
            </div>
            <div>
              <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">City / Area</label>
              <input v-model="form.address.city" type="text" required class="input" placeholder="e.g. Downtown" />
            </div>
          </div>
        </div>

        <!-- Dine In Table selection -->
        <div v-else-if="form.type === 'dine_in'" class="space-y-3 pt-2 border-t border-warm-100 dark:border-warm-800">
          <div>
            <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5 flex items-center gap-1.5">
              <Utensils :size="16" class="text-primary-500" /> Table Number (1 - 20)
            </label>
            <input v-model.number="form.table_id" type="number" min="1" max="20" required class="input" placeholder="Enter your table number (e.g. 1)" />
          </div>
        </div>

        <!-- Payment Method -->
        <div class="pt-2 border-t border-warm-100 dark:border-warm-800">
          <label class="block text-sm font-semibold text-warm-800 dark:text-warm-200 mb-2">Select Payment Method</label>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <label
              v-for="method in [
                { id: 'chapa', name: 'Chapa Pay', desc: 'Telebirr, CBE Birr, Cards', badge: 'Ethiopia' },
                { id: 'cash', name: 'Cash on Arrival', desc: 'Pay cashier or delivery driver', badge: 'Cash' },
                { id: 'stripe', name: 'Credit / Debit Card', desc: 'International Visa & Mastercard', badge: 'Card' },
              ]"
              :key="method.id"
              :class="[
                'flex flex-col justify-between p-3.5 rounded-xl border-2 cursor-pointer transition-all text-sm',
                form.payment_method === method.id
                  ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/40 text-primary-900 dark:text-primary-100 shadow-sm'
                  : 'border-warm-200 dark:border-warm-700 text-warm-600 dark:text-warm-400 hover:border-warm-300'
              ]"
            >
              <div>
                <div class="flex items-center justify-between mb-1">
                  <span class="font-bold text-warm-900 dark:text-white">{{ method.name }}</span>
                  <input type="radio" v-model="form.payment_method" :value="method.id" class="sr-only" />
                  <span :class="['text-[10px] font-bold px-1.5 py-0.5 rounded', method.id === 'chapa' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400']">
                    {{ method.badge }}
                  </span>
                </div>
                <p class="text-[11px] text-warm-500 leading-tight">{{ method.desc }}</p>
              </div>
            </label>
          </div>
        </div>

        <!-- Order Notes -->
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Order Notes</label>
          <textarea v-model="form.notes" rows="2" class="input resize-none" placeholder="Any special preparation or delivery instructions..."></textarea>
        </div>

        <!-- Totals summary -->
        <div class="space-y-2 text-sm border-t border-warm-100 dark:border-warm-800 pt-4">
          <div class="flex justify-between text-warm-600 dark:text-warm-400">
            <span>Subtotal</span>
            <span>${{ Number(cart.totals.subtotal).toFixed(2) }}</span>
          </div>
          <div v-if="cart.totals.discount > 0" class="flex justify-between text-green-600 font-medium">
            <span>Discount</span>
            <span>-${{ Number(cart.totals.discount).toFixed(2) }}</span>
          </div>
          <div v-if="form.type === 'delivery' && selectedZone" class="flex justify-between text-warm-600 dark:text-warm-400">
            <span>Delivery Fee ({{ selectedZone.name }})</span>
            <span>${{ Number(selectedZone.fee).toFixed(2) }}</span>
          </div>
          <div class="flex justify-between text-warm-600 dark:text-warm-400">
            <span>Tax</span>
            <span>${{ Number(cart.totals.tax).toFixed(2) }}</span>
          </div>
          <div class="flex justify-between font-bold text-warm-900 dark:text-white text-lg pt-2 border-t border-warm-100 dark:border-warm-800">
            <span>Total</span>
            <span class="text-primary-600 dark:text-primary-400">
              ${{ (Number(cart.totals.total) + (form.type === 'delivery' && selectedZone ? Number(selectedZone.fee) : 0)).toFixed(2) }}
            </span>
          </div>
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="btn-primary w-full justify-center py-3.5 text-base font-semibold shadow-md hover:shadow-lg transition-all"
        >
          {{ loading ? 'Placing Order...' : 'Confirm & Place Order' }}
        </button>
      </form>
    </div>
  </DefaultLayout>
</template>
