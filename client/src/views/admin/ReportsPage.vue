<script setup>
import { ref } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useI18n } from 'vue-i18n'
import StatCardSkeleton from '../../components/ui/StatCardSkeleton.vue'

const { t } = useI18n()

const from = ref(new Date(Date.now() - 30 * 86400000).toISOString().split('T')[0])
const to   = ref(new Date().toISOString().split('T')[0])

const { data, isLoading } = useQuery({
  queryKey: ['admin', 'reports', from, to],
  queryFn:  () => adminApi.salesReport({ from: from.value, to: to.value }).then(r => r.data.data),
  staleTime: 60000,
})
</script>
<template>
  <div>
    <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white mb-6">{{ $t('adminReports.title') }}</h1>
    <div class="flex gap-3 mb-6">
      <input v-model="from" type="date" class="input w-auto" /><span class="self-center text-warm-400">{{ $t('adminReports.toLabel') }}</span>
      <input v-model="to" type="date" class="input w-auto" />
    </div>
    <div v-if="isLoading" class="mb-8">
      <StatCardSkeleton :count="3" :columns="3" />
    </div>
    <div v-else class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
      <div class="card p-5 text-center">
        <p class="text-3xl font-heading font-bold text-warm-900 dark:text-white">{{ data?.summary?.total_orders ?? 0 }}</p>
        <p class="text-sm text-warm-500 mt-1">{{ $t('adminReports.ordersLabel') }}</p>
      </div>
      <div class="card p-5 text-center">
        <p class="text-3xl font-heading font-bold text-primary-600">ETB {{ Number(data?.summary?.total_revenue ?? 0).toFixed(2) }}</p>
        <p class="text-sm text-warm-500 mt-1">{{ $t('adminReports.revenueLabel') }}</p>
      </div>
      <div class="card p-5 text-center">
        <p class="text-3xl font-heading font-bold text-warm-900 dark:text-white">ETB {{ Number(data?.summary?.avg_order ?? 0).toFixed(2) }}</p>
        <p class="text-sm text-warm-500 mt-1">{{ $t('adminReports.avgOrderLabel') }}</p>
      </div>
    </div>
  </div>
</template>
