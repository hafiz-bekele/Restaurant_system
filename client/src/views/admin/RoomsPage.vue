<script setup>
import { ref } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import {
  Plus, Trash2, Pencil, X, BedDouble, Upload, Link,
  ImageOff, Users, DollarSign, RefreshCw, Search, CheckCircle,
} from '@lucide/vue'
import RoomCardSkeleton from '../../components/ui/RoomCardSkeleton.vue'
import TableSkeleton from '../../components/ui/TableSkeleton.vue'

const ui = useUiStore()
const qc = useQueryClient()

// ── State ──────────────────────────────────────────────────────────────────────
const tab          = ref('rooms')       // 'rooms' | 'bookings'
const showModal    = ref(false)
const saving       = ref(false)
const editingRoom  = ref(null)          // null = create, object = edit
const imageMode    = ref('url')         // 'url' | 'file'
const imageFile    = ref(null)
const imagePreview = ref('')
const fileInput    = ref(null)
const bookingStatusFilter = ref('')

const roomTypes   = ['standard', 'deluxe', 'suite', 'twin', 'single', 'family']
const amenityList = ['Wi-Fi', 'Air Conditioning', 'TV', 'Mini Bar', 'Room Service', 'Safe', 'Balcony', 'Bathtub', 'Coffee Maker', 'Sea View']

const bookingStatusColors = {
  pending:     'badge-orange',
  confirmed:   'badge-blue',
  checked_in:  'badge-green',
  checked_out: 'badge',
  cancelled:   'badge-red',
}

const emptyForm = () => ({
  room_number:     '',
  type:            'standard',
  capacity:        2,
  price_per_night: '',
  description:     '',
  image_url:       '',
  amenities:       [],
  is_active:       true,
})
const form = ref(emptyForm())

// ── Queries ────────────────────────────────────────────────────────────────────
const { data: roomsData, isLoading: roomsLoading } = useQuery({
  queryKey: ['admin', 'rooms'],
  queryFn:  () => adminApi.adminRooms().then(r => r.data.data ?? []),
  staleTime: 60000,
})

const { data: bookingsData, isLoading: bookingsLoading } = useQuery({
  queryKey: ['admin', 'room-bookings', bookingStatusFilter],
  queryFn:  () => adminApi.roomBookings({
    status: bookingStatusFilter.value || undefined,
  }).then(r => r.data),
  staleTime: 30000,
})

const rooms    = () => roomsData.value ?? []
const bookings = () => bookingsData.value?.data ?? []

// ── Image handling ─────────────────────────────────────────────────────────────
function onFileChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  imageFile.value    = file
  imagePreview.value = URL.createObjectURL(file)
}

function triggerFileInput() { fileInput.value?.click() }

function clearImage() {
  imageFile.value      = null
  imagePreview.value   = ''
  form.value.image_url = ''
  if (fileInput.value) fileInput.value.value = ''
}

function switchImageMode(mode) {
  imageMode.value = mode
  clearImage()
}

// ── Amenity toggle ─────────────────────────────────────────────────────────────
function toggleAmenity(a) {
  const idx = form.value.amenities.indexOf(a)
  if (idx > -1) form.value.amenities.splice(idx, 1)
  else form.value.amenities.push(a)
}

// ── Build payload ─────────────────────────────────────────────────────────────
function buildPayload() {
  if (imageMode.value === 'file' && imageFile.value) {
    const fd = new FormData()
    Object.entries(form.value).forEach(([k, v]) => {
      if (k === 'amenities') {
        form.value.amenities.forEach(a => fd.append('amenities[]', a))
      } else if (k !== 'image_url' && v !== '' && v !== null && v !== undefined) {
        fd.append(k, String(v))
      }
    })
    fd.append('image', imageFile.value)
    return fd
  }
  const payload = { ...form.value }
  if (!payload.image_url) delete payload.image_url
  if (!payload.description) delete payload.description
  return payload
}

// ── Modal open / close ────────────────────────────────────────────────────────
function openCreate() {
  editingRoom.value  = null
  form.value         = emptyForm()
  imageMode.value    = 'url'
  imagePreview.value = ''
  imageFile.value    = null
  showModal.value    = true
}

function openEdit(room) {
  editingRoom.value = room
  form.value = {
    room_number:     room.room_number     ?? '',
    type:            room.type            ?? 'standard',
    capacity:        room.capacity        ?? 2,
    price_per_night: room.price_per_night ?? '',
    description:     room.description     ?? '',
    image_url:       room.image           ?? '',
    amenities:       Array.isArray(room.amenities) ? [...room.amenities] : [],
    is_active:       room.is_active       ?? true,
  }
  imageMode.value    = 'url'
  imagePreview.value = room.image ?? ''
  imageFile.value    = null
  showModal.value    = true
}

