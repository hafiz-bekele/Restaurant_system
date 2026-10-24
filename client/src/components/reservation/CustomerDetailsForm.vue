<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useForm, useField } from 'vee-validate'
import { z } from 'zod'
import { User, Phone, Mail, Users, Calendar, Clock, AlertCircle, ArrowRight, Sparkles } from '@lucide/vue'
import DurationSelector from './DurationSelector.vue'
import { useReservationStore } from '../../stores/reservation'

const store = useReservationStore()

const emit = defineEmits<{
  (e: 'submit'): void
}>()

// ── Validation Schema (Zod) ────────────────────────────────────────────────
const ethiopianPhoneRegex = /^(\+251\s?[79]\d{8}|0[79]\d{8})$/

const rawSchema = z.object({
  customer_name: z
    .string()
    .min(2, 'Full name must be at least 2 characters')
    .max(100, 'Name cannot exceed 100 characters'),
  phone: z
    .string()
    .regex(ethiopianPhoneRegex, 'Enter valid Ethiopian phone (+251 9XXXXXXXX or 07XXXXXXXX)'),
  email: z
    .string()
    .email('Enter a valid email address')
    .max(255),
  party_size: z
    .number()
    .min(1, 'Minimum 1 guest')
    .max(20, 'Maximum 20 guests per reservation'),
  date: z.string().min(1, 'Please select a date'),
  start_time: z.string().min(1, 'Please select a time'),
  duration_hours: z
    .number()
    .min(1, 'Minimum duration is 1 hour')
    .max(4, 'Maximum duration is 4 hours'),
  special_requests: z.string().max(500, 'Max 500 characters').optional(),
}).refine((data) => {
  // Validate 15-minute lead time for today
  const now = new Date()
  const todayStr = now.toISOString().split('T')[0]
  if (data.date === todayStr && data.start_time) {
    const [h, m] = data.start_time.split(':').map(Number)
    const selectedDate = new Date()
    selectedDate.setHours(h, m, 0, 0)
    const minAllowed = new Date(now.getTime() + 14 * 60 * 1000)
    return selectedDate >= minAllowed
  }
  return true
}, {
  message: 'Start time must be at least 15 minutes from now for same-day reservations',
  path: ['start_time'],
})

// Custom VeeValidate adapter for Zod schema
function toTypedSchema<T extends z.ZodTypeAny>(schema: T) {
  return {
    __type: 'custom' as const,
    async parse(values: any) {
      const result = await schema.safeParseAsync(values)
      if (result.success) {
        return { value: result.data, errors: [] }
      }
      return {
        errors: result.error.issues.map((issue) => ({
          path: issue.path.join('.'),
          errors: [issue.message],
        })),
      }
    },
  }
}

const validationSchema = toTypedSchema(rawSchema)


const { handleSubmit, errors, setFieldValue, values } = useForm({
  validationSchema,
  initialValues: {
    customer_name:    store.form.customer_name,
    phone:            store.form.phone,
    email:            store.form.email,
    party_size:       store.form.party_size,
    date:             store.form.date,
    start_time:       store.form.start_time,
    duration_hours:   store.form.duration_hours,
    special_requests: store.form.special_requests,
  },
})

const { value: customerName } = useField<string>('customer_name')
const { value: phone } = useField<string>('phone')
const { value: email } = useField<string>('email')
const { value: partySize } = useField<number>('party_size')
const { value: selectedDate } = useField<string>('date')
const { value: selectedTime } = useField<string>('start_time')
const { value: durationHours } = useField<number>('duration_hours')
const { value: specialRequests } = useField<string>('special_requests')

// Sync with store
watch(values, (newVals) => {
  store.form.customer_name    = newVals.customer_name ?? ''
  store.form.phone            = newVals.phone ?? ''
  store.form.email            = newVals.email ?? ''
  store.form.party_size       = Number(newVals.party_size) || 2
  store.form.date             = newVals.date ?? ''
  store.form.start_time       = newVals.start_time ?? ''
  store.form.duration_hours   = Number(newVals.duration_hours) || 2
  store.form.special_requests = newVals.special_requests ?? ''
}, { deep: true })

