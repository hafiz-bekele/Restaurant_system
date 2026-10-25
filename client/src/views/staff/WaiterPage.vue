<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { staffApi, menuApi, paymentApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import {
  UtensilsCrossed, CheckCircle, Clock, Bell, RefreshCw, AlertCircle,
  Plus, X, Search, Trash2, User, Phone, MessageSquare, CreditCard,
  Printer, DollarSign, Check, ChevronRight, Layers, Smartphone, Banknote
} from '@lucide/vue'
import AppButton from '../../components/ui/AppButton.vue'

const { t } = useI18n()
const ui = useUiStore()

const loading = ref(true)
const tables = ref([])
const activeOrders = ref([])
const activeTab = ref('tables') // 'ready' | 'kitchen' | 'served' | 'tables'
const areaFilter = ref('All')
const tableSearch = ref('')

// Take order & Add items modal state
const showOrderModal = ref(false)
const isAddingToExisting = ref(false)
const targetOrderUlid = ref(null)
const selectedTable = ref(null)
const dishesList = ref([])
const categoriesList = ref([])
const selectedCategory = ref('all')
const loadingDishes = ref(false)
const orderItems = ref([])
const orderNotes = ref('')
const dishSearch = ref('')
const submittingOrder = ref(false)

// Bill Receipt & Settlement Modal
const showBillModal = ref(false)
const selectedBillOrder = ref(null)
const selectedPaymentMethod = ref('cash')
const settlingPayment = ref(false)

// Audio synthesizer for ready alerts
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
    gain.gain.setValueAtTime(0.18, ctx.currentTime)
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

const servedOrders = computed(() => {
  return activeOrders.value.filter(o => o.status === 'delivered' && o.payment_status === 'pending')
})

const occupiedTablesCount = computed(() => {
  return tables.value.filter(t => t.status === 'occupied').length
})

const availableTablesCount = computed(() => {
  return tables.value.filter(t => t.status === 'available').length
})

const areas = computed(() => {
  const set = new Set(tables.value.map(t => t.area || 'Floor'))
  return ['All', ...Array.from(set)]
})

const filteredTables = computed(() => {
  let list = tables.value
  if (areaFilter.value !== 'All') {
    list = list.filter(t => (t.area || 'Floor') === areaFilter.value)
  }
  if (tableSearch.value) {
    const q = tableSearch.value.toLowerCase()
    list = list.filter(t => t.name.toLowerCase().includes(q) || (t.area || '').toLowerCase().includes(q))
  }
  return list
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

    // Sound chime if new ready orders arrive
    const currentReady = readyOrders.value.length
    if (currentReady > lastReadyCount && lastReadyCount >= 0) {
      playReadyChime()
      if (activeTab.value !== 'ready') {
        ui.toast(t('waiterPage.readyAlertBanner', { count: currentReady }), 'info')
      }
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
    ui.toast(t('waiterPage.orderServedToast', { ulid: order.ulid.slice(-6).toUpperCase() }), 'success')
    await fetchData()
  } catch (err) {
    ui.toast(t('waiterPage.failedToSubmitOrder'), 'error')
  }
}

function openBillModal(order) {
  selectedBillOrder.value = order
  selectedPaymentMethod.value = 'cash'
  showBillModal.value = true
}

async function settleOrderPayment() {
  if (!selectedBillOrder.value) return
  settlingPayment.value = true
  try {
    await paymentApi.payNow({
      order_ulid: selectedBillOrder.value.ulid,
      payment_method: selectedPaymentMethod.value,
    })
    ui.toast(
      t('waiterPage.paymentSuccessToast', {
        total: Number(selectedBillOrder.value.total).toFixed(2),
        method: selectedPaymentMethod.value.toUpperCase(),
      }),
      'success'
    )
    showBillModal.value = false
    await fetchData()
  } catch (err) {
    ui.toast(err.response?.data?.message || t('waiterPage.failedToSubmitOrder'), 'error')
  } finally {
    settlingPayment.value = false
  }
}

function printReceipt() {
  window.print()
}

async function releaseTable(table) {
  if (!confirm(t('waiterPage.confirmRelease', { name: table.name }))) return
  try {
    await staffApi.waiterUpdateTableStatus(table.id, 'available')
    ui.toast(t('waiterPage.tableReleasedSuccess', { name: table.name }), 'success')
    await fetchData()
  } catch (err) {
    ui.toast(t('waiterPage.failedToSubmitOrder'), 'error')
  }
}

async function seatGuests(table) {
  try {
    await staffApi.waiterUpdateTableStatus(table.id, 'occupied')
    ui.toast(`Table ${table.name} is now occupied`, 'success')
    await fetchData()
  } catch (err) {
    ui.toast(t('waiterPage.failedToSubmitOrder'), 'error')
  }
}

async function loadDishes() {
  if (dishesList.value.length) return
  loadingDishes.value = true
  try {
    const res = await menuApi.dishes()
    const list = (res.data.data ?? []).filter(d => d.is_available)
    dishesList.value = list

    // Extract unique categories
    const catMap = new Map()
    list.forEach(dish => {
      if (dish.category) {
        catMap.set(dish.category.id, dish.category.name)
      }
    })
    categoriesList.value = Array.from(catMap.entries()).map(([id, name]) => ({ id, name }))
  } finally {
    loadingDishes.value = false
  }
}

async function openTakeOrder(table = null) {
  isAddingToExisting.value = false
  targetOrderUlid.value = null
  selectedTable.value = table ? table.id : (tables.value[0]?.id || '')
  orderItems.value = []
  orderNotes.value = ''
  dishSearch.value = ''
  selectedCategory.value = 'all'
  showOrderModal.value = true
  await loadDishes()
}

async function openAddItemsToTable(table) {
  const activeOrder = table.orders?.[0] || activeOrders.value.find(o => o.table_id === table.id)
  if (!activeOrder) {
    // If no existing order, fall back to new order
    return openTakeOrder(table)
  }
  isAddingToExisting.value = true
  targetOrderUlid.value = activeOrder.ulid
  selectedTable.value = table.id
  orderItems.value = []
  orderNotes.value = ''
  dishSearch.value = ''
  selectedCategory.value = 'all'
  showOrderModal.value = true
  await loadDishes()
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

// Financial calculations per REQ-6.2 (ETB)
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
    if (isAddingToExisting.value && targetOrderUlid.value) {
      await staffApi.waiterAddItems(targetOrderUlid.value, {
        items: orderItems.value.map(i => ({ dish_id: i.dish_id, quantity: i.quantity })),
        notes: orderNotes.value || undefined,
      })
      ui.toast(t('waiterPage.itemsAddedSuccess'), 'success')
    } else {
      await staffApi.createWaiterOrder({
        table_id: selectedTable.value,
        items: orderItems.value.map(i => ({ dish_id: i.dish_id, quantity: i.quantity })),
        notes: orderNotes.value || undefined,
      })
      ui.toast(t('waiterPage.orderSentSuccess'), 'success')
    }
    showOrderModal.value = false
    await fetchData()
  } catch (err) {
    ui.toast(err.response?.data?.message || t('waiterPage.failedToSubmitOrder'), 'error')
  } finally {
    submittingOrder.value = false
  }
}

const availableDishesFiltered = computed(() => {
  let list = dishesList.value
  if (selectedCategory.value !== 'all') {
    list = list.filter(d => d.category?.id === selectedCategory.value)
  }
  if (dishSearch.value) {
    const q = dishSearch.value.toLowerCase()
    list = list.filter(d => d.name.toLowerCase().includes(q))
  }
  return list
})

function getTableActiveOrder(table) {
  if (table.orders && table.orders.length) return table.orders[0]
  return activeOrders.value.find(o => o.table?.id === table.id || o.table_id === table.id)
}

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
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
          <UtensilsCrossed :size="26" class="text-primary-500" /> {{ $t('waiterPage.title') }}
        </h1>
        <p class="text-xs text-warm-500 dark:text-warm-400 mt-0.5">{{ $t('waiterPage.subtitle') }}</p>
      </div>

      <div class="flex items-center gap-3">
        <button @click="openTakeOrder()" class="btn-primary text-xs gap-1.5 py-2 px-3.5 shadow-sm">
          <Plus :size="15" /> {{ $t('waiterPage.takeNewOrder') }}
        </button>
        <button @click="fetchData" class="btn-ghost text-xs gap-1.5 py-2 px-3">
          <RefreshCw :size="14" /> {{ $t('waiterPage.refresh') }}
        </button>
      </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
      <div
        @click="activeTab = 'ready'"
        :class="[
          'card p-4 flex items-center gap-3.5 cursor-pointer transition-all border-2',
          activeTab === 'ready'
            ? 'border-emerald-500 bg-emerald-50/40 dark:bg-emerald-950/30'
            : 'border-warm-100 dark:border-warm-800 hover:border-emerald-300'
        ]"
      >
        <div class="p-2.5 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 shrink-0">
          <Bell :size="20" :class="{ 'animate-bounce': readyOrders.length > 0 }" />
        </div>
        <div>
          <p class="text-[11px] font-medium text-warm-500">{{ $t('waiterPage.statReadyOrders') }}</p>
          <p class="text-xl font-bold font-mono text-warm-900 dark:text-white">{{ readyOrders.length }}</p>
        </div>
      </div>

      <div
        @click="activeTab = 'kitchen'"
        :class="[
          'card p-4 flex items-center gap-3.5 cursor-pointer transition-all border-2',
          activeTab === 'kitchen'
            ? 'border-amber-500 bg-amber-50/40 dark:bg-amber-950/30'
            : 'border-warm-100 dark:border-warm-800 hover:border-amber-300'
        ]"
      >
        <div class="p-2.5 rounded-xl bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 shrink-0">
          <Clock :size="20" />
        </div>
        <div>
          <p class="text-[11px] font-medium text-warm-500">{{ $t('waiterPage.statCookingOrders') }}</p>
          <p class="text-xl font-bold font-mono text-warm-900 dark:text-white">{{ inProgressOrders.length }}</p>
        </div>
      </div>

      <div
        @click="activeTab = 'served'"
        :class="[
          'card p-4 flex items-center gap-3.5 cursor-pointer transition-all border-2',
          activeTab === 'served'
            ? 'border-blue-500 bg-blue-50/40 dark:bg-blue-950/30'
            : 'border-warm-100 dark:border-warm-800 hover:border-blue-300'
        ]"
      >
        <div class="p-2.5 rounded-xl bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 shrink-0">
          <CreditCard :size="20" />
        </div>
        <div>
          <p class="text-[11px] font-medium text-warm-500">{{ $t('waiterPage.tabServed') }}</p>
          <p class="text-xl font-bold font-mono text-warm-900 dark:text-white">{{ servedOrders.length }}</p>
        </div>
      </div>

      <div
        @click="activeTab = 'tables'"
        :class="[
          'card p-4 flex items-center gap-3.5 cursor-pointer transition-all border-2',
          activeTab === 'tables'
            ? 'border-primary-500 bg-primary-50/40 dark:bg-primary-950/30'
            : 'border-warm-100 dark:border-warm-800 hover:border-primary-300'
        ]"
      >
        <div class="p-2.5 rounded-xl bg-primary-100 dark:bg-primary-900/50 text-primary-600 dark:text-primary-400 shrink-0">
          <Layers :size="20" />
        </div>
        <div>
          <p class="text-[11px] font-medium text-warm-500">{{ $t('waiterPage.statOccupiedTables') }}</p>
          <p class="text-xl font-bold font-mono text-warm-900 dark:text-white">
            {{ occupiedTablesCount }} / {{ tables.length }}
          </p>
        </div>
      </div>
    </div>

    <!-- Navigation Queue Tabs -->
    <div class="flex items-center gap-2 border-b border-warm-200 dark:border-warm-800 pb-2 overflow-x-auto text-xs font-semibold">
      <button
        @click="activeTab = 'tables'"
        :class="[
          'flex items-center gap-2 px-3.5 py-2 rounded-xl transition-all',
          activeTab === 'tables'
            ? 'bg-primary-600 text-white shadow-xs'
            : 'bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400 hover:bg-warm-200'
        ]"
      >
        <Layers :size="15" />
        <span>{{ $t('waiterPage.tabTables') }}</span>
        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-white/20">
          {{ tables.length }}
        </span>
      </button>

      <button
        @click="activeTab = 'ready'"
        :class="[
          'flex items-center gap-2 px-3.5 py-2 rounded-xl transition-all',
          activeTab === 'ready'
            ? 'bg-emerald-600 text-white shadow-xs'
            : 'bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400 hover:bg-warm-200'
        ]"
      >
        <Bell :size="15" :class="{ 'animate-bounce': readyOrders.length > 0 }" />
        <span>{{ $t('waiterPage.tabReady') }}</span>
        <span v-if="readyOrders.length" class="px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-emerald-500 text-white animate-pulse">
          {{ readyOrders.length }}
        </span>
      </button>

      <button
        @click="activeTab = 'kitchen'"
        :class="[
          'flex items-center gap-2 px-3.5 py-2 rounded-xl transition-all',
          activeTab === 'kitchen'
            ? 'bg-amber-600 text-white shadow-xs'
            : 'bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400 hover:bg-warm-200'
        ]"
      >
        <Clock :size="15" />
        <span>{{ $t('waiterPage.tabKitchen') }}</span>
        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-white/20">
          {{ inProgressOrders.length }}
        </span>
      </button>

      <button
        @click="activeTab = 'served'"
        :class="[
          'flex items-center gap-2 px-3.5 py-2 rounded-xl transition-all',
          activeTab === 'served'
            ? 'bg-blue-600 text-white shadow-xs'
            : 'bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400 hover:bg-warm-200'
        ]"
      >
        <CreditCard :size="15" />
        <span>{{ $t('waiterPage.tabServed') }}</span>
        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-white/20">
          {{ servedOrders.length }}
        </span>
      </button>
    </div>

    <!-- TAB 1: FLOOR TABLES & STATIONS (Default) -->
    <div v-if="activeTab === 'tables'" class="space-y-4">
      <!-- Area Filters and Search -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
          <button
            v-for="area in areas"
            :key="area"
            @click="areaFilter = area"
            :class="[
              'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap',
              areaFilter === area
                ? 'bg-primary-600 text-white shadow-sm'
                : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-warm-200'
            ]"
          >
            {{ area === 'All' ? $t('waiterPage.allAreas') : area }}
          </button>
        </div>

        <div class="relative">
          <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
          <input
            v-model="tableSearch"
            type="search"
            :placeholder="$t('waiterPage.searchTablePlaceholder')"
            class="input pl-8 py-1.5 text-xs w-48 sm:w-60"
          />
        </div>
      </div>

      <!-- Loading Skeleton -->
      <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3.5">
        <div v-for="i in 10" :key="i" class="card p-4 space-y-3">
          <div class="skeleton h-5 w-24 rounded"></div>
          <div class="skeleton h-8 w-16 rounded"></div>
          <div class="skeleton h-6 w-full rounded"></div>
        </div>
      </div>

      <!-- Tables Grid -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <div
          v-for="table in filteredTables"
          :key="table.id"
          :class="[
            'card p-4.5 flex flex-col justify-between transition-all border-2 shadow-xs',
            table.status === 'available'
              ? 'border-emerald-200 dark:border-emerald-900/40 hover:border-emerald-400 bg-white dark:bg-warm-850'
              : table.status === 'occupied'
                ? 'border-amber-300 dark:border-amber-800 bg-amber-50/25 dark:bg-amber-950/20'
                : 'border-blue-200 dark:border-blue-900/40 bg-blue-50/20'
          ]"
        >
          <div>
            <!-- Table Header -->
            <div class="flex items-start justify-between mb-2">
              <div>
                <h3 class="font-heading font-bold text-lg text-warm-900 dark:text-white flex items-center gap-1.5">
                  {{ table.name }}
                </h3>
                <p class="text-xs text-warm-500 flex items-center gap-1 mt-0.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-primary-500"></span>
                  {{ table.area || 'Main Dining Floor' }} • {{ $t('waiterPage.capacityGuests', { count: table.capacity }) }}
                </p>
              </div>

              <span
                :class="[
                  'badge text-[11px] font-semibold px-2.5 py-0.5 rounded-full',
                  table.status === 'available' ? 'badge-green' : table.status === 'occupied' ? 'badge-orange' : 'badge-blue'
                ]"
              >
                {{ table.status === 'available' ? $t('waiterPage.tableStatusAvailable') : (table.status === 'occupied' ? $t('waiterPage.tableStatusOccupied') : $t('waiterPage.tableStatusReserved')) }}
              </span>
            </div>

            <!-- Active Order Details if Occupied -->
            <div v-if="table.status === 'occupied'" class="my-3 p-3 rounded-xl bg-warm-100/70 dark:bg-warm-900/50 border border-warm-200 dark:border-warm-800 text-xs space-y-1.5">
              <div class="flex items-center justify-between text-warm-700 dark:text-warm-300">
                <span class="font-semibold">{{ $t('waiterPage.currentOrderSlip') }}:</span>
                <span class="font-mono font-bold text-primary-600 dark:text-primary-400">
                  #{{ getTableActiveOrder(table)?.ulid?.slice(-6)?.toUpperCase() || 'ACTIVE' }}
                </span>
              </div>

              <!-- Items snippet -->
              <div v-if="getTableActiveOrder(table)?.items?.length" class="text-[11px] text-warm-500 space-y-0.5 pt-1 border-t border-warm-200/60 dark:border-warm-800">
                <p v-for="it in getTableActiveOrder(table).items.slice(0, 2)" :key="it.id" class="truncate">
                  {{ it.quantity }}x {{ it.name_snapshot }}
                </p>
                <p v-if="getTableActiveOrder(table).items.length > 2" class="text-[10px] text-primary-500 italic">
                  + {{ getTableActiveOrder(table).items.length - 2 }} more items...
                </p>
              </div>

              <div class="flex items-center justify-between font-bold pt-1.5 border-t border-warm-200/60 dark:border-warm-800 text-warm-900 dark:text-white">
                <span>{{ $t('waiterPage.grandTotal') }}:</span>
                <span class="text-primary-600 dark:text-primary-400 font-mono text-sm">
                  ETB {{ Number(getTableActiveOrder(table)?.total || 0).toFixed(2) }}
                </span>
              </div>
            </div>

            <!-- Available Status Hint -->
            <div v-else class="my-4 py-3 text-center border border-dashed border-emerald-300 dark:border-emerald-800 rounded-xl bg-emerald-50/30 dark:bg-emerald-950/20 text-xs text-emerald-700 dark:text-emerald-300">
              ✓ {{ $t('waiterPage.tableStatusAvailable') }} for guest party
            </div>
          </div>

          <!-- Action Buttons for Table -->
          <div class="space-y-1.5 pt-2">
            <!-- If occupied -->
            <template v-if="table.status === 'occupied'">
              <div class="grid grid-cols-2 gap-1.5">
                <button
                  @click="openAddItemsToTable(table)"
                  type="button"
                  class="btn-secondary text-[11px] py-1.5 px-2 justify-center gap-1 font-semibold shadow-xs"
                >
                  <Plus :size="12" /> {{ $t('waiterPage.addItemsBtn') }}
                </button>
                <button
                  v-if="getTableActiveOrder(table)"
                  @click="openBillModal(getTableActiveOrder(table))"
                  type="button"
                  class="btn-primary text-[11px] py-1.5 px-2 justify-center gap-1 font-semibold shadow-xs"
                >
                  <DollarSign :size="12" /> {{ $t('waiterPage.viewActiveOrder') }}
                </button>
              </div>
              <button
                @click="releaseTable(table)"
                type="button"
                class="btn-outline text-[11px] py-1 px-2 w-full justify-center text-warm-500 hover:text-red-600 hover:border-red-300 transition-colors"
              >
                {{ $t('waiterPage.releaseTableBtn') }}
              </button>
            </template>

            <!-- If available -->
            <template v-else>
              <div class="grid grid-cols-2 gap-1.5">
                <button
                  @click="openTakeOrder(table)"
                  type="button"
                  class="btn-primary text-xs py-2 px-2.5 justify-center gap-1 font-semibold shadow-xs"
                >
                  <Plus :size="13" /> {{ $t('waiterPage.takeNewOrder') }}
                </button>
                <button
                  @click="seatGuests(table)"
                  type="button"
                  class="btn-secondary text-xs py-2 px-2.5 justify-center gap-1 font-semibold shadow-xs"
                >
                  {{ $t('waiterPage.seatGuestBtn') }}
                </button>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: READY FOR SERVICE -->
    <div v-else-if="activeTab === 'ready'" class="space-y-4">
      <div v-if="!readyOrders.length" class="card p-12 text-center text-warm-400 space-y-3">
        <CheckCircle :size="44" class="mx-auto text-emerald-500/70" />
        <h3 class="font-heading font-bold text-base text-warm-800 dark:text-warm-200">
          All Orders Delivered!
        </h3>
        <p class="text-xs text-warm-500">{{ $t('waiterPage.noOrdersInStatus') }}</p>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="order in readyOrders"
          :key="order.ulid"
          class="card p-5 border-2 border-emerald-400 bg-emerald-50/20 dark:bg-emerald-950/20 shadow-float flex flex-col justify-between"
        >
          <div>
            <div class="flex items-center justify-between mb-2">
              <span class="font-mono font-bold text-sm text-warm-900 dark:text-white">
                #{{ order.ulid.slice(-6).toUpperCase() }}
              </span>
              <span class="badge badge-green text-xs animate-pulse">{{ $t('waiterPage.readyBadge') }}</span>
            </div>

            <p class="text-base font-bold text-primary-600 dark:text-primary-400 mb-1">
              {{ order.table ? `${$t('kitchenPage.tableNum', { name: order.table.name })} (${order.table.area || 'Floor'})` : `${$t('kitchenPage.delivery')}: ${order.type}` }}
            </p>

            <div v-if="order.address_snapshot?.customer_name" class="mb-2 flex items-center gap-1.5 text-xs text-warm-700 dark:text-warm-300 font-medium bg-warm-100/70 dark:bg-warm-800/80 px-2 py-0.5 rounded-md">
              <User :size="12" class="text-primary-500 shrink-0" />
              <span class="truncate">{{ order.address_snapshot.customer_name }}</span>
              <span v-if="order.address_snapshot.customer_phone" class="text-warm-400 font-mono text-[10px]">
                ({{ order.address_snapshot.customer_phone }})
              </span>
            </div>

            <div v-if="order.notes" class="mb-3 text-[11px] bg-amber-50 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300 p-2 rounded-lg border border-amber-200 flex items-start gap-1.5">
              <MessageSquare :size="12" class="mt-0.5 shrink-0 text-amber-600" />
              <span>{{ order.notes }}</span>
            </div>

            <ul class="text-xs space-y-1 mb-4 bg-white dark:bg-warm-850 p-3 rounded-xl border border-warm-100 dark:border-warm-800">
              <li v-for="item in order.items" :key="item.id" class="text-warm-800 dark:text-warm-200">
                <strong>{{ item.quantity }}x</strong> {{ item.name_snapshot }}
              </li>
            </ul>
          </div>

          <button
            @click="markServed(order)"
            class="btn-primary w-full justify-center text-xs py-2.5 gap-1.5 shadow-md font-bold"
          >
            <CheckCircle :size="15" /> {{ $t('waiterPage.markServed') }}
          </button>
        </div>
      </div>
    </div>

    <!-- TAB 3: IN KITCHEN COOKING -->
    <div v-else-if="activeTab === 'kitchen'" class="space-y-4">
      <div v-if="!inProgressOrders.length" class="card p-12 text-center text-warm-400 space-y-3">
        <Clock :size="44" class="mx-auto text-amber-500/70" />
        <h3 class="font-heading font-bold text-base text-warm-800 dark:text-warm-200">
          Kitchen Queue Clear!
        </h3>
        <p class="text-xs text-warm-500">{{ $t('waiterPage.noOrdersInStatus') }}</p>
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="o in inProgressOrders"
          :key="o.ulid"
          class="card p-4 text-xs border border-amber-200 dark:border-amber-900/40 bg-amber-50/15"
        >
          <div class="flex justify-between items-center mb-2">
            <span class="font-mono font-bold text-sm">#{{ o.ulid.slice(-6).toUpperCase() }}</span>
            <span class="badge badge-orange text-[10px] capitalize">{{ o.status }}</span>
          </div>

          <p class="font-bold text-sm text-warm-900 dark:text-white mb-2">
            {{ o.table ? `${$t('kitchenPage.tableNum', { name: o.table.name })} (${o.table.area || 'Floor'})` : o.type }}
          </p>

          <ul class="space-y-1 mb-3 bg-white dark:bg-warm-850 p-2.5 rounded-xl border border-warm-100 dark:border-warm-800">
            <li v-for="item in o.items" :key="item.id" class="text-warm-700 dark:text-warm-300">
              <strong>{{ item.quantity }}x</strong> {{ item.name_snapshot }}
            </li>
          </ul>

          <div class="flex justify-between items-center text-warm-500 font-semibold pt-2 border-t border-warm-200/60 dark:border-warm-800">
            <span>{{ $t('waiterPage.grandTotal') }}:</span>
            <span class="font-mono text-primary-600 font-bold">ETB {{ Number(o.total).toFixed(2) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 4: SERVED & BILL PENDING -->
    <div v-else-if="activeTab === 'served'" class="space-y-4">
      <div v-if="!servedOrders.length" class="card p-12 text-center text-warm-400 space-y-3">
        <CreditCard :size="44" class="mx-auto text-blue-500/70" />
        <h3 class="font-heading font-bold text-base text-warm-800 dark:text-warm-200">
          No Pending Guest Bills!
        </h3>
        <p class="text-xs text-warm-500">All served tables have settled payments.</p>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="order in servedOrders"
          :key="order.ulid"
          class="card p-5 border-2 border-blue-300 dark:border-blue-900/60 bg-blue-50/20 dark:bg-blue-950/20 shadow-sm flex flex-col justify-between"
        >
          <div>
            <div class="flex items-center justify-between mb-2">
              <span class="font-mono font-bold text-sm text-warm-900 dark:text-white">
                #{{ order.ulid.slice(-6).toUpperCase() }}
              </span>
              <span class="badge badge-orange text-xs font-semibold">Bill Pending</span>
            </div>

            <p class="text-base font-bold text-primary-600 dark:text-primary-400 mb-2">
              {{ order.table ? `${$t('kitchenPage.tableNum', { name: order.table.name })} (${order.table.area || 'Floor'})` : order.type }}
            </p>

            <ul class="text-xs space-y-1 mb-4 bg-white dark:bg-warm-850 p-3 rounded-xl border border-warm-100 dark:border-warm-800">
              <li v-for="item in order.items" :key="item.id" class="flex justify-between text-warm-800 dark:text-warm-200">
                <span>{{ item.quantity }}x {{ item.name_snapshot }}</span>
                <span class="font-mono text-warm-500">ETB {{ (item.unit_price * item.quantity).toFixed(2) }}</span>
              </li>
            </ul>

            <div class="p-2.5 rounded-xl bg-warm-100/70 dark:bg-warm-900/60 text-xs space-y-1 mb-4">
              <div class="flex justify-between text-warm-500">
                <span>{{ $t('waiterPage.subtotal') }}:</span>
                <span>ETB {{ Number(order.subtotal).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between text-warm-500">
                <span>{{ $t('waiterPage.serviceCharge') }}:</span>
                <span>ETB {{ Number(order.service_charge).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between text-warm-500">
                <span>{{ $t('waiterPage.vat') }}:</span>
                <span>ETB {{ Number(order.tax).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between font-bold text-sm pt-1 border-t border-warm-200 dark:border-warm-800 text-warm-900 dark:text-white">
                <span>{{ $t('waiterPage.grandTotal') }}:</span>
                <span class="text-primary-600 dark:text-primary-400 font-mono">
                  ETB {{ Number(order.total).toFixed(2) }}
                </span>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <button
              @click="openBillModal(order)"
              class="btn-primary text-xs py-2.5 justify-center gap-1 font-bold shadow-sm"
            >
              <CreditCard :size="14" /> {{ $t('waiterPage.viewActiveOrder') }}
            </button>
            <button
              v-if="order.table"
              @click="releaseTable(order.table)"
              class="btn-outline text-xs py-2.5 justify-center"
            >
              {{ $t('waiterPage.releaseTableBtn') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 1: TAKE ORDER / ADD ITEMS MODAL -->
    <div v-if="showOrderModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
      <div class="card w-full max-w-3xl shadow-float max-h-[92vh] flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between pb-3.5 border-b border-warm-100 dark:border-warm-800 mb-3">
          <div>
            <h3 class="font-heading font-bold text-lg text-warm-900 dark:text-white flex items-center gap-2">
              <UtensilsCrossed :size="18" class="text-primary-500" />
              {{ isAddingToExisting ? $t('waiterPage.addItemsToOrder', { table: selectedTable }) : $t('waiterPage.takeOrderModalTitle') }}
            </h3>
            <p class="text-xs text-warm-500">{{ $t('waiterPage.subtitle') }}</p>
          </div>
          <button @click="showOrderModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
        </div>

        <!-- Body -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1 overflow-y-auto pr-1">
          <!-- Left: Menu Selector & Categories -->
          <div class="space-y-3">
            <div>
              <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">
                {{ $t('waiterPage.selectFloorTable') }} *
              </label>
              <select v-model="selectedTable" :disabled="isAddingToExisting" class="input text-xs">
                <option v-for="t in tables" :key="t.id" :value="t.id">
                  {{ $t('waiterPage.tableWithArea', { name: t.name, area: t.area || 'Floor', capacity: t.capacity }) }}
                </option>
              </select>
            </div>

            <!-- Category Pills -->
            <div class="flex items-center gap-1 overflow-x-auto pb-1 text-xs">
              <button
                type="button"
                @click="selectedCategory = 'all'"
                :class="[
                  'px-2.5 py-1 rounded-xl font-medium transition-all text-[11px] whitespace-nowrap',
                  selectedCategory === 'all'
                    ? 'bg-primary-600 text-white shadow-xs'
                    : 'bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400 hover:bg-warm-200'
                ]"
              >
                {{ $t('waiterPage.filterCategoryAll') }}
              </button>
              <button
                v-for="cat in categoriesList"
                :key="cat.id"
                type="button"
                @click="selectedCategory = cat.id"
                :class="[
                  'px-2.5 py-1 rounded-xl font-medium transition-all text-[11px] whitespace-nowrap',
                  selectedCategory === cat.id
                    ? 'bg-primary-600 text-white shadow-xs'
                    : 'bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400 hover:bg-warm-200'
                ]"
              >
                {{ cat.name }}
              </button>
            </div>

            <div class="relative">
              <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
              <input
                v-model="dishSearch"
                type="search"
                :placeholder="$t('waiterPage.searchMenuToOrder')"
                class="input pl-8 text-xs"
              />
            </div>

            <div class="max-h-64 overflow-y-auto space-y-1.5 pr-1">
              <div
                v-for="d in availableDishesFiltered"
                :key="d.id"
                @click="addItemToOrder(d)"
                class="flex items-center justify-between p-2 rounded-xl bg-warm-50 dark:bg-warm-850 hover:bg-primary-50 dark:hover:bg-primary-950/40 cursor-pointer transition-colors border border-warm-100 dark:border-warm-800"
              >
                <div>
                  <p class="font-semibold text-xs text-warm-900 dark:text-white">{{ d.name }}</p>
                  <p class="text-[11px] text-primary-600 dark:text-primary-400 font-mono font-medium">ETB {{ Number(d.price).toFixed(2) }}</p>
                </div>
                <button type="button" class="btn-primary p-1 rounded-lg text-[10px]"><Plus :size="12" /></button>
              </div>
            </div>
          </div>

          <!-- Right: Order Ticket Summary -->
          <div class="flex flex-col justify-between bg-warm-50 dark:bg-warm-900 p-3.5 rounded-2xl border border-warm-200 dark:border-warm-800">
            <div>
              <h4 class="font-bold text-xs uppercase text-warm-600 dark:text-warm-400 mb-2 flex items-center justify-between">
                <span>{{ $t('waiterPage.currentOrderSlip') }}</span>
                <span class="text-primary-600 font-mono">({{ orderItems.length }} items)</span>
              </h4>

              <div v-if="!orderItems.length" class="text-center py-10 text-xs text-warm-400 italic">
                {{ $t('waiterPage.noItemsSelected') }}
              </div>

              <div v-else class="space-y-2 max-h-52 overflow-y-auto pr-1">
                <div
                  v-for="(item, idx) in orderItems"
                  :key="item.dish_id"
                  class="flex items-center justify-between text-xs bg-white dark:bg-warm-850 p-2.5 rounded-xl shadow-xs border border-warm-100 dark:border-warm-800"
                >
                  <div class="flex-1 truncate mr-2">
                    <p class="font-semibold text-warm-900 dark:text-white truncate">{{ item.name }}</p>
                    <span class="text-[10px] text-warm-400 font-mono">ETB {{ (item.price * item.quantity).toFixed(2) }}</span>
                  </div>
                  <div class="flex items-center gap-1.5 shrink-0">
                    <button type="button" @click="updateItemQty(idx, -1)" class="w-6 h-6 rounded-lg bg-warm-200 dark:bg-warm-700 font-bold text-xs flex items-center justify-center hover:bg-warm-300">-</button>
                    <span class="font-bold w-4 text-center font-mono">{{ item.quantity }}</span>
                    <button type="button" @click="updateItemQty(idx, 1)" class="w-6 h-6 rounded-lg bg-warm-200 dark:bg-warm-700 font-bold text-xs flex items-center justify-center hover:bg-warm-300">+</button>
                  </div>
                </div>
              </div>

              <!-- Notes -->
              <div class="mt-3">
                <input v-model="orderNotes" type="text" :placeholder="$t('waiterPage.specialInstructionsNotes')" class="input text-xs" />
              </div>
            </div>

            <!-- Financials Breakdown -->
            <div class="pt-3 mt-3 border-t border-warm-200 dark:border-warm-800 space-y-1 text-xs">
              <div class="flex justify-between text-warm-500">
                <span>{{ $t('waiterPage.subtotal') }}:</span>
                <span class="font-mono">ETB {{ orderSubtotal.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between text-warm-500">
                <span>{{ $t('waiterPage.serviceCharge') }}:</span>
                <span class="font-mono">ETB {{ orderServiceCharge.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between text-warm-500">
                <span>{{ $t('waiterPage.vat') }}:</span>
                <span class="font-mono">ETB {{ orderVat.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between font-bold text-sm text-warm-900 dark:text-white pt-1 border-t border-warm-200 dark:border-warm-800">
                <span>{{ $t('waiterPage.grandTotal') }}:</span>
                <span class="text-primary-600 dark:text-primary-400 font-mono">ETB {{ orderGrandTotal.toFixed(2) }}</span>
              </div>

              <AppButton
                @click="submitOrder"
                :loading="submittingOrder"
                :loading-text="$t('waiterPage.sending')"
                :disabled="!orderItems.length"
                full-width
                class="text-xs py-2.5 mt-2 shadow-md font-bold"
              >
                {{ $t('waiterPage.sendOrderDirectToKds') }}
              </AppButton>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 2: GUEST CHECK BILL & SETTLEMENT MODAL -->
    <div v-if="showBillModal && selectedBillOrder" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
      <div class="card w-full max-w-lg shadow-float max-h-[90vh] flex flex-col p-6">
        <!-- Header -->
        <div class="flex items-center justify-between pb-3 border-b border-warm-100 dark:border-warm-800 mb-4">
          <div>
            <h3 class="font-heading font-bold text-lg text-warm-900 dark:text-white flex items-center gap-2">
              <CreditCard :size="20" class="text-primary-500" />
              {{ $t('waiterPage.viewBillModal') }}
            </h3>
            <p class="text-xs text-warm-500">
              #{{ selectedBillOrder.ulid?.slice(-8)?.toUpperCase() }} • {{ selectedBillOrder.table ? selectedBillOrder.table.name : 'Dine-In' }}
            </p>
          </div>
          <button @click="showBillModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
        </div>

        <!-- Printable Bill Content -->
        <div class="space-y-4 flex-1 overflow-y-auto pr-1 text-xs">
          <!-- Receipt Slip Style Box -->
          <div class="p-4 rounded-2xl bg-warm-50 dark:bg-warm-900/60 border border-warm-200 dark:border-warm-800 space-y-2.5 font-sans">
            <div class="text-center pb-2 border-b border-warm-200 dark:border-warm-800 space-y-0.5">
              <p class="font-bold text-sm text-warm-900 dark:text-white uppercase tracking-wider">Restaurant &amp; Hotel</p>
              <p class="text-[11px] text-warm-500">{{ selectedBillOrder.table ? `Table: ${selectedBillOrder.table.name}` : '' }} • Order #{{ selectedBillOrder.ulid?.slice(-6)?.toUpperCase() }}</p>
              <p class="text-[10px] text-warm-400 font-mono">{{ new Date().toLocaleString() }}</p>
            </div>

            <!-- Items -->
            <div class="space-y-1.5 py-1">
              <div
                v-for="item in selectedBillOrder.items"
                :key="item.id"
                class="flex justify-between items-center text-warm-800 dark:text-warm-200"
              >
                <span><strong>{{ item.quantity }}x</strong> {{ item.name_snapshot }}</span>
                <span class="font-mono">ETB {{ (item.unit_price * item.quantity).toFixed(2) }}</span>
              </div>
            </div>

            <!-- Financial Calculations -->
            <div class="pt-2 border-t border-warm-200 dark:border-warm-800 space-y-1 text-warm-600 dark:text-warm-400">
              <div class="flex justify-between">
                <span>{{ $t('waiterPage.subtotal') }}:</span>
                <span class="font-mono">ETB {{ Number(selectedBillOrder.subtotal).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between">
                <span>{{ $t('waiterPage.serviceCharge') }}:</span>
                <span class="font-mono">ETB {{ Number(selectedBillOrder.service_charge).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between">
                <span>{{ $t('waiterPage.vat') }}:</span>
                <span class="font-mono">ETB {{ Number(selectedBillOrder.tax).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between font-bold text-base text-warm-900 dark:text-white pt-2 border-t-2 border-warm-300 dark:border-warm-700">
                <span>{{ $t('waiterPage.grandTotal') }}:</span>
                <span class="text-primary-600 dark:text-primary-400 font-mono">
                  ETB {{ Number(selectedBillOrder.total).toFixed(2) }}
                </span>
              </div>
              <p class="text-[10px] text-center text-warm-400 pt-1 italic">
                {{ $t('waiterPage.receiptTaxNotice') }}
              </p>
            </div>
          </div>

          <!-- Payment Method Options -->
          <div class="space-y-2">
            <label class="block text-xs font-bold text-warm-700 dark:text-warm-300">
              {{ $t('waiterPage.settlePayment') }}:
            </label>
            <div class="grid grid-cols-2 gap-2">
              <button
                type="button"
                @click="selectedPaymentMethod = 'cash'"
                :class="[
                  'p-2.5 rounded-xl border flex items-center gap-2 text-xs font-semibold transition-all',
                  selectedPaymentMethod === 'cash'
                    ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300'
                    : 'border-warm-200 dark:border-warm-700 hover:bg-warm-100'
                ]"
              >
                <Banknote :size="16" class="text-emerald-500" />
                <span>{{ $t('waiterPage.settleCash') }}</span>
              </button>

              <button
                type="button"
                @click="selectedPaymentMethod = 'telebirr'"
                :class="[
                  'p-2.5 rounded-xl border flex items-center gap-2 text-xs font-semibold transition-all',
                  selectedPaymentMethod === 'telebirr'
                    ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300'
                    : 'border-warm-200 dark:border-warm-700 hover:bg-warm-100'
                ]"
              >
                <Smartphone :size="16" class="text-blue-500" />
                <span>{{ $t('waiterPage.settleTelebirr') }}</span>
              </button>

              <button
                type="button"
                @click="selectedPaymentMethod = 'cbe_birr'"
                :class="[
                  'p-2.5 rounded-xl border flex items-center gap-2 text-xs font-semibold transition-all',
                  selectedPaymentMethod === 'cbe_birr'
                    ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300'
                    : 'border-warm-200 dark:border-warm-700 hover:bg-warm-100'
                ]"
              >
                <Smartphone :size="16" class="text-purple-500" />
                <span>{{ $t('waiterPage.settleCbe') }}</span>
              </button>

              <button
                type="button"
                @click="selectedPaymentMethod = 'card'"
                :class="[
                  'p-2.5 rounded-xl border flex items-center gap-2 text-xs font-semibold transition-all',
                  selectedPaymentMethod === 'card'
                    ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/40 text-primary-700 dark:text-primary-300'
                    : 'border-warm-200 dark:border-warm-700 hover:bg-warm-100'
                ]"
              >
                <CreditCard :size="16" class="text-amber-500" />
                <span>{{ $t('waiterPage.settleCard') }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="pt-3 border-t border-warm-100 dark:border-warm-800 flex gap-2">
          <button
            type="button"
            @click="printReceipt"
            class="btn-outline text-xs py-2.5 px-3 gap-1.5"
            title="Print thermal guest check"
          >
            <Printer :size="14" /> {{ $t('waiterPage.printGuestCheck') }}
          </button>
          <AppButton
            type="button"
            @click="settleOrderPayment"
            :loading="settlingPayment"
            loading-text="Processing…"
            :icon="Check"
            class="flex-1 text-xs py-2.5 justify-center gap-1.5 font-bold shadow-md"
          >
            Confirm Payment (ETB {{ Number(selectedBillOrder.total).toFixed(2) }})
          </AppButton>
        </div>
      </div>
    </div>
  </div>
</template>
