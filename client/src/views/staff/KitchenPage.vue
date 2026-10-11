<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { staffApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { Clock, ChefHat, CheckCircle } from '@lucide/vue'

const ui     = useUiStore()
const orders = ref([])
const loading = ref(true)

const newOrders      = computed(() => orders.value.filter(o => o.status === 'confirmed'))
const preparingOrders = computed(() => orders.value.filter(o => o.status === 'preparing'))
const readyOrders    = computed(() => orders.value.filter(o => o.status === 'ready'))

async function fetchTickets() {
  const { data } = await staffApi.kitchenTickets()
  orders.value = data.data
  loading.value = false
}

async function updateStatus(id, status) {
  try {
    await staffApi.updateTicket(id, status)
    await fetchTickets()
    ui.toast(`Ticket marked as ${status}`)
  } catch {
    ui.toast('Failed to update ticket', 'error')
  }
}

let interval
onMounted(() => {
  fetchTickets()
  interval = setInterval(fetchTickets, 10000)
  if (window.Echo) window.Echo.channel('kitchen').listen('OrderStatusChanged', fetchTickets)
})
onUnmounted(() => {
  clearInterval(interval)
  if (window.Echo) window.Echo.leaveChannel('kitchen')
})

function minutesAgo(dateStr) {
  return Math.round((Date.now() - new Date(dateStr)) / 60000)
}
</script>

<template>
  <div>
    <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white mb-6 flex items-center gap-2">
      <ChefHat :size="24" class="text-primary-500" /> Kitchen Display
    </h1>

    <div v-if="loading" class="grid grid-cols-3 gap-6">
      <div v-for="col in 3" :key="col" class="space-y-3">
        <div v-for="i in 2" :key="i" class="skeleton h-40 rounded-2xl"></div>
      </div>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- New -->
      <div>
        <h2 class="badge badge-blue text-sm font-semibold mb-4 px-3 py-1.5">New ({{ newOrders.length }})</h2>
        <div class="space-y-3">
          <div v-for="order in newOrders" :key="order.ulid" class="card p-4 border-l-4 border-blue-500">
            <div class="flex items-center justify-between mb-2">
              <span class="font-mono text-xs text-warm-500">{{ order.ulid.slice(-8) }}</span>
              <span :class="['flex items-center gap-1 text-xs', minutesAgo(order.created_at) > 10 ? 'text-red-500' : 'text-warm-400']">
                <Clock :size="12" /> {{ minutesAgo(order.created_at) }}m
              </span>
            </div>
            <ul class="space-y-0.5 mb-3">
              <li v-for="item in order.items" :key="item.id" class="text-sm text-warm-800 dark:text-warm-200">
                {{ item.quantity }}x {{ item.name_snapshot }}
              </li>
            </ul>
            <button @click="updateStatus(order.ulid, 'preparing')" class="btn-primary w-full py-1.5 text-xs">
              Start Preparing
            </button>
          </div>
          <p v-if="!newOrders.length" class="text-center text-warm-400 text-sm py-8">No new orders</p>
        </div>
      </div>

      <!-- Preparing -->
      <div>
        <h2 class="badge badge-orange text-sm font-semibold mb-4 px-3 py-1.5">Preparing ({{ preparingOrders.length }})</h2>
        <div class="space-y-3">
          <div v-for="order in preparingOrders" :key="order.ulid" class="card p-4 border-l-4 border-orange-500">
            <div class="flex items-center justify-between mb-2">
              <span class="font-mono text-xs text-warm-500">{{ order.ulid.slice(-8) }}</span>
              <span class="flex items-center gap-1 text-xs text-orange-500">
                <Clock :size="12" /> {{ minutesAgo(order.created_at) }}m
              </span>
            </div>
            <ul class="space-y-0.5 mb-3">
              <li v-for="item in order.items" :key="item.id" class="text-sm text-warm-800 dark:text-warm-200">
                {{ item.quantity }}x {{ item.name_snapshot }}
              </li>
            </ul>
            <button @click="updateStatus(order.ulid, 'ready')" class="btn-primary w-full py-1.5 text-xs bg-orange-500 hover:bg-orange-600">
              <CheckCircle :size="14" /> Mark Ready
            </button>
          </div>
          <p v-if="!preparingOrders.length" class="text-center text-warm-400 text-sm py-8">No orders in progress</p>
        </div>
      </div>

      <!-- Ready -->
      <div>
        <h2 class="badge badge-green text-sm font-semibold mb-4 px-3 py-1.5">Ready ({{ readyOrders.length }})</h2>
        <div class="space-y-3">
          <div v-for="order in readyOrders" :key="order.ulid" class="card p-4 border-l-4 border-green-500 opacity-75">
            <span class="font-mono text-xs text-warm-500">{{ order.ulid.slice(-8) }}</span>
            <ul class="space-y-0.5 mt-2">
              <li v-for="item in order.items" :key="item.id" class="text-sm text-warm-800 dark:text-warm-200">
                {{ item.quantity }}x {{ item.name_snapshot }}
              </li>
            </ul>
          </div>
          <p v-if="!readyOrders.length" class="text-center text-warm-400 text-sm py-8">No ready orders</p>
        </div>
      </div>
    </div>
  </div>
</template>
