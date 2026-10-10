<script setup>
import { ref } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { Plus, Trash2, X, BedDouble } from '@lucide/vue'

const ui = useUiStore()
const qc = useQueryClient()

const tab       = ref('rooms') // 'rooms' | 'bookings'
const showModal = ref(false)
const saving    = ref(false)
const form = ref({ room_number: '', type: 'standard', capacity: 2, price_per_night: 0, is_active: true })

const roomTypes = ['standard', 'deluxe', 'suite', 'twin', 'single']

const bookingStatusColors = { pending: 'badge-orange', confirmed: 'badge-blue', checked_in: 'badge-green', checked_out: 'badge', cancelled: 'badge-red' }

const { data: roomsData, isLoading: roomsLoading } = useQuery({
  queryKey: ['admin', 'rooms'],
  queryFn:  () => adminApi.adminRooms().then(r => r.data.data ?? []),
  staleTime: 60000,
})

const { data: bookingsData, isLoading: bookingsLoading } = useQuery({
  queryKey: ['admin', 'room-bookings'],
  queryFn:  () => adminApi.roomBookings().then(r => r.data),
  staleTime: 60000,
})

const rooms    = () => roomsData.value ?? []
const bookings = () => bookingsData.value?.data ?? []

function openCreate() {
  form.value = { room_number: '', type: 'standard', capacity: 2, price_per_night: 0, is_active: true }
  showModal.value = true
}

async function createRoom() {
  saving.value = true
  try {
    await adminApi.createRoom(form.value)
    qc.invalidateQueries({ queryKey: ['admin', 'rooms'] })
    ui.toast('Room created', 'success')
    showModal.value = false
  } catch (e) {
    const err = e.response?.data?.errors
    ui.toast(err ? Object.values(err).flat()[0] : 'Failed to create room', 'error')
  } finally { saving.value = false }
}

async function deleteRoom(room) {
  if (!confirm(`Delete Room ${room.room_number}?`)) return
  await adminApi.deleteRoom(room.id)
  qc.invalidateQueries({ queryKey: ['admin', 'rooms'] })
  ui.toast('Room deleted')
}

