<script setup>
import { ref, computed, onMounted } from 'vue'
import { staffApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { useQueryClient } from '@tanstack/vue-query'
import { CalendarCheck, Grid3x3, Unlock, UserCheck, X, Plus, AlertCircle } from '@lucide/vue'

const ui = useUiStore()

const data     = ref(null)
const loading  = ref(true)
const saving   = ref(false)

// Walk-in modal
const showWalkIn   = ref(false)
const walkInForm   = ref({ table_id: '', party_size: 2, notes: '' })

async function fetchDashboard() {
  loading.value = true
  try {
    const res    = await staffApi.receptionDashboard()
    data.value   = res.data.data
  } finally {
    loading.value = false
  }
}

async function checkIn(reservation) {
  saving.value = true
  try {
    await staffApi.checkIn(reservation.id)
    ui.toast(`${reservation.name} checked in`)
    await fetchDashboard()
  } catch { ui.toast('Check-in failed', 'error') }
  finally { saving.value = false }
}

async function noShow(reservation) {
  if (!confirm(`Mark ${reservation.name} as no-show?`)) return
  await staffApi.noShow(reservation.id)
  ui.toast('Marked as no-show')
  await fetchDashboard()
}

async function releaseTable(table) {
  await staffApi.releaseTable(table.id)
  ui.toast(`Table ${table.name} released`)
  await fetchDashboard()
}

async function submitWalkIn() {
  saving.value = true
  try {
    const res = await staffApi.walkIn(walkInForm.value)
    ui.toast(`Walk-in created — ${res.data.data.table}`, 'success')
    showWalkIn.value = false
    await fetchDashboard()
  } catch (e) {
    ui.toast(e.response?.data?.message ?? 'Walk-in failed', 'error')
  } finally { saving.value = false }
}

const availableTables = computed(() =>
  (data.value?.tables ?? []).filter(t => t.status === 'available')
)

const statusColors = { available: 'badge-green', occupied: 'badge-red', reserved: 'badge-orange', inactive: 'badge' }

onMounted(fetchDashboard)

let interval
import { onUnmounted } from 'vue'
onMounted(() => { interval = setInterval(fetchDashboard, 30000) })
onUnmounted(() => clearInterval(interval))
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
        <CalendarCheck :size="22" class="text-primary-500" /> Reception
      </h1>
      <button @click="showWalkIn = true" class="btn-primary gap-2 text-sm">
        <Plus :size="15" /> Walk-in
      </button>
    </div>

    <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
      <div v-for="i in 4" :key="i" class="skeleton h-24 rounded-2xl"></div>
    </div>

    <template v-else-if="data">
      <!-- Summary stats -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <div class="card p-4 text-center">
          <p class="text-2xl font-heading font-bold text-warm-900 dark:text-white">{{ data.summary.total_tables }}</p>
          <p class="text-xs text-warm-500 mt-1">Total Tables</p>
        </div>
        <div class="card p-4 text-center">
          <p class="text-2xl font-heading font-bold text-green-500">{{ data.summary.available_tables }}</p>
          <p class="text-xs text-warm-500 mt-1">Available</p>
        </div>
        <div class="card p-4 text-center">
          <p class="text-2xl font-heading font-bold text-orange-500">{{ data.summary.occupied_tables }}</p>
          <p class="text-xs text-warm-500 mt-1">Occupied</p>
        </div>
        <div class="card p-4 text-center">
          <p class="text-2xl font-heading font-bold text-blue-500">{{ data.summary.total_today }}</p>
          <p class="text-xs text-warm-500 mt-1">Today's Reservations</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Today's reservations -->
        <div>
          <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white mb-3">Today's Reservations</h2>
          <div class="space-y-3">
            <div v-for="r in data.todayReservations" :key="r.id" class="card p-4">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-semibold text-warm-900 dark:text-white">{{ r.name }}</p>
                  <p class="text-xs text-warm-400">{{ r.phone }} · Party of {{ r.party_size }}</p>
                  <p class="text-xs text-warm-400 mt-0.5">{{ new Date(r.reserved_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }} · {{ r.table?.name ?? 'No table' }}</p>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                  <span :class="['badge text-xs', r.status === 'confirmed' ? 'badge-green' : r.status === 'cancelled' ? 'badge-red' : 'badge-orange']">{{ r.status }}</span>
                  <div v-if="['pending','confirmed'].includes(r.status)" class="flex gap-1">
                    <button @click="checkIn(r)" :disabled="saving" class="btn-primary py-1 px-2 text-xs gap-1">
                      <UserCheck :size="12" /> Check In
                    </button>
                    <button @click="noShow(r)" class="btn-ghost py-1 px-2 text-xs text-red-400 hover:text-red-600">
                      <AlertCircle :size="12" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <p v-if="!data.todayReservations.length" class="text-center py-8 text-warm-400 text-sm">No reservations today</p>
          </div>
        </div>

        <!-- Floor plan (table grid) -->
        <div>
          <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white mb-3">Floor Status</h2>
          <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
            <div v-for="table in data.tables" :key="table.id" :class="['card p-3 text-center text-sm', table.status === 'available' ? 'border-green-200 dark:border-green-800' : table.status === 'occupied' ? 'border-red-200 dark:border-red-800 border-2' : 'border-orange-200 dark:border-orange-800 border-2']">
              <p class="font-heading font-bold text-warm-900 dark:text-white text-xs">{{ table.name }}</p>
              <p class="text-xs text-warm-400">{{ table.capacity }}p</p>
              <span :class="['badge mt-1 text-[10px]', statusColors[table.status] ?? 'badge']">{{ table.status }}</span>
              <button v-if="table.status === 'occupied'" @click="releaseTable(table)" class="mt-1.5 w-full flex items-center justify-center gap-1 text-[10px] text-green-600 hover:text-green-700 font-medium">
                <Unlock :size="10" /> Release
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Walk-in modal -->
    <Teleport to="body">
      <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-200" leave-to-class="opacity-0" leave-active-class="transition-opacity duration-150">
        <div v-if="showWalkIn" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="showWalkIn = false">
          <div class="card w-full max-w-sm p-6">
            <div class="flex items-center justify-between mb-5">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">Walk-in Order</h2>
              <button @click="showWalkIn = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <form @submit.prevent="submitWalkIn" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Table</label>
                <select v-model="walkInForm.table_id" required class="input">
                  <option value="">Select table…</option>
                  <option v-for="t in availableTables" :key="t.id" :value="t.id">{{ t.name }} ({{ t.capacity }} seats, {{ t.area || 'No area' }})</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Party Size</label>
                <input v-model.number="walkInForm.party_size" type="number" min="1" max="50" required class="input" />
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Notes</label>
                <input v-model="walkInForm.notes" type="text" class="input" placeholder="Any special requests…" />
              </div>
              <div class="flex gap-3 pt-2">
                <button type="button" @click="showWalkIn = false" class="btn-outline flex-1">Cancel</button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1">{{ saving ? 'Creating…' : 'Create Walk-in' }}</button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
