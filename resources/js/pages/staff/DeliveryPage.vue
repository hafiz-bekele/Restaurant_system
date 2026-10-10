<script setup>
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { staffApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { Truck } from '@lucide/vue'

const ui = useUiStore()
const qc = useQueryClient()

const { data, isLoading } = useQuery({
  queryKey: ['delivery', 'orders'],
  queryFn:  () => staffApi.deliveryOrders().then(r => r.data.data),
  refetchInterval: 15000,
})

async function updateStatus(id, status) {
  await staffApi.updateDelivery(id, status)
  qc.invalidateQueries({ queryKey: ['delivery', 'orders'] })
  ui.toast('Status updated')
}
</script>
<template>
  <div>
    <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white mb-6 flex items-center gap-2"><Truck :size="22" class="text-primary-500" /> Deliveries</h1>
    <div v-if="isLoading" class="space-y-3"><div v-for="i in 3" :key="i" class="skeleton h-20 rounded-2xl"></div></div>
    <div v-else-if="(data ?? []).length" class="space-y-4">
      <div v-for="order in data" :key="order.ulid" class="card p-5 flex items-center justify-between">
        <div>
          <p class="font-mono text-xs text-warm-500">{{ order.ulid.slice(-8) }}</p>
          <p class="font-semibold text-warm-900 dark:text-white mt-0.5">{{ order.items?.length }} items</p>
          <p class="text-xs text-warm-400">Zone: {{ order.deliveryZone?.name ?? 'N/A' }}</p>
        </div>
        <div class="flex gap-2">
          <button v-if="order.status === 'ready'" @click="updateStatus(order.ulid, 'out_for_delivery')" class="btn-primary py-1.5 px-3 text-xs">Pick Up</button>
          <button v-if="order.status === 'out_for_delivery'" @click="updateStatus(order.ulid, 'delivered')" class="btn-primary py-1.5 px-3 text-xs bg-green-500 hover:bg-green-600">Delivered</button>
        </div>
      </div>
    </div>
    <div v-else class="card p-10 text-center text-warm-400">
      <Truck :size="40" class="mx-auto mb-3 opacity-40" />
      <p>No active deliveries</p>
    </div>
  </div>
</template>
