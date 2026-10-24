import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { reservationApi } from '../services/api'

export interface TableOption {
  id: number
  name: string
  area: 'indoor' | 'outdoor' | 'window' | 'vip' | 'quiet' | string
  capacity: number
  features: string[]
  is_active: boolean
  status: string
  position_x?: number
  position_y?: number
}

export interface PricingConfig {
  base_rate_per_hour: number
  durations: Array<{ duration_hours: number; price: string | number }>
  day_surcharges: Array<{ days_ahead: number; percent: string | number }>
  currency: string
  max_duration_hours: number
  max_advance_days: number
}

export interface ReservationData {
  id: number
  ulid: string
  customer_name: string
  name: string
  phone: string
  email: string
  party_size: number
  start_at: string
  end_at: string
  duration_hours: number
  status: string
  hold_expires_at?: string
  price_base: number
  surcharge_percent: number
  surcharge_amount: number
  total_price: number
  currency: string
  special_requests?: string
  paid_at?: string
  remaining_seconds?: number
  seconds_until_start?: number
  is_paid?: boolean
  is_active_window?: boolean
  server_time?: string
  table?: TableOption
  tx_ref?: string
}

export const useReservationStore = defineStore('reservation', () => {
  const step = ref<number>(1)

  // Default date to today, start_time to 1 hour from now
  const now = new Date()
  const todayStr = now.toISOString().split('T')[0]
  const defaultHour = String((now.getHours() + 1) % 24).padStart(2, '0')
  const defaultTime = `${defaultHour}:00`

  const form = ref({
    customer_name: '',
    phone: '',
    email: '',
    party_size: 2,
    date: todayStr,
    start_time: defaultTime,
    duration_hours: 2,
    table_id: null as number | null,
    special_requests: '',
  })

  const pricingConfig = ref<PricingConfig>({
    base_rate_per_hour: 0,
    durations: [
      { duration_hours: 1, price: 0 },
      { duration_hours: 2, price: 0 },
      { duration_hours: 3, price: 0 },
      { duration_hours: 4, price: 0 },
    ],
    day_surcharges: [],
    currency: 'ETB',
    max_duration_hours: 4,
    max_advance_days: 14,
  })

  const availableTables = ref<TableOption[]>([])
  const selectedTable = ref<TableOption | null>(null)
  const currentReservation = ref<ReservationData | null>(null)
  const txRef = ref<string | null>(null)
  const checkoutUrl = ref<string | null>(null)

  const isLoadingPricing = ref(false)
  const isLoadingTables  = ref(false)
  const isSubmitting     = ref(false)
  const isVerifying      = ref(false)
  const serverTimeOffset = ref<number>(0) // in milliseconds

  // ── Computeds ─────────────────────────────────────────────────────────────

  const daysAhead = computed(() => {
    if (!form.value.date) return 0
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    const [y, m, d] = form.value.date.split('-').map(Number)
    const selected = new Date(y, m - 1, d)
    selected.setHours(0, 0, 0, 0)
    const diffDays = Math.round((selected.getTime() - today.getTime()) / (1000 * 60 * 60 * 24))
    return Math.max(0, diffDays)
  })

  // Table reservations are 100% complimentary
  const basePrice = computed(() => 0)
  const surchargePercent = computed(() => 0)
  const surchargeAmount = computed(() => 0)
  const totalPrice = computed(() => 0)

  const formattedEndTime = computed(() => {
    if (!form.value.start_time) return ''
    const [h, m] = form.value.start_time.split(':').map(Number)
    const startMins = h * 60 + m
    const endMins   = startMins + form.value.duration_hours * 60
    const endH      = Math.floor(endMins / 60) % 24
    const endM      = endMins % 60
    return `${String(endH).padStart(2, '0')}:${String(endM).padStart(2, '0')}`
  })

  const canProceedToStep2 = computed(() => {
    return (
      form.value.customer_name.trim().length >= 2 &&
      form.value.email.includes('@') &&
      form.value.phone.trim().length >= 9 &&
      form.value.party_size >= 1 &&
      form.value.party_size <= 20 &&
      !!form.value.date &&
      !!form.value.start_time &&
      form.value.duration_hours >= 1
    )
  })

  const canProceedToStep3 = computed(() => {
    return canProceedToStep2.value && !!form.value.table_id && !!selectedTable.value
  })

  // ── Actions ───────────────────────────────────────────────────────────────

  async function fetchPricingConfig() {
    isLoadingPricing.value = true
    try {
      const res = await reservationApi.pricing()
      if (res.data?.data) {
        pricingConfig.value = res.data.data
      }
    } catch (e) {
      console.warn('Failed to fetch pricing config, using seeded defaults', e)
    } finally {
      isLoadingPricing.value = false
    }
  }

  async function fetchAvailableTables() {
    isLoadingTables.value = true
    try {
      const res = await reservationApi.availableTables({
        date: form.value.date,
        start_time: form.value.start_time,
        duration_hours: form.value.duration_hours,
        party_size: form.value.party_size,
      })
      availableTables.value = res.data?.data ?? []

      // If previously selected table is no longer available in this window, reset it
      if (form.value.table_id && !availableTables.value.some(t => t.id === form.value.table_id)) {
        form.value.table_id = null
        selectedTable.value = null
      }
      return availableTables.value
    } finally {
      isLoadingTables.value = false
    }
  }

  function selectTable(table: TableOption) {
    form.value.table_id = table.id
    selectedTable.value = table
  }

  async function submitReservation() {
    if (!selectedTable.value || !form.value.table_id) {
      throw new Error('Please choose an available table before payment.')
    }

    isSubmitting.value = true
    try {
      const payload = {
        customer_name:    form.value.customer_name,
        name:             form.value.customer_name,
        email:            form.value.email,
        phone:            form.value.phone,
        party_size:       Number(form.value.party_size),
        date:             form.value.date,
        start_time:       form.value.start_time,
        duration_hours:   Number(form.value.duration_hours),
        table_id:         form.value.table_id,
        special_requests: form.value.special_requests,
      }

      const res = await reservationApi.create(payload)
      const data = res.data.data

      currentReservation.value = data.reservation
      checkoutUrl.value        = data.checkout_url
      txRef.value              = data.tx_ref

      // Sync server time offset
      if (data.reservation?.server_time) {
        const sTime = new Date(data.reservation.server_time).getTime()
        serverTimeOffset.value = sTime - Date.now()
      }

      return data
    } finally {
      isSubmitting.value = false
    }
  }

  async function verifyPayment(ulid: string) {
    isVerifying.value = true
    try {
      const res = await reservationApi.verifyPayment(ulid)
      const data = res.data.data
      currentReservation.value = data.reservation

      if (data.reservation?.server_time) {
        const sTime = new Date(data.reservation.server_time).getTime()
        serverTimeOffset.value = sTime - Date.now()
      }

      return data
    } finally {
      isVerifying.value = false
    }
  }

  async function fetchReservation(ulid: string) {
    const res = await reservationApi.get(ulid)
    const data = res.data.data
    currentReservation.value = data

    if (data?.server_time) {
      const sTime = new Date(data.server_time).getTime()
      serverTimeOffset.value = sTime - Date.now()
    }

    return data
  }

  function goToStep(s: number) {
    if (s === 2 && !canProceedToStep2.value) return
    if (s === 3 && !canProceedToStep3.value) return
    step.value = s
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  function resetWizard() {
    step.value = 1
    form.value.table_id = null
    selectedTable.value = null
    currentReservation.value = null
    txRef.value = null
    checkoutUrl.value = null
  }

  return {
    step,
    form,
    pricingConfig,
    availableTables,
    selectedTable,
    currentReservation,
    txRef,
    checkoutUrl,
    isLoadingPricing,
    isLoadingTables,
    isSubmitting,
    isVerifying,
    serverTimeOffset,
    daysAhead,
    basePrice,
    surchargePercent,
    surchargeAmount,
    totalPrice,
    formattedEndTime,
    canProceedToStep2,
    canProceedToStep3,
    fetchPricingConfig,
    fetchAvailableTables,
    selectTable,
    submitReservation,
    verifyPayment,
    fetchReservation,
    goToStep,
    resetWizard,
  }
}, {
  persist: {
    pick: ['step', 'form', 'selectedTable', 'currentReservation', 'txRef', 'checkoutUrl'],
  },
})
