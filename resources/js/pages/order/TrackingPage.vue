<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { orderApi } from '../../services/api'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import { CheckCircle, Clock, ChefHat, Truck, Package } from '@lucide/vue'

const route = useRoute()
const order = ref(null)
const loading = ref(true)

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

const stepIndex = (status) => steps.findIndex(s => s.status === status)

let interval
onMounted(() => {
  fetchOrder()
  interval = setInterval(fetchOrder, 15000)

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

      <div v-if="loading" class="card p-8 space-y-4">
        <div v-for="i in 3" :key="i" class="skeleton h-6 w-full"></div>
      </div>

      <div v-else-if="order" class="card p-8">
        <div class="flex items-center justify-between mb-8">
          <div>
            <p class="text-sm text-warm-500">Order ID</p>
            <p class="font-mono font-semibold text-warm-900 dark:text-white text-sm">{{ order.ulid }}</p>
          </div>
          <span :class="['badge', order.status === 'delivered' ? 'badge-green' : order.status === 'cancelled' ? 'badge-red' : 'badge-orange']">
            {{ order.status_label }}
          </span>
        </div>

        <!-- Progress steps -->
        <div class="relative">
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

        <div v-if="order.estimated_time" class="mt-8 flex items-center gap-2 text-sm text-warm-600 dark:text-warm-400 bg-warm-50 dark:bg-warm-800/50 rounded-xl px-4 py-3">
          <Clock :size="16" class="text-primary-500" />
          Estimated delivery: ~{{ order.estimated_time }} minutes
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