// ── 7-Day Date Selector Chips ──────────────────────────────────────────────
const nextSevenDays = computed(() => {
  const days = []
  const today = new Date()
  for (let i = 0; i <= 7; i++) {
    const d = new Date(today)
    d.setDate(today.getDate() + i)
    const yyyy = d.getFullYear()
    const mm = String(d.getMonth() + 1).padStart(2, '0')
    const dd = String(d.getDate()).padStart(2, '0')
    const iso = `${yyyy}-${mm}-${dd}`

    const weekday = i === 0 ? 'Today' : i === 1 ? 'Tomorrow' : d.toLocaleDateString(undefined, { weekday: 'short' })
    const month = d.toLocaleDateString(undefined, { month: 'short' })
    const dayNum = d.getDate()

    days.push({ iso, weekday, month, dayNum, daysAhead: i })
  }
  return days
})

function selectDay(iso: string) {
  setFieldValue('date', iso)
}

// ── Time Slots Generator ───────────────────────────────────────────────────
const timeSlots = [
  '11:30', '12:00', '12:30', '13:00', '13:30', '14:00',
  '17:00', '17:30', '18:00', '18:30', '19:00', '19:30',
  '20:00', '20:30', '21:00', '21:30', '22:00', '22:30'
]

function isTimeSlotPast(timeStr: string): boolean {
  const now = new Date()
  const todayStr = now.toISOString().split('T')[0]
  if (selectedDate.value !== todayStr) return false

  const [h, m] = timeStr.split(':').map(Number)
  const slotDate = new Date()
  slotDate.setHours(h, m, 0, 0)
  const minAllowed = new Date(now.getTime() + 14 * 60 * 1000)
  return slotDate < minAllowed
}

function selectTime(t: string) {
  if (isTimeSlotPast(t)) return
  setFieldValue('start_time', t)
}

// ── Party Size Helpers ─────────────────────────────────────────────────────
const partyPresets = [1, 2, 4, 6, 8, 10, 12, 16, 20]

function setParty(size: number) {
  setFieldValue('party_size', size)
}

const onSubmit = handleSubmit(async () => {
  emit('submit')
})
</script>

