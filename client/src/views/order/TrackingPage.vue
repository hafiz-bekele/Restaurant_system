<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { orderApi, paymentApi, reviewApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import {
  CheckCircle, Clock, ChefHat, Truck, Package, ShieldCheck,
  RefreshCw, Smartphone, ExternalLink, Star, MessageSquare, Check, Send,
  Printer, ReceiptText, X, QrCode, MapPin, Phone, User
} from '@lucide/vue'

const route = useRoute()
const ui = useUiStore()
const order = ref(null)
const loading = ref(true)
const paying = ref(false)
const verifying = ref(false)
const showReceiptModal = ref(false)

function printReceipt() {
  window.print()
}

// Review system state
const activeRatingDishId = ref(null)
const ratingValues = ref({})
const commentValues = ref({})
const hoverStars = ref({})
const ratedDishes = ref({})
const submittingReview = ref(false)

const steps = [
  { status: 'pending',          label: 'Order Placed',  icon: CheckCircle },
  { status: 'confirmed',        label: 'Confirmed',     icon: CheckCircle },
  { status: 'preparing',        label: 'Preparing',     icon: ChefHat },
  { status: 'ready',            label: 'Ready',         icon: Package },
  { status: 'out_for_delivery', label: 'On the Way',    icon: Truck },
  { status: 'delivered',        label: 'Delivered',     icon: CheckCircle },
]

async function fetchOrder() {
  const { data } = await orderApi.track(route.params.ulid)
  order.value = data.data
  loading.value = false
}

async function payWithChapa() {
  paying.value = true
  try {
    const { data } = await paymentApi.createChapaIntent(route.params.ulid)
    const checkoutUrl = data.data?.checkout_url
    if (checkoutUrl) {
      window.location.href = checkoutUrl
    }
  } catch (err) {
    ui.toast('Failed to initialize Chapa gateway', 'error')
  } finally {
    paying.value = false
  }
}

async function verifyChapaPayment(silent = false) {
  verifying.value = true
  try {
    await paymentApi.verifyChapa({ order_ulid: route.params.ulid })
    if (!silent) ui.toast('Chapa payment verified and confirmed!', 'success')
    await fetchOrder()
  } catch (err) {
    if (!silent) ui.toast('Could not verify Chapa transaction', 'error')
  } finally {
    verifying.value = false
  }
}

const stepIndex = (status) => steps.findIndex(s => s.status === status)

function toggleRatingForm(dishId) {
  if (activeRatingDishId.value === dishId) {
    activeRatingDishId.value = null
  } else {
    activeRatingDishId.value = dishId
    if (!ratingValues.value[dishId]) ratingValues.value[dishId] = 5
  }
}

async function submitDishReview(dishId) {
  const rating = ratingValues.value[dishId] || 5
  const comment = commentValues.value[dishId]?.trim() || null

  submittingReview.value = true
  try {
    await reviewApi.submit({
      dish_id:  dishId,
      order_id: order.value?.id,
      rating,
      comment,
    })

    ui.toast('Thank you for rating your dish!', 'success')
    ratedDishes.value[dishId] = true
    activeRatingDishId.value = null
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to submit rating'
    ui.toast(msg, 'error')
  } finally {
    submittingReview.value = false
  }
}

let interval
onMounted(async () => {
  await fetchOrder()
  interval = setInterval(fetchOrder, 15000)

  // Auto-verify if returning from Chapa redirect
  if (route.query.payment === 'chapa_return' || route.query.payment === 'chapa_simulation') {
    await verifyChapaPayment(true)
  }

  // Listen via WebSocket
  if (window.Echo) {
    window.Echo.channel(`orders.${route.params.ulid}`).listen('OrderStatusChanged', fetchOrder)
  }
})

onUnmounted(() => {
  clearInterval(interval)
  if (window.Echo) window.Echo.leaveChannel(`orders.${route.params.ulid}`)
})
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-12 max-w-2xl">
      <h1 class="font-heading text-3xl font-bold text-warm-900 dark:text-white mb-8">Order Tracking</h1>

      <!-- Order Tracking Loading Skeleton -->
      <div v-if="loading" class="card p-8 space-y-8">
        <div class="flex items-center justify-between">
          <div class="space-y-2">
            <div class="skeleton h-3.5 w-24 rounded"></div>
            <div class="skeleton h-6 w-40 rounded-lg"></div>
          </div>
          <div class="skeleton h-7 w-28 !rounded-full"></div>
        </div>

        <!-- Progress step bar skeleton -->
        <div class="flex items-center justify-between px-2 sm:px-4 py-4 border-y border-warm-100 dark:border-warm-800">
          <div v-for="i in 4" :key="i" class="flex flex-col items-center gap-2">
            <div class="skeleton w-10 h-10 !rounded-full"></div>
            <div class="skeleton h-3 w-14 rounded"></div>
          </div>
        </div>

        <!-- Order Items Skeleton -->
        <div class="space-y-3 pt-2">
          <div class="skeleton h-4 w-28 rounded"></div>
          <div v-for="i in 3" :key="i" class="flex items-center justify-between">
            <div class="skeleton h-4 w-44 rounded"></div>
            <div class="skeleton h-4 w-16 rounded"></div>
          </div>
        </div>
      </div>

      <div v-else-if="order" class="space-y-6">
        <!-- Main Tracking Progress Card -->
        <div class="card p-8">
          <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
            <div>
              <p class="text-xs text-warm-500 font-semibold uppercase tracking-wider">Order Reference</p>
              <p class="font-mono font-bold text-warm-900 dark:text-white text-base">
                {{ order.order_number || order.ulid }}
              </p>
            </div>
            <div class="flex items-center gap-2.5">
              <button
                @click="showReceiptModal = true"
                class="btn-secondary text-xs py-2 px-3.5 rounded-xl gap-2 shadow-sm border border-warm-200 dark:border-warm-700 hover:border-primary-400 transition-colors"
                title="View and print official tax invoice & order receipt"
              >
                <Printer :size="15" class="text-primary-600 dark:text-primary-400" />
                <span>View / Print Receipt</span>
              </button>
              <span :class="['badge font-bold uppercase text-xs', order.status === 'delivered' ? 'badge-green' : order.status === 'cancelled' ? 'badge-red' : 'badge-orange']">
                {{ order.status_label }}
              </span>
            </div>
          </div>

          <!-- Progress steps -->
          <div class="relative mb-6">
            <div class="absolute top-5 left-5 right-5 h-0.5 bg-warm-200 dark:bg-warm-700"></div>
            <div
              class="absolute top-5 left-5 h-0.5 bg-primary-500 transition-all duration-700"
              :style="{ width: `${(stepIndex(order.status) / (steps.length - 1)) * 100}%`, maxWidth: 'calc(100% - 2.5rem)' }"
            ></div>
            <div class="relative flex justify-between">
              <div v-for="(step, idx) in steps" :key="step.status" class="flex flex-col items-center gap-2">
                <div :class="[
                  'w-10 h-10 rounded-full flex items-center justify-center z-10 transition-all duration-300',
                  idx <= stepIndex(order.status)
                    ? 'bg-primary-500 text-white shadow-md shadow-primary-500/30'
                    : 'bg-warm-100 dark:bg-warm-800 text-warm-400'
                ]">
                  <component :is="step.icon" :size="18" />
                </div>
                <span class="text-xs font-medium text-warm-600 dark:text-warm-400 text-center leading-tight max-w-16">{{ step.label }}</span>
              </div>
            </div>
          </div>

          <!-- Chapa Payment Status Section -->
          <div v-if="order.payment_method === 'chapa'" class="mt-8 pt-6 border-t border-warm-100 dark:border-warm-800">
            <!-- Paid State -->
            <div v-if="order.payment_status === 'paid'" class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-800 flex items-center justify-between gap-3">
              <div class="flex items-center gap-3 text-emerald-800 dark:text-emerald-200">
                <ShieldCheck :size="24" class="text-emerald-500 shrink-0" />
                <div>
                  <p class="font-bold text-sm">Payment Verified via Chapa</p>
                  <p class="text-xs text-emerald-600 dark:text-emerald-400">Total of ETB {{ Number(order.total).toFixed(2) }} settled successfully via Telebirr / CBE / Card.</p>
                </div>
              </div>
              <span class="badge badge-green text-xs font-bold uppercase">PAID</span>
            </div>

            <!-- Pending State -->
            <div v-else class="p-5 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-800 space-y-3">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5 text-amber-900 dark:text-amber-200">
                  <Smartphone :size="20" class="text-amber-600" />
                  <div>
                    <p class="font-bold text-sm">Chapa Payment Pending</p>
                    <p class="text-xs text-amber-700 dark:text-amber-400">Please complete payment of ETB {{ Number(order.total).toFixed(2) }} via Telebirr, CBE Birr, or Card.</p>
                  </div>
                </div>
                <span class="badge badge-orange text-xs font-bold uppercase">UNPAID</span>
              </div>

              <div class="flex flex-wrap gap-2 pt-1">
                <button
                  @click="payWithChapa"
                  :disabled="paying"
                  class="btn-primary text-xs py-2 px-4 shadow-sm gap-1.5"
                >
                  <ExternalLink :size="14" />
                  <span>{{ paying ? 'Launching Chapa...' : 'Pay with Chapa (Telebirr / CBE)' }}</span>
                </button>
                <button
                  @click="verifyChapaPayment(false)"
                  :disabled="verifying"
                  class="btn-secondary text-xs py-2 px-3.5 gap-1.5"
                >
                  <RefreshCw :size="14" :class="{ 'animate-spin': verifying }" />
                  <span>{{ verifying ? 'Verifying...' : 'Verify Payment Status' }}</span>
                </button>
              </div>
            </div>
          </div>

          <div v-if="order.estimated_time" class="mt-6 flex items-center gap-2 text-sm text-warm-600 dark:text-warm-400 bg-warm-50 dark:bg-warm-800/50 rounded-xl px-4 py-3">
            <Clock :size="16" class="text-primary-500" />
            Estimated delivery: ~{{ order.estimated_time }} minutes
          </div>
        </div>

        <!-- ──────────────── Rating & Feedback for Order Dishes ──────────────── -->
        <div v-if="order.items?.length" class="card p-6 md:p-8 border border-amber-500/20 bg-gradient-to-br from-amber-500/5 via-primary-500/5 to-transparent rounded-3xl">
          <div class="flex items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-2.5">
              <div class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-950/50 text-amber-500 flex items-center justify-center">
                <Star :size="20" class="fill-amber-400" />
              </div>
              <div>
                <h3 class="font-heading font-bold text-lg text-warm-900 dark:text-white">
                  Rate Your Culinary Experience
                </h3>
                <p class="text-xs text-warm-500 dark:text-warm-400">
                  How was each dish prepared? Rate your meal to help our chefs!
                </p>
              </div>
            </div>
          </div>

          <!-- Dishes in this order list -->
          <div class="divide-y divide-warm-100 dark:divide-warm-800">
            <div
              v-for="item in order.items"
              :key="item.id"
              class="py-4 first:pt-2 last:pb-0"
            >
              <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                  <div class="w-12 h-12 rounded-xl bg-warm-100 dark:bg-warm-800 overflow-hidden shrink-0">
                    <img
                      v-if="item.dish?.image"
                      :src="item.dish.image"
                      :alt="item.dish?.name"
                      class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center text-warm-400">
                      <ChefHat :size="18" />
                    </div>
                  </div>
                  <div>
                    <h4 class="font-heading font-semibold text-sm text-warm-900 dark:text-white">
                      {{ item.dish?.name || 'Dish' }}
                    </h4>
                    <p class="text-xs text-warm-400">
                      Qty: {{ item.quantity }} &bull; ${{ Number(item.unit_price).toFixed(2) }}
                    </p>
                  </div>
                </div>

                <!-- Rating action / state -->
                <div>
                  <div v-if="ratedDishes[item.dish_id]" class="flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-50 dark:bg-emerald-950/40 px-3 py-1.5 rounded-xl border border-emerald-300 dark:border-emerald-800">
                    <Check :size="14" />
                    Rated
                  </div>
                  <button
                    v-else
                    @click="toggleRatingForm(item.dish_id)"
                    class="btn-secondary text-xs py-1.5 px-3 rounded-xl gap-1.5"
                  >
                    <Star :size="13" class="text-amber-400 fill-amber-400" />
                    <span>{{ activeRatingDishId === item.dish_id ? 'Close' : 'Rate Dish' }}</span>
                  </button>
                </div>
              </div>

              <!-- Inline Rating Expand Form -->
              <div
                v-if="activeRatingDishId === item.dish_id && !ratedDishes[item.dish_id]"
                class="mt-4 p-4 rounded-2xl bg-white dark:bg-warm-800/80 border border-warm-200 dark:border-warm-700 space-y-4"
              >
                <div>
                  <label class="block text-xs font-semibold text-warm-500 mb-1.5 uppercase tracking-wider">
                    Select Rating
                  </label>
                  <div class="flex items-center gap-1.5">
                    <button
                      v-for="s in 5"
                      :key="s"
                      type="button"
                      @mouseenter="hoverStars[item.dish_id] = s"
                      @mouseleave="hoverStars[item.dish_id] = 0"
                      @click="ratingValues[item.dish_id] = s"
                      class="p-1 hover:scale-110 transition-transform"
                    >
                      <Star
                        :size="24"
                        :class="[
                          'transition-colors',
                          s <= (hoverStars[item.dish_id] || ratingValues[item.dish_id] || 5)
                            ? 'text-amber-400 fill-amber-400'
                            : 'text-warm-300 dark:text-warm-700'
                        ]"
                      />
                    </button>
                    <span class="text-xs font-bold text-amber-600 ml-2">
                      {{ (hoverStars[item.dish_id] || ratingValues[item.dish_id] || 5) }} Stars
                    </span>
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-semibold text-warm-500 mb-1.5 uppercase tracking-wider">
                    Comments (Optional)
                  </label>
                  <textarea
                    v-model="commentValues[item.dish_id]"
                    rows="2"
                    placeholder="Taste, temperature, portion size..."
                    class="input w-full text-xs p-2.5 rounded-xl resize-none"
                  ></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                  <button
                    type="button"
                    @click="activeRatingDishId = null"
                    class="btn-secondary text-xs py-1.5 px-3 rounded-xl"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    :disabled="submittingReview"
                    @click="submitDishReview(item.dish_id)"
                    class="btn-primary text-xs py-1.5 px-4 rounded-xl gap-1.5"
                  >
                    <Send :size="13" />
                    <span>{{ submittingReview ? 'Submitting...' : 'Submit Review' }}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ──────────────── Print & View Receipt Modal ──────────────── -->
    <Teleport to="body">
      <div
        v-if="showReceiptModal && order"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-warm-950/70 backdrop-blur-sm overflow-y-auto"
        @click.self="showReceiptModal = false"
      >
        <div class="relative w-full max-w-lg my-8 bg-white text-warm-900 rounded-3xl shadow-2xl border border-warm-200 overflow-hidden printable-receipt">
          <!-- Non-printable top action bar -->
          <div class="no-print bg-warm-900 text-white px-6 py-3.5 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <ReceiptText :size="18" class="text-primary-400" />
              <span class="font-bold text-sm tracking-wide">Guest Receipt & Tax Invoice</span>
            </div>
            <div class="flex items-center gap-2">
              <button
                @click="printReceipt"
                class="bg-primary-500 hover:bg-primary-600 text-white text-xs font-bold py-1.5 px-3 rounded-lg flex items-center gap-1.5 transition-colors shadow-sm"
              >
                <Printer :size="13" />
                <span>Print / Save PDF</span>
              </button>
              <button
                @click="showReceiptModal = false"
                class="p-1 rounded-lg hover:bg-warm-800 text-warm-400 hover:text-white transition-colors"
              >
                <X :size="18" />
              </button>
            </div>
          </div>

          <!-- Printable Receipt Sheet Content -->
          <div class="p-6 sm:p-8 space-y-6">
            <!-- Restaurant Header -->
            <div class="text-center pb-5 border-b border-dashed border-warm-300">
              <h2 class="font-heading font-black text-2xl text-warm-900 tracking-tight">
                Taste & Stay Bistro
              </h2>
              <p class="text-xs uppercase tracking-widest text-warm-500 font-semibold mt-0.5">
                Boutique Suites & Fine Dining
              </p>
              <div class="text-[11px] text-warm-600 space-y-0.5 mt-2">
                <p>Bole Medhanialem, Next to Edna Mall &bull; Addis Ababa, Ethiopia</p>
                <p>Tel: +251 91 123 4567 / +251 11 661 2345 &bull; TIN: 0048192014</p>
              </div>
            </div>

            <!-- Invoice & Customer Meta Grid -->
            <div class="grid grid-cols-2 gap-3 text-xs bg-warm-50 p-3.5 rounded-2xl border border-warm-200">
              <div>
                <p class="text-warm-400 uppercase text-[10px] font-bold">Order Ref</p>
                <p class="font-mono font-bold text-warm-900">{{ order.order_number || order.ulid }}</p>
              </div>
              <div class="text-right">
                <p class="text-warm-400 uppercase text-[10px] font-bold">Date & Time</p>
                <p class="font-medium text-warm-800">
                  {{ order.created_at ? new Date(order.created_at).toLocaleString() : new Date().toLocaleString() }}
                </p>
              </div>
              <div>
                <p class="text-warm-400 uppercase text-[10px] font-bold">Customer</p>
                <p class="font-semibold text-warm-900">
                  {{ order.address_snapshot?.customer_name || 'Guest Diner' }}
                </p>
                <p v-if="order.address_snapshot?.customer_phone" class="text-warm-600 text-[11px]">
                  {{ order.address_snapshot.customer_phone }}
                </p>
              </div>
              <div class="text-right">
                <p class="text-warm-400 uppercase text-[10px] font-bold">Order Type</p>
                <span class="inline-block uppercase font-bold text-[10px] px-2 py-0.5 rounded bg-primary-100 text-primary-800 mt-0.5">
                  {{ order.type || 'Dine-In' }}
                </span>
                <p v-if="order.address_snapshot?.delivery_address" class="text-warm-600 text-[11px] mt-0.5 truncate max-w-[180px] ml-auto">
                  {{ order.address_snapshot.delivery_address }}
                </p>
              </div>
            </div>

            <!-- Itemized Table -->
            <div class="space-y-2">
              <div class="flex justify-between text-[11px] font-bold text-warm-500 uppercase tracking-wider border-b border-warm-200 pb-1.5">
                <span class="flex-1">Item Description</span>
                <span class="w-12 text-center">Qty</span>
                <span class="w-20 text-right">Price</span>
                <span class="w-20 text-right">Total</span>
              </div>
              <div class="divide-y divide-warm-100 text-xs">
                <div
                  v-for="item in order.items"
                  :key="item.id"
                  class="py-2 flex items-center justify-between"
                >
                  <div class="flex-1 pr-2">
                    <p class="font-semibold text-warm-900 leading-snug">{{ item.dish?.name || 'Dish Item' }}</p>
                  </div>
                  <span class="w-12 text-center font-medium text-warm-700">{{ item.quantity }}</span>
                  <span class="w-20 text-right text-warm-600 font-mono">
                    ETB {{ Number(item.unit_price).toFixed(2) }}
                  </span>
                  <span class="w-20 text-right font-bold text-warm-900 font-mono">
                    ETB {{ (item.quantity * item.unit_price).toFixed(2) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Financial Totals Section -->
            <div class="border-t-2 border-warm-900 pt-3 space-y-1.5 text-xs">
              <div class="flex justify-between text-warm-600">
                <span>Subtotal</span>
                <span class="font-mono">ETB {{ Number(order.subtotal || order.total).toFixed(2) }}</span>
              </div>
              <div v-if="order.discount" class="flex justify-between text-emerald-600 font-medium">
                <span>Discount Applied</span>
                <span class="font-mono">-ETB {{ Number(order.discount).toFixed(2) }}</span>
              </div>
              <div v-if="order.tax" class="flex justify-between text-warm-600">
                <span>VAT (15%)</span>
                <span class="font-mono">ETB {{ Number(order.tax).toFixed(2) }}</span>
              </div>
              <div v-if="order.service_charge" class="flex justify-between text-warm-600">
                <span>Service Charge (10%)</span>
                <span class="font-mono">ETB {{ Number(order.service_charge).toFixed(2) }}</span>
              </div>
              <div v-if="order.delivery_fee" class="flex justify-between text-warm-600">
                <span>Delivery Fee</span>
                <span class="font-mono">ETB {{ Number(order.delivery_fee).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between items-center text-base font-black text-warm-900 border-t border-dashed border-warm-300 pt-2 mt-2">
                <span>Grand Total</span>
                <span class="font-mono text-lg text-primary-600">
                  ETB {{ Number(order.total).toFixed(2) }}
                </span>
              </div>
            </div>

            <!-- Payment & Verification Footer -->
            <div class="pt-3 border-t border-warm-200 flex items-center justify-between text-xs">
              <div>
                <p class="text-[10px] text-warm-400 uppercase font-bold">Payment Method & Status</p>
                <div class="flex items-center gap-2 mt-0.5">
                  <span class="font-bold text-warm-800 uppercase">{{ order.payment_method || 'CASH' }}</span>
                  <span
                    :class="[
                      'px-2 py-0.5 rounded text-[10px] font-bold uppercase',
                      order.payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'
                    ]"
                  >
                    {{ order.payment_status || 'PENDING' }}
                  </span>
                </div>
              </div>

              <!-- Barcode / QR Simulation -->
              <div class="text-right">
                <div class="inline-flex flex-col items-center">
                  <div class="font-mono tracking-widest text-[10px] text-warm-400">||| | |||| | || |||</div>
                  <span class="text-[9px] text-warm-500 font-mono uppercase">EXPEDITE VERIFIED</span>
                </div>
              </div>
            </div>

            <!-- Thank you note -->
            <div class="text-center text-[11px] text-warm-500 pt-2 border-t border-dashed border-warm-200">
              <p class="font-medium text-warm-700">Thank you for dining with Taste & Stay!</p>
              <p>Experience Ethiopian Hospitality & Boutique Living &bull; www.tasteandstay.et</p>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </DefaultLayout>
</template>

<style scoped>
@media print {
  /* Hide standard page elements */
  :deep(header),
  :deep(footer),
  :deep(nav),
  .no-print {
    display: none !important;
  }

  body {
    background: #ffffff !important;
    color: #000000 !important;
  }

  .printable-receipt {
    position: static !important;
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
    border: none !important;
    box-shadow: none !important;
    background: #ffffff !important;
    color: #000000 !important;
  }
}
</style>
