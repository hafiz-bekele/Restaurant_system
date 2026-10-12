<script setup>
import { ref } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { Calendar, Search } from '@lucide/vue'
import TableSkeleton from '../../components/ui/TableSkeleton.vue'

const ui = useUiStore()
const qc = useQueryClient()

const dateFilter   = ref(new Date().toISOString().split('T')[0])
const statusFilter = ref('')
const search       = ref('')

const statusOptions = ['', 'pending', 'confirmed', 'cancelled', 'completed', 'no_show']
const statusColors  = {
  pending:   'badge-orange',
  confirmed: 'badge-green',
  cancelled: 'badge-red',
  completed: 'badge-blue',
  no_show:   'badge',
}

const { data, isLoading } = useQuery({
  queryKey: ['admin', 'reservations', dateFilter, statusFilter, search],
  queryFn:  () => adminApi.adminReservations({
    date:   dateFilter.value  || undefined,
    status: statusFilter.value || undefined,
    search: search.value       || undefined,
  }).then(r => r.data),
  keepPreviousData: true,
})

const reservations = () => data.value?.data ?? []

async function updateStatus(reservation, status) {
  await adminApi.updateReservation(reservation.id, { status })
  qc.invalidateQueries({ queryKey: ['admin', 'reservations'] })
  ui.toast('Reservation updated')
}

async function cancel(reservation) {
  if (!confirm('Cancel this reservation?')) return
  await adminApi.deleteReservation(reservation.id)
  qc.invalidateQueries({ queryKey: ['admin', 'reservations'] })
  ui.toast('Reservation cancelled')
}

function formatDate(dt) {
  return dt ? new Date(dt).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '—'
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
        <Calendar :size="22" class="text-primary-500" /> Reservations
      </h1>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-6">
      <input v-model="dateFilter" type="date" class="input w-auto" />
      <select v-model="statusFilter" class="input w-auto">
        <option v-for="s in statusOptions" :key="s" :value="s">{{ s || 'All Statuses' }}</option>
      </select>
      <div class="relative">
        <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
        <input v-model="search" type="search" placeholder="Name, email, phone…" class="input pl-9 w-60" />
      </div>
    </div>

    <TableSkeleton v-if="isLoading" :rows="6" :cols="7" />

    <div v-else class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-warm-50 dark:bg-warm-800">
          <tr class="text-left text-warm-600 dark:text-warm-400">
            <th class="px-5 py-3 font-medium">Guest</th>
            <th class="px-5 py-3 font-medium">Date & Time</th>
            <th class="px-5 py-3 font-medium">Table</th>
            <th class="px-5 py-3 font-medium">Party</th>
            <th class="px-5 py-3 font-medium">Status</th>
            <th class="px-5 py-3 font-medium">Update</th>
            <th class="px-5 py-3 font-medium">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-warm-50 dark:divide-warm-800">
          <tr v-for="r in reservations()" :key="r.id" class="hover:bg-warm-50 dark:hover:bg-warm-800/50 transition-colors">
            <td class="px-5 py-3">
              <p class="font-medium text-warm-900 dark:text-white">{{ r.name }}</p>
              <p class="text-xs text-warm-400">{{ r.email }}</p>
            </td>
            <td class="px-5 py-3 text-warm-600 dark:text-warm-400 whitespace-nowrap">{{ formatDate(r.reserved_at) }}</td>
            <td class="px-5 py-3 text-warm-600 dark:text-warm-400">{{ r.table?.name ?? '—' }}</td>
            <td class="px-5 py-3 text-center font-semibold text-warm-700 dark:text-warm-300">{{ r.party_size }}</td>
            <td class="px-5 py-3">
              <span :class="['badge', statusColors[r.status] ?? 'badge']">{{ r.status }}</span>
            </td>
            <td class="px-5 py-3">
              <select
                :value="r.status"
                @change="e => updateStatus(r, e.target.value)"
                class="input text-xs py-1 px-2 w-auto"
              >
                <option v-for="s in ['pending','confirmed','completed','no_show','cancelled']" :key="s" :value="s">{{ s }}</option>
              </select>
            </td>
            <td class="px-5 py-3">
              <button
                v-if="r.status !== 'cancelled'"
                @click="cancel(r)"
                class="text-xs text-red-400 hover:text-red-600 font-medium"
              >Cancel</button>
            </td>
          </tr>
          <tr v-if="!reservations().length">
            <td colspan="7" class="px-5 py-10 text-center text-warm-400">No reservations found</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="data?.meta" class="flex items-center justify-between mt-4 text-sm text-warm-500">
      <span>{{ data.meta.total }} total</span>
      <span>Page {{ data.meta.current_page }} of {{ data.meta.last_page }}</span>
    </div>
  </div>
</template>