async function updateBookingStatus(booking, status) {
  await adminApi.updateBooking(booking.id, { status })
  qc.invalidateQueries({ queryKey: ['admin', 'room-bookings'] })
  ui.toast('Booking updated')
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '—' }
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
        <BedDouble :size="22" class="text-primary-500" /> Rooms
      </h1>
      <button v-if="tab === 'rooms'" @click="openCreate" class="btn-primary gap-2"><Plus :size="16" /> New Room</button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-6 bg-warm-100 dark:bg-warm-800 p-1 rounded-xl w-fit">
      <button @click="tab = 'rooms'" :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors', tab === 'rooms' ? 'bg-white dark:bg-gray-900 shadow text-warm-900 dark:text-white' : 'text-warm-500 hover:text-warm-700 dark:hover:text-warm-300']">
        Rooms
      </button>
      <button @click="tab = 'bookings'" :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors', tab === 'bookings' ? 'bg-white dark:bg-gray-900 shadow text-warm-900 dark:text-white' : 'text-warm-500 hover:text-warm-700 dark:hover:text-warm-300']">
        Bookings
      </button>
    </div>

    <!-- Rooms tab -->
    <div v-if="tab === 'rooms'">
      <div v-if="roomsLoading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        <div v-for="i in 6" :key="i" class="skeleton h-36 rounded-2xl"></div>
      </div>
      <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        <div v-for="room in rooms()" :key="room.id" class="card p-4 flex flex-col gap-2">
          <div class="flex items-start justify-between">
            <div>
              <p class="font-heading font-bold text-warm-900 dark:text-white">Room {{ room.room_number }}</p>
              <p class="text-xs text-warm-500 capitalize">{{ room.type }} · {{ room.capacity }} guests</p>
            </div>
            <span :class="['badge text-xs', room.status === 'available' ? 'badge-green' : room.status === 'occupied' ? 'badge-red' : 'badge-orange']">{{ room.status }}</span>
          </div>
          <p class="font-semibold text-primary-600 dark:text-primary-400">${{ Number(room.price_per_night).toFixed(2) }}<span class="text-xs font-normal text-warm-400">/night</span></p>
          <button @click="deleteRoom(room)" class="mt-auto flex items-center justify-center gap-1 text-xs text-red-400 hover:text-red-600 py-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
            <Trash2 :size="13" /> Remove
          </button>
        </div>
        <div v-if="!rooms().length" class="col-span-full card p-10 text-center text-warm-400">No rooms added yet</div>
      </div>
    </div>

    <!-- Bookings tab -->
    <div v-else>
      <div v-if="bookingsLoading" class="space-y-3">
        <div v-for="i in 5" :key="i" class="skeleton h-16 rounded-xl"></div>
      </div>
      <div v-else class="card overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-warm-50 dark:bg-warm-800">
            <tr class="text-left text-warm-600 dark:text-warm-400">
              <th class="px-5 py-3 font-medium">Guest</th>
              <th class="px-5 py-3 font-medium">Room</th>
              <th class="px-5 py-3 font-medium">Check-in</th>
              <th class="px-5 py-3 font-medium">Check-out</th>
              <th class="px-5 py-3 font-medium">Total</th>
              <th class="px-5 py-3 font-medium">Status</th>
              <th class="px-5 py-3 font-medium">Update</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-50 dark:divide-warm-800">
            <tr v-for="b in bookings()" :key="b.id" class="hover:bg-warm-50 dark:hover:bg-warm-800/50 transition-colors">
              <td class="px-5 py-3">
                <p class="font-medium text-warm-900 dark:text-white">{{ b.guest_name }}</p>
                <p class="text-xs text-warm-400">{{ b.guest_email }}</p>
              </td>
              <td class="px-5 py-3 text-warm-600 dark:text-warm-400">{{ b.room?.room_number ?? '—' }}</td>
              <td class="px-5 py-3 text-warm-500 whitespace-nowrap">{{ formatDate(b.check_in_date) }}</td>
              <td class="px-5 py-3 text-warm-500 whitespace-nowrap">{{ formatDate(b.check_out_date) }}</td>
              <td class="px-5 py-3 font-semibold text-primary-600 dark:text-primary-400">${{ Number(b.total_amount).toFixed(2) }}</td>
              <td class="px-5 py-3">
                <span :class="['badge', bookingStatusColors[b.status] ?? 'badge']">{{ b.status?.replace('_', ' ') }}</span>
              </td>
              <td class="px-5 py-3">
                <select class="input text-xs py-1 px-2 w-auto" :value="b.status" @change="e => updateBookingStatus(b, e.target.value)">
                  <option v-for="s in ['pending','confirmed','checked_in','checked_out','cancelled']" :key="s" :value="s">{{ s.replace('_', ' ') }}</option>
                </select>
              </td>
            </tr>
            <tr v-if="!bookings().length">
              <td colspan="7" class="px-5 py-10 text-center text-warm-400">No bookings yet</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create room modal -->
    <Teleport to="body">
      <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-200" leave-to-class="opacity-0" leave-active-class="transition-opacity duration-150">
        <div v-if="showModal" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="showModal = false">
          <div class="card w-full max-w-sm p-6">
            <div class="flex items-center justify-between mb-5">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">New Room</h2>
              <button @click="showModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <form @submit.prevent="createRoom" class="space-y-4">
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Room Number</label>
                  <input v-model="form.room_number" type="text" required class="input" placeholder="101" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Type</label>
                  <select v-model="form.type" class="input capitalize">
                    <option v-for="t in roomTypes" :key="t" :value="t" class="capitalize">{{ t }}</option>
                  </select>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Capacity</label>
                  <input v-model.number="form.capacity" type="number" min="1" max="10" required class="input" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Price/Night ($)</label>
                  <input v-model.number="form.price_per_night" type="number" min="0" step="0.01" required class="input" />
                </div>
              </div>
              <label class="flex items-center gap-2 text-sm font-medium text-warm-700 dark:text-warm-300 cursor-pointer">
                <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded accent-primary-500" />
                Active immediately
              </label>
              <div class="flex gap-3 pt-2">
                <button type="button" @click="showModal = false" class="btn-outline flex-1">Cancel</button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1">{{ saving ? 'Creating…' : 'Create Room' }}</button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