function closeModal() {
  showModal.value = false
  clearImage()
}

// ── Save ──────────────────────────────────────────────────────────────────────
async function save() {
  if (!form.value.room_number || !form.value.price_per_night) {
    ui.toast('Room number and price are required', 'error')
    return
  }
  saving.value = true
  try {
    const payload = buildPayload()
    if (editingRoom.value) {
      await adminApi.updateRoom(editingRoom.value.id, payload)
      ui.toast(`Room ${form.value.room_number} updated`, 'success')
    } else {
      await adminApi.createRoom(payload)
      ui.toast(`Room ${form.value.room_number} created`, 'success')
    }
    qc.invalidateQueries({ queryKey: ['admin', 'rooms'] })
    closeModal()
  } catch (e) {
    const errors = e.response?.data?.errors
    ui.toast(errors ? Object.values(errors).flat()[0] : 'Failed to save room', 'error')
  } finally {
    saving.value = false
  }
}

async function deleteRoom(room) {
  if (!confirm(`Remove Room ${room.room_number}? This cannot be undone.`)) return
  try {
    await adminApi.deleteRoom(room.id)
    qc.invalidateQueries({ queryKey: ['admin', 'rooms'] })
    ui.toast(`Room ${room.room_number} removed`, 'success')
  } catch {
    ui.toast('Failed to delete room', 'error')
  }
}

