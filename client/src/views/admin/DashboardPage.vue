<script setup>
import { useQuery } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useAuthStore } from '../../stores/auth'
import { ShoppingBag, TrendingUp, BarChart2, Star, Clock, User, ArrowRight, CheckCircle, AlertCircle } from '@lucide/vue'
import { RouterLink } from 'vue-router'
import { computed } from 'vue'
import StatCardSkeleton from '../../components/ui/StatCardSkeleton.vue'
import TableSkeleton from '../../components/ui/TableSkeleton.vue'

const auth = useAuthStore()
const dashTitle    = computed(() => auth.hasRole('manager') ? 'Operations Dashboard' : 'Admin Dashboard')
const dashSubtitle = computed(() => auth.hasRole('manager') ? 'Live orders, shift revenue, and today\'s operations' : 'Overview of live customer orders, revenue, and restaurant operations')

const today = new Date().toISOString().split('T')[0]
const weekAgo = new Date(Date.now() - 7 * 86400000).toISOString().split('T')[0]

const { data: report, isLoading: isReportLoading } = useQuery({
  queryKey: ['admin', 'sales', weekAgo, today],
  queryFn:  () => adminApi.salesReport({ from: weekAgo, to: today }).then(r => r.data.data),
  staleTime: 300000,
})

const { data: ordersData, isLoading: isOrdersLoading } = useQuery({
  queryKey: ['admin', 'dashboard-orders'],
  queryFn:  () => adminApi.orders().then(r => r.data.data),
  refetchInterval: 10000,
})

const recentOrders = () => (ordersData.value ?? []).slice(0, 6)

function statusBadgeClass(status) {
  switch (status) {
    case 'pending': return 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300'
    case 'confirmed': return 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300'
    case 'preparing': return 'bg-orange-100 text-orange-800 dark:bg-orange-950/50 dark:text-orange-300'
    case 'ready': return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'
    case 'out_for_delivery': return 'bg-purple-100 text-purple-800 dark:bg-purple-950/50 dark:text-purple-300'
    case 'delivered': return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'
    case 'cancelled': return 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-300'
    default: return 'bg-warm-100 text-warm-800 dark:bg-warm-800 dark:text-warm-300'
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
      <div>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white">{{ dashTitle }}</h1>
        <p class="text-xs text-warm-500 dark:text-warm-400 mt-0.5">{{ dashSubtitle }}</p>
      </div>
      <RouterLink to="/admin/orders" class="btn-secondary text-xs py-1.5 px-3 self-start sm:self-auto gap-1.5">
        <span>View Full Order Dispatch</span>
        <ArrowRight :size="14" />
      </RouterLink>
    </div>

    <!-- Stat cards -->
    <div v-if="isReportLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <StatCardSkeleton v-for="i in 4" :key="i" />
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
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

    <!-- Live Customer Orders Feed -->
    <div class="card p-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
            Live Customer Orders (Incoming from Users)
          </h2>
          <p class="text-xs text-warm-500 dark:text-warm-400">Real-time orders placed by customers through online storefront, QR scan, or table seating</p>
        </div>
        <RouterLink to="/admin/orders" class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline flex items-center gap-1">
          <span>Manage Orders</span>
          <ArrowRight :size="14" />
        </RouterLink>
      </div>

      <div v-if="isOrdersLoading">
        <TableSkeleton :rows="4" :cols="6" :showHeader="true" />
      </div>

      <div v-else-if="!recentOrders().length" class="text-center py-10 text-warm-400 text-sm">
        <ShoppingBag :size="36" class="mx-auto mb-2 opacity-40" />
        <p>No customer orders yet today.</p>
        <p class="text-xs mt-1">Orders placed by customers on the public menu will appear here automatically.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-warm-500 text-xs uppercase border-b border-warm-100 dark:border-warm-800">
              <th class="pb-3 pr-4 font-medium">Order Ref</th>
              <th class="pb-3 pr-4 font-medium">Customer (User)</th>
              <th class="pb-3 pr-4 font-medium">Type</th>
              <th class="pb-3 pr-4 font-medium">Dishes</th>
              <th class="pb-3 pr-4 font-medium">Total</th>
              <th class="pb-3 font-medium">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-50 dark:divide-warm-800">
            <tr v-for="order in recentOrders()" :key="order.ulid" class="hover:bg-warm-50/50 dark:hover:bg-warm-800/30">
              <td class="py-3 pr-4 font-mono font-bold text-xs text-primary-600 dark:text-primary-400">
                #{{ order.ulid.slice(-6).toUpperCase() }}
              </td>
              <td class="py-3 pr-4">
                <p class="font-semibold text-xs text-warm-900 dark:text-white">{{ order.user?.name ?? 'Walk-in Guest' }}</p>
                <p class="text-[11px] text-warm-400">{{ order.user?.email ?? 'Direct QR' }}</p>
              </td>
              <td class="py-3 pr-4">
                <span class="text-xs capitalize font-medium text-warm-700 dark:text-warm-300">{{ order.type }}</span>
                <span v-if="order.table" class="ml-1 text-[10px] bg-warm-100 dark:bg-warm-800 px-1.5 py-0.5 rounded text-warm-600 dark:text-warm-300">
                  {{ order.table.name }}
                </span>
              </td>
              <td class="py-3 pr-4 text-xs text-warm-600 dark:text-warm-400 max-w-xs truncate">
                {{ order.items?.map(i => `${i.quantity}x ${i.name_snapshot}`).join(', ') || 'N/A' }}
              </td>
              <td class="py-3 pr-4 font-bold text-xs text-warm-900 dark:text-white">
                ${{ Number(order.total).toFixed(2) }}
              </td>
              <td class="py-3">
                <span :class="['text-[11px] font-semibold px-2 py-0.5 rounded-full capitalize inline-block', statusBadgeClass(order.status)]">
                  {{ order.status_label || order.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Top dishes -->
    <div class="card p-6">
      <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white mb-4">Top Dishes (7 days)</h2>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-warm-500 border-b border-warm-100 dark:border-warm-800 text-xs uppercase">
              <th class="pb-3 pr-4 font-medium">Dish</th>
              <th class="pb-3 pr-4 font-medium">Orders</th>
              <th class="pb-3 font-medium">Revenue</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-50 dark:divide-warm-800">
            <tr v-for="dish in (report?.top_dishes ?? [])" :key="dish.name_snapshot">
              <td class="py-3 pr-4 font-medium text-warm-900 dark:text-white text-xs">{{ dish.name_snapshot }}</td>
              <td class="py-3 pr-4 text-warm-600 dark:text-warm-400 text-xs">{{ dish.total_qty }}</td>
              <td class="py-3 font-semibold text-primary-600 dark:text-primary-400 text-xs">${{ Number(dish.revenue).toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
