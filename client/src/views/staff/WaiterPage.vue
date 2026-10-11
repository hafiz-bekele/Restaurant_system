<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { staffApi, adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { UtensilsCrossed, CheckCircle, Clock, Bell, RefreshCw, AlertCircle, Sparkles, Plus, X, Search, Trash2 } from '@lucide/vue'

const ui = useUiStore()

const loading = ref(true)
const tables = ref([])
const activeOrders = ref([])
const areaFilter = ref('All')

// Take order modal state
const showOrderModal = ref(false)
const selectedTable = ref(null)
const dishesList = ref([])
const loadingDishes = ref(false)
const orderItems = ref([])
const orderNotes = ref('')
const dishSearch = ref('')
const submittingOrder = ref(false)

// Audio synthesizer for ready alerts (REQ-12.1)
function playReadyChime() {
  try {
    const AudioContext = window.AudioContext || window.webkitAudioContext
    if (!AudioContext) return
    const ctx = new AudioContext()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.type = 'triangle'
    osc.frequency.setValueAtTime(440, ctx.currentTime) // A4
    osc.frequency.setValueAtTime(659.25, ctx.currentTime + 0.12) // E5
    gain.gain.setValueAtTime(0.15, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35)
    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.start()
    osc.stop(ctx.currentTime + 0.36)
  } catch {}
}

const readyOrders = computed(() => {
  return activeOrders.value.filter(o => o.status === 'ready')
})

const inProgressOrders = computed(() => {
  return activeOrders.value.filter(o => ['confirmed', 'preparing'].includes(o.status))
})

const areas = computed(() => {
  const set = new Set(tables.value.map(t => t.area || 'General'))
  return ['All', ...Array.from(set)]
})

const filteredTables = computed(() => {
  if (areaFilter.value === 'All') return tables.value
  return tables.value.filter(t => (t.area || 'General') === areaFilter.value)
})

let lastReadyCount = 0

async function fetchData() {
  try {
    const [tablesRes, ordersRes] = await Promise.all([
      staffApi.waiterTables(),
      staffApi.waiterOrders(),
    ])
    tables.value = tablesRes.data.data ?? []
    activeOrders.value = ordersRes.data.data ?? []

    // Sound chime if new ready orders arrive (REQ-12.1)
    const currentReady = readyOrders.value.length
    if (currentReady > lastReadyCount && lastReadyCount >= 0) {
      playReadyChime()
    }
    lastReadyCount = currentReady
  } catch (err) {
    console.error('Failed to load waiter data', err)
  } finally {
    loading.value = false
  }
}

async function markServed(order) {
  try {
    await staffApi.markServed(order.ulid)
    ui.toast(`Order #${order.ulid.slice(-6).toUpperCase()} marked as served to table!`, 'success')
    await fetchData()
  } catch (err) {
    ui.toast('Failed to mark as served', 'error')
  }
}

async function openTakeOrder(table = null) {
  selectedTable.value = table ? table.id : (tables.value[0]?.id || '')
  orderItems.value = []
  orderNotes.value = ''
  dishSearch.value = ''
  showOrderModal.value = true

  if (!dishesList.value.length) {
    loadingDishes.value = true
    try {
      const res = await adminApi.dishes()
      dishesList.value = (res.data.data ?? []).filter(d => d.is_available)
    } finally {
      loadingDishes.value = false
    }
  }
}

function addItemToOrder(dish) {
  const existing = orderItems.value.find(i => i.dish_id === dish.id)
  if (existing) {
    existing.quantity++
  } else {
    orderItems.value.push({
      dish_id: dish.id,
      name: dish.name,
      price: Number(dish.price),
      quantity: 1,
    })
  }
}

function updateItemQty(index, delta) {
  orderItems.value[index].quantity += delta
  if (orderItems.value[index].quantity <= 0) {
    orderItems.value.splice(index, 1)
  }
}

// Financial calculations per REQ-6.2
const orderSubtotal = computed(() => {
  return orderItems.value.reduce((sum, item) => sum + item.price * item.quantity, 0)
})
const orderServiceCharge = computed(() => {
  return Math.round(orderSubtotal.value * 0.10 * 100) / 100
})
const orderVat = computed(() => {
  const basis = orderSubtotal.value + orderServiceCharge.value
  return Math.round(basis * 0.15 * 100) / 100
})
const orderGrandTotal = computed(() => {
  return orderSubtotal.value + orderServiceCharge.value + orderVat.value
})

async function submitOrder() {
  if (!selectedTable.value || !orderItems.value.length) return
  submittingOrder.value = true
  try {
    await staffApi.createWaiterOrder({
      table_id: selectedTable.value,
      items: orderItems.value.map(i => ({ dish_id: i.dish_id, quantity: i.quantity })),
      notes: orderNotes.value || undefined,
    })
    ui.toast('Order sent directly to Kitchen KDS!', 'success')
    showOrderModal.value = false
    await fetchData()
  } catch (err) {
    ui.toast(err.response?.data?.message || 'Failed to submit order', 'error')
  } finally {
    submittingOrder.value = false
  }
}

const availableDishesFiltered = computed(() => {
  if (!dishSearch.value) return dishesList.value
  const q = dishSearch.value.toLowerCase()
  return dishesList.value.filter(d => d.name.toLowerCase().includes(q))
})

let timer
onMounted(() => {
  fetchData()
  timer = setInterval(fetchData, 8000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
          <UtensilsCrossed :size="26" class="text-primary-500" /> Waiter Floor Terminal
        </h1>
        <p class="text-sm text-warm-500">Monitor table sections, receive kitchen pickup alerts, and take table orders (REQ-7.x).</p>
      </div>
      <div class="flex items-center gap-3">
        <button @click="openTakeOrder()" class="btn-primary text-xs gap-1.5 py-2 px-3.5 shadow-sm">
          <Plus :size="15" /> Take New Table Order
        </button>
        <button @click="fetchData" class="btn-ghost text-xs gap-1.5 py-1.5 px-3">
          <RefreshCw :size="14" /> Refresh
        </button>
      </div>
    </div>

    <!-- Ready for Pickup Alert Banner (REQ-8.3 & REQ-12.1) -->
    <div v-if="readyOrders.length" class="mb-8 card border-2 border-primary-500 bg-primary-50/20 dark:bg-primary-950/20 shadow-float">
      <div class="flex items-center justify-between p-4 border-b border-primary-200 dark:border-primary-900">
        <div class="flex items-center gap-2 text-primary-700 dark:text-primary-300 font-bold">
          <Bell :size="20" class="animate-bounce" />
          <span>{{ readyOrders.length }} Order(s) READY for Table Delivery!</span>
        </div>
        <span class="badge badge-green text-xs animate-pulse">Kitchen Dispatch Alert</span>
      </div>

      <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="order in readyOrders"
          :key="order.ulid"
          class="card p-4 border border-warm-200 dark:border-warm-800 flex flex-col justify-between"
        >
          <div>
            <div class="flex items-center justify-between mb-2">
              <span class="font-mono font-bold text-sm text-warm-900 dark:text-white">
                #{{ order.ulid.slice(-6).toUpperCase() }}
              </span>
              <span class="badge badge-green text-xs">Ready</span>
            </div>

            <p class="text-sm font-semibold text-primary-600 dark:text-primary-400 mb-2">
              {{ order.table ? `Table: ${order.table.name} (${order.table.area || 'Floor'})` : `Type: ${order.type}` }}
            </p>

            <ul class="text-xs space-y-1 mb-4 bg-warm-50 dark:bg-warm-800/40 p-2.5 rounded-xl">
              <li v-for="item in order.items" :key="item.id" class="text-warm-800 dark:text-warm-200">
                <strong>{{ item.quantity }}x</strong> {{ item.name_snapshot }}
              </li>
            </ul>
          </div>

          <button
            @click="markServed(order)"
            class="btn-primary w-full justify-center text-xs py-2 gap-1.5 shadow-sm"
          >
            <CheckCircle :size="15" /> Mark Delivered & Served
          </button>
        </div>
      </div>
    </div>

    <!-- In Preparation Queue -->
    <div v-if="inProgressOrders.length" class="mb-8">
      <h2 class="font-heading font-semibold text-base text-warm-800 dark:text-warm-200 mb-3 flex items-center gap-2">
        <Clock :size="18" class="text-amber-500" /> Currently Cooking in Kitchen ({{ inProgressOrders.length }})
      </h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div
          v-for="o in inProgressOrders"
          :key="o.ulid"
          class="card p-3 text-xs border border-amber-200 dark:border-amber-900/40 bg-amber-50/10"
        >
          <div class="flex justify-between items-center mb-1">
            <span class="font-mono font-bold">#{{ o.ulid.slice(-6).toUpperCase() }}</span>
            <span class="badge badge-orange text-[10px] capitalize">{{ o.status }}</span>
          </div>
          <p class="font-semibold text-warm-900 dark:text-white">
            {{ o.table ? `Table ${o.table.name}` : o.type }}
          </p>
          <p class="text-warm-500 mt-1">{{ o.items?.length ?? 1 }} item(s)</p>
        </div>
      </div>
    </div>

    <!-- Table Floor Plan Section (REQ-3.10) -->
    <div>
      <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">
          Floor Plan Tables
        </h2>

        <!-- Area Filter Buttons -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1">
          <button
            v-for="area in areas"
            :key="area"
            @click="areaFilter = area"
            :class="[
              'px-3 py-1 rounded-xl text-xs font-semibold transition-all whitespace-nowrap',
              areaFilter === area
                ? 'bg-primary-500 text-white shadow-sm'
                : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-warm-200'
            ]"
          >
            {{ area }}
          </button>
        </div>
      </div>

      <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-4">
        <div v-for="i in 10" :key="i" class="skeleton h-32 rounded-2xl"></div>
      </div>

      <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <div
          v-for="table in filteredTables"
          :key="table.id"
          :class="[
            'card p-4 flex flex-col justify-between transition-all border-2',
            table.status === 'available'
              ? 'border-green-200 dark:border-green-900/40 hover:border-green-400'
              : table.status === 'occupied'
                ? 'border-orange-300 dark:border-orange-800 bg-orange-50/30 dark:bg-orange-950/20'
                : 'border-warm-200 dark:border-warm-800'
          ]"
        >
          <div>
            <div class="flex items-start justify-between">
              <div>
                <p class="font-heading font-bold text-base text-warm-900 dark:text-white">{{ table.name }}</p>
                <p class="text-[11px] text-warm-500">{{ table.area || 'Floor' }}</p>
              </div>
              <span
                :class="[
                  'badge text-[10px] font-semibold',
                  table.status === 'available' ? 'badge-green' : table.status === 'occupied' ? 'badge-orange' : 'badge'
                ]"
              >
                {{ table.status }}
              </span>
            </div>

            <div class="mt-3 pt-2 border-t border-warm-100 dark:border-warm-800 flex items-center justify-between text-xs text-warm-500">
              <span>{{ table.capacity }} Seats</span>
              <span v-if="table.orders_count" class="text-primary-600 dark:text-primary-400 font-semibold font-mono">
                {{ table.orders_count }} active
              </span>
            </div>
          </div>

          <button
            @click="openTakeOrder(table)"
            class="btn-secondary text-[11px] py-1.5 px-2 mt-3 w-full justify-center gap-1 font-semibold"
          >
            <Plus :size="13" /> Take Order
          </button>
        </div>
      </div>
    </div>

    <!-- Take Table Order Modal (REQ-7.x) -->
    <div v-if="showOrderModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
      <div class="card w-full max-w-2xl shadow-float max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between pb-3 border-b border-warm-100 dark:border-warm-800 mb-3">
          <div>
            <h3 class="font-heading font-bold text-lg text-warm-900 dark:text-white flex items-center gap-2">
              <UtensilsCrossed :size="18" class="text-primary-500" />
              Take Table Order
            </h3>
            <p class="text-xs text-warm-500">Add dishes and submit directly to Kitchen KDS & auto-assign to you.</p>
          </div>
          <button @click="showOrderModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1 overflow-y-auto pr-1">
          <!-- Left: Menu Selector -->
          <div class="space-y-3">
            <div>
              <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">Target Table *</label>
              <select v-model="selectedTable" class="input text-xs">
                <option v-for="t in tables" :key="t.id" :value="t.id">
                  {{ t.name }} ({{ t.area || 'Floor' }}, {{ t.capacity }} seats)
                </option>
              </select>
            </div>

            <div class="relative">
              <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
              <input
                v-model="dishSearch"
                type="search"
                placeholder="Search dishes..."
                class="input pl-8 text-xs"
              />
            </div>

            <div class="max-h-60 overflow-y-auto space-y-1.5 pr-1">
              <div
                v-for="d in availableDishesFiltered"
                :key="d.id"
                @click="addItemToOrder(d)"
                class="flex items-center justify-between p-2 rounded-xl bg-warm-50 dark:bg-warm-850 hover:bg-primary-50 dark:hover:bg-primary-950/40 cursor-pointer transition-colors border border-warm-100 dark:border-warm-800"
              >
                <div>
                  <p class="font-semibold text-xs text-warm-900 dark:text-white">{{ d.name }}</p>
                  <p class="text-[11px] text-warm-400">${{ Number(d.price).toFixed(2) }}</p>
                </div>
                <button type="button" class="btn-primary p-1 rounded-lg text-[10px]"><Plus :size="12" /></button>
              </div>
            </div>
          </div>

          <!-- Right: Order Ticket Summary -->
          <div class="flex flex-col justify-between bg-warm-50 dark:bg-warm-900 p-3.5 rounded-2xl border border-warm-200 dark:border-warm-800">
            <div>
              <h4 class="font-bold text-xs uppercase text-warm-600 dark:text-warm-400 mb-2">Order Items ({{ orderItems.length }})</h4>

              <div v-if="!orderItems.length" class="text-center py-8 text-xs text-warm-400 italic">
                Click dishes on the left to add items to table bill
              </div>

              <div v-else class="space-y-2 max-h-48 overflow-y-auto pr-1">
                <div
                  v-for="(item, idx) in orderItems"
                  :key="item.dish_id"
                  class="flex items-center justify-between text-xs bg-white dark:bg-warm-850 p-2 rounded-xl shadow-xs"
                >
                  <div class="flex-1 truncate mr-2">
                    <p class="font-semibold text-warm-900 dark:text-white truncate">{{ item.name }}</p>
                    <span class="text-[10px] text-warm-400">${{ (item.price * item.quantity).toFixed(2) }}</span>
                  </div>
                  <div class="flex items-center gap-1.5 shrink-0">
                    <button type="button" @click="updateItemQty(idx, -1)" class="w-5 h-5 rounded bg-warm-200 dark:bg-warm-700 font-bold text-xs flex items-center justify-center">-</button>
                    <span class="font-bold w-4 text-center">{{ item.quantity }}</span>
                    <button type="button" @click="updateItemQty(idx, 1)" class="w-5 h-5 rounded bg-warm-200 dark:bg-warm-700 font-bold text-xs flex items-center justify-center">+</button>
                  </div>
                </div>
              </div>

              <!-- Notes -->
              <div class="mt-3">
                <input v-model="orderNotes" type="text" placeholder="Kitchen notes (e.g., no onions)..." class="input text-xs" />
              </div>
            </div>

            <!-- Financials Breakdown (REQ-6.2) -->
            <div class="pt-3 mt-3 border-t border-warm-200 dark:border-warm-800 space-y-1 text-xs">
              <div class="flex justify-between text-warm-500">
                <span>Subtotal:</span>
                <span>${{ orderSubtotal.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between text-warm-500">
                <span>Service Charge (10%):</span>
                <span>${{ orderServiceCharge.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between text-warm-500">
                <span>VAT Tax (15%):</span>
                <span>${{ orderVat.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between font-bold text-sm text-warm-900 dark:text-white pt-1 border-t border-warm-200 dark:border-warm-800">
                <span>Total Payable:</span>
                <span class="text-primary-600 dark:text-primary-400">${{ orderGrandTotal.toFixed(2) }}</span>
              </div>

              <button
                @click="submitOrder"
                :disabled="!orderItems.length || submittingOrder"
                class="btn-primary w-full justify-center text-xs py-2.5 mt-2 shadow-md"
              >
                {{ submittingOrder ? 'Dispatching to Kitchen...' : 'Send Order to Kitchen KDS' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
