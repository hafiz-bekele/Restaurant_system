<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { staffApi, adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { CalendarCheck, Grid3x3, Unlock, UserCheck, X, Plus, AlertCircle, BedDouble, CheckCircle, Clock } from '@lucide/vue'

const ui = useUiStore()

const activeTab = ref('tables') // 'tables' | 'rooms'

const data     = ref(null)
const loading  = ref(true)
const saving   = ref(false)

// Walk-in modal
const showWalkIn   = ref(false)
const walkInForm   = ref({ table_id: '', party_size: 2, notes: '' })

// Room bookings state
const roomBookings = ref([])
const loadingRooms = ref(false)
const roomStatusFilter = ref('')

async function fetchDashboard() {
  loading.value = true
  try {
    const res  = await staffApi.receptionDashboard()
    data.value = res.data.data
  } finally {
    loading.value = false
  }
}

async function fetchRoomBookings() {
  loadingRooms.value = true
  try {
    const res = await adminApi.roomBookings({ status: roomStatusFilter.value || undefined })
    roomBookings.value = res.data.data ?? []
  } catch {
    ui.toast('Failed to load room bookings', 'error')
  } finally {
    loadingRooms.value = false
  }
}

async function checkIn(reservation) {
  saving.value = true
  try {
    await staffApi.checkIn(reservation.id)
    ui.toast(`${reservation.name} checked in`, 'success')
    await fetchDashboard()
  } catch {
    ui.toast('Check-in failed', 'error')
  } finally {
    saving.value = false
  }
}

async function noShow(reservation) {
  if (!confirm(`Mark ${reservation.name} as no-show?`)) return
  await staffApi.noShow(reservation.id)
  ui.toast('Marked as no-show')
  await fetchDashboard()
}

async function releaseTable(table) {
  await staffApi.releaseTable(table.id)
  ui.toast(`Table ${table.name} released`, 'success')
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
  } finally {
    saving.value = false
  }
}

async function updateRoomBookingStatus(bookingId, status) {
  try {
    await adminApi.updateBooking(bookingId, { status })
    ui.toast(`Room booking updated to ${status}`, 'success')
    await fetchRoomBookings()
  } catch {
    ui.toast('Failed to update booking status', 'error')
  }
}

const availableTables = computed(() =>
  (data.value?.tables ?? []).filter(t => t.status === 'available')
)

const statusColors = { available: 'badge-green', occupied: 'badge-red', reserved: 'badge-orange', inactive: 'badge' }

let interval
onMounted(() => {
  fetchDashboard()
  fetchRoomBookings()
  interval = setInterval(() => {
    fetchDashboard()
    if (activeTab.value === 'rooms') fetchRoomBookings()
  }, 20000)
})

