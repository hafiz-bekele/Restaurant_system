<script setup>
import { ref } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { useI18n } from 'vue-i18n'
import { Calendar, Search } from '@lucide/vue'
import TableSkeleton from '../../components/ui/TableSkeleton.vue'

const { t } = useI18n()
const ui = useUiStore()
const qc = useQueryClient()

const dateFilter   = ref(new Date().toISOString().split('T')[0])
const statusFilter = ref('')
const search       = ref('')

const statusOptions = ['', 'pending', 'confirmed', 'redeemed', 'cancelled', 'completed', 'no_show']
const statusColors  = {
  pending:   'badge-orange',
  confirmed: 'badge-green',
  redeemed:  'bg-purple-100 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 border border-purple-200',
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
  ui.toast(t('adminReservations.toastUpdated'), 'success')
}

async function redeem(reservation) {
  await adminApi.redeemReservation(reservation.id)
  qc.invalidateQueries({ queryKey: ['admin', 'reservations'] })
  ui.toast('Reservation fee redeemed successfully', 'success')
}

async function cancel(reservation) {
  if (!confirm(t('adminReservations.confirmCancelPrompt'))) return
  await adminApi.deleteReservation(reservation.id)
  qc.invalidateQueries({ queryKey: ['admin', 'reservations'] })
  ui.toast(t('adminReservations.toastCancelled'), 'info')
}

function formatDate(dt) {
  return dt ? new Date(dt).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '—'
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
        <Calendar :size="22" class="text-primary-500" /> {{ $t('adminReservations.title') }}
      </h1>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-6">
      <input v-model="dateFilter" type="date" class="input w-auto" />
      <select v-model="statusFilter" class="input w-auto">
        <option v-for="s in statusOptions" :key="s" :value="s">{{ s ? s.toUpperCase() : $t('adminReservations.allStatuses') }}</option>
      </select>
      <div class="relative">
        <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
        <input v-model="search" type="search" :placeholder="$t('adminReservations.searchPlaceholder')" class="input pl-9 w-60" />
      </div>
    </div>

    <TableSkeleton v-if="isLoading" :rows="6" :cols="7" />

    <div v-else class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-warm-50 dark:bg-warm-800">
          <tr class="text-left text-warm-600 dark:text-warm-400">
            <th class="px-5 py-3 font-medium">{{ $t('adminReservations.thGuest') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminReservations.thDateTime') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminReservations.thTable') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminReservations.thParty') }}</th>
            <th class="px-5 py-3 font-medium">Fee & Policy</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminReservations.thStatus') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminReservations.thUpdate') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminReservations.thActions') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-warm-50 dark:divide-warm-800">
          <tr v-for="r in reservations()" :key="r.id" class="hover:bg-warm-50 dark:hover:bg-warm-800/50 transition-colors">
            <td class="px-5 py-3">
              <p class="font-medium text-warm-900 dark:text-white">{{ r.name }}</p>
              <p class="text-xs text-warm-400">{{ r.email }}</p>
            </td>
            <td class="px-5 py-3 text-warm-600 dark:text-warm-400 whitespace-nowrap">
              {{ formatDate(r.reserved_at) }}
              <p class="text-[10px] text-warm-400">{{ r.duration || 60 }} mins</p>
            </td>
            <td class="px-5 py-3 text-warm-600 dark:text-warm-400">{{ r.table?.name ?? '—' }}</td>
            <td class="px-5 py-3 text-center font-semibold text-warm-700 dark:text-warm-300">{{ r.party_size }}</td>
            <td class="px-5 py-3">
              <p class="font-mono font-bold text-warm-900 dark:text-white">ETB {{ Number(r.current_fee ?? r.reservation_fee ?? 50).toFixed(2) }}</p>
              <p class="text-[10px] text-warm-400">Base: 50 ETB (+5/10s)</p>
              <p v-if="r.status === 'redeemed'" class="text-[10px] text-purple-600 dark:text-purple-400 font-bold">✓ Redeemed</p>
              <p v-else-if="r.seconds_until_auto_redeem > 0" class="text-[10px] text-amber-600 font-mono">Auto-redeems in {{ Math.floor(r.seconds_until_auto_redeem / 60) }}m</p>
            </td>
            <td class="px-5 py-3">
              <span :class="['badge', statusColors[r.status] ?? 'badge']">{{ r.status }}</span>
            </td>
            <td class="px-5 py-3">
              <select
                :value="r.status"
                @change="e => updateStatus(r, e.target.value)"
                class="input text-xs py-1 px-2 w-auto"
              >
                <option v-for="s in ['pending','confirmed','redeemed','completed','no_show','cancelled']" :key="s" :value="s">{{ s }}</option>
              </select>
            </td>
            <td class="px-5 py-3">
              <div class="flex items-center gap-2">
                <button
                  v-if="r.status !== 'redeemed' && r.status !== 'cancelled' && r.status !== 'no_show'"
                  @click="redeem(r)"
                  class="text-xs text-purple-600 hover:text-purple-800 font-medium"
                  title="Redeem fee towards dining bill"
                >
                  Redeem
                </button>
                <button
                  v-if="r.status !== 'cancelled'"
                  @click="cancel(r)"
                  class="text-xs text-red-400 hover:text-red-600 font-medium"
                >{{ $t('adminReservations.cancelBtn') }}</button>
              </div>
            </td>
          </tr>
          <tr v-if="!reservations().length">
            <td colspan="8" class="px-5 py-10 text-center text-warm-400">{{ $t('adminReservations.noReservationsFound') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="data?.meta" class="flex items-center justify-between mt-4 text-sm text-warm-500">
      <span>{{ $t('adminReservations.totalLabel', { total: data.meta.total }) }}</span>
      <span>{{ $t('adminReservations.pageLabel', { current: data.meta.current_page, last: data.meta.last_page }) }}</span>
    </div>
  </div>
</template>