async function updateBookingStatus(booking, status) {
  try {
    await adminApi.updateBooking(booking.id, { status })
    qc.invalidateQueries({ queryKey: ['admin', 'room-bookings'] })
    ui.toast('Booking status updated', 'success')
  } catch {
    ui.toast('Failed to update booking', 'error')
  }
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—' }

const statusBadgeClass = (s) => bookingStatusColors[s] ?? 'badge'
const roomStatusClass  = (s) => s === 'available' ? 'badge-green' : s === 'occupied' ? 'badge-red' : 'badge-orange'
</script>

<template>
  <div class="space-y-6">

    <!-- ── Header ──────────────────────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
          <BedDouble :size="24" class="text-primary-500" /> Rooms & Bookings
        </h1>
        <p class="text-sm text-warm-500 dark:text-warm-400 mt-0.5">Manage hotel rooms and guest reservations</p>
      </div>
      <div class="flex items-center gap-3">
        <button
          @click="qc.invalidateQueries({ queryKey: tab === 'rooms' ? ['admin','rooms'] : ['admin','room-bookings'] })"
          class="btn-ghost text-xs gap-1.5 py-2 px-3"
        >
          <RefreshCw :size="13" /> Refresh
        </button>
        <button v-if="tab === 'rooms'" @click="openCreate" class="btn-primary gap-2 shadow-sm">
          <Plus :size="16" /> Add Room
        </button>
      </div>
    </div>

    <!-- ── Tabs ────────────────────────────────────────────────────────────── -->
    <div class="flex gap-1 p-1 bg-warm-100 dark:bg-warm-800 rounded-xl w-fit">
      <button
        @click="tab = 'rooms'"
        :class="['px-4 py-1.5 rounded-lg text-sm font-semibold transition-all', tab === 'rooms' ? 'bg-white dark:bg-gray-900 shadow text-warm-900 dark:text-white' : 'text-warm-500 hover:text-warm-700 dark:hover:text-warm-300']"
      >
        Rooms <span class="ml-1 text-xs text-warm-400">({{ rooms().length }})</span>
      </button>
      <button
        @click="tab = 'bookings'"
        :class="['px-4 py-1.5 rounded-lg text-sm font-semibold transition-all', tab === 'bookings' ? 'bg-white dark:bg-gray-900 shadow text-warm-900 dark:text-white' : 'text-warm-500 hover:text-warm-700 dark:hover:text-warm-300']"
      >
        Bookings <span class="ml-1 text-xs text-warm-400">({{ bookings().length }})</span>
      </button>
    </div>

    <!-- ══ ROOMS TAB ══════════════════════════════════════════════════════════ -->
    <div v-if="tab === 'rooms'">

      <!-- Skeleton -->
      <div v-if="roomsLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <RoomCardSkeleton v-for="i in 8" :key="i" />
      </div>

      <!-- Room cards grid -->
      <div v-else-if="rooms().length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <div
          v-for="room in rooms()"
          :key="room.id"
          class="card overflow-hidden group hover:shadow-float hover:-translate-y-0.5 transition-all duration-200"
        >
          <!-- Image -->
          <div class="aspect-[4/3] bg-warm-100 dark:bg-warm-800 relative overflow-hidden">
            <img
              v-if="room.image"
              :src="room.image"
              :alt="`Room ${room.room_number}`"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
              <BedDouble :size="36" class="text-warm-300 dark:text-warm-600" />
            </div>
            <!-- Status badge -->
            <div class="absolute top-2 left-2 flex gap-1">
              <span :class="['badge text-[10px]', roomStatusClass(room.status)]">{{ room.status }}</span>
              <span v-if="!room.is_active" class="badge badge-red text-[10px]">Inactive</span>
            </div>
          </div>

          <!-- Body -->
          <div class="p-4">
            <div class="flex items-start justify-between gap-2 mb-1">
              <h3 class="font-semibold text-sm text-warm-900 dark:text-white leading-tight">
                Room {{ room.room_number }}
              </h3>
              <span class="font-bold text-primary-600 dark:text-primary-400 text-sm shrink-0">
                ${{ Number(room.price_per_night).toFixed(0) }}<span class="text-[10px] font-normal text-warm-400">/night</span>
              </span>
            </div>

            <p class="text-xs text-warm-400 capitalize mb-2 flex items-center gap-2">
              <span class="inline-flex items-center gap-1"><BedDouble :size="11" /> {{ room.type }}</span>
              <span>·</span>
              <span class="inline-flex items-center gap-1"><Users :size="11" /> {{ room.capacity }} guests</span>
            </p>

            <p v-if="room.description" class="text-xs text-warm-500 dark:text-warm-400 line-clamp-2 mb-3">
              {{ room.description }}
            </p>

            <!-- Amenity chips -->
            <div v-if="room.amenities?.length" class="flex flex-wrap gap-1 mb-3">
              <span
                v-for="a in room.amenities.slice(0, 3)"
                :key="a"
                class="text-[10px] bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-300 px-1.5 py-0.5 rounded-md"
              >{{ a }}</span>
              <span v-if="room.amenities.length > 3" class="text-[10px] text-warm-400">+{{ room.amenities.length - 3 }}</span>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-1 pt-2 border-t border-warm-100 dark:border-warm-800">
              <button @click="openEdit(room)" class="flex-1 flex items-center justify-center gap-1.5 text-xs font-medium py-1.5 rounded-lg text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors">
                <Pencil :size="12" /> Edit
              </button>
              <button @click="deleteRoom(room)" class="flex-1 flex items-center justify-center gap-1.5 text-xs font-medium py-1.5 rounded-lg text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                <Trash2 :size="12" /> Remove
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else class="card p-16 text-center">
        <BedDouble :size="48" class="mx-auto text-warm-300 dark:text-warm-600 mb-4" />
        <p class="font-semibold text-warm-700 dark:text-warm-300 mb-1">No rooms yet</p>
        <p class="text-sm text-warm-400 mb-5">Add your first room to start accepting bookings.</p>
        <button @click="openCreate" class="btn-primary mx-auto">
          <Plus :size="16" /> Add First Room
        </button>
      </div>
    </div>

    <!-- ══ BOOKINGS TAB ═══════════════════════════════════════════════════════ -->
    <div v-else class="space-y-4">

      <!-- Bookings filter -->
      <div class="flex flex-wrap items-center gap-3">
        <select v-model="bookingStatusFilter" class="input w-auto text-sm py-2">
          <option value="">All Statuses</option>
          <option v-for="s in ['pending','confirmed','checked_in','checked_out','cancelled']" :key="s" :value="s">
            {{ s.replace('_', ' ') }}
          </option>
        </select>
        <p class="text-xs text-warm-500">{{ bookings().length }} bookings</p>
      </div>

      <!-- Skeleton -->
      <TableSkeleton v-if="bookingsLoading" :rows="5" :cols="6" />

      <!-- Bookings table -->
      <div v-else-if="bookings().length" class="card overflow-x-auto shadow-sm">
        <table class="w-full text-sm text-left">
          <thead class="bg-warm-50 dark:bg-warm-850 text-warm-600 dark:text-warm-400 text-xs uppercase font-semibold">
            <tr>
              <th class="px-5 py-3.5">Guest</th>
              <th class="px-5 py-3.5">Room</th>
              <th class="px-5 py-3.5">Check-In</th>
              <th class="px-5 py-3.5">Check-Out</th>
              <th class="px-5 py-3.5">Total</th>
              <th class="px-5 py-3.5">Status</th>
              <th class="px-5 py-3.5 text-right">Update</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-100 dark:divide-warm-800">
            <tr
              v-for="b in bookings()"
              :key="b.id"
              class="hover:bg-warm-50/70 dark:hover:bg-warm-800/40 transition-colors"
            >
              <td class="px-5 py-3.5">
                <p class="font-medium text-warm-900 dark:text-white">{{ b.guest_name }}</p>
                <p class="text-xs text-warm-400">{{ b.guest_email }}</p>
              </td>
              <td class="px-5 py-3.5">
                <p class="font-semibold text-warm-800 dark:text-warm-200">Room {{ b.room?.room_number ?? '—' }}</p>
                <p class="text-xs text-warm-400 capitalize">{{ b.room?.type ?? '' }}</p>
              </td>
              <td class="px-5 py-3.5 text-warm-600 dark:text-warm-400 whitespace-nowrap">{{ formatDate(b.check_in_date) }}</td>
              <td class="px-5 py-3.5 text-warm-600 dark:text-warm-400 whitespace-nowrap">{{ formatDate(b.check_out_date) }}</td>
              <td class="px-5 py-3.5 font-bold text-primary-600 dark:text-primary-400">
                ${{ Number(b.total_amount ?? b.total_price ?? 0).toFixed(2) }}
              </td>
              <td class="px-5 py-3.5">
                <span :class="['badge text-xs capitalize', statusBadgeClass(b.status)]">
                  {{ b.status?.replace('_', ' ') }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-right">
                <select
                  class="input text-xs py-1.5 px-2 w-auto"
                  :value="b.status"
                  @change="e => updateBookingStatus(b, e.target.value)"
                >
                  <option v-for="s in ['pending','confirmed','checked_in','checked_out','cancelled']" :key="s" :value="s">
                    {{ s.replace('_', ' ') }}
                  </option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Empty bookings -->
      <div v-else class="card p-12 text-center">
        <BedDouble :size="40" class="mx-auto text-warm-300 mb-3" />
        <p class="font-semibold text-warm-700 dark:text-warm-300">No bookings found</p>
        <p class="text-sm text-warm-400 mt-1">Adjust the status filter or check back later.</p>
      </div>
    </div>


    <!-- ══ ADD / EDIT ROOM MODAL ═════════════════════════════════════════════ -->
    <Teleport to="body">
      <Transition
        enter-from-class="opacity-0"
        enter-active-class="transition-opacity duration-200"
        leave-to-class="opacity-0"
        leave-active-class="transition-opacity duration-150"
      >
        <div v-if="showModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4" @click.self="closeModal">
          <div class="card w-full max-w-2xl shadow-float max-h-[92vh] flex flex-col">

            <!-- Header -->
            <div class="flex items-center justify-between p-6 pb-4 border-b border-warm-100 dark:border-warm-800 shrink-0">
              <div>
                <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">
                  {{ editingRoom ? `Edit Room ${editingRoom.room_number}` : 'Add New Room' }}
                </h2>
                <p class="text-xs text-warm-400 mt-0.5">
                  {{ editingRoom ? 'Update room details, image, and amenities' : 'Fill in the details to register a new hotel room' }}
                </p>
              </div>
              <button @click="closeModal" class="btn-ghost p-1.5 rounded-lg shrink-0">
                <X :size="18" />
              </button>
            </div>

            <!-- Body -->
            <div class="overflow-y-auto p-6 space-y-5 flex-1">

              <!-- Image section -->
              <div>
                <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-2">Room Image</label>

                <!-- Mode toggle -->
                <div class="flex gap-1 p-1 bg-warm-100 dark:bg-warm-800 rounded-xl w-fit mb-3">
                  <button
                    @click="switchImageMode('url')"
                    :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all', imageMode === 'url' ? 'bg-white dark:bg-gray-900 shadow text-warm-900 dark:text-white' : 'text-warm-500 hover:text-warm-700 dark:hover:text-warm-300']"
                  >
                    <Link :size="13" /> Image URL
                  </button>
                  <button
                    @click="switchImageMode('file')"
                    :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all', imageMode === 'file' ? 'bg-white dark:bg-gray-900 shadow text-warm-900 dark:text-white' : 'text-warm-500 hover:text-warm-700 dark:hover:text-warm-300']"
                  >
                    <Upload :size="13" /> Upload File
                  </button>
                </div>

                <!-- URL input -->
                <input
                  v-if="imageMode === 'url'"
                  v-model="form.image_url"
                  type="url"
                  class="input text-sm"
                  placeholder="https://example.com/room.jpg"
                  @input="imagePreview = form.image_url"
                />

                <!-- File upload -->
                <div v-else>
                  <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFileChange" />
                  <div
                    @click="triggerFileInput"
                    class="border-2 border-dashed border-warm-300 dark:border-warm-700 rounded-xl p-6 text-center cursor-pointer hover:border-primary-400 hover:bg-primary-50/40 dark:hover:bg-primary-900/10 transition-all duration-200"
                  >
                    <Upload :size="28" class="mx-auto text-warm-400 mb-2" />
                    <p class="text-sm font-medium text-warm-600 dark:text-warm-300">Click to choose a photo</p>
                    <p class="text-xs text-warm-400 mt-1">PNG, JPG, WEBP — max 5 MB</p>
                  </div>
                </div>

                <!-- Preview -->
                <div v-if="imagePreview" class="mt-3 relative inline-block">
                  <img :src="imagePreview" alt="Preview" class="h-36 w-56 object-cover rounded-xl border border-warm-200 dark:border-warm-700 shadow-sm" />
                  <button
                    @click="clearImage"
                    class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-colors shadow-md"
                    title="Remove image"
                  >
                    <X :size="12" />
                  </button>
                </div>
              </div>

              <!-- Room number + type -->
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">
                    Room Number <span class="text-red-500">*</span>
                  </label>
                  <input v-model="form.room_number" type="text" required class="input text-sm" placeholder="e.g. 101, A-12" />
                </div>
                <div>
                  <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">Room Type</label>
                  <select v-model="form.type" class="input text-sm capitalize">
                    <option v-for="t in roomTypes" :key="t" :value="t" class="capitalize">{{ t }}</option>
                  </select>
                </div>
              </div>

              <!-- Capacity + Price -->
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">
                    Guest Capacity
                  </label>
                  <div class="relative">
                    <Users :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
                    <input v-model.number="form.capacity" type="number" min="1" max="20" required class="input pl-8 text-sm" />
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">
                    Price per Night ($) <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <DollarSign :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
                    <input v-model="form.price_per_night" type="number" min="0" step="0.01" required class="input pl-8 text-sm" placeholder="0.00" />
                  </div>
                </div>
              </div>

              <!-- Description -->
              <div>
                <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">Description</label>
                <textarea v-model="form.description" rows="3" class="input text-sm resize-none" placeholder="Describe the room — size, view, special features…"></textarea>
              </div>

              <!-- Amenities -->
              <div>
                <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-2">Amenities</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                  <button
                    v-for="a in amenityList"
                    :key="a"
                    type="button"
                    @click="toggleAmenity(a)"
                    :class="[
                      'flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-medium border transition-all duration-150',
                      form.amenities.includes(a)
                        ? 'bg-primary-500 text-white border-primary-500 shadow-sm'
                        : 'bg-white dark:bg-warm-850 text-warm-600 dark:text-warm-300 border-warm-200 dark:border-warm-700 hover:border-primary-400'
                    ]"
                  >
                    <CheckCircle :size="12" :class="form.amenities.includes(a) ? 'opacity-100' : 'opacity-0'" />
                    {{ a }}
                  </button>
                </div>
              </div>

              <!-- Active toggle -->
              <div class="p-4 bg-warm-50 dark:bg-warm-900/50 rounded-xl border border-warm-100 dark:border-warm-800">
                <label class="flex items-center gap-3 cursor-pointer select-none">
                  <div
                    @click="form.is_active = !form.is_active"
                    :class="['relative w-10 h-5 rounded-full transition-colors duration-200 flex-shrink-0', form.is_active ? 'bg-green-500' : 'bg-warm-300 dark:bg-warm-600']"
                  >
                    <span :class="['absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200', form.is_active ? 'translate-x-5' : 'translate-x-0.5']"></span>
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-warm-800 dark:text-warm-200">Active & Bookable</p>
                    <p class="text-xs text-warm-400">Room will appear in guest booking flow</p>
                  </div>
                </label>
              </div>

            </div>

            <!-- Footer -->
            <div class="p-6 pt-4 border-t border-warm-100 dark:border-warm-800 shrink-0 flex items-center justify-between gap-3">
              <p class="text-xs text-warm-400">
                {{ editingRoom ? 'Changes apply immediately.' : 'Room will be visible to guests after saving.' }}
              </p>
              <div class="flex gap-2">
                <button @click="closeModal" class="btn-outline text-sm py-2 px-4">Cancel</button>
                <button @click="save" :disabled="saving" class="btn-primary text-sm py-2 px-5 shadow-md">
                  {{ saving ? 'Saving…' : editingRoom ? 'Save Changes' : 'Add Room' }}
                </button>
              </div>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>
