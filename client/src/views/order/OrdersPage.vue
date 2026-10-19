<script setup>
import { useQuery } from '@tanstack/vue-query'
import { RouterLink } from 'vue-router'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import OrderCardSkeleton from '../../components/ui/OrderCardSkeleton.vue'
import { orderApi } from '../../services/api'
import { ShoppingBag } from '@lucide/vue'

const { data, isLoading } = useQuery({
  queryKey: ['orders'],
  queryFn:  () => orderApi.list().then(r => r.data),
})
const orders = () => data.value?.data ?? []
</script>
<template>
  <DefaultLayout>
    <div class="page-container py-10 max-w-3xl">
      <h1 class="font-heading text-3xl font-bold text-warm-900 dark:text-white mb-8">{{ $t('guestOrders.title') }}</h1>
      <div v-if="isLoading" class="space-y-4">
        <OrderCardSkeleton v-for="i in 4" :key="i" />
      </div>
      <div v-else-if="orders().length" class="space-y-4">
        <RouterLink v-for="order in orders()" :key="order.ulid" :to="`/orders/${order.ulid}/track`" class="card p-5 flex items-center justify-between hover:shadow-float transition-all group">
          <div>
            <p class="font-mono text-sm text-warm-500">{{ order.ulid.slice(-10) }}</p>
            <p class="font-semibold text-warm-900 dark:text-white mt-0.5">
              {{ $t('guestOrders.itemsCount', { count: order.items?.length ?? 0, total: Number(order.total).toFixed(2) }) }}
            </p>
          </div>
          <div class="flex items-center gap-3">
            <span :class="['badge', order.status === 'delivered' ? 'badge-green' : order.status === 'cancelled' ? 'badge-red' : 'badge-orange']">{{ order.status_label }}</span>
            <span class="text-warm-300 dark:text-warm-600 group-hover:text-primary-500 transition-colors">→</span>
          </div>
        </RouterLink>
      </div>
      <div v-else class="text-center py-20">
        <ShoppingBag :size="48" class="mx-auto text-warm-300 mb-4" />
        <p class="text-warm-500 mb-4">{{ $t('guestOrders.noOrders') }}</p>
        <RouterLink to="/menu" class="btn-primary">{{ $t('guestOrders.startOrdering') }}</RouterLink>
      </div>
    </div>
  </DefaultLayout>
</template>
