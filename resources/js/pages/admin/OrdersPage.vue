<script setup>
import { ref } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'

const ui     = useUiStore()
const qc     = useQueryClient()
const status = ref('')

const { data, isLoading } = useQuery({
  queryKey: ['admin', 'orders', status],
  queryFn:  () => adminApi.orders({ status: status.value || undefined }).then(r => r.data),
})
const orders = () => data.value?.data ?? []

const statusOptions = ['', 'pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled']

async function updateStatus(id, newStatus) {
  await adminApi.updateOrder(id, { status: newStatus })
  qc.invalidateQueries({ queryKey: ['admin', 'orders'] })
  ui.toast('Order updated')
}
</script>
<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white">Orders</h1>
      <select v-model="status" class="input w-auto text-sm">
        <option v-for="s in statusOptions" :key="s" :value="s">{{ s || 'All Statuses' }}</option>
      </select>
    </div>
    <div v-if="isLoading" class="space-y-3">
      <div v-for="i in 5" :key="i" class="skeleton h-14 rounded-xl"></div>
    </div>
    <div v-else class="card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-warm-50 dark:bg-warm-800">
          <tr class="text-left text-warm-600 dark:text-warm-400">
            <th class="px-5 py-3 font-medium">Order</th>
            <th class="px-5 py-3 font-medium">Customer</th>
            <th class="px-5 py-3 font-medium">Total</th>
            <th class="px-5 py-3 font-medium">Status</th>
            <th class="px-5 py-3 font-medium">Update</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-warm-50 dark:divide-warm-800">
          <tr v-for="order in orders()" :key="order.ulid" class="hover:bg-warm-50 dark:hover:bg-warm-800/50">
            <td class="px-5 py-3 font-mono text-xs text-warm-500">{{ order.ulid.slice(-8) }}</td>
            <td class="px-5 py-3 text-warm-700 dark:text-warm-300">{{ order.user?.name ?? 'Guest' }}</td>
            <td class="px-5 py-3 font-semibold text-primary-600 dark:text-primary-400">${{ Number(order.total).toFixed(2) }}</td>
            <td class="px-5 py-3">
              <span :class="['badge', order.status === 'delivered' ? 'badge-green' : order.status === 'cancelled' ? 'badge-red' : 'badge-orange']">{{ order.status_label }}</span>
            </td>
            <td class="px-5 py-3">
              <select class="input text-xs py-1 px-2 w-auto" @change="e => updateStatus(order.ulid, e.target.value)" :value="order.status">
                <option v-for="s in ['confirmed','preparing','ready','out_for_delivery','delivered','cancelled']" :key="s" :value="s">{{ s }}</option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