<template>
  <form @submit.prevent="onSubmit" class="space-y-6">
    <!-- Section 1: Customer Contact -->
    <div class="card p-6 border border-warm-100 dark:border-warm-800 space-y-4">
      <div class="flex items-center gap-2 pb-2 border-b border-warm-100 dark:border-warm-800">
        <User :size="18" class="text-primary-500" />
        <h2 class="font-heading font-bold text-base text-warm-900 dark:text-white">
          Guest Information
        </h2>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Full Name -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
            Full Name *
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-warm-400">
              <User :size="16" />
            </span>
            <input
              v-model="customerName"
              type="text"
              placeholder="e.g. Abebe Bikila"
              class="input pl-10"
              :class="errors.customer_name ? 'border-red-500 focus:ring-red-400' : ''"
            />
          </div>
          <p v-if="errors.customer_name" class="text-xs text-red-500 mt-1 flex items-center gap-1">
            <AlertCircle :size="12" /> {{ errors.customer_name }}
          </p>
        </div>

        <!-- Phone Number -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
            Phone Number *
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-warm-400">
              <Phone :size="16" />
            </span>
            <input
              v-model="phone"
              type="tel"
              placeholder="+251 9XXXXXXXX or 07XXXXXXXX"
              class="input pl-10 font-mono"
              :class="errors.phone ? 'border-red-500 focus:ring-red-400' : ''"
            />
          </div>
          <p v-if="errors.phone" class="text-xs text-red-500 mt-1 flex items-center gap-1">
            <AlertCircle :size="12" /> {{ errors.phone }}
          </p>
          <p v-else class="text-[11px] text-warm-400 mt-1">SMS confirmation sent to this number</p>
        </div>

        <!-- Email Address -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
            Email Address *
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-warm-400">
              <Mail :size="16" />
            </span>
            <input
              v-model="email"
              type="email"
              placeholder="diner@example.com"
              class="input pl-10"
              :class="errors.email ? 'border-red-500 focus:ring-red-400' : ''"
            />
          </div>
          <p v-if="errors.email" class="text-xs text-red-500 mt-1 flex items-center gap-1">
            <AlertCircle :size="12" /> {{ errors.email }}
          </p>
        </div>
      </div>
    </div>

    <!-- Section 2: Party Size -->
    <div class="card p-6 border border-warm-100 dark:border-warm-800 space-y-4">
      <div class="flex items-center justify-between pb-2 border-b border-warm-100 dark:border-warm-800">
        <div class="flex items-center gap-2">
          <Users :size="18" class="text-primary-500" />
          <h2 class="font-heading font-bold text-base text-warm-900 dark:text-white">
            Party Size (Guests)
          </h2>
        </div>
        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-primary-100 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400 font-mono">
          {{ partySize }} {{ partySize === 1 ? 'Guest' : 'Guests' }}
        </span>
      </div>

      <div>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="p in partyPresets"
            :key="p"
            type="button"
            @click="setParty(p)"
            class="px-4 py-2 rounded-xl text-xs font-bold transition-all"
            :class="[
              partySize === p
                ? 'bg-primary-500 text-white shadow-md shadow-primary-500/25 ring-2 ring-primary-500 ring-offset-1 dark:ring-offset-warm-900'
                : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-warm-200 dark:hover:bg-warm-700'
            ]"
          >
            {{ p }} {{ p === 1 ? 'Guest' : 'Guests' }}
          </button>
        </div>
        <p v-if="errors.party_size" class="text-xs text-red-500 mt-1 flex items-center gap-1">
          <AlertCircle :size="12" /> {{ errors.party_size }}
        </p>
      </div>
    </div>

    <!-- Section 3: Clean Date & Time Selection -->
    <div class="card p-6 border border-warm-100 dark:border-warm-800 space-y-5">
      <div class="flex items-center justify-between pb-2 border-b border-warm-100 dark:border-warm-800">
        <div class="flex items-center gap-2">
          <Calendar :size="18" class="text-primary-500" />
          <h2 class="font-heading font-bold text-base text-warm-900 dark:text-white">
            Reservation Date & Start Time
          </h2>
        </div>
        <span class="text-xs text-primary-600 dark:text-primary-400 font-medium">Instant Confirmation</span>
      </div>

      <!-- 7-Day Date Chips -->
      <div class="space-y-2">
        <label class="block text-xs font-bold uppercase tracking-wider text-warm-600 dark:text-warm-300">
          Select Date
        </label>
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2">
          <button
            v-for="d in nextSevenDays"
            :key="d.iso"
            type="button"
            @click="selectDay(d.iso)"
            class="p-2.5 rounded-2xl border text-center transition-all flex flex-col items-center justify-center relative"
            :class="[
              selectedDate === d.iso
                ? 'border-primary-500 bg-primary-50/60 dark:bg-primary-950/20 shadow-sm ring-1 ring-primary-500'
                : 'border-warm-200 dark:border-warm-800 bg-white dark:bg-warm-900 hover:border-warm-300 dark:hover:border-warm-700'
            ]"
          >
            <span class="text-[11px] font-medium" :class="selectedDate === d.iso ? 'text-primary-600 dark:text-primary-400 font-bold' : 'text-warm-500'">
              {{ d.weekday }}
            </span>
            <span class="text-lg font-heading font-bold text-warm-900 dark:text-white my-0.5">
              {{ d.dayNum }}
            </span>
            <span class="text-[10px] text-warm-400 uppercase">
              {{ d.month }}
            </span>
            <span
              class="mt-1 text-[9px] font-bold px-1.5 py-0.2 rounded-full font-mono"
              :class="d.daysAhead === 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400' : 'bg-warm-100 text-warm-600 dark:bg-warm-800 dark:text-warm-300'"
            >
              {{ d.daysAhead === 0 ? 'Today' : d.daysAhead === 1 ? 'Tomorrow' : 'Open' }}
            </span>
          </button>
        </div>
        <p v-if="errors.date" class="text-xs text-red-500 mt-1 flex items-center gap-1">
          <AlertCircle :size="12" /> {{ errors.date }}
        </p>
      </div>

      <!-- Time Slots Grid -->
      <div class="space-y-2">
        <label class="block text-xs font-bold uppercase tracking-wider text-warm-600 dark:text-warm-300 flex items-center justify-between">
          <span>Select Start Time</span>
          <span class="text-[11px] text-warm-400 font-normal">Service Hours: 11:30 - 23:00</span>
        </label>
        <div class="grid grid-cols-3 sm:grid-cols-6 lg:grid-cols-9 gap-2">
          <button
            v-for="timeStr in timeSlots"
            :key="timeStr"
            type="button"
            :disabled="isTimeSlotPast(timeStr)"
            @click="selectTime(timeStr)"
            class="py-2 px-1 rounded-xl border text-center text-xs font-mono font-bold transition-all"
            :class="[
              isTimeSlotPast(timeStr)
                ? 'opacity-35 cursor-not-allowed bg-warm-100 dark:bg-warm-800/40 border-dashed border-warm-200 dark:border-warm-800 text-warm-400'
                : selectedTime === timeStr
                  ? 'bg-primary-500 border-primary-500 text-white shadow-sm ring-2 ring-primary-400 ring-offset-1 dark:ring-offset-warm-900'
                  : 'border-warm-200 dark:border-warm-800 bg-white dark:bg-warm-900 text-warm-800 dark:text-warm-200 hover:border-primary-400'
            ]"
          >
            {{ timeStr }}
          </button>
        </div>
        <p v-if="errors.start_time" class="text-xs text-red-500 mt-1 flex items-center gap-1">
          <AlertCircle :size="12" /> {{ errors.start_time }}
        </p>
      </div>
    </div>

    <!-- Section 4: Duration Selector -->
    <div class="card p-6 border border-warm-100 dark:border-warm-800">
      <DurationSelector
        v-model="durationHours"
        :durations="store.pricingConfig.durations"
        :surcharge-percent="store.surchargePercent"
        :currency="store.pricingConfig.currency"
      />
      <p v-if="errors.duration_hours" class="text-xs text-red-500 mt-1 flex items-center gap-1">
        <AlertCircle :size="12" /> {{ errors.duration_hours }}
      </p>
    </div>

    <!-- Section 5: Special Requests -->
    <div class="card p-6 border border-warm-100 dark:border-warm-800 space-y-2">
      <label class="block text-xs font-bold uppercase tracking-wider text-warm-600 dark:text-warm-300">
        Special Requests & Dietary Notes (Optional)
      </label>
      <textarea
        v-model="specialRequests"
        rows="2"
        placeholder="e.g. Anniversary dinner, high chair needed, dietary allergies or prefer a corner booth..."
        class="input resize-none text-sm"
      ></textarea>
    </div>

    <!-- Continue Button -->
    <div class="flex justify-end pt-2">
      <button
        type="submit"
        :disabled="store.isLoadingTables"
        class="btn-primary gap-2 text-base px-8 py-3.5 shadow-lg shadow-primary-500/25"
      >
        <span>Continue to Table Selection</span>
        <ArrowRight :size="18" />
      </button>
    </div>
  </form>
</template>
