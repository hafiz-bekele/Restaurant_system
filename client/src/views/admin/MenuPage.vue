<script setup>
import { ref } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { Plus, Trash2, Pencil } from '@lucide/vue'

const ui     = useUiStore()
const qc     = useQueryClient()
const { data, isLoading } = useQuery({
  queryKey: ['admin', 'dishes'],
  queryFn:  () => adminApi.dishes().then(r => r.data),
})
const dishes = () => data.value?.data ?? []

async function toggleAvail(dish) {
  await adminApi.updateDish(dish.id, { is_available: !dish.is_available })
  qc.invalidateQueries({ queryKey: ['admin', 'dishes'] })
  ui.toast('Updated')
}

async function deleteDish(id) {
  if (!confirm('Delete this dish?')) return
  await adminApi.deleteDish(id)
  qc.invalidateQueries({ queryKey: ['admin', 'dishes'] })
  ui.toast('Dish deleted')
}
</script>
<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white">Menu Management</h1>
    </div>
    <div v-if="isLoading" class="space-y-3">
      <div v-for="i in 5" :key="i" class="skeleton h-14 rounded-xl"></div>
    </div>
    <div v-else class="card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-warm-50 dark:bg-warm-800">
          <tr class="text-left text-warm-600 dark:text-warm-400">
            <th class="px-5 py-3 font-medium">Name</th>
            <th class="px-5 py-3 font-medium">Category</th>
            <th class="px-5 py-3 font-medium">Price</th>
            <th class="px-5 py-3 font-medium">Available</th>
            <th class="px-5 py-3 font-medium">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-warm-50 dark:divide-warm-800">
          <tr v-for="dish in dishes()" :key="dish.id" class="hover:bg-warm-50 dark:hover:bg-warm-800/50 transition-colors">
            <td class="px-5 py-3 font-medium text-warm-900 dark:text-white">{{ dish.name }}</td>
            <td class="px-5 py-3 text-warm-500">{{ dish.category?.name }}</td>
            <td class="px-5 py-3 font-semibold text-primary-600 dark:text-primary-400">${{ Number(dish.price).toFixed(2) }}</td>
            <td class="px-5 py-3">
              <button @click="toggleAvail(dish)" :class="['w-10 h-5 rounded-full transition-colors', dish.is_available ? 'bg-green-500' : 'bg-warm-300']" :aria-label="dish.is_available ? 'Mark unavailable' : 'Mark available'">
                <span :class="['block w-4 h-4 bg-white rounded-full shadow transition-transform mx-0.5', dish.is_available ? 'translate-x-5' : 'translate-x-0']"></span>
              </button>
            </td>
            <td class="px-5 py-3">
              <button @click="deleteDish(dish.id)" class="text-red-400 hover:text-red-600 p-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" :aria-label="`Delete ${dish.name}`">
                <Trash2 :size="16" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
