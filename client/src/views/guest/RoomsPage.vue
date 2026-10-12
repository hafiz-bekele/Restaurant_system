<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { guestApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import { BedDouble, Users, CalendarCheck, CheckCircle, X } from '@lucide/vue'

const route  = useRoute()
const ui     = useUiStore()

const loading   = ref(false)
const rooms     = ref([])
const searched  = ref(false)
const done      = ref(false)
const booking   = ref(null)
const saving    = ref(false)
const showModal = ref(false)
const selected  = ref(null)

const todayStr = new Date().toISOString().split('T')[0]
const tomorrowStr = new Date(Date.now() + 86400000).toISOString().split('T')[0]

const form = ref({
  hotel_id:       Number(route.query.hotel_id) || 1,
  check_in_date:  todayStr,
  check_out_date: tomorrowStr,
  capacity:       2,
})

const guestForm = ref({ guest_name: '', guest_email: '', guest_phone: '', notes: '' })

const nights = computed(() => {
  if (!form.value.check_in_date || !form.value.check_out_date) return 0
  return Math.max(0, Math.ceil(
    (new Date(form.value.check_out_date) - new Date(form.value.check_in_date)) / 86400000
  ))
})

async function search() {
  loading.value = true
  searched.value = false
  try {
    const { data } = await guestApi.availableRooms(form.value)
    rooms.value = data.data
    searched.value = true
  } catch (e) {
    const msg = e.response?.data?.message ?? 'Failed to search rooms'
    ui.toast(msg, 'error')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  search()
})

function openBooking(room) {
  selected.value = room
  guestForm.value = { guest_name: '', guest_email: '', guest_phone: '', notes: '' }
  showModal.value = true
}

async function confirmBooking() {
  saving.value = true
  try {
    const { data } = await guestApi.bookRoom({
      hotel_id:       form.value.hotel_id,
      room_id:        selected.value.id,
      check_in_date:  form.value.check_in_date,
      check_out_date: form.value.check_out_date,
      ...guestForm.value,
    })
    booking.value  = data.data
    done.value     = true
    showModal.value = false
    ui.toast('Booking confirmed!', 'success')
  } catch (e) {
    ui.toast(e.response?.data?.message ?? 'Failed to book room', 'error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-10 max-w-4xl">
      <h1 class="font-heading text-4xl font-bold text-warm-900 dark:text-white mb-2">Book a Room</h1>
      <p class="text-warm-500 dark:text-warm-400 mb-8">Check availability and reserve your perfect stay.</p>

      <!-- Booking confirmed -->
      <div v-if="done" class="card p-10 text-center space-y-4">
        <CheckCircle :size="52" class="mx-auto text-green-500" />
        <h2 class="font-heading text-2xl font-bold text-warm-900 dark:text-white">Booking Confirmed!</h2>
        <p class="text-warm-500">Room: <strong class="text-warm-800 dark:text-white">{{ booking?.room?.room_number }}</strong></p>
        <p class="text-warm-500">Check-in: <strong class="text-warm-800 dark:text-white">{{ booking?.check_in_date }}</strong></p>
        <p class="text-warm-500">Check-out: <strong class="text-warm-800 dark:text-white">{{ booking?.check_out_date }}</strong></p>
        <p class="text-warm-500">Total: <strong class="text-primary-600 dark:text-primary-400 text-lg">${{ Number(booking?.total_amount).toFixed(2) }}</strong></p>
        <button @click="done = false; rooms = []; searched = false" class="btn-outline">Search Again</button>
      </div>

      <!-- Search form -->
      <template v-else>
        <div class="card p-6 mb-8">
          <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Check-in</label>
              <input v-model="form.check_in_date" type="date" required class="input" />
            </div>
            <div>
              <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Check-out</label>
              <input v-model="form.check_out_date" type="date" required class="input" />
            </div>
            <div>
              <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Guests</label>
              <input v-model.number="form.capacity" type="number" min="1" max="10" class="input" />
            </div>
            <div class="flex items-end">
              <button @click="search" :disabled="loading || !form.check_in_date || !form.check_out_date" class="btn-primary w-full justify-center">
                {{ loading ? 'Searching…' : 'Search' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Results -->
        <div v-if="searched">
          <p v-if="!rooms.length" class="text-center py-12 text-warm-400">No rooms available for the selected dates.</p>
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="room in rooms" :key="room.id" class="card overflow-hidden hover:shadow-float transition-all hover:-translate-y-1">
              <img v-if="room.image" :src="room.image" :alt="`Room ${room.room_number}`" class="w-full h-44 object-cover" />
              <div v-else class="w-full h-44 bg-warm-100 dark:bg-warm-800 flex items-center justify-center text-warm-400">
                <BedDouble :size="40" />
              </div>
              <div class="p-4 space-y-2">
                <div class="flex items-center justify-between">
                  <h3 class="font-heading font-bold text-warm-900 dark:text-white">Room {{ room.room_number }}</h3>
                  <span class="badge badge-blue capitalize">{{ room.type }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs text-warm-400">
                  <Users :size="13" /> {{ room.capacity }} guests
                </div>
                <div class="flex items-center justify-between pt-2">
                  <div>
                    <p class="text-xs text-warm-400">per night</p>
                    <p class="font-heading font-bold text-lg text-primary-600 dark:text-primary-400">${{ Number(room.price_per_night).toFixed(2) }}</p>
                  </div>
                  <div v-if="nights > 0" class="text-right">
                    <p class="text-xs text-warm-400">{{ nights }} nights</p>
                    <p class="font-semibold text-warm-900 dark:text-white">${{ (Number(room.price_per_night) * nights).toFixed(2) }}</p>
                  </div>
                </div>
                <button @click="openBooking(room)" class="btn-primary w-full justify-center gap-2 mt-1">
                  <CalendarCheck :size="16" /> Book Now
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Guest details modal -->
    <Teleport to="body">
      <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-200" leave-to-class="opacity-0" leave-active-class="transition-opacity duration-150">
        <div v-if="showModal" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="showModal = false">
          <div class="card w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-5">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">Complete Booking</h2>
              <button @click="showModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <p class="text-sm text-warm-500 mb-4">Room <strong class="text-warm-800 dark:text-white">{{ selected?.room_number }}</strong> · {{ nights }} nights · <strong class="text-primary-600 dark:text-primary-400">${{ (Number(selected?.price_per_night ?? 0) * nights).toFixed(2) }}</strong></p>
            <form @submit.prevent="confirmBooking" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Full Name</label>
                <input v-model="guestForm.guest_name" type="text" required class="input" placeholder="John Doe" />
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Email</label>
                <input v-model="guestForm.guest_email" type="email" required class="input" placeholder="you@example.com" />
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Phone</label>
                <input v-model="guestForm.guest_phone" type="tel" required class="input" placeholder="+251 911 000 000" />
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Special Requests</label>
                <textarea v-model="guestForm.notes" rows="2" class="input resize-none" placeholder="Late check-in, accessibility needs…"></textarea>
              </div>
              <div class="flex gap-3 pt-2">
                <button type="button" @click="showModal = false" class="btn-outline flex-1">Cancel</button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1">{{ saving ? 'Booking…' : 'Confirm Booking' }}</button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </DefaultLayout>
</template>
