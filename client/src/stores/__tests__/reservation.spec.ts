import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useReservationStore, type TableOption } from '../reservation'

// Mock api service to prevent network calls in unit tests
vi.mock('../../services/api', () => ({
  reservationApi: {
    pricing: vi.fn().mockResolvedValue({
      data: {
        data: {
          base_rate_per_hour: 12.5,
          durations: [
            { duration_hours: 1, price: 12.50 },
            { duration_hours: 2, price: 25.00 },
            { duration_hours: 3, price: 37.50 },
            { duration_hours: 6, price: 75.00 },
            { duration_hours: 12, price: 150.00 },
            { duration_hours: 18, price: 225.00 },
            { duration_hours: 24, price: 300.00 },
          ],
          day_surcharges: [
            { days_ahead: 0, percent: 0.00 },
            { days_ahead: 1, percent: 25.00 },
            { days_ahead: 2, percent: 40.00 },
          ],
          currency: 'ETB',
          max_duration_hours: 24,
          max_advance_days: 7,
        },
      },
    }),
    availableTables: vi.fn().mockResolvedValue({
      data: {
        data: [
          {
            id: 1,
            name: 'Table 1',
            area: 'indoor',
            capacity: 4,
            features: ['High Chair', 'Air Conditioned'],
            is_active: true,
            status: 'available',
          },
        ],
      },
    }),
    create: vi.fn(),
    verifyPayment: vi.fn(),
    get: vi.fn(),
  },
}))

describe('Reservation Store & Pricing Engine', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('initializes with step 1 and sensible defaults', () => {
    const store = useReservationStore()
    expect(store.step).toBe(1)
    expect(store.form.duration_hours).toBe(2)
    expect(store.form.party_size).toBe(2)
    expect(store.form.table_id).toBeNull()
    expect(store.selectedTable).toBeNull()
  })

  it('provides complimentary table reservations without hourly fees', () => {
    const store = useReservationStore()

    const realisticDurations = [1, 2, 3, 4]
    for (const hours of realisticDurations) {
      store.form.duration_hours = hours
      expect(store.basePrice).toBe(0)
      expect(store.totalPrice).toBe(0)
    }
  })

  it('does not penalize guests with surcharges for advance bookings', () => {
    const store = useReservationStore()
    const today = new Date()

    const pad = (n: number) => String(n).padStart(2, '0')
    const toIso = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`

    store.form.date = toIso(today)
    store.form.duration_hours = 2
    expect(store.daysAhead).toBe(0)
    expect(store.surchargePercent).toBe(0)
    expect(store.surchargeAmount).toBe(0)
    expect(store.totalPrice).toBe(0)

    const tomorrow = new Date(today)
    tomorrow.setDate(today.getDate() + 1)
    store.form.date = toIso(tomorrow)
    expect(store.daysAhead).toBe(1)
    expect(store.surchargePercent).toBe(0)
    expect(store.totalPrice).toBe(0)
  })

  it('correctly calculates end time from start time and duration', () => {
    const store = useReservationStore()
    store.form.start_time = '18:30'
    store.form.duration_hours = 3
    expect(store.formattedEndTime).toBe('21:30')

    store.form.start_time = '23:00'
    store.form.duration_hours = 2
    expect(store.formattedEndTime).toBe('01:00')
  })

  it('enforces validation guards so customer cannot proceed without table selection', () => {
    const store = useReservationStore()

    // Missing fields in step 1
    store.form.customer_name = ''
    store.form.email = 'invalid'
    store.form.phone = '123'
    expect(store.canProceedToStep2).toBe(false)
    expect(store.canProceedToStep3).toBe(false)

    // Complete valid step 1 details
    store.form.customer_name = 'Almaz Ayana'
    store.form.email = 'almaz@example.com'
    store.form.phone = '+251 91 122 3344'
    store.form.party_size = 4
    store.form.date = '2026-10-25'
    store.form.start_time = '19:00'
    store.form.duration_hours = 2
    expect(store.canProceedToStep2).toBe(true)

    // Cannot proceed to step 3 because no table is selected yet
    expect(store.canProceedToStep3).toBe(false)

    // Select a table
    const mockTable: TableOption = {
      id: 5,
      name: 'Window Table 5',
      area: 'window',
      capacity: 4,
      features: ['City View'],
      is_active: true,
      status: 'available',
    }
    store.selectTable(mockTable)

    expect(store.form.table_id).toBe(5)
    expect(store.selectedTable).toEqual(mockTable)
    expect(store.canProceedToStep3).toBe(true)
  })

  it('throws error when submitting reservation without selecting a table', async () => {
    const store = useReservationStore()
    store.form.table_id = null
    store.selectedTable = null

    await expect(store.submitReservation()).rejects.toThrow(
      'Please choose an available table before payment.'
    )
  })

  it('resets wizard state properly', () => {
    const store = useReservationStore()
    store.step = 3
    store.form.table_id = 1
    store.selectedTable = { id: 1, name: 'T1', area: 'indoor', capacity: 2, features: [], is_active: true, status: 'available' }

    store.resetWizard()

    expect(store.step).toBe(1)
    expect(store.form.table_id).toBeNull()
    expect(store.selectedTable).toBeNull()
    expect(store.currentReservation).toBeNull()
  })
})