onUnmounted(() => clearInterval(interval))
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
          <CalendarCheck :size="24" class="text-primary-500" /> Front Desk Reception Portal
        </h1>
        <p class="text-sm text-warm-500 mt-1">Table seating, dining reservations &amp; hotel room folio management</p>
      </div>

      <div class="flex items-center gap-3">
        <!-- Tab Switcher -->
        <div class="flex bg-warm-100 dark:bg-warm-800 p-1 rounded-xl">
          <button
            @click="activeTab = 'tables'"
            :class="[
              'px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5',
              activeTab === 'tables' ? 'bg-white dark:bg-warm-900 text-warm-900 dark:text-white shadow-sm' : 'text-warm-600 dark:text-warm-400'
            ]"
          >
            <Grid3x3 :size="14" /> Tables Floor Plan
          </button>
          <button
            @click="activeTab = 'rooms'; fetchRoomBookings()"
            :class="[
              'px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5',
              activeTab === 'rooms' ? 'bg-white dark:bg-warm-900 text-warm-900 dark:text-white shadow-sm' : 'text-warm-600 dark:text-warm-400'
            ]"
          >
            <BedDouble :size="14" /> Hotel Room Bookings
          </button>
        </div>

        <button v-if="activeTab === 'tables'" @click="showWalkIn = true" class="btn-primary gap-1.5 text-xs py-2">
          <Plus :size="15" /> Seat Walk-in
        </button>
      </div>
    </div>

    <!-- TAB 1: TABLE FLOOR PLAN & RESERVATIONS -->
    <div v-if="activeTab === 'tables'">
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
            <p class="text-xs text-warm-500 mt-1">Available Now</p>
          </div>
          <div class="card p-4 text-center">
            <p class="text-2xl font-heading font-bold text-red-500">{{ data.summary.occupied_tables }}</p>
            <p class="text-xs text-warm-500 mt-1">Occupied</p>
          </div>
          <div class="card p-4 text-center">
            <p class="text-2xl font-heading font-bold text-primary-500">{{ data.summary.today_reservations }}</p>
            <p class="text-xs text-warm-500 mt-1">Today's Bookings</p>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <!-- Reservations list -->
          <div class="lg:col-span-1 space-y-4">
            <h2 class="font-heading font-semibold text-warm-900 dark:text-white flex items-center justify-between text-base">
              <span>Today's Dining Reservations</span>
              <span class="badge badge-orange text-xs">{{ data.reservations.length }}</span>
            </h2>

            <div v-if="!data.reservations.length" class="card p-6 text-center text-warm-400 text-sm">
              No reservations scheduled for today
            </div>

            <div v-for="res in data.reservations" :key="res.id" class="card p-4 space-y-3">
              <div class="flex items-start justify-between">
                <div>
                  <p class="font-semibold text-warm-900 dark:text-white text-sm">{{ res.name }}</p>
                  <p class="text-xs text-warm-500">{{ res.phone }} · {{ res.party_size }} guests</p>
                </div>
                <span class="badge badge-orange text-xs">{{ res.time }}</span>
              </div>

              <div class="flex items-center justify-between text-xs text-warm-500">
                <span>Table: {{ res.table_name ?? 'Any' }}</span>
                <span :class="['badge text-[10px]', res.status === 'confirmed' ? 'badge-green' : 'badge-orange']">{{ res.status }}</span>
              </div>

              <div v-if="res.status === 'confirmed'" class="flex gap-2 pt-1 border-t border-warm-100 dark:border-warm-800">
                <button @click="checkIn(res)" :disabled="saving" class="btn-primary flex-1 py-1 text-xs justify-center">
                  <UserCheck :size="13" /> Check In
                </button>
                <button @click="noShow(res)" class="btn-ghost flex-1 py-1 text-xs justify-center text-red-500 hover:text-red-600">
                  No-Show
                </button>
              </div>
            </div>
          </div>

          <!-- Floor tables grid -->
          <div class="lg:col-span-2 space-y-4">
            <h2 class="font-heading font-semibold text-warm-900 dark:text-white text-base">
              Floor Plan Status
            </h2>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
              <div
                v-for="table in data.tables"
                :key="table.id"
                :class="[
                  'card p-3 flex flex-col justify-between transition-all border-2',
                  table.status === 'available' ? 'border-green-400/40 bg-green-50/20 dark:bg-green-950/10' :
                  table.status === 'occupied'  ? 'border-red-400/40 bg-red-50/20 dark:bg-red-950/10' :
                  'border-amber-400/40 bg-amber-50/20 dark:bg-amber-950/10'
                ]"
              >
                <div>
                  <div class="flex items-center justify-between mb-1">
                    <span class="font-bold text-warm-900 dark:text-white text-sm">{{ table.name }}</span>
                    <span :class="['badge text-[10px]', statusColors[table.status]]">{{ table.status }}</span>
                  </div>
                  <p class="text-[11px] text-warm-500">{{ table.capacity }} seats · {{ table.area || 'Floor' }}</p>
                </div>

                <button
                  v-if="table.status === 'occupied'"
                  @click="releaseTable(table)"
                  class="mt-2 w-full btn-secondary py-1 text-[11px] justify-center gap-1 text-green-700 dark:text-green-400"
                >
                  <Unlock :size="11" /> Release Table
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- TAB 2: HOTEL ROOM BOOKINGS -->
    <div v-else>
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
          <select v-model="roomStatusFilter" @change="fetchRoomBookings" class="input w-auto text-xs py-1.5">
            <option value="">All Room Booking Statuses</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="checked_in">Checked In</option>
            <option value="checked_out">Checked Out</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <span class="text-xs text-warm-500 font-medium">{{ roomBookings.length }} Bookings found</span>
        </div>
        <button @click="fetchRoomBookings" class="btn-ghost text-xs py-1.5 px-3">Refresh</button>
      </div>

      <div v-if="loadingRooms" class="space-y-3">
        <div v-for="i in 4" :key="i" class="skeleton h-16 rounded-2xl"></div>
      </div>

      <div v-else-if="roomBookings.length === 0" class="card text-center py-12">
        <BedDouble :size="40" class="mx-auto text-warm-400 mb-2" />
        <p class="text-sm font-semibold text-warm-700 dark:text-warm-300">No room bookings recorded</p>
        <p class="text-xs text-warm-400 mt-1">Guests booking through /rooms or scanning room QR codes will appear here.</p>
      </div>

      <div v-else class="card overflow-x-auto shadow-sm">
        <table class="w-full text-xs text-left">
          <thead class="bg-warm-50 dark:bg-warm-850 text-warm-600 dark:text-warm-400 uppercase font-semibold">
            <tr>
              <th class="px-4 py-3">Room</th>
              <th class="px-4 py-3">Guest Name</th>
              <th class="px-4 py-3">Contact</th>
              <th class="px-4 py-3">Stay Dates</th>
              <th class="px-4 py-3">Total Folio</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-100 dark:divide-warm-800">
            <tr v-for="b in roomBookings" :key="b.id" class="hover:bg-warm-50/70 dark:hover:bg-warm-800/40 transition-colors">
              <td class="px-4 py-3">
                <span class="font-bold text-warm-900 dark:text-white">Suite {{ b.room?.room_number ?? b.room_id }}</span>
                <p class="text-[10px] text-warm-400 capitalize">{{ b.room?.type ?? 'Deluxe' }}</p>
              </td>
              <td class="px-4 py-3 font-semibold text-warm-800 dark:text-warm-200">
                {{ b.guest_name }}
              </td>
              <td class="px-4 py-3 text-warm-600 dark:text-warm-400">
                <p>{{ b.guest_phone }}</p>
                <p class="text-[10px] text-warm-400">{{ b.guest_email }}</p>
              </td>
              <td class="px-4 py-3 font-mono text-[11px] text-warm-700 dark:text-warm-300">
                {{ b.check_in_date }} → {{ b.check_out_date }}
              </td>
              <td class="px-4 py-3 font-bold text-primary-600 dark:text-primary-400">
                ${{ Number(b.total_price).toFixed(2) }}
              </td>
              <td class="px-4 py-3">
                <span
                  :class="[
                    'badge text-[10px] capitalize',
                    b.status === 'checked_in' ? 'badge-green' :
                    b.status === 'confirmed'  ? 'badge-orange' :
                    b.status === 'checked_out' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'badge-red'
                  ]"
                >
                  {{ b.status.replace('_', ' ') }}
                </span>
              </td>
              <td class="px-4 py-3 text-right space-x-1.5">
                <button
                  v-if="b.status === 'confirmed' || b.status === 'pending'"
                  @click="updateRoomBookingStatus(b.id, 'checked_in')"
                  class="btn-primary py-1 px-2.5 text-[11px]"
                >
                  Check In
                </button>
                <button
                  v-if="b.status === 'checked_in'"
                  @click="updateRoomBookingStatus(b.id, 'checked_out')"
                  class="btn-secondary py-1 px-2.5 text-[11px]"
                >
                  Check Out
                </button>
                <button
                  v-if="b.status !== 'cancelled' && b.status !== 'checked_out'"
                  @click="updateRoomBookingStatus(b.id, 'cancelled')"
                  class="btn-ghost py-1 px-2 text-[11px] text-red-500"
                >
                  Cancel
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Walk-in modal -->
    <Teleport to="body">
      <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-200" leave-to-class="opacity-0" leave-active-class="transition-opacity duration-150">
        <div v-if="showWalkIn" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="showWalkIn = false">
          <div class="card w-full max-w-sm p-6">
            <div class="flex items-center justify-between mb-5">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">Seat Walk-in Guest</h2>
              <button @click="showWalkIn = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <form @submit.prevent="submitWalkIn" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Table</label>
                <select v-model="walkInForm.table_id" required class="input">
                  <option value="">Select table…</option>
                  <option v-for="t in availableTables" :key="t.id" :value="t.id">{{ t.name }} ({{ t.capacity }} seats, {{ t.area || 'Floor' }})</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Party Size</label>
                <input v-model.number="walkInForm.party_size" type="number" min="1" max="50" required class="input" />
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Notes</label>
                <input v-model="walkInForm.notes" type="text" class="input" placeholder="e.g. Near window, highchair needed" />
              </div>
              <div class="flex gap-3 pt-2">
                <button type="button" @click="showWalkIn = false" class="btn-outline flex-1">Cancel</button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1">{{ saving ? 'Seating…' : 'Seat Table' }}</button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
