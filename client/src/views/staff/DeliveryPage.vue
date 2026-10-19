<script setup>
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import { staffApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { Truck } from '@lucide/vue'
import OrderCardSkeleton from '../../components/ui/OrderCardSkeleton.vue'

const { t } = useI18n()
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
  ui.toast(t('deliveryPage.statusUpdated'))
}
</script>

<template>
  <div>
    <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white mb-6 flex items-center gap-2">
      <Truck :size="22" class="text-primary-500" /> {{ $t('deliveryPage.title') }}
    </h1>
    <div v-if="isLoading" class="space-y-3">
      <OrderCardSkeleton v-for="i in 4" :key="i" />
    </div>
    <div v-else-if="(data ?? []).length" class="space-y-4">
      <div v-for="order in data" :key="order.ulid" class="card p-5 flex items-center justify-between">
        <div>
          <p class="font-mono text-xs text-warm-500">#{{ order.ulid.slice(-8) }}</p>
          <p class="font-semibold text-warm-900 dark:text-white mt-0.5">{{ $t('deliveryPage.itemsCount', { count: order.items?.length }) }}</p>
          <p class="text-xs text-warm-400">{{ $t('deliveryPage.zone', { name: order.deliveryZone?.name ?? 'N/A' }) }}</p>
        </div>
        <div class="flex gap-2">
          <button v-if="order.status === 'ready'" @click="updateStatus(order.ulid, 'out_for_delivery')" class="btn-primary py-1.5 px-3 text-xs">
            {{ $t('deliveryPage.pickUp') }}
          </button>
          <button v-if="order.status === 'out_for_delivery'" @click="updateStatus(order.ulid, 'delivered')" class="btn-primary py-1.5 px-3 text-xs bg-green-500 hover:bg-green-600">
            {{ $t('deliveryPage.delivered') }}
          </button>
        </div>
      </div>
    </div>
    <div v-else class="card p-10 text-center text-warm-400">
      <Truck :size="40" class="mx-auto mb-3 opacity-40" />
      <p>{{ $t('deliveryPage.noActiveDeliveries') }}</p>
    </div>
  </div>
</template>
