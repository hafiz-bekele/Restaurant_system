<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { staffApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { CalendarCheck, Grid3x3, Unlock, UserCheck, X, Plus, AlertCircle, BedDouble, CheckCircle, Clock, DollarSign, Sparkles } from '@lucide/vue'
import StatCardSkeleton from '../../components/ui/StatCardSkeleton.vue'
import TableSkeleton from '../../components/ui/TableSkeleton.vue'

const { t } = useI18n()
const ui = useUiStore()

const activeTab = ref('tables') // 'tables' | 'rooms'

const data     = ref(null)
const loading  = ref(true)
const saving   = ref(false)
const tick     = ref(0) // Live 1-second reactive ticker for elapsed fees and countdowns

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
    const res = await staffApi.roomBookings({ status: roomStatusFilter.value || undefined })
    roomBookings.value = res.data.data ?? []
  } catch {
    ui.toast(t('receptionPage.checkInFailed'), 'error')
  } finally {
    loadingRooms.value = false
  }
}

async function checkIn(reservation) {
  saving.value = true
  try {
    await staffApi.checkIn(reservation.id)
    ui.toast(t('receptionPage.checkedInToast', { name: reservation.name }), 'success')
    await fetchDashboard()
  } catch {
    ui.toast(t('receptionPage.checkInFailed'), 'error')
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

async function redeem(reservation) {
  saving.value = true
  try {
    await staffApi.redeemReservation(reservation.id)
    ui.toast(`Reservation fee for ${reservation.name} redeemed successfully!`, 'success')
    await fetchDashboard()
  } catch (err) {
    ui.toast(err.response?.data?.message ?? 'Failed to redeem reservation fee', 'error')
  } finally {
    saving.value = false
  }
}

function calculateLiveFee(res) {
  // Read tick to establish reactive tracking
  const _ = tick.value
  if (res.is_redeemed || res.status === 'redeemed') {
    return Number(res.redeem_amount ?? res.current_fee ?? res.reservation_fee ?? 50).toFixed(2)
  }
  const baseFee = 50.00
  if (!res.reserved_at) return baseFee.toFixed(2)
  const reservedTime = new Date(res.reserved_at).getTime()
  const now = Date.now()
  if (now <= reservedTime) {
    return Number(res.reservation_fee ?? 50).toFixed(2)
  }
  const elapsedSeconds = Math.min(7200, Math.floor((now - reservedTime) / 1000))
  if (elapsedSeconds <= 3600) {
    return baseFee.toFixed(2)
  }
  const extraSeconds = elapsedSeconds - 3600
  const intervals = Math.floor(extraSeconds / 10)
  return (baseFee + (intervals * 5)).toFixed(2)
}

function getCountdownText(res) {
  const _ = tick.value
  if (res.is_redeemed || res.status === 'redeemed') return 'Redeemed'
  if (!res.reserved_at) return ''
  const reservedTime = new Date(res.reserved_at).getTime()
  const autoRedeemTime = reservedTime + (2 * 60 * 60 * 1000)
  const now = Date.now()
  if (now >= autoRedeemTime) {
    return '2h Limit Reached (Auto-Redeemed)'
  }
  if (now < reservedTime) {
    return 'Scheduled: ' + (res.time || '')
  }
  const diffSec = Math.floor((autoRedeemTime - now) / 1000)
  const mins = Math.floor(diffSec / 60)
  const secs = diffSec % 60
  return `Auto-redeems in ${mins}m ${secs < 10 ? '0' : ''}${secs}s`
}

async function releaseTable(table) {
  await staffApi.releaseTable(table.id)
  ui.toast(t('receptionPage.tableReleasedToast', { name: table.name }), 'success')
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
    await staffApi.updateRoomBooking(bookingId, { status })
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
let secondTicker
onMounted(() => {
  fetchDashboard()
  fetchRoomBookings()
  interval = setInterval(() => {
    fetchDashboard()
    if (activeTab.value === 'rooms') fetchRoomBookings()
  }, 15000)

  // 1-second ticker for live seconds calculation & overtime increment
  secondTicker = setInterval(() => {
    tick.value++
  }, 1000)
})

onUnmounted(() => {
  if (interval) clearInterval(interval)
  if (secondTicker) clearInterval(secondTicker)
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
          <CalendarCheck :size="24" class="text-primary-500" /> {{ $t('receptionPage.title') }}
        </h1>
        <p class="text-sm text-warm-500 mt-1">{{ $t('receptionPage.subtitle') }}</p>
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
            <Grid3x3 :size="14" /> {{ $t('receptionPage.tabTables') }}
          </button>
          <button
            @click="activeTab = 'rooms'; fetchRoomBookings()"
            :class="[
              'px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5',
              activeTab === 'rooms' ? 'bg-white dark:bg-warm-900 text-warm-900 dark:text-white shadow-sm' : 'text-warm-600 dark:text-warm-400'
            ]"
          >
            <BedDouble :size="14" /> {{ $t('receptionPage.tabRooms') }}
          </button>
        </div>

        <button v-if="activeTab === 'tables'" @click="showWalkIn = true" class="btn-primary gap-1.5 text-xs py-2">
          <Plus :size="15" /> {{ $t('receptionPage.walkInBtn') }}
        </button>
      </div>
    </div>

    <!-- TAB 1: TABLE FLOOR PLAN & RESERVATIONS -->
    <div v-if="activeTab === 'tables'">
      <div v-if="loading" class="space-y-6 mb-8">
        <StatCardSkeleton :count="4" :columns="4" />
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div class="space-y-4">
            <div class="skeleton h-5 w-44 rounded-lg"></div>
            <div v-for="i in 3" :key="i" class="card p-4 space-y-3 border border-warm-100 dark:border-warm-800">
              <div class="skeleton h-4 w-32 rounded"></div>
              <div class="skeleton h-3 w-40 rounded"></div>
              <div class="skeleton h-7 w-full rounded-lg"></div>
            </div>
          </div>
          <div class="lg:col-span-2 space-y-4">
            <div class="skeleton h-5 w-36 rounded-lg"></div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
              <div v-for="i in 8" :key="i" class="card p-3 space-y-2 border border-warm-100 dark:border-warm-800">
                <div class="flex items-center justify-between">
                  <div class="skeleton h-4 w-14 rounded"></div>
                  <div class="skeleton h-4 w-12 !rounded-full"></div>
                </div>
                <div class="skeleton h-3 w-20 rounded"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <template v-else-if="data">
        <!-- Summary stats -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-8">
          <div class="card p-3.5 text-center">
            <p class="text-xl sm:text-2xl font-heading font-bold text-warm-900 dark:text-white">{{ data.summary?.total_tables ?? 0 }}</p>
            <p class="text-xs text-warm-500 mt-1">{{ $t('receptionPage.activeTables') }}</p>
          </div>
          <div class="card p-3.5 text-center">
            <p class="text-xl sm:text-2xl font-heading font-bold text-green-500">{{ data.summary?.available_tables ?? 0 }}</p>
            <p class="text-xs text-warm-500 mt-1">{{ $t('waiterPage.tableStatusAvailable') }}</p>
          </div>
          <div class="card p-3.5 text-center">
            <p class="text-xl sm:text-2xl font-heading font-bold text-red-500">{{ data.summary?.occupied_tables ?? 0 }}</p>
            <p class="text-xs text-warm-500 mt-1">{{ $t('waiterPage.tableStatusOccupied') }}</p>
          </div>
          <div class="card p-3.5 text-center">
            <p class="text-xl sm:text-2xl font-heading font-bold text-primary-500">{{ data.summary?.today_reservations ?? (data.reservations?.length ?? 0) }}</p>
            <p class="text-xs text-warm-500 mt-1">{{ $t('receptionPage.todayReservations') }}</p>
          </div>
          <div class="card p-3.5 text-center bg-purple-50/40 dark:bg-purple-950/20 border border-purple-200/50 dark:border-purple-800/40">
            <p class="text-xl sm:text-2xl font-heading font-bold text-purple-600 dark:text-purple-400">{{ data.summary?.redeemed_today ?? 0 }}</p>
            <p class="text-xs text-purple-600/80 dark:text-purple-300/80 mt-1">Redeemed Today</p>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <!-- Reservations list -->
          <div class="lg:col-span-1 space-y-4">
            <h2 class="font-heading font-semibold text-warm-900 dark:text-white flex items-center justify-between text-base">
              <span>{{ $t('receptionPage.todayReservations') }}</span>
              <span class="badge badge-orange text-xs">{{ (data.reservations ?? data.todayReservations ?? []).length }}</span>
            </h2>

            <div v-if="!(data.reservations ?? data.todayReservations ?? []).length" class="card p-6 text-center text-warm-400 text-sm">
              No reservations scheduled for today
            </div>

            <div
              v-for="res in (data.reservations ?? data.todayReservations ?? [])"
              :key="res.id"
              class="card p-4 space-y-3 border border-warm-200/70 dark:border-warm-800"
            >
              <div class="flex items-start justify-between">
                <div>
                  <p class="font-semibold text-warm-900 dark:text-white text-sm">{{ res.name }}</p>
                  <p class="text-xs text-warm-500">{{ res.phone }} · {{ res.party_size }} {{ $t('receptionPage.party') }}</p>
                </div>
                <div class="text-right">
                  <span class="badge badge-orange text-xs">{{ res.time }}</span>
                  <p class="text-[10px] text-warm-400 mt-0.5">{{ res.duration || 60 }} mins</p>
                </div>
              </div>

              <div class="flex items-center justify-between text-xs text-warm-500">
                <span>{{ $t('kitchenPage.tableNum', { name: res.table_name ?? res.table?.name ?? 'Any' }) }}</span>
                <span :class="[
                  'badge text-[10px] font-bold uppercase tracking-wider',
                  res.status === 'confirmed' ? 'badge-green' :
                  res.status === 'redeemed' ? 'bg-purple-100 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 border-purple-200' :
                  res.status === 'no_show' ? 'badge-red' : 'badge-orange'
                ]">
                  {{ res.status }}
                </span>
              </div>

              <!-- Reservation Fee & Auto-Redeem Live Widget -->
              <div class="p-2.5 rounded-xl bg-warm-50 dark:bg-warm-800/60 border border-warm-200/60 dark:border-warm-700/60 space-y-1.5">
                <div class="flex items-center justify-between">
                  <span class="text-[11px] font-medium text-warm-600 dark:text-warm-400 flex items-center gap-1">
                    <DollarSign :size="12" class="text-primary-500" />
                    Reservation Fee:
                  </span>
                  <span class="font-mono text-xs font-bold text-warm-900 dark:text-white">
                    ETB {{ calculateLiveFee(res) }}
                  </span>
                </div>

                <!-- Rate note and live ticker -->
                <div class="flex items-center justify-between text-[10px] text-warm-500 flex-wrap gap-1">
                  <span class="text-primary-600 dark:text-primary-400 font-medium">Base: 50 ETB (+5 ETB/10s after 1h)</span>
                  <span v-if="res.status === 'redeemed'" class="text-purple-600 dark:text-purple-400 font-bold">
                    ✓ Fee Redeemed
                  </span>
                  <span v-else class="text-amber-600 dark:text-amber-400 font-mono font-medium">
                    {{ getCountdownText(res) }}
                  </span>
                </div>
              </div>

              <div class="flex flex-wrap gap-2 pt-1 border-t border-warm-100 dark:border-warm-800">
                <button
                  v-if="res.status === 'confirmed'"
                  @click="checkIn(res)"
                  :disabled="saving"
                  class="btn-primary flex-1 py-1 text-xs justify-center"
                >
                  <UserCheck :size="13" /> {{ $t('receptionPage.checkInBtn') }}
                </button>

                <button
                  v-if="res.status !== 'redeemed' && res.status !== 'cancelled' && res.status !== 'no_show'"
                  @click="redeem(res)"
                  :disabled="saving"
                  class="btn-secondary flex-1 py-1 text-xs justify-center text-purple-700 dark:text-purple-300 border-purple-200 hover:bg-purple-50 dark:hover:bg-purple-950/30"
                  title="Redeem reservation fee credit towards dining"
                >
                  <Sparkles :size="12" /> Redeem Fee
                </button>

                <button
                  v-if="res.status !== 'redeemed' && res.status !== 'cancelled' && res.status !== 'no_show'"
                  @click="noShow(res)"
                  class="btn-ghost py-1 px-2 text-xs justify-center text-red-500 hover:text-red-600"
                >
                  {{ $t('receptionPage.noShowBtn') }}
                </button>
              </div>
            </div>
          </div>

          <!-- Floor tables grid -->
          <div class="lg:col-span-2 space-y-4">
            <h2 class="font-heading font-semibold text-warm-900 dark:text-white text-base">
              {{ $t('waiterPage.tablesFloorHeader') }}
            </h2>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
              <div
                v-for="table in (data.tables ?? [])"
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
                    <span :class="['badge text-[10px]', statusColors[table.status]]">{{ table.status === 'available' ? $t('waiterPage.tableStatusAvailable') : (table.status === 'occupied' ? $t('waiterPage.tableStatusOccupied') : $t('waiterPage.tableStatusReserved')) }}</span>
                  </div>
                  <p class="text-[11px] text-warm-500">{{ $t('waiterPage.capacityGuests', { count: table.capacity }) }} · {{ table.area || 'Floor' }}</p>
                </div>

                <button
                  v-if="table.status === 'occupied'"
                  @click="releaseTable(table)"
                  class="mt-2 w-full btn-secondary py-1 text-[11px] justify-center gap-1 text-green-700 dark:text-green-400"
                >
                  <Unlock :size="11" /> {{ $t('receptionPage.releaseTable') }}
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
            <option value="">{{ $t('common.viewAll') }}</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="checked_in">Checked In</option>
            <option value="checked_out">Checked Out</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <span class="text-xs text-warm-500 font-medium">{{ roomBookings.length }} Bookings found</span>
        </div>
        <button @click="fetchRoomBookings" class="btn-ghost text-xs py-1.5 px-3">{{ $t('receptionPage.refresh') }}</button>
      </div>

      <TableSkeleton v-if="loadingRooms" :rows="5" :columns="7" />

      <div v-else-if="roomBookings.length === 0" class="card text-center py-12">
        <BedDouble :size="40" class="mx-auto text-warm-400 mb-2" />
        <p class="text-sm font-semibold text-warm-700 dark:text-warm-300">No room bookings recorded</p>
        <p class="text-xs text-warm-400 mt-1">Guests booking through /rooms or scanning room QR codes will appear here.</p>
      </div>

      <div v-else class="card overflow-x-auto shadow-sm">
        <table class="w-full text-xs text-left">
          <thead class="bg-warm-50 dark:bg-warm-850 text-warm-600 dark:text-warm-400 uppercase font-semibold">
            <tr>
              <th class="px-4 py-3">{{ $t('roomsPage.roomNum') }}</th>
              <th class="px-4 py-3">{{ $t('receptionPage.guestName') }}</th>
              <th class="px-4 py-3">{{ $t('checkoutPage.contactInfo') }}</th>
              <th class="px-4 py-3">{{ $t('reservationPage.dateTime') }}</th>
              <th class="px-4 py-3">{{ $t('roomsPage.totalAmount') }}</th>
              <th class="px-4 py-3">{{ $t('receptionPage.status') }}</th>
              <th class="px-4 py-3 text-right">{{ $t('receptionPage.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-100 dark:divide-warm-800">
            <tr v-for="b in roomBookings" :key="b.id" class="hover:bg-warm-50/70 dark:hover:bg-warm-800/40 transition-colors">
              <td class="px-4 py-3">
                <span class="font-bold text-warm-900 dark:text-white">{{ $t('roomsPage.roomTitle', { number: b.room?.room_number ?? b.room_id }) }}</span>
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
              <td class="px-4 py-3 font-bold text-primary-600 dark:text-primary-400 font-mono">
                ETB {{ Number(b.total_price).toFixed(2) }}
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
                  {{ $t('receptionPage.checkInBtn') }}
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
                  {{ $t('common.cancel') }}
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
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">{{ $t('receptionPage.walkInBtn') }}</h2>
              <button @click="showWalkIn = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <form @submit.prevent="submitWalkIn" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('waiterPage.selectFloorTable') }}</label>
                <select v-model="walkInForm.table_id" required class="input">
                  <option value="">{{ $t('common.search') }}…</option>
                  <option v-for="t in availableTables" :key="t.id" :value="t.id">{{ t.name }} ({{ t.capacity }} seats, {{ t.area || 'Floor' }})</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('reservationPage.partySize') }}</label>
                <input v-model.number="walkInForm.party_size" type="number" min="1" max="50" required class="input" />
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('reservationPage.specialRequests') }}</label>
                <input v-model="walkInForm.notes" type="text" class="input" :placeholder="$t('reservationPage.requestsPlaceholder')" />
              </div>
              <div class="flex gap-3 pt-2">
                <button type="button" @click="showWalkIn = false" class="btn-outline flex-1">{{ $t('common.cancel') }}</button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1">{{ saving ? $t('common.loading') : $t('receptionPage.checkInBtn') }}</button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
