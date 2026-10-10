<script setup>
import { useQuery } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { ShoppingBag, TrendingUp, BarChart2, Star } from '@lucide/vue'

const today = new Date().toISOString().split('T')[0]
const weekAgo = new Date(Date.now() - 7 * 86400000).toISOString().split('T')[0]

const { data: report, isLoading } = useQuery({
  queryKey: ['admin', 'sales', weekAgo, today],
  queryFn:  () => adminApi.salesReport({ from: weekAgo, to: today }).then(r => r.data.data),
  staleTime: 300000,
})
</script>

<template>
  <div>
    <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white mb-6">Dashboard</h1>

    <!-- Stat cards -->
    <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div v-for="i in 4" :key="i" class="card p-5 skeleton h-28"></div>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="card p-5">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm text-warm-500">Total Orders</span>
          <div class="p-2 rounded-xl bg-blue-50 dark:bg-blue-900/20"><ShoppingBag :size="18" class="text-blue-500" /></div>
        </div>
        <p class="font-heading text-3xl font-bold text-warm-900 dark:text-white">{{ report?.summary?.total_orders ?? 0 }}</p>
        <p class="text-xs text-warm-400 mt-1">Last 7 days</p>
      </div>
      <div class="card p-5">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm text-warm-500">Revenue</span>
          <div class="p-2 rounded-xl bg-green-50 dark:bg-green-900/20"><TrendingUp :size="18" class="text-green-500" /></div>
        </div>
        <p class="font-heading text-3xl font-bold text-warm-900 dark:text-white">${{ Number(report?.summary?.total_revenue ?? 0).toFixed(0) }}</p>
        <p class="text-xs text-warm-400 mt-1">Last 7 days</p>
      </div>
      <div class="card p-5">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm text-warm-500">Avg Order</span>
          <div class="p-2 rounded-xl bg-purple-50 dark:bg-purple-900/20"><BarChart2 :size="18" class="text-purple-500" /></div>
        </div>
        <p class="font-heading text-3xl font-bold text-warm-900 dark:text-white">${{ Number(report?.summary?.avg_order ?? 0).toFixed(2) }}</p>
        <p class="text-xs text-warm-400 mt-1">Per order</p>
      </div>
      <div class="card p-5">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm text-warm-500">Top Dish</span>
          <div class="p-2 rounded-xl bg-orange-50 dark:bg-orange-900/20"><Star :size="18" class="text-orange-500" /></div>
        </div>
        <p class="font-heading text-base font-bold text-warm-900 dark:text-white truncate">{{ report?.top_dishes?.[0]?.name_snapshot ?? 'N/A' }}</p>
        <p class="text-xs text-warm-400 mt-1">{{ report?.top_dishes?.[0]?.total_qty ?? 0 }} orders</p>
      </div>
    </div>

    <!-- Top dishes -->
    <div class="card p-6">
      <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white mb-4">Top Dishes (7 days)</h2>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-warm-500 border-b border-warm-100 dark:border-warm-800">
              <th class="pb-3 pr-4 font-medium">Dish</th>
              <th class="pb-3 pr-4 font-medium">Orders</th>
              <th class="pb-3 font-medium">Revenue</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-50 dark:divide-warm-800">
            <tr v-for="dish in (report?.top_dishes ?? [])" :key="dish.name_snapshot">
              <td class="py-3 pr-4 font-medium text-warm-900 dark:text-white">{{ dish.name_snapshot }}</td>
              <td class="py-3 pr-4 text-warm-600 dark:text-warm-400">{{ dish.total_qty }}</td>
              <td class="py-3 font-semibold text-primary-600 dark:text-primary-400">${{ Number(dish.revenue).toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
