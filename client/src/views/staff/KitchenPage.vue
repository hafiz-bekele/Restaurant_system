<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { staffApi, adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { Clock, ChefHat, CheckCircle, Volume2, VolumeX, AlertTriangle, Flame, Utensils, X, ToggleLeft, ToggleRight, Search } from '@lucide/vue'

const ui     = useUiStore()
const orders = ref([])
const loading = ref(true)
const soundEnabled = ref(true)

// Menu Availability / 86 Stock Control (REQ-5.3)
const showStockModal = ref(false)
const dishes = ref([])
const loadingDishes = ref(false)
const dishSearch = ref('')

async function openStockModal() {
  showStockModal.value = true
  loadingDishes.value = true
  try {
    const { data } = await adminApi.dishes()
    dishes.value = data.data ?? []
  } catch {
    ui.toast('Failed to load menu items', 'error')
  } finally {
    loadingDishes.value = false
  }
}

async function toggleDishAvailability(dish) {
  const newStatus = !dish.is_available
  dish.is_available = newStatus
  try {
    await adminApi.updateDish(dish.id, { is_available: newStatus })
    ui.toast(`${dish.name} marked ${newStatus ? 'AVAILABLE' : 'OUT OF STOCK (86)'}`, newStatus ? 'success' : 'warning')
  } catch {
    dish.is_available = !newStatus
    ui.toast('Failed to update dish availability', 'error')
  }
}

const filteredDishes = computed(() => {
  if (!dishSearch.value) return dishes.value
  const q = dishSearch.value.toLowerCase()
  return dishes.value.filter(d => d.name?.toLowerCase().includes(q))
})

// Web Audio API Ding Chime (REQ-12.1)
function playKitchenDing() {
  if (!soundEnabled.value) return
  try {
    const AudioContext = window.AudioContext || window.webkitAudioContext
    if (!AudioContext) return
    const ctx = new AudioContext()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.type = 'sine'
    osc.frequency.setValueAtTime(587.33, ctx.currentTime) // D5
    osc.frequency.setValueAtTime(880.00, ctx.currentTime + 0.1) // A5
    gain.gain.setValueAtTime(0.2, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5)
    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.start()
    osc.stop(ctx.currentTime + 0.51)
  } catch {}
}

const newOrders      = computed(() => orders.value.filter(o => o.status === 'confirmed'))
const preparingOrders = computed(() => orders.value.filter(o => o.status === 'preparing'))
const readyOrders    = computed(() => orders.value.filter(o => o.status === 'ready'))

let lastOrdersCount = 0

async function fetchTickets() {
  try {
    const { data } = await staffApi.kitchenTickets()
    const fetched = data.data ?? []

    if (fetched.length > lastOrdersCount && lastOrdersCount >= 0) {
      playKitchenDing()
    }
    lastOrdersCount = fetched.length
    orders.value = fetched
  } catch (err) {
    console.error('Failed to load tickets', err)
  } finally {
    loading.value = false
  }
}

async function updateStatus(id, status) {
  try {
    await staffApi.updateTicket(id, status)
    await fetchTickets()
    ui.toast(`Ticket marked as ${status}`, 'success')
  } catch {
    ui.toast('Failed to update ticket', 'error')
  }
}

let interval
onMounted(() => {
  fetchTickets()
  interval = setInterval(fetchTickets, 8000)
  if (window.Echo) window.Echo.channel('kitchen').listen('OrderStatusChanged', fetchTickets)
})

onUnmounted(() => {
  clearInterval(interval)
  if (window.Echo) window.Echo.leaveChannel('kitchen')
})

function minutesAgo(dateStr) {
  if (!dateStr) return 0
  return Math.max(0, Math.round((Date.now() - new Date(dateStr).getTime()) / 60000))
}

// REQ-8.2: Highlight elapsed preparation times vs target SLA
function getSlaStatus(order) {
  const elapsed = minutesAgo(order.created_at)
  const target = order.target_prep_time || 15 // minutes

  if (elapsed < target * 0.5) {
    return {
      type: 'normal',
      badgeClass: 'badge-green',
      cardBorder: 'border-l-4 border-green-500',
      textClass: 'text-green-600 dark:text-green-400',
      label: 'Normal (< 50%)',
    }
  } else if (elapsed <= target) {
    return {
      type: 'warning',
      badgeClass: 'badge-orange',
      cardBorder: 'border-l-4 border-amber-500 bg-amber-50/20 dark:bg-amber-950/20',
      textClass: 'text-amber-600 dark:text-amber-400',
      label: 'Warning (50-100%)',
    }
  } else {
    return {
      type: 'critical',
      badgeClass: 'badge-red animate-pulse',
      cardBorder: 'border-l-4 border-red-500 bg-red-50/30 dark:bg-red-950/30 animate-pulse',
      textClass: 'text-red-600 dark:text-red-400 font-bold',
      label: 'SLA Breach (> 100%)',
    }
  }
}
</script>

<template>
  <div>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
          <ChefHat :size="26" class="text-primary-500" /> Kitchen Display System (KDS)
        </h1>
        <p class="text-sm text-warm-500">Live order queue with active SLA timers and audio alerts.</p>
      </div>

      <div class="flex items-center gap-3">
        <!-- Sound toggle -->
        <button
          @click="soundEnabled = !soundEnabled"
          :class="['btn-ghost text-xs gap-1.5 py-1.5 px-3 rounded-xl border', soundEnabled ? 'border-primary-300 text-primary-600 dark:text-primary-400' : 'text-warm-400']"
        >
          <Volume2 v-if="soundEnabled" :size="15" />
          <VolumeX v-else :size="15" />
          {{ soundEnabled ? 'Audio Alerts On' : 'Audio Muted' }}
        </button>

        <!-- Stock Availability control -->
        <button
          @click="openStockModal"
          class="btn-secondary text-xs gap-1.5 py-1.5 px-3 rounded-xl border"
        >
          <Utensils :size="14" />
          86 / Stock Availability
        </button>

        <button @click="fetchTickets" class="btn-ghost text-xs py-1.5 px-3">
          Refresh
        </button>
      </div>
    </div>

    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div v-for="col in 3" :key="col" class="space-y-3">
        <div v-for="i in 2" :key="i" class="skeleton h-44 rounded-2xl"></div>
      </div>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- 1. New Orders Queue (Confirmed) -->
      <div>
        <div class="flex items-center justify-between mb-4">
          <h2 class="badge badge-blue text-sm font-semibold px-3 py-1.5">
            New Orders ({{ newOrders.length }})
          </h2>
        </div>

        <div class="space-y-3">
          <div
            v-for="order in newOrders"
            :key="order.ulid"
            :class="['card p-4 transition-all shadow-sm', getSlaStatus(order).cardBorder]"
          >
            <div class="flex items-center justify-between mb-2">
              <span class="font-mono text-xs font-semibold text-warm-700 dark:text-warm-300">
                #{{ order.ulid.slice(-8) }}
              </span>
              <span :class="['flex items-center gap-1 text-xs font-mono', getSlaStatus(order).textClass]">
                <Clock :size="12" /> {{ minutesAgo(order.created_at) }}m
              </span>
            </div>

            <div class="text-xs text-warm-500 mb-2">
              <span v-if="order.table" class="font-medium text-warm-800 dark:text-warm-200">Table {{ order.table.name }}</span>
              <span v-else-if="order.type === 'delivery'" class="text-primary-600 dark:text-primary-400">Delivery</span>
              <span v-else>Takeout</span>
            </div>

            <ul class="space-y-1 mb-4 bg-warm-50 dark:bg-warm-800/40 p-2.5 rounded-xl text-xs">
              <li v-for="item in order.items" :key="item.id" class="text-warm-800 dark:text-warm-200">
                <strong>{{ item.quantity }}x</strong> {{ item.name_snapshot }}
              </li>
            </ul>

            <button
              @click="updateStatus(order.ulid, 'preparing')"
              class="btn-primary w-full py-2 text-xs font-semibold flex items-center justify-center gap-1.5 shadow-sm"
            >
              <Flame :size="14" /> Start Preparing
            </button>
          </div>

          <div v-if="!newOrders.length" class="card p-8 text-center text-warm-400 text-xs">
            No incoming orders pending
          </div>
        </div>
      </div>

      <!-- 2. Currently Preparing -->
      <div>
        <div class="flex items-center justify-between mb-4">
          <h2 class="badge badge-orange text-sm font-semibold px-3 py-1.5">
            Cooking / Preparing ({{ preparingOrders.length }})
          </h2>
        </div>

        <div class="space-y-3">
          <div
            v-for="order in preparingOrders"
            :key="order.ulid"
            :class="['card p-4 transition-all shadow-sm', getSlaStatus(order).cardBorder]"
          >
            <div class="flex items-center justify-between mb-2">
              <span class="font-mono text-xs font-semibold text-warm-700 dark:text-warm-300">
                #{{ order.ulid.slice(-8) }}
              </span>
              <span :class="['flex items-center gap-1 text-xs font-mono', getSlaStatus(order).textClass]">
                <Clock :size="12" /> {{ minutesAgo(order.created_at) }}m
              </span>
            </div>

            <div class="text-xs text-warm-500 mb-2">
              <span v-if="order.table" class="font-medium text-warm-800 dark:text-warm-200">Table {{ order.table.name }}</span>
              <span v-else-if="order.type === 'delivery'" class="text-primary-600 dark:text-primary-400">Delivery</span>
              <span v-else>Takeout</span>
            </div>

            <ul class="space-y-1 mb-4 bg-warm-50 dark:bg-warm-800/40 p-2.5 rounded-xl text-xs">
              <li v-for="item in order.items" :key="item.id" class="text-warm-800 dark:text-warm-200">
                <strong>{{ item.quantity }}x</strong> {{ item.name_snapshot }}
              </li>
            </ul>

            <button
              @click="updateStatus(order.ulid, 'ready')"
              class="btn-primary w-full py-2 text-xs font-semibold bg-orange-600 hover:bg-orange-700 flex items-center justify-center gap-1.5 shadow-sm"
            >
              <CheckCircle :size="14" /> Mark Order Ready
            </button>
          </div>

          <div v-if="!preparingOrders.length" class="card p-8 text-center text-warm-400 text-xs">
            No orders in progress
          </div>
        </div>
      </div>

      <!-- 3. Ready for Service / Delivery -->
      <div>
        <div class="flex items-center justify-between mb-4">
          <h2 class="badge badge-green text-sm font-semibold px-3 py-1.5">
            Ready for Pickup ({{ readyOrders.length }})
          </h2>
        </div>

        <div class="space-y-3">
          <div
            v-for="order in readyOrders"
            :key="order.ulid"
            class="card p-4 border-l-4 border-green-500 opacity-90 shadow-sm"
          >
            <div class="flex items-center justify-between mb-2">
              <span class="font-mono text-xs font-semibold text-warm-700 dark:text-warm-300">
                #{{ order.ulid.slice(-8) }}
              </span>
              <span class="badge badge-green text-[10px]">Awaiting Waiter / Driver</span>
            </div>

            <p class="text-xs text-warm-600 dark:text-warm-400 font-medium mb-2">
              {{ order.table ? `Table ${order.table.name}` : `Destination: ${order.type}` }}
            </p>

            <ul class="space-y-1 bg-warm-50 dark:bg-warm-800/40 p-2.5 rounded-xl text-xs">
              <li v-for="item in order.items" :key="item.id" class="text-warm-800 dark:text-warm-200">
                <strong>{{ item.quantity }}x</strong> {{ item.name_snapshot }}
              </li>
            </ul>
          </div>

          <div v-if="!readyOrders.length" class="card p-8 text-center text-warm-400 text-xs">
            No completed tickets waiting
          </div>
        </div>
      </div>
    </div>

    <!-- Stock Availability Modal -->
    <div v-if="showStockModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
      <div class="card w-full max-w-xl shadow-float max-h-[85vh] flex flex-col">
        <div class="flex items-center justify-between pb-3 border-b border-warm-100 dark:border-warm-800 mb-3">
          <div>
            <h3 class="font-heading font-bold text-warm-900 dark:text-white flex items-center gap-2">
              <Utensils :size="18" class="text-primary-500" />
              Kitchen Item Availability (86 Stock List)
            </h3>
            <p class="text-xs text-warm-500">Toggle sold-out items to instantly update the guest digital menu.</p>
          </div>
          <button @click="showStockModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
        </div>

        <div class="mb-3">
          <input
            v-model="dishSearch"
            type="search"
            placeholder="Search menu dishes..."
            class="input text-xs"
          />
        </div>

        <div v-if="loadingDishes" class="p-8 text-center text-xs text-warm-400">
          Loading catalog items...
        </div>

        <div v-else class="flex-1 overflow-y-auto divide-y divide-warm-100 dark:divide-warm-800 pr-1 space-y-1">
          <div
            v-for="dish in filteredDishes"
            :key="dish.id"
            class="flex items-center justify-between py-2.5 px-2 hover:bg-warm-50/70 dark:hover:bg-warm-800/40 rounded-xl transition-colors"
          >
            <div class="flex items-center gap-3">
              <div
                :class="[
                  'w-2 h-10 rounded-full',
                  dish.is_available ? 'bg-emerald-500' : 'bg-red-500'
                ]"
              ></div>
              <div>
                <p class="font-semibold text-xs text-warm-900 dark:text-white">{{ dish.name }}</p>
                <p class="text-[11px] text-warm-500">${{ Number(dish.price).toFixed(2) }} · Prep: {{ dish.preparation_time ?? 15 }}m</p>
              </div>
            </div>

            <button
              @click="toggleDishAvailability(dish)"
              :class="[
                'px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm',
                dish.is_available
                  ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800'
                  : 'bg-red-50 text-red-700 border border-red-200 dark:bg-red-950/40 dark:text-red-400 dark:border-red-800'
              ]"
            >
              <ToggleRight v-if="dish.is_available" :size="16" class="text-emerald-600" />
              <ToggleLeft v-else :size="16" class="text-red-500" />
              {{ dish.is_available ? 'Available' : '86 / Sold Out' }}
            </button>
          </div>

          <div v-if="filteredDishes.length === 0" class="text-center py-6 text-xs text-warm-400">
            No dishes matching search query.
          </div>
        </div>

        <div class="pt-3 border-t border-warm-100 dark:border-warm-800 text-right mt-3">
          <button @click="showStockModal = false" class="btn-primary text-xs">Done</button>
        </div>
      </div>
    </div>
  </div>
</template>
