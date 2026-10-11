<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { staffApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { CreditCard, DollarSign, Printer, CheckCircle, RefreshCw, AlertCircle, ShoppingBag, BedDouble, Receipt } from '@lucide/vue'

const ui = useUiStore()

const loading = ref(true)
const orders = ref([])
const summary = ref({
  orders_count: 0,
  cash_total: 0,
  digital_total: 0,
  room_charge_total: 0,
  grand_total: 0,
})

const filter = ref('all') // all, pending, paid
const selectedOrder = ref(null)
const showReceiptModal = ref(false)

const filteredOrders = computed(() => {
  if (filter.value === 'pending') {
    return orders.value.filter(o => o.payment_status === 'pending')
  }
  if (filter.value === 'paid') {
    return orders.value.filter(o => o.payment_status === 'paid')
  }
  return orders.value
})

async function fetchData() {
  try {
    const [ordersRes, summaryRes] = await Promise.all([
      staffApi.cashierOrders(),
      staffApi.cashierSummary(),
    ])
    orders.value = ordersRes.data.data ?? []
    summary.value = summaryRes.data.data ?? summary.value
  } catch (err) {
    console.error('Failed to load cashier data', err)
  } finally {
    loading.value = false
  }
}

async function confirmPayment(order, method = 'cash') {
  try {
    await staffApi.confirmPayment(order.ulid, { payment_type: method })
    ui.toast(`Payment for order #${order.ulid.slice(-6)} confirmed!`, 'success')
    await fetchData()
  } catch (err) {
    ui.toast('Failed to confirm payment', 'error')
  }
}

function openReceipt(order) {
  selectedOrder.value = order
  showReceiptModal.value = true
}

function printReceipt() {
  window.print()
}

let timer
onMounted(() => {
  fetchData()
  timer = setInterval(fetchData, 10000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<template>
  <div>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
          <CreditCard :size="26" class="text-primary-500" /> Cashier &amp; POS Billing
        </h1>
        <p class="text-sm text-warm-500">Reconcile shift balances, settle bills, and issue fiscal receipts.</p>
      </div>
      <button @click="fetchData" class="btn-ghost text-xs gap-1.5 py-1.5 px-3">
        <RefreshCw :size="14" /> Refresh
      </button>
    </div>

    <!-- Shift Financial Balance Summary Cards (REQ-2.3, REQ-9.1) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="card p-5 border-l-4 border-green-500">
        <p class="text-xs font-medium text-warm-500 mb-1">Cash Collected</p>
        <p class="font-heading text-2xl font-bold text-green-600 dark:text-green-400">
          ${{ Number(summary.cash_total || 0).toFixed(2) }}
        </p>
        <p class="text-[11px] text-warm-400 mt-1">Cash drawer register</p>
      </div>

      <div class="card p-5 border-l-4 border-blue-500">
        <p class="text-xs font-medium text-warm-500 mb-1">Digital / Cards / Chapa</p>
        <p class="font-heading text-2xl font-bold text-blue-600 dark:text-blue-400">
          ${{ Number(summary.digital_total || 0).toFixed(2) }}
        </p>
        <p class="text-[11px] text-warm-400 mt-1">Stripe, Chapa, Telebirr</p>
      </div>

      <div class="card p-5 border-l-4 border-purple-500">
        <p class="text-xs font-medium text-warm-500 mb-1">Room Charges Billed</p>
        <p class="font-heading text-2xl font-bold text-purple-600 dark:text-purple-400">
          ${{ Number(summary.room_charge_total || 0).toFixed(2) }}
        </p>
        <p class="text-[11px] text-warm-400 mt-1">Hotel suite guest folios</p>
      </div>

      <div class="card p-5 border-l-4 border-primary-500">
        <p class="text-xs font-medium text-warm-500 mb-1">Shift Total Revenue</p>
        <p class="font-heading text-2xl font-bold text-primary-600 dark:text-primary-400">
          ${{ Number(summary.grand_total || 0).toFixed(2) }}
        </p>
        <p class="text-[11px] text-warm-400 mt-1">{{ summary.orders_count }} total orders today</p>
      </div>
    </div>

    <!-- Orders Management & Filter -->
    <div class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white flex items-center gap-2">
          <Receipt :size="20" class="text-primary-500" /> Recent Orders &amp; Billing
        </h2>
        <div class="flex gap-2">
          <button
            @click="filter = 'all'"
            :class="['px-3 py-1.5 rounded-lg text-xs font-medium transition-colors', filter === 'all' ? 'bg-primary-500 text-white' : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300']"
          >
            All Orders ({{ orders.length }})
          </button>
          <button
            @click="filter = 'pending'"
            :class="['px-3 py-1.5 rounded-lg text-xs font-medium transition-colors', filter === 'pending' ? 'bg-amber-500 text-white' : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300']"
          >
            Unpaid / Pending ({{ orders.filter(o => o.payment_status === 'pending').length }})
          </button>
          <button
            @click="filter = 'paid'"
            :class="['px-3 py-1.5 rounded-lg text-xs font-medium transition-colors', filter === 'paid' ? 'bg-green-600 text-white' : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300']"
          >
            Paid / Settled ({{ orders.filter(o => o.payment_status === 'paid').length }})
          </button>
        </div>
      </div>

      <div v-if="loading" class="space-y-3">
        <div v-for="i in 5" :key="i" class="skeleton h-16 rounded-xl"></div>
      </div>

      <div v-else-if="filteredOrders.length" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-warm-50 dark:bg-warm-800/60 text-warm-600 dark:text-warm-300 text-xs">
            <tr>
              <th class="px-4 py-3 text-left font-semibold">Order / ULID</th>
              <th class="px-4 py-3 text-left font-semibold">Customer / Destination</th>
              <th class="px-4 py-3 text-left font-semibold">Method</th>
              <th class="px-4 py-3 text-left font-semibold">Total Amount</th>
              <th class="px-4 py-3 text-left font-semibold">Payment Status</th>
              <th class="px-4 py-3 text-right font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-100 dark:divide-warm-800">
            <tr v-for="order in filteredOrders" :key="order.ulid" class="hover:bg-warm-50/50 dark:hover:bg-warm-850 transition-colors">
              <td class="px-4 py-3">
                <span class="font-mono text-xs text-warm-500 font-semibold">{{ order.ulid.slice(-8) }}</span>
                <p class="text-[11px] text-warm-400">{{ order.items?.length ?? 0 }} items</p>
              </td>
              <td class="px-4 py-3">
                <p class="font-medium text-warm-900 dark:text-white">{{ order.user?.name ?? 'Guest Customer' }}</p>
                <p class="text-xs text-warm-400">
                  {{ order.type === 'dine_in' ? `Table ${order.table?.name ?? order.table_id ?? 'N/A'}` : order.type === 'delivery' ? 'Delivery Order' : 'Takeout' }}
                </p>
              </td>
              <td class="px-4 py-3">
                <span class="capitalize text-xs font-medium text-warm-700 dark:text-warm-300">
                  {{ order.payment_method }}
                </span>
              </td>
              <td class="px-4 py-3 font-semibold text-primary-600 dark:text-primary-400 font-mono text-sm">
                ${{ Number(order.total).toFixed(2) }}
              </td>
              <td class="px-4 py-3">
                <span :class="['badge text-xs', order.payment_status === 'paid' ? 'badge-green' : 'badge-orange']">
                  {{ order.payment_status === 'paid' ? 'Paid' : 'Unpaid' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right space-x-2">
                <button
                  v-if="order.payment_status !== 'paid'"
                  @click="confirmPayment(order, 'cash')"
                  class="btn-primary py-1 px-3 text-xs bg-green-600 hover:bg-green-700 inline-flex items-center gap-1"
                >
                  <DollarSign :size="13" /> Settle Cash
                </button>
                <button
                  @click="openReceipt(order)"
                  class="btn-outline py-1 px-3 text-xs inline-flex items-center gap-1"
                >
                  <Printer :size="13" /> Receipt
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="text-center py-16 text-warm-400">
        <Receipt :size="48" class="mx-auto mb-3 opacity-30" />
        <p class="font-medium">No orders matching filter</p>
      </div>
    </div>

    <!-- ESC/POS Thermal Receipt Modal (REQ-4.2) -->
    <div v-if="showReceiptModal && selectedOrder" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-sm space-y-4 bg-white dark:bg-warm-900 border text-warm-900 dark:text-white">
        <!-- Printable area -->
        <div id="thermal-receipt" class="font-mono text-xs space-y-2 border-b border-dashed border-warm-300 pb-4">
          <div class="text-center border-b border-dashed border-warm-300 pb-3">
            <h3 class="font-bold text-base tracking-wider uppercase">RESTAURANT &amp; RESORT</h3>
            <p class="text-[10px] text-warm-500">VAT Registration No: 10492850</p>
            <p class="text-[10px] text-warm-500">Tel: +251 11 000 0000</p>
            <p class="text-[10px] text-warm-400 mt-1">{{ new Date().toLocaleString() }}</p>
          </div>

          <div class="flex justify-between text-[11px] pt-1">
            <span>Order #: {{ selectedOrder.ulid.slice(-8) }}</span>
            <span class="capitalize">{{ selectedOrder.type }}</span>
          </div>
          <div class="flex justify-between text-[11px]">
            <span>Guest: {{ selectedOrder.user?.name ?? 'Guest' }}</span>
            <span v-if="selectedOrder.table">Table: {{ selectedOrder.table.name }}</span>
          </div>

          <hr class="border-dashed border-warm-300 my-2" />

          <div class="space-y-1">
            <div v-for="item in selectedOrder.items" :key="item.id" class="flex justify-between">
              <span>{{ item.quantity }}x {{ item.name_snapshot }}</span>
              <span>${{ (Number(item.price_snapshot) * item.quantity).toFixed(2) }}</span>
            </div>
          </div>

          <hr class="border-dashed border-warm-300 my-2" />

          <div class="space-y-1 text-right">
            <div class="flex justify-between">
              <span>Subtotal:</span>
              <span>${{ Number(selectedOrder.subtotal).toFixed(2) }}</span>
            </div>
            <div v-if="selectedOrder.service_charge" class="flex justify-between text-warm-500">
              <span>Service Charge:</span>
              <span>${{ Number(selectedOrder.service_charge).toFixed(2) }}</span>
            </div>
            <div v-if="selectedOrder.tax" class="flex justify-between text-warm-500">
              <span>VAT:</span>
              <span>${{ Number(selectedOrder.tax).toFixed(2) }}</span>
            </div>
            <div v-if="selectedOrder.discount" class="flex justify-between text-green-600">
              <span>Discount:</span>
              <span>-${{ Number(selectedOrder.discount).toFixed(2) }}</span>
            </div>
            <div class="flex justify-between font-bold text-sm pt-1 border-t border-dashed border-warm-300">
              <span>TOTAL:</span>
              <span>${{ Number(selectedOrder.total).toFixed(2) }}</span>
            </div>
            <div class="flex justify-between text-[11px] text-warm-500 pt-1">
              <span>Method / Status:</span>
              <span class="capitalize font-semibold">{{ selectedOrder.payment_method }} ({{ selectedOrder.payment_status }})</span>
            </div>
          </div>

          <div class="text-center text-[10px] text-warm-400 pt-3">
            <p>Thank you for dining with us!</p>
            <p>Please come again.</p>
          </div>
        </div>

        <div class="flex gap-2">
          <button @click="printReceipt" class="btn-primary flex-1 justify-center py-2 text-xs">
            <Printer :size="14" /> Print Thermal Slip
          </button>
          <button @click="showReceiptModal = false" class="btn-outline flex-1 justify-center py-2 text-xs">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
