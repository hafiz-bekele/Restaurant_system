<script setup>
import { useQuery } from '@tanstack/vue-query'
import { staffApi } from '../../services/api'
import { UtensilsCrossed } from '@lucide/vue'

const { data, isLoading } = useQuery({
  queryKey: ['waiter', 'tables'],
  queryFn:  () => staffApi.waiterTables().then(r => r.data.data),
  refetchInterval: 15000,
})
</script>
<template>
  <div>
    <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white mb-6 flex items-center gap-2"><UtensilsCrossed :size="22" class="text-primary-500" /> Tables</h1>
    <div v-if="isLoading" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div v-for="i in 8" :key="i" class="skeleton h-28 rounded-2xl"></div>
    </div>
    <div v-else class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div v-for="table in (data ?? [])" :key="table.id" :class="['card p-4 text-center', table.status === 'available' ? 'border-green-200 dark:border-green-800' : 'border-orange-200 dark:border-orange-800 border-2']">
        <p class="font-heading font-bold text-warm-900 dark:text-white">{{ table.name }}</p>
        <p class="text-xs text-warm-500 mt-0.5">Capacity: {{ table.capacity }}</p>
        <span :class="['badge mt-2', table.status === 'available' ? 'badge-green' : 'badge-orange']">{{ table.status }}</span>
      </div>
    </div>
  </div>
</template>
