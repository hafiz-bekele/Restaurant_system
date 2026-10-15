<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useCartStore } from '../../stores/cart'
import { useAuthStore } from '../../stores/auth'
import { useUiStore }   from '../../stores/ui'
import { orderApi, guestApi, paymentApi } from '../../services/api'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import {
  MapPin, CreditCard, ShoppingBag, CheckCircle, Truck,
  Utensils, Smartphone, User, Phone, Mail, Sparkles, AlertCircle
} from '@lucide/vue'

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
  customer_name: '',
  customer_phone: '',
  customer_email: '',
  notes: '',
  address: {
    line1: '',
    city:  'Addis Ababa',
  },
})

// Auto-fill from authenticated user if available
function populateUserInfo() {
  if (auth.isAuthenticated && auth.user) {
    if (!form.value.customer_name)  form.value.customer_name  = auth.user.name || ''
    if (!form.value.customer_phone) form.value.customer_phone = auth.user.phone || ''
    if (!form.value.customer_email) form.value.customer_email = auth.user.email || ''
  }
}

watch(() => auth.isAuthenticated, populateUserInfo, { immediate: true })

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
  populateUserInfo()
})

async function placeOrder() {
  if (!cart.cart?.items?.length) {
    ui.toast('Your cart is empty', 'warning')
    router.push('/menu')
    return
  }

  // Basic validation for contact info
  if (!form.value.customer_name.trim()) {
    ui.toast('Please enter your name', 'warning')
    return
  }

  if (!form.value.customer_phone.trim()) {
    ui.toast('Please provide a contact phone number', 'warning')
    return
  }

  loading.value = true
  try {
    const payload = {
      type: form.value.type,
      payment_method: form.value.payment_method,
      customer_name: form.value.customer_name.trim(),
      customer_phone: form.value.customer_phone.trim(),
      customer_email: form.value.customer_email?.trim() || undefined,
      notes: form.value.notes || undefined,
    }

    if (form.value.type === 'delivery') {
      if (!form.value.delivery_zone_id) {
        ui.toast('Please select a delivery zone', 'warning')
        loading.value = false
        return
      }
      if (!form.value.address.line1?.trim()) {
        ui.toast('Please provide your delivery street address', 'warning')
        loading.value = false
        return
      }

      payload.delivery_zone_id = Number(form.value.delivery_zone_id)
      payload.address = {
        line1: form.value.address.line1,
        city: form.value.address.city || 'Addis Ababa',
        customer_name: form.value.customer_name.trim(),
        customer_phone: form.value.customer_phone.trim(),
      }
    } else if (form.value.type === 'dine_in') {
      if (!form.value.table_id) {
        ui.toast('Please specify your table number', 'warning')
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
      ui.toast('Connecting to payment gateway (Telebirr / CBE Birr / Cards)...', 'info')
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

    // Direct to order tracking page
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
      <p class="text-warm-500 dark:text-warm-400 mb-6">Review your order details and payment preference. No account required!</p>

      <!-- Guest Checkout Info Banner -->
      <div v-if="!auth.isAuthenticated" class="card p-4 mb-6 border-primary-200 dark:border-primary-800/60 bg-primary-50/70 dark:bg-primary-950/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 text-primary-900 dark:text-primary-200">
          <Sparkles :size="20" class="shrink-0 text-primary-500" />
          <p class="text-xs sm:text-sm">
            <strong>Fast Guest Checkout:</strong> You can order &amp; pay right now without registering or signing in.
          </p>
        </div>
        <RouterLink to="/login?redirect=/checkout" class="text-xs font-bold text-primary-600 dark:text-primary-400 hover:underline shrink-0">
          Have an account? Sign in
        </RouterLink>
      </div>

      <!-- Authenticated Member Badge -->
      <div v-else class="card p-3.5 mb-6 border-emerald-200 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-950/30 flex items-center justify-between">
        <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 text-xs sm:text-sm">
          <CheckCircle :size="16" class="text-emerald-500" />
          <span>Ordering as <strong>{{ auth.user?.name }}</strong> ({{ auth.user?.email }})</span>
        </div>
        <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">Member</span>
      </div>

      <!-- Checkout Loading Skeleton -->
      <div v-if="cart.loading && !cart.cart?.items?.length" class="card p-8 space-y-6 border border-warm-100 dark:border-warm-800">
        <div class="space-y-2">
          <div class="skeleton h-4 w-24 rounded"></div>
          <div class="grid grid-cols-3 gap-3">
            <div class="skeleton h-12 rounded-xl"></div>
            <div class="skeleton h-12 rounded-xl"></div>
            <div class="skeleton h-12 rounded-xl"></div>
          </div>
        </div>
        <div class="space-y-3 pt-4 border-t border-warm-100 dark:border-warm-800">
          <div class="skeleton h-4 w-32 rounded"></div>
          <div class="skeleton h-11 w-full rounded-xl"></div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="skeleton h-11 rounded-xl"></div>
            <div class="skeleton h-11 rounded-xl"></div>
          </div>
        </div>
      </div>

      <!-- Empty Cart Alert -->
      <div v-else-if="!cart.cart?.items?.length" class="card p-10 text-center border border-warm-100 dark:border-warm-800">
        <ShoppingBag :size="48" class="mx-auto text-warm-300 dark:text-warm-700 mb-4" />
        <h2 class="font-heading text-xl font-bold text-warm-900 dark:text-white mb-2">Your Cart is Empty</h2>
        <p class="text-warm-500 dark:text-warm-400 mb-6">Add delicious dishes from our menu before checking out.</p>
        <RouterLink to="/menu" class="btn-primary">Browse Menu</RouterLink>
      </div>

      <!-- Main Checkout Form -->
      <form v-else @submit.prevent="placeOrder" class="card p-6 sm:p-8 space-y-6">

        <!-- 1. Contact Information (For Guest & User) -->
        <div>
          <h2 class="text-sm font-bold text-warm-900 dark:text-white mb-3 flex items-center gap-1.5 uppercase tracking-wider">
            <User :size="16" class="text-primary-500" />
            <span>Contact Information</span>
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">
                Your Full Name <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <User :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
                <input
                  v-model="form.customer_name"
                  type="text"
                  required
                  placeholder="e.g. Abebe Kebede"
                  class="input pl-9"
                />
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">
                Phone Number <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <Phone :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
                <input
                  v-model="form.customer_phone"
                  type="tel"
                  required
                  placeholder="+251 91 123 4567"
                  class="input pl-9"
                />
              </div>
            </div>

            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">
                Email Address <span class="text-xs text-warm-400 font-normal">(Optional — for order receipt &amp; live tracking)</span>
              </label>
              <div class="relative">
                <Mail :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
                <input
                  v-model="form.customer_email"
                  type="email"
                  placeholder="you@example.com"
                  class="input pl-9"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- 2. Order Type -->
        <div class="pt-4 border-t border-warm-100 dark:border-warm-800">
          <label class="block text-sm font-bold text-warm-900 dark:text-white mb-2 uppercase tracking-wider">
            Dining Option
          </label>
          <div class="grid grid-cols-3 gap-3">
            <label
              v-for="type in ['delivery', 'pickup', 'dine_in']"
              :key="type"
              :class="[
                'flex items-center justify-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all text-xs sm:text-sm font-semibold text-center',
                form.type === type
                  ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300 shadow-sm'
                  : 'border-warm-200 dark:border-warm-700 text-warm-600 dark:text-warm-400 hover:border-warm-300'
              ]"
            >
              <input type="radio" v-model="form.type" :value="type" class="sr-only" />
              {{ type === 'delivery' ? '🛵 Delivery' : type === 'pickup' ? '🛍️ Takeout' : '🍽️ Dine In' }}
            </label>
          </div>
        </div>

        <!-- Delivery Zone & Street Address -->
        <div v-if="form.type === 'delivery'" class="space-y-4 pt-2">
          <div>
            <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1.5 flex items-center gap-1.5">
              <Truck :size="15" class="text-primary-500" /> Select Delivery Zone <span class="text-red-500">*</span>
            </label>
            <select v-model="form.delivery_zone_id" required class="input">
              <option v-for="zone in deliveryZones" :key="zone.id" :value="zone.id">
                {{ zone.name }} — ${{ Number(zone.fee).toFixed(2) }} (Est. ~{{ zone.eta_minutes }} mins)
              </option>
            </select>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">
                Street Address / Building <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.address.line1"
                type="text"
                required
                class="input"
                placeholder="e.g. Namibia St, Bole, Apt 4B"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">City / Area</label>
              <input
                v-model="form.address.city"
                type="text"
                required
                class="input"
                placeholder="Addis Ababa"
              />
            </div>
          </div>
        </div>

        <!-- Dine In Table selection -->
        <div v-else-if="form.type === 'dine_in'" class="space-y-3 pt-2">
          <div>
            <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1.5 flex items-center gap-1.5">
              <Utensils :size="15" class="text-primary-500" /> Table Number (1 - 20) <span class="text-red-500">*</span>
            </label>
            <input
              v-model.number="form.table_id"
              type="number"
              min="1"
              max="20"
              required
              class="input"
              placeholder="Enter your table number (check table QR badge)"
            />
          </div>
        </div>

        <!-- Takeout Pickup instructions -->
        <div v-else-if="form.type === 'pickup'" class="p-3.5 rounded-xl bg-warm-50 dark:bg-white/5 border border-warm-200 dark:border-white/10 text-xs text-warm-600 dark:text-warm-300">
          <p class="font-semibold text-warm-900 dark:text-white mb-0.5">Pickup Location:</p>
          <p>Main Restaurant Host Counter, Bole Medhanialem, Namibia St. Ready in approx. 15–20 minutes.</p>
        </div>

        <!-- 3. Payment Method -->
        <div class="pt-4 border-t border-warm-100 dark:border-warm-800">
          <label class="block text-sm font-bold text-warm-900 dark:text-white mb-2 uppercase tracking-wider">
            Payment Method
          </label>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <label
              v-for="method in [
                { id: 'chapa', name: 'Chapa Pay', desc: 'Telebirr, CBE Birr, Cards', badge: 'Fast Online' },
                { id: 'cash', name: 'Cash on Arrival', desc: 'Pay cashier or delivery driver', badge: 'Cash' },
                { id: 'stripe', name: 'Credit / Debit Card', desc: 'Visa & Mastercard', badge: 'Card' },
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
                  <span class="font-bold text-warm-900 dark:text-white text-xs sm:text-sm">{{ method.name }}</span>
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

        <!-- 4. Order Notes -->
        <div>
          <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1.5">Special Instructions / Allergies</label>
          <textarea
            v-model="form.notes"
            rows="2"
            class="input resize-none"
            placeholder="e.g. Extra spicy, no onions, ring doorbell, etc."
          ></textarea>
        </div>

        <!-- 5. Totals summary -->
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
            <span>Total to Pay</span>
            <span class="text-primary-600 dark:text-primary-400">
              ${{ (Number(cart.totals.total) + (form.type === 'delivery' && selectedZone ? Number(selectedZone.fee) : 0)).toFixed(2) }}
            </span>
          </div>
        </div>

        <!-- 6. Submit Button -->
        <button
          type="submit"
          :disabled="loading"
          class="btn-primary w-full justify-center py-3.5 text-base font-bold shadow-lg shadow-primary-500/25 hover:shadow-primary-500/40 transition-all cursor-pointer"
        >
          {{ loading ? 'Placing Order...' : (form.payment_method === 'chapa' ? 'Pay with Chapa (Telebirr / Card)' : 'Confirm & Place Order') }}
        </button>
      </form>
    </div>
  </DefaultLayout>
</template>
