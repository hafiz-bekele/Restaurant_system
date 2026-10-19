<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { staffApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { useAuthStore } from '../../stores/auth'
import {
  CreditCard, DollarSign, Printer, CheckCircle, RefreshCw, AlertCircle,
  ShoppingBag, BedDouble, Receipt, Plus, Minus, Trash2, Search, X,
  Clock, User, Phone, Tag, Sparkles, ShieldCheck, ArrowRight,
  Lock, Unlock, TrendingUp, Wallet, Smartphone, Split, FileText,
  Filter, SlidersHorizontal, UtensilsCrossed, Truck, Calendar,
  Building2, ArrowUpDown, RotateCcw, Layers, ChevronDown, Globe, CalendarCheck
} from '@lucide/vue'
import TableSkeleton from '../../components/ui/TableSkeleton.vue'
import { APP_NAME } from '../../config'

const { t } = useI18n()
const ui = useUiStore()
const auth = useAuthStore()

// ── State ─────────────────────────────────────────────────────────────────
const loading = ref(true)
const activeTab = ref('orders') // 'orders', 'pos', 'shifts'
const orders = ref([])
const categories = ref([])
const tables = ref([])
const shiftsHistory = ref([])

const currentShiftData = ref({
  has_active_shift: false,
  shift: null,
  summary: null,
})

const summary = ref({
  orders_count: 0,
  pending_count: 0,
  pending_total: 0,
  cash_total: 0,
  telebirr_total: 0,
  cbe_total: 0,
  card_total: 0,
  digital_total: 0,
  room_charge_total: 0,
  grand_total: 0,
  has_active_shift: false,
})

// ── Orders Multi-Dimensional Filter & Search ──────────────────────────────
const statusFilter = ref('all') // 'all', 'pending', 'paid', 'refunded'
const typeFilter = ref('all') // 'all', 'dine_in', 'pickup', 'delivery', 'room_service'
const methodFilter = ref('all') // 'all', 'cash', 'telebirr', 'cbe_birr', 'card', 'room_charge'
const dateFilter = ref('all') // 'all', 'today', 'custom'
const customDate = ref('')
const sortBy = ref('newest') // 'newest', 'oldest', 'amount_desc', 'amount_asc'
const searchQuery = ref('')
const showAdvancedFilters = ref(false)

const unpaidOrdersCount = computed(() => orders.value.filter(o => o.payment_status === 'pending').length)
const paidOrdersCount = computed(() => orders.value.filter(o => o.payment_status === 'paid').length)
const refundedOrdersCount = computed(() => orders.value.filter(o => o.payment_status === 'refunded').length)

const hasActiveFilters = computed(() => {
  return statusFilter.value !== 'all' ||
    typeFilter.value !== 'all' ||
    methodFilter.value !== 'all' ||
    dateFilter.value !== 'all' ||
    Boolean(customDate.value) ||
    sortBy.value !== 'newest' ||
    Boolean(searchQuery.value.trim())
})

const activeFiltersCount = computed(() => {
  let count = 0
  if (statusFilter.value !== 'all') count++
  if (typeFilter.value !== 'all') count++
  if (methodFilter.value !== 'all') count++
  if (dateFilter.value !== 'all') count++
  if (customDate.value) count++
  if (sortBy.value !== 'newest') count++
  if (searchQuery.value.trim()) count++
  return count
})

function resetFilters() {
  statusFilter.value = 'all'
  typeFilter.value = 'all'
  methodFilter.value = 'all'
  dateFilter.value = 'all'
  customDate.value = ''
  sortBy.value = 'newest'
  searchQuery.value = ''
}

const filteredOrders = computed(() => {
  const result = orders.value.filter(order => {
    // 1. Status filter
    if (statusFilter.value !== 'all') {
      if (order.payment_status !== statusFilter.value) return false
    }

    // 2. Type / Channel filter
    if (typeFilter.value !== 'all') {
      if (typeFilter.value === 'room_service') {
        const isRoom = order.type === 'room_service' ||
          order.payment_method === 'room_charge' ||
          (order.notes && order.notes.toLowerCase().includes('room'))
        if (!isRoom) return false
      } else if (order.type !== typeFilter.value) {
        return false
      }
    }

    // 3. Payment Method filter
    if (methodFilter.value !== 'all') {
      const pMethod = (order.payment_method || (order.payment?.gateway ?? '')).toLowerCase()
      if (methodFilter.value === 'cash' && pMethod !== 'cash') return false
      if (methodFilter.value === 'telebirr' && !pMethod.includes('telebirr')) return false
      if (methodFilter.value === 'cbe_birr' && !pMethod.includes('cbe')) return false
      if (methodFilter.value === 'card' && !['card', 'pos', 'stripe'].includes(pMethod)) return false
      if (methodFilter.value === 'room_charge' && pMethod !== 'room_charge') return false
    }

    // 4. Date filter
    if (dateFilter.value === 'today') {
      const todayStr = new Date().toISOString().slice(0, 10)
      const orderDateStr = new Date(order.created_at).toISOString().slice(0, 10)
      if (todayStr !== orderDateStr) return false
    } else if (dateFilter.value === 'custom' && customDate.value) {
      const orderDateStr = new Date(order.created_at).toISOString().slice(0, 10)
      if (customDate.value !== orderDateStr) return false
    }

    // 5. Search query filter
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim()
      const matchUlid = order.ulid?.toLowerCase().includes(q)
      const matchCustomer = order.user?.name?.toLowerCase().includes(q)
      const matchPhone = order.user?.phone?.toLowerCase().includes(q)
      const matchTable = order.table?.name?.toLowerCase().includes(q)
      const matchNotes = order.notes?.toLowerCase().includes(q)
      if (!matchUlid && !matchCustomer && !matchPhone && !matchTable && !matchNotes) return false
    }

    return true
  })

  // 6. Sorting
  if (sortBy.value === 'newest') {
    result.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
  } else if (sortBy.value === 'oldest') {
    result.sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime())
  } else if (sortBy.value === 'amount_desc') {
    result.sort((a, b) => Number(b.total) - Number(a.total))
  } else if (sortBy.value === 'amount_asc') {
    result.sort((a, b) => Number(a.total) - Number(b.total))
  }

  return result
})

// ── POS Register Ticket State ─────────────────────────────────────────────
const selectedCategory = ref('all')
const menuSearch = ref('')
const posOrderType = ref('dine_in') // 'dine_in', 'pickup', 'delivery'
const posTableId = ref('')
const posCustomerName = ref('')
const posCustomerPhone = ref('')
const posNotes = ref('')
const posDiscount = ref(0)
const ticketItems = ref([])

const filteredDishes = computed(() => {
  let list = []
  categories.value.forEach(cat => {
    if (selectedCategory.value === 'all' || cat.id === selectedCategory.value) {
      if (cat.dishes) {
        list.push(...cat.dishes.map(d => ({ ...d, category_name: cat.name })))
      }
    }
  })

  if (menuSearch.value.trim()) {
    const q = menuSearch.value.toLowerCase().trim()
    list = list.filter(d => d.name?.toLowerCase().includes(q) || d.category_name?.toLowerCase().includes(q))
  }

  return list
})

const ticketSubtotal = computed(() => {
  return ticketItems.value.reduce((sum, item) => sum + (Number(item.price) * item.quantity), 0)
})

const ticketTax = computed(() => {
  return round(ticketSubtotal.value * 0.15)
})

const ticketServiceCharge = computed(() => {
  return posOrderType.value === 'dine_in' ? round(ticketSubtotal.value * 0.10) : 0
})

const ticketGrandTotal = computed(() => {
  const discount = Math.min(Number(posDiscount.value || 0), ticketSubtotal.value)
  return Math.max(0, round(ticketSubtotal.value + ticketTax.value + ticketServiceCharge.value - discount))
})

function addToTicket(dish) {
  const existing = ticketItems.value.find(i => i.dish_id === dish.id && !i.variant_id)
  if (existing) {
    existing.quantity++
  } else {
    ticketItems.value.push({
      dish_id: dish.id,
      name: dish.name,
      price: Number(dish.price),
      quantity: 1,
      variant_id: null,
      options: null,
    })
  }
}

function updateItemQuantity(index, delta) {
  const item = ticketItems.value[index]
  if (!item) return
  item.quantity += delta
  if (item.quantity <= 0) {
    ticketItems.value.splice(index, 1)
  }
}

function clearTicket() {
  ticketItems.value = []
  posDiscount.value = 0
  posCustomerName.value = ''
  posCustomerPhone.value = ''
  posNotes.value = ''
}

// ── Modals State ──────────────────────────────────────────────────────────
const selectedOrder = ref(null)

// Payment Settlement Modal
const showPaymentModal = ref(false)
const paymentMethod = ref('cash')
const amountTendered = ref(0)
const paymentTxnRef = ref('')
const paymentRoomNum = ref('')
const splitCashPart = ref(0)
const splitDigitalPart = ref(0)
const splitDigitalMethod = ref('telebirr')
const paymentNotes = ref('')
const processingPayment = ref(false)

// Split Bill Modal
const showSplitModal = ref(false)
const splitWays = ref(2)
const splitBillData = ref(null)

// Thermal Receipt Modal
const showReceiptModal = ref(false)
const receiptMode = ref('order') // 'order', 'z_report', 'x_report'
const zReportData = ref(null)

// Void & Refund Modals
const showVoidModal = ref(false)
const showRefundModal = ref(false)
const actionReason = ref('')
const actionLoading = ref(false)

// Shift Modals
const showOpenShiftModal = ref(false)
const showCloseShiftModal = ref(false)
const showDrawerActionModal = ref(false)

const openShiftFloat = ref(500)
const openShiftNotes = ref('')
const closingCashCounted = ref(0)
const closingShiftNotes = ref('')
const drawerActionType = ref('pay_in') // 'pay_in', 'pay_out', 'drop'
const drawerActionAmount = ref(0)
const drawerActionReason = ref('')
const shiftActionLoading = ref(false)

// ── Live Calculation Helpers ──────────────────────────────────────────────
function round(num) {
  return Math.round((Number(num) + Number.EPSILON) * 100) / 100
}

const changeDue = computed(() => {
  const total = selectedOrder.value ? Number(selectedOrder.value.total) : ticketGrandTotal.value
  return Math.max(0, round(Number(amountTendered.value || 0) - total))
})

const closingVariance = computed(() => {
  const expected = currentShiftData.value.summary?.expected_cash || 0
  return round(Number(closingCashCounted.value || 0) - expected)
})

// ── API Fetch Functions ───────────────────────────────────────────────────
async function fetchData() {
  try {
    const [ordersRes, summaryRes, shiftRes] = await Promise.all([
      staffApi.cashierOrders(),
      staffApi.cashierSummary(),
      staffApi.cashierCurrentShift(),
    ])
    orders.value = ordersRes.data.data ?? []
    summary.value = summaryRes.data.data ?? summary.value
    currentShiftData.value = shiftRes.data.data ?? currentShiftData.value
  } catch (err) {
    console.error('Failed to load cashier data', err)
  } finally {
    loading.value = false
  }
}

async function loadMenuAndTables() {
  try {
    const [menuRes, tablesRes] = await Promise.all([
      staffApi.cashierMenu(),
      staffApi.waiterTables(),
    ])
    categories.value = menuRes.data.data ?? []
    tables.value = tablesRes.data.data ?? []
  } catch (err) {
    console.error('Failed to load menu / tables', err)
  }
}

async function loadShiftHistory() {
  try {
    const { data } = await staffApi.cashierShiftHistory()
    shiftsHistory.value = data.data ?? []
  } catch (err) {
    console.error('Failed to load shift history', err)
  }
}

// ── Shift History Filter State ────────────────────────────────────────────
const shiftSearchQuery = ref('')
const shiftVarianceFilter = ref('all') // 'all', 'exact', 'discrepancy'

const filteredShiftsHistory = computed(() => {
  return shiftsHistory.value.filter(sh => {
    if (shiftVarianceFilter.value === 'exact' && Number(sh.variance || 0) !== 0) return false
    if (shiftVarianceFilter.value === 'discrepancy' && Number(sh.variance || 0) === 0) return false

    if (shiftSearchQuery.value.trim()) {
      const q = shiftSearchQuery.value.toLowerCase().trim()
      const matchId = String(sh.id).includes(q)
      const matchName = sh.user?.name?.toLowerCase().includes(q)
      const matchNotes = (sh.opening_notes || '').toLowerCase().includes(q) || (sh.closing_notes || '').toLowerCase().includes(q)
      if (!matchId && !matchName && !matchNotes) return false
    }

    return true
  })
})

// ── Payment Settlement ────────────────────────────────────────────────────
function openPaymentModal(order) {
  selectedOrder.value = order
  paymentMethod.value = order.payment_method === 'cash' ? 'cash' : (order.payment_method || 'cash')
  amountTendered.value = Number(order.total)
  paymentTxnRef.value = ''
  paymentRoomNum.value = ''
  splitCashPart.value = round(Number(order.total) / 2)
  splitDigitalPart.value = round(Number(order.total) - splitCashPart.value)
  splitDigitalMethod.value = 'telebirr'
  paymentNotes.value = ''
  showPaymentModal.value = true
}

function setQuickCash(amount) {
  amountTendered.value = amount
}

function addQuickCash(delta) {
  amountTendered.value = round(Number(amountTendered.value || 0) + delta)
}

async function handleConfirmPayment() {
  if (!selectedOrder.value) return
  processingPayment.value = true
  try {
    const payload = {
      payment_type: paymentMethod.value,
      amount_tendered: paymentMethod.value === 'cash' ? Number(amountTendered.value) : undefined,
      change_due: paymentMethod.value === 'cash' ? changeDue.value : undefined,
      transaction_id: ['telebirr', 'cbe_birr', 'card'].includes(paymentMethod.value) ? paymentTxnRef.value || undefined : undefined,
      room_number: paymentMethod.value === 'room_charge' ? paymentRoomNum.value || undefined : undefined,
      split_details: paymentMethod.value === 'split' ? {
        cash_amount: splitCashPart.value,
        digital_amount: splitDigitalPart.value,
        digital_method: splitDigitalMethod.value,
        digital_ref: paymentTxnRef.value,
      } : undefined,
      notes: paymentNotes.value || undefined,
    }

    const { data } = await staffApi.confirmPayment(selectedOrder.value.ulid, payload)
    ui.toast(t('cashierPage.toastPaymentConfirmed', { ulid: selectedOrder.value.ulid.slice(-6).toUpperCase() }), 'success')

    showPaymentModal.value = false
    selectedOrder.value = data.data
    openReceipt(data.data)
    await fetchData()
  } catch (err) {
    ui.toast(t('cashierPage.failedToConfirmPayment'), 'error')
  } finally {
    processingPayment.value = false
  }
}

// ── POS Quick Order Creation ──────────────────────────────────────────────
async function handleCreatePosOrder(autoSettle = false) {
  if (!ticketItems.value.length) {
    ui.toast(t('cashierPage.emptyTicket'), 'warning')
    return
  }

  processingPayment.value = true
  try {
    const payload = {
      type: posOrderType.value,
      table_id: posOrderType.value === 'dine_in' && posTableId.value ? posTableId.value : undefined,
      customer_name: posCustomerName.value || undefined,
      customer_phone: posCustomerPhone.value || undefined,
      notes: posNotes.value || undefined,
      discount: Number(posDiscount.value || 0),
      items: ticketItems.value.map(i => ({
        dish_id: i.dish_id,
        quantity: i.quantity,
        variant_id: i.variant_id,
        options: i.options,
      })),
      payment_type: autoSettle ? 'cash' : undefined,
      auto_settle: autoSettle,
    }

    const { data } = await staffApi.createPosOrder(payload)
    ui.toast(t('cashierPage.toastPosOrderCreated'), 'success')

    clearTicket()
    await fetchData()

    if (autoSettle) {
      openReceipt(data.data)
    } else {
      activeTab.value = 'orders'
    }
  } catch (err) {
    ui.toast(err.response?.data?.message ?? t('cashierPage.failedToConfirmPayment'), 'error')
  } finally {
    processingPayment.value = false
  }
}

// ── Bill Splitting ────────────────────────────────────────────────────────
async function openSplitModal(order) {
  selectedOrder.value = order
  splitWays.value = 2
  try {
    const { data } = await staffApi.splitBill(order.ulid, splitWays.value)
    splitBillData.value = data.data
    showSplitModal.value = true
  } catch (err) {
    console.error('Failed to calculate split', err)
  }
}

async function updateSplitWays(newWays) {
  splitWays.value = newWays
  if (selectedOrder.value) {
    const { data } = await staffApi.splitBill(selectedOrder.value.ulid, splitWays.value)
    splitBillData.value = data.data
  }
}

// ── Thermal Receipt Printing ──────────────────────────────────────────────
function openReceipt(order) {
  selectedOrder.value = order
  receiptMode.value = 'order'
  showReceiptModal.value = true
}

function openXReport() {
  receiptMode.value = 'x_report'
  showReceiptModal.value = true
}

function openZReport(data) {
  zReportData.value = data
  receiptMode.value = 'z_report'
  showReceiptModal.value = true
}

function triggerPrint() {
  window.print()
}

// ── Void & Refund ─────────────────────────────────────────────────────────
function openVoidModal(order) {
  selectedOrder.value = order
  actionReason.value = ''
  showVoidModal.value = true
}

async function handleConfirmVoid() {
  if (!selectedOrder.value || !actionReason.value.trim()) return
  actionLoading.value = true
  try {
    await staffApi.voidOrder(selectedOrder.value.ulid, actionReason.value.trim())
    ui.toast(t('cashierPage.toastOrderVoided', { ulid: selectedOrder.value.ulid.slice(-6).toUpperCase() }), 'info')
    showVoidModal.value = false
    await fetchData()
  } catch (err) {
    ui.toast(err.response?.data?.message ?? 'Failed to void order', 'error')
  } finally {
    actionLoading.value = false
  }
}

function openRefundModal(order) {
  selectedOrder.value = order
  actionReason.value = ''
  showRefundModal.value = true
}

async function handleConfirmRefund() {
  if (!selectedOrder.value || !actionReason.value.trim()) return
  actionLoading.value = true
  try {
    await staffApi.refundOrder(selectedOrder.value.ulid, { reason: actionReason.value.trim() })
    ui.toast(t('cashierPage.toastOrderRefunded', { ulid: selectedOrder.value.ulid.slice(-6).toUpperCase() }), 'info')
    showRefundModal.value = false
    await fetchData()
  } catch (err) {
    ui.toast(err.response?.data?.message ?? 'Failed to process refund', 'error')
  } finally {
    actionLoading.value = false
  }
}

// ── Cashier Shift Actions ─────────────────────────────────────────────────
async function handleOpenShift() {
  shiftActionLoading.value = true
  try {
    const { data } = await staffApi.cashierOpenShift({
      opening_float: Number(openShiftFloat.value || 0),
      opening_notes: openShiftNotes.value || undefined,
    })
    ui.toast(t('cashierPage.toastShiftOpened', { id: data.data.id, float: openShiftFloat.value }), 'success')
    showOpenShiftModal.value = false
    await fetchData()
  } catch (err) {
    ui.toast(err.response?.data?.message ?? 'Failed to open shift', 'error')
  } finally {
    shiftActionLoading.value = false
  }
}

async function handleCloseShift() {
  shiftActionLoading.value = true
  try {
    const { data } = await staffApi.cashierCloseShift({
      closing_cash_counted: Number(closingCashCounted.value || 0),
      closing_notes: closingShiftNotes.value || undefined,
    })
    ui.toast(t('cashierPage.toastShiftClosed'), 'success')
    showCloseShiftModal.value = false
    await fetchData()
    openZReport(data.data.z_report)
  } catch (err) {
    ui.toast(err.response?.data?.message ?? 'Failed to close shift', 'error')
  } finally {
    shiftActionLoading.value = false
  }
}

async function handleDrawerAction() {
  if (!drawerActionReason.value.trim() || Number(drawerActionAmount.value) <= 0) return
  shiftActionLoading.value = true
  try {
    await staffApi.cashierDrawerAction({
      type: drawerActionType.value,
      amount: Number(drawerActionAmount.value),
      reason: drawerActionReason.value.trim(),
    })
    ui.toast(t('cashierPage.toastDrawerRecorded'), 'success')
    showDrawerActionModal.value = false
    drawerActionAmount.value = 0
    drawerActionReason.value = ''
    await fetchData()
  } catch (err) {
    ui.toast(err.response?.data?.message ?? 'Failed to record drawer transaction', 'error')
  } finally {
    shiftActionLoading.value = false
  }
}

// ── Auto Refresh Timer ────────────────────────────────────────────────────
let timer
onMounted(() => {
  fetchData()
  loadMenuAndTables()
  timer = setInterval(fetchData, 10000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<template>
  <div class="max-w-7xl mx-auto">
    <!-- MASTER ALL-IN-ONE POS WORKSTATION CONSOLE -->
    <div class="card overflow-hidden shadow-card border border-warm-200/90 dark:border-warm-800/90">
      <!-- Section 1: Command Hub Header (Title, Shift Status & Till Actions) -->
      <div class="p-4 sm:p-5 bg-white dark:bg-warm-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="p-2.5 rounded-2xl bg-primary-500/10 text-primary-600 dark:text-primary-400 shrink-0">
            <CreditCard :size="22" />
          </div>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <h1 class="font-heading text-lg sm:text-xl font-bold text-warm-900 dark:text-white">
                {{ $t('cashierPage.title') }}
              </h1>
              <span
                v-if="currentShiftData.has_active_shift"
                class="badge badge-green text-[10px] uppercase font-bold tracking-wider inline-flex items-center gap-1.5 py-0.5 px-2"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                {{ $t('cashierPage.liveBadge') }} #{{ currentShiftData.shift?.id }}
              </span>
              <span
                v-else
                class="badge badge-orange text-[10px] font-bold py-0.5 px-2 inline-flex items-center gap-1"
              >
                <AlertCircle :size="11" /> {{ $t('cashierPage.noActiveShift') }}
              </span>
            </div>
            <p class="text-[11px] text-warm-500 dark:text-warm-400 mt-0.5">
              <template v-if="currentShiftData.has_active_shift">
                {{ currentShiftData.shift?.user?.name || auth.user?.name }} •
                Opened {{ new Date(currentShiftData.shift?.opened_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }} •
                Float: ETB {{ Number(currentShiftData.shift?.opening_float || 0).toFixed(2) }}
              </template>
              <template v-else>
                {{ $t('cashierPage.noActiveShiftPrompt') }}
              </template>
            </p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-1.5 shrink-0 self-start sm:self-auto flex-wrap">
          <button
            v-if="!currentShiftData.has_active_shift"
            @click="showOpenShiftModal = true"
            class="btn-primary text-xs py-1.5 px-3.5 inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 shadow-xs font-semibold"
          >
            <Unlock :size="13" /> {{ $t('cashierPage.openShiftBtn') }}
          </button>

          <template v-else>
            <button
              @click="showDrawerActionModal = true"
              class="btn-outline text-xs py-1.5 px-2.5 inline-flex items-center gap-1 font-medium"
            >
              <Wallet :size="13" /> {{ $t('cashierPage.drawerActionBtn') }}
            </button>
            <button
              @click="openXReport"
              class="btn-ghost text-xs py-1.5 px-2.5 inline-flex items-center gap-1 font-medium text-warm-600 dark:text-warm-300"
            >
              <FileText :size="13" /> {{ $t('cashierPage.xReportBtn') }}
            </button>
            <button
              @click="closingCashCounted = currentShiftData.summary?.expected_cash || 0; showCloseShiftModal = true"
              class="btn-outline text-xs py-1.5 px-2.5 inline-flex items-center gap-1 text-red-600 border-red-200 dark:border-red-900/40 hover:bg-red-50 dark:hover:bg-red-950/30 font-medium"
            >
              <Lock :size="13" /> {{ $t('cashierPage.closeShiftBtn') }}
            </button>
          </template>

          <button @click="fetchData" class="btn-ghost text-xs p-1.5 text-warm-500 hover:text-warm-800" title="Refresh">
            <RefreshCw :size="14" />
          </button>
        </div>
      </div>

      <!-- Section 2: Integrated Financial Metrics Ribbon (Horizontal Ticker) -->
      <div class="p-3 sm:px-5 sm:py-3 bg-warm-50/70 dark:bg-warm-850/60 border-t border-warm-200/70 dark:border-warm-800/70">
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-2">
          <!-- Cash in Till -->
          <div class="p-2.5 rounded-xl bg-white/90 dark:bg-warm-800/80 border border-warm-200/60 dark:border-warm-700/60 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-green-600 dark:text-green-400 flex items-center gap-1">
              <DollarSign :size="11" /> {{ $t('cashierPage.cashInDrawer') }}
            </span>
            <p class="font-mono text-sm font-bold text-warm-900 dark:text-white mt-0.5">
              ETB {{ Number(currentShiftData.summary?.expected_cash || summary.cash_total || 0).toFixed(2) }}
            </p>
          </div>

          <!-- Telebirr -->
          <div class="p-2.5 rounded-xl bg-white/90 dark:bg-warm-800/80 border border-warm-200/60 dark:border-warm-700/60 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400 flex items-center gap-1">
              <Smartphone :size="11" /> {{ $t('cashierPage.digitalCollected') }}
            </span>
            <p class="font-mono text-sm font-bold text-warm-900 dark:text-white mt-0.5">
              ETB {{ Number(summary.telebirr_total || summary.digital_total || 0).toFixed(2) }}
            </p>
          </div>

          <!-- CBE Birr -->
          <div class="p-2.5 rounded-xl bg-white/90 dark:bg-warm-800/80 border border-warm-200/60 dark:border-warm-700/60 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 flex items-center gap-1">
              <Building2 :size="11" /> {{ $t('cashierPage.cbeCollected') }}
            </span>
            <p class="font-mono text-sm font-bold text-warm-900 dark:text-white mt-0.5">
              ETB {{ Number(summary.cbe_total || 0).toFixed(2) }}
            </p>
          </div>

          <!-- Card / POS -->
          <div class="p-2.5 rounded-xl bg-white/90 dark:bg-warm-800/80 border border-warm-200/60 dark:border-warm-700/60 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-cyan-600 dark:text-cyan-400 flex items-center gap-1">
              <CreditCard :size="11" /> {{ $t('cashierPage.cardCollected') }}
            </span>
            <p class="font-mono text-sm font-bold text-warm-900 dark:text-white mt-0.5">
              ETB {{ Number(summary.card_total || 0).toFixed(2) }}
            </p>
          </div>

          <!-- Room Charges -->
          <div class="p-2.5 rounded-xl bg-white/90 dark:bg-warm-800/80 border border-warm-200/60 dark:border-warm-700/60 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400 flex items-center gap-1">
              <BedDouble :size="11" /> {{ $t('cashierPage.roomChargeCollected') }}
            </span>
            <p class="font-mono text-sm font-bold text-warm-900 dark:text-white mt-0.5">
              ETB {{ Number(summary.room_charge_total || 0).toFixed(2) }}
            </p>
          </div>

          <!-- Grand Total -->
          <div class="p-2.5 rounded-xl bg-primary-50 dark:bg-primary-950/40 border border-primary-200/60 dark:border-primary-800/60 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 flex items-center justify-between">
              <span class="flex items-center gap-1"><TrendingUp :size="11" /> {{ $t('cashierPage.grandTotal') }}</span>
              <span class="text-[9px] opacity-80">({{ summary.orders_count }})</span>
            </span>
            <p class="font-mono text-sm font-bold text-primary-700 dark:text-primary-300 mt-0.5">
              ETB {{ Number(summary.grand_total || 0).toFixed(2) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Section 3: Master Navigation Bar: Segmented Workstation Switcher (Left) & Context Quick Controls (Right) -->
      <div class="px-4 py-3 bg-gradient-to-r from-warm-50/80 via-white/80 to-warm-50/80 dark:from-warm-900/80 dark:via-warm-850/80 dark:to-warm-900/80 border-t border-warm-200/70 dark:border-warm-800/70 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <!-- Sleek Segmented Tab Switcher with Glassmorphic Pill -->
        <div class="inline-flex p-1 rounded-2xl bg-warm-200/50 dark:bg-warm-800/60 border border-warm-200/60 dark:border-warm-700/50 shadow-inner gap-1 self-start md:self-auto">
          <button
            @click="activeTab = 'orders'"
            :class="[
              'px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all duration-200 inline-flex items-center gap-1.5 cursor-pointer',
              activeTab === 'orders'
                ? 'bg-white dark:bg-warm-700 text-warm-900 dark:text-white shadow-sm ring-1 ring-black/5 dark:ring-white/10'
                : 'text-warm-600 dark:text-warm-400 hover:text-warm-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-warm-700/40'
            ]"
          >
            <Receipt :size="14" :class="activeTab === 'orders' ? 'text-primary-500' : ''" />
            <span>{{ $t('cashierPage.tabs.orders') }}</span>
            <span
              v-if="unpaidOrdersCount > 0"
              class="text-[10px] px-1.5 py-0.2 rounded-full font-black bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-xs animate-pulse"
            >
              {{ unpaidOrdersCount }}
            </span>
          </button>

          <button
            @click="activeTab = 'pos'"
            :class="[
              'px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all duration-200 inline-flex items-center gap-1.5 cursor-pointer',
              activeTab === 'pos'
                ? 'bg-white dark:bg-warm-700 text-warm-900 dark:text-white shadow-sm ring-1 ring-black/5 dark:ring-white/10'
                : 'text-warm-600 dark:text-warm-400 hover:text-warm-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-warm-700/40'
            ]"
          >
            <ShoppingBag :size="14" :class="activeTab === 'pos' ? 'text-primary-500' : ''" />
            <span>{{ $t('cashierPage.tabs.pos') }}</span>
            <span
              v-if="ticketItems.length > 0"
              class="text-[10px] px-1.5 py-0.2 rounded-full font-black bg-primary-500 text-white shadow-xs"
            >
              {{ ticketItems.length }}
            </span>
          </button>

          <button
            @click="activeTab = 'shifts'; loadShiftHistory()"
            :class="[
              'px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all duration-200 inline-flex items-center gap-1.5 cursor-pointer',
              activeTab === 'shifts'
                ? 'bg-white dark:bg-warm-700 text-warm-900 dark:text-white shadow-sm ring-1 ring-black/5 dark:ring-white/10'
                : 'text-warm-600 dark:text-warm-400 hover:text-warm-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-warm-700/40'
            ]"
          >
            <FileText :size="14" :class="activeTab === 'shifts' ? 'text-primary-500' : ''" />
            <span>{{ $t('cashierPage.tabs.shifts') }}</span>
          </button>
        </div>

        <!-- Integrated Search & Filter Controls (Orders Tab) -->
        <div v-if="activeTab === 'orders'" class="flex items-center gap-2 self-stretch md:self-auto">
          <!-- Search Input with Subtle Ring Focus -->
          <div class="relative flex-1 md:w-72">
            <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none group-focus-within:text-primary-500 transition-colors" />
            <input
              v-model="searchQuery"
              type="text"
              class="w-full bg-white dark:bg-warm-800/90 border border-warm-200/80 dark:border-warm-700/80 rounded-xl pl-9 pr-8 text-xs py-2 text-warm-900 dark:text-white placeholder:text-warm-400 shadow-2xs focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all font-medium"
              :placeholder="$t('cashierPage.searchPlaceholder')"
            />
            <button
              v-if="searchQuery"
              @click="searchQuery = ''"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-warm-400 hover:text-warm-600 dark:hover:text-warm-200 p-0.5 rounded-md transition-colors cursor-pointer"
            >
              <X :size="13" />
            </button>
          </div>

          <!-- Advanced Filters Trigger Pill -->
          <button
            @click="showAdvancedFilters = !showAdvancedFilters"
            :class="[
              'px-3.5 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-1.5 transition-all duration-200 cursor-pointer shrink-0 shadow-2xs',
              showAdvancedFilters || (typeFilter !== 'all' || methodFilter !== 'all' || dateFilter !== 'all' || sortBy !== 'newest')
                ? 'bg-gradient-to-r from-primary-500 to-orange-500 text-white shadow-xs shadow-primary-500/20 border border-transparent font-bold'
                : 'bg-white dark:bg-warm-800 text-warm-700 dark:text-warm-300 border border-warm-200/80 dark:border-warm-700/80 hover:bg-warm-50 dark:hover:bg-warm-750 hover:border-warm-300'
            ]"
          >
            <SlidersHorizontal :size="13" />
            <span class="hidden sm:inline">{{ $t('cashierPage.filtersTitle') }}</span>
            <span
              v-if="activeFiltersCount > 0"
              class="px-1.5 py-0.2 rounded-full text-[10px] font-black bg-white text-primary-600 shadow-2xs ml-0.5"
            >
              {{ activeFiltersCount }}
            </span>
            <ChevronDown :size="12" :class="['transition-transform duration-200', showAdvancedFilters ? 'rotate-180' : '']" />
          </button>

          <!-- Reset Button -->
          <button
            v-if="hasActiveFilters"
            @click="resetFilters"
            class="px-2.5 py-2 rounded-xl text-xs font-semibold text-warm-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 transition-all inline-flex items-center gap-1 shrink-0 cursor-pointer"
            :title="$t('cashierPage.filterReset')"
          >
            <RotateCcw :size="12" />
            <span class="hidden sm:inline">{{ $t('cashierPage.clearAll') }}</span>
          </button>
        </div>
      </div>

      <!-- ───────────────────────────────────────────────────────────────────────── -->
      <!-- TAB 1: ORDERS & BILL SETTLEMENT                                          -->
      <!-- ───────────────────────────────────────────────────────────────────────── -->
      <div v-if="activeTab === 'orders'">
        <!-- Status Filter Pills Sub-Row with Harmonious POS Styling -->
        <div class="px-4 py-2.5 bg-gradient-to-r from-warm-50/50 via-white to-warm-50/50 dark:from-warm-900/50 dark:via-warm-850 dark:to-warm-900/50 border-b border-warm-200/60 dark:border-warm-800/60 flex flex-wrap items-center justify-between gap-2.5">
          <div class="flex flex-wrap items-center gap-2">
            <!-- All Orders -->
            <button
              @click="statusFilter = 'all'"
              :class="[
                'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all duration-200 inline-flex items-center gap-1.5 whitespace-nowrap cursor-pointer shadow-2xs',
                statusFilter === 'all'
                  ? 'bg-warm-900 text-white dark:bg-white dark:text-warm-950 shadow-sm border border-transparent font-bold'
                  : 'bg-white dark:bg-warm-800/80 text-warm-700 dark:text-warm-300 border border-warm-200/80 dark:border-warm-700/60 hover:bg-warm-100/70 hover:border-warm-300'
              ]"
            >
              <Receipt :size="13" />
              <span>{{ $t('cashierPage.filterAll') }}</span>
              <span
                :class="[
                  'px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold',
                  statusFilter === 'all'
                    ? 'bg-white/20 text-white dark:bg-warm-900/15 dark:text-warm-950'
                    : 'bg-warm-100 dark:bg-warm-700 text-warm-600 dark:text-warm-400'
                ]"
              >
                {{ orders.length }}
              </span>
            </button>

            <!-- Unpaid / Action Needed (Pulsating Beacon & Gradient Active) -->
            <button
              @click="statusFilter = 'pending'"
              :class="[
                'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all duration-200 inline-flex items-center gap-1.5 whitespace-nowrap cursor-pointer shadow-2xs',
                statusFilter === 'pending'
                  ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-sm shadow-amber-500/25 border border-transparent font-bold'
                  : 'bg-amber-50/70 dark:bg-amber-950/30 text-amber-900 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800/50 hover:bg-amber-100/80'
              ]"
            >
              <span v-if="unpaidOrdersCount > 0" class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
              </span>
              <Clock v-else :size="13" />
              <span>{{ $t('cashierPage.filterPending') }}</span>
              <span
                v-if="unpaidOrdersCount > 0"
                :class="[
                  'px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold',
                  statusFilter === 'pending'
                    ? 'bg-white text-amber-700 shadow-2xs'
                    : 'bg-amber-500 text-white'
                ]"
              >
                {{ unpaidOrdersCount }}
              </span>
            </button>

            <!-- Settled / Paid -->
            <button
              @click="statusFilter = 'paid'"
              :class="[
                'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all duration-200 inline-flex items-center gap-1.5 whitespace-nowrap cursor-pointer shadow-2xs',
                statusFilter === 'paid'
                  ? 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-sm shadow-emerald-500/25 border border-transparent font-bold'
                  : 'bg-emerald-50/60 dark:bg-emerald-950/25 text-emerald-900 dark:text-emerald-300 border border-emerald-200/70 dark:border-emerald-800/40 hover:bg-emerald-100/70'
              ]"
            >
              <CheckCircle :size="13" :class="statusFilter === 'paid' ? 'text-white' : 'text-emerald-500'" />
              <span>{{ $t('cashierPage.filterPaid') }}</span>
              <span
                :class="[
                  'px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold',
                  statusFilter === 'paid'
                    ? 'bg-white/20 text-white'
                    : 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300'
                ]"
              >
                {{ paidOrdersCount }}
              </span>
            </button>

            <!-- Refunded / Void -->
            <button
              @click="statusFilter = 'refunded'"
              :class="[
                'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all duration-200 inline-flex items-center gap-1.5 whitespace-nowrap cursor-pointer shadow-2xs',
                statusFilter === 'refunded'
                  ? 'bg-gradient-to-r from-rose-600 to-red-600 text-white shadow-sm shadow-rose-500/25 border border-transparent font-bold'
                  : 'bg-rose-50/60 dark:bg-rose-950/25 text-rose-900 dark:text-rose-300 border border-rose-200/70 dark:border-rose-800/40 hover:bg-rose-100/70'
              ]"
            >
              <RotateCcw :size="13" />
              <span>{{ $t('cashierPage.filterRefunded') }}</span>
              <span
                v-if="refundedOrdersCount > 0"
                :class="[
                  'px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold',
                  statusFilter === 'refunded'
                    ? 'bg-white/20 text-white'
                    : 'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300'
                ]"
              >
                {{ refundedOrdersCount }}
              </span>
            </button>
          </div>

          <div class="flex items-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            <span class="text-[11px] font-semibold text-warm-500 dark:text-warm-400">
              {{ $t('cashierPage.showingOrdersCount', { filtered: filteredOrders.length, total: orders.length }) }}
            </span>
          </div>
        </div>

        <!-- Collapsible Multi-Dimensional Filter Controls Panel (Custom Interactive Options) -->
        <div
          v-if="showAdvancedFilters"
          class="p-4 sm:p-5 bg-gradient-to-b from-warm-50/90 via-white to-warm-50/70 dark:from-warm-900/90 dark:via-warm-850 dark:to-warm-900/80 border-b border-warm-200/80 dark:border-warm-800/80 backdrop-blur-sm space-y-4 animate-in fade-in duration-200"
        >
          <!-- Category 1: Service Channel (Type) with Interactive Option Chips -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="text-[11px] font-bold uppercase tracking-wider text-warm-600 dark:text-warm-300 flex items-center gap-1.5">
                <UtensilsCrossed :size="13" class="text-primary-500" /> {{ $t('cashierPage.filterType') }}
              </label>
              <span v-if="typeFilter !== 'all'" class="text-[10px] font-bold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-950/40 px-2 py-0.5 rounded-full">
                Active Filter
              </span>
            </div>
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="opt in [
                  { id: 'all', label: $t('cashierPage.filterAllTypes'), icon: Globe, iconColor: 'text-warm-400' },
                  { id: 'dine_in', label: $t('cashierPage.filterDineIn'), icon: UtensilsCrossed, iconColor: 'text-blue-500' },
                  { id: 'pickup', label: $t('cashierPage.filterPickup'), icon: ShoppingBag, iconColor: 'text-amber-500' },
                  { id: 'delivery', label: $t('cashierPage.filterDelivery'), icon: Truck, iconColor: 'text-emerald-500' },
                  { id: 'room_service', label: $t('cashierPage.filterRoomService'), icon: BedDouble, iconColor: 'text-purple-500' },
                ]"
                :key="opt.id"
                type="button"
                @click="typeFilter = opt.id"
                :class="[
                  'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all inline-flex items-center gap-1.5 cursor-pointer shadow-2xs',
                  typeFilter === opt.id
                    ? 'bg-primary-500 text-white shadow-xs shadow-primary-500/25 ring-2 ring-primary-500/30 font-bold'
                    : 'bg-white dark:bg-warm-800 text-warm-700 dark:text-warm-300 border border-warm-200/80 dark:border-warm-700/80 hover:bg-warm-100/70 hover:border-warm-300'
                ]"
              >
                <component :is="opt.icon" :size="13" :class="typeFilter === opt.id ? 'text-white' : opt.iconColor" />
                <span>{{ opt.label }}</span>
              </button>
            </div>
          </div>

          <!-- Category 2: Payment Method with Interactive Option Chips -->
          <div class="pt-3 border-t border-warm-200/60 dark:border-warm-800/60">
            <div class="flex items-center justify-between mb-2">
              <label class="text-[11px] font-bold uppercase tracking-wider text-warm-600 dark:text-warm-300 flex items-center gap-1.5">
                <Wallet :size="13" class="text-emerald-500" /> {{ $t('cashierPage.filterMethod') }}
              </label>
              <span v-if="methodFilter !== 'all'" class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-full">
                Active Filter
              </span>
            </div>
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="opt in [
                  { id: 'all', label: $t('cashierPage.filterAllMethods'), icon: Wallet, iconColor: 'text-warm-400' },
                  { id: 'cash', label: $t('cashierPage.filterCashOnly'), icon: DollarSign, iconColor: 'text-emerald-500' },
                  { id: 'telebirr', label: $t('cashierPage.filterTelebirrOnly'), icon: Smartphone, iconColor: 'text-blue-500' },
                  { id: 'cbe_birr', label: $t('cashierPage.filterCbeOnly'), icon: Building2, iconColor: 'text-amber-500' },
                  { id: 'card', label: $t('cashierPage.filterCardOnly'), icon: CreditCard, iconColor: 'text-cyan-500' },
                  { id: 'room_charge', label: $t('cashierPage.filterRoomOnly'), icon: BedDouble, iconColor: 'text-purple-500' },
                ]"
                :key="opt.id"
                type="button"
                @click="methodFilter = opt.id"
                :class="[
                  'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all inline-flex items-center gap-1.5 cursor-pointer shadow-2xs',
                  methodFilter === opt.id
                    ? 'bg-emerald-600 text-white shadow-xs shadow-emerald-500/25 ring-2 ring-emerald-500/30 font-bold'
                    : 'bg-white dark:bg-warm-800 text-warm-700 dark:text-warm-300 border border-warm-200/80 dark:border-warm-700/80 hover:bg-warm-100/70 hover:border-warm-300'
                ]"
              >
                <component :is="opt.icon" :size="13" :class="methodFilter === opt.id ? 'text-white' : opt.iconColor" />
                <span>{{ opt.label }}</span>
              </button>
            </div>
          </div>

          <!-- Bottom Row: Date Window & Sort By -->
          <div class="pt-3 border-t border-warm-200/60 dark:border-warm-800/60 grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Category 3: Date Window -->
            <div>
              <label class="block text-[11px] font-bold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-2 flex items-center gap-1.5">
                <Calendar :size="13" class="text-purple-500" /> {{ $t('cashierPage.filterDate') }}
              </label>
              <div class="flex flex-wrap items-center gap-1.5">
                <button
                  v-for="opt in [
                    { id: 'all', label: $t('cashierPage.dateAll'), icon: Calendar },
                    { id: 'today', label: $t('cashierPage.dateToday'), icon: Clock },
                    { id: 'custom', label: $t('cashierPage.dateCustom'), icon: CalendarCheck },
                  ]"
                  :key="opt.id"
                  type="button"
                  @click="dateFilter = opt.id"
                  :class="[
                    'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all inline-flex items-center gap-1.5 cursor-pointer shadow-2xs',
                    dateFilter === opt.id
                      ? 'bg-purple-600 text-white shadow-xs shadow-purple-500/25 ring-2 ring-purple-500/30 font-bold'
                      : 'bg-white dark:bg-warm-800 text-warm-700 dark:text-warm-300 border border-warm-200/80 dark:border-warm-700/80 hover:bg-warm-100/70'
                  ]"
                >
                  <component :is="opt.icon" :size="13" />
                  <span>{{ opt.label }}</span>
                </button>

                <input
                  v-if="dateFilter === 'custom'"
                  v-model="customDate"
                  type="date"
                  class="input text-xs py-1 px-2.5 bg-white dark:bg-warm-800 font-semibold border-purple-400 focus:ring-2 focus:ring-purple-500/20 rounded-xl"
                />
              </div>
            </div>

            <!-- Category 4: Sort By -->
            <div>
              <label class="block text-[11px] font-bold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-2 flex items-center gap-1.5">
                <ArrowUpDown :size="13" class="text-indigo-500" /> {{ $t('cashierPage.filterSort') }}
              </label>
              <div class="flex flex-wrap gap-1.5">
                <button
                  v-for="opt in [
                    { id: 'newest', label: $t('cashierPage.sortNewest') },
                    { id: 'oldest', label: $t('cashierPage.sortOldest') },
                    { id: 'amount_desc', label: $t('cashierPage.sortAmountHigh') },
                    { id: 'amount_asc', label: $t('cashierPage.sortAmountLow') },
                  ]"
                  :key="opt.id"
                  type="button"
                  @click="sortBy = opt.id"
                  :class="[
                    'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all inline-flex items-center gap-1.5 cursor-pointer shadow-2xs',
                    sortBy === opt.id
                      ? 'bg-indigo-600 text-white shadow-xs shadow-indigo-500/25 ring-2 ring-indigo-500/30 font-bold'
                      : 'bg-white dark:bg-warm-800 text-warm-700 dark:text-warm-300 border border-warm-200/80 dark:border-warm-700/80 hover:bg-warm-100/70'
                  ]"
                >
                  <span>{{ opt.label }}</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Drawer Footer Controls -->
          <div class="pt-3 border-t border-warm-200/60 dark:border-warm-800/60 flex items-center justify-between">
            <span class="text-xs text-warm-400">
              <span v-if="activeFiltersCount > 0" class="font-semibold text-primary-600 dark:text-primary-400">
                {{ activeFiltersCount }} filter(s) active
              </span>
              <span v-else>No optional filters active</span>
            </span>

            <div class="flex items-center gap-2">
              <button
                v-if="hasActiveFilters"
                @click="resetFilters"
                class="px-3 py-1.5 rounded-xl text-xs font-semibold text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 inline-flex items-center gap-1 transition-all cursor-pointer"
              >
                <RotateCcw :size="12" /> {{ $t('cashierPage.clearAll') }}
              </button>
              <button
                @click="showAdvancedFilters = false"
                class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-warm-900 text-white dark:bg-warm-100 dark:text-warm-900 hover:opacity-90 transition-all cursor-pointer shadow-xs"
              >
                Done
              </button>
            </div>
          </div>
        </div>

        <!-- Active Filter Summary & Chips Bar -->
        <div
          v-if="hasActiveFilters"
          class="px-4 py-2 bg-warm-50/70 dark:bg-warm-900/40 border-b border-warm-200/60 dark:border-warm-800/60 flex flex-wrap items-center justify-between gap-2 text-xs"
        >
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-warm-500 dark:text-warm-400 text-[11px] font-bold uppercase tracking-wider flex items-center gap-1 mr-1">
              <Filter :size="11" class="text-primary-500" /> Active:
            </span>

            <!-- Status Tag -->
            <span
              v-if="statusFilter !== 'all'"
              class="px-2 py-0.5 rounded-lg bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800 text-[11px] inline-flex items-center gap-1 font-semibold"
            >
              <span class="capitalize">{{ statusFilter }}</span>
              <button @click="statusFilter = 'all'" class="hover:opacity-75 cursor-pointer"><X :size="11" /></button>
            </span>

            <!-- Channel Tag -->
            <span
              v-if="typeFilter !== 'all'"
              class="px-2 py-0.5 rounded-lg bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800 text-[11px] inline-flex items-center gap-1 font-semibold"
            >
              <span>{{ typeFilter === 'dine_in' ? 'Dine-In' : typeFilter === 'pickup' ? 'Takeaway' : typeFilter === 'delivery' ? 'Delivery' : 'Room Service' }}</span>
              <button @click="typeFilter = 'all'" class="hover:opacity-75 cursor-pointer"><X :size="11" /></button>
            </span>

            <!-- Method Tag -->
            <span
              v-if="methodFilter !== 'all'"
              class="px-2 py-0.5 rounded-lg bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-[11px] inline-flex items-center gap-1 font-semibold"
            >
              <span class="capitalize">{{ methodFilter }}</span>
              <button @click="methodFilter = 'all'" class="hover:opacity-75 cursor-pointer"><X :size="11" /></button>
            </span>

            <!-- Date Tag -->
            <span
              v-if="dateFilter !== 'all'"
              class="px-2 py-0.5 rounded-lg bg-purple-100 dark:bg-purple-950/60 text-purple-800 dark:text-purple-300 border border-purple-200 dark:border-purple-800 text-[11px] inline-flex items-center gap-1 font-semibold"
            >
              <span>{{ dateFilter === 'today' ? 'Today' : customDate || 'Custom' }}</span>
              <button @click="dateFilter = 'all'; customDate = ''" class="hover:opacity-75 cursor-pointer"><X :size="11" /></button>
            </span>

            <!-- Search Query Tag -->
            <span
              v-if="searchQuery.trim()"
              class="px-2 py-0.5 rounded-lg bg-warm-200 dark:bg-warm-700 text-warm-800 dark:text-warm-200 border border-warm-300 dark:border-warm-600 text-[11px] inline-flex items-center gap-1 font-semibold"
            >
              <span>"{{ searchQuery.trim() }}"</span>
              <button @click="searchQuery = ''" class="hover:opacity-75 cursor-pointer"><X :size="11" /></button>
            </span>
          </div>

          <button
            @click="resetFilters"
            class="text-[11px] text-red-500 hover:text-red-700 font-bold inline-flex items-center gap-1 cursor-pointer"
          >
            <RotateCcw :size="11" /> {{ $t('cashierPage.clearAll') }}
          </button>
        </div>

        <!-- Orders Table (Seated directly in master workstation container) -->
        <TableSkeleton v-if="loading" :rows="6" :cols="6" />

        <div v-else-if="filteredOrders.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-warm-50 dark:bg-warm-800/60 text-warm-600 dark:text-warm-300 text-xs uppercase tracking-wider">
              <tr>
                <th class="px-4 py-3 text-left font-semibold">{{ $t('cashierPage.orderCol') }}</th>
                <th class="px-4 py-3 text-left font-semibold">{{ $t('cashierPage.tableOrTypeCol') }}</th>
                <th class="px-4 py-3 text-left font-semibold">{{ $t('cashierPage.paymentMethodCol') }}</th>
                <th class="px-4 py-3 text-left font-semibold">{{ $t('cashierPage.totalCol') }}</th>
                <th class="px-4 py-3 text-left font-semibold">{{ $t('cashierPage.statusCol') }}</th>
                <th class="px-4 py-3 text-right font-semibold">{{ $t('cashierPage.actionsCol') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-warm-100 dark:divide-warm-800">
              <tr
                v-for="order in filteredOrders"
                :key="order.ulid"
                class="hover:bg-warm-50/60 dark:hover:bg-warm-850/60 transition-colors"
              >
                <!-- Order ULID & Items count -->
                <td class="px-4 py-3.5">
                  <div class="flex items-center gap-2">
                    <span class="font-mono text-xs font-bold text-warm-900 dark:text-white">#{{ order.ulid.slice(-8).toUpperCase() }}</span>
                    <span class="text-[10px] text-warm-400">({{ new Date(order.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }})</span>
                  </div>
                  <p class="text-[11px] text-warm-500 mt-0.5">
                    {{ order.items?.length ?? 0 }} items
                    <span v-if="order.waiter" class="text-primary-500"> • {{ order.waiter.name }}</span>
                  </p>
                </td>

                <!-- Customer / Table info with rich channel icons -->
                <td class="px-4 py-3.5">
                  <p class="font-medium text-warm-900 dark:text-white text-xs">
                    {{ order.user?.name ?? (order.notes?.match(/Guest:\s*([^,\n]+)/)?.[1] || $t('trackingPage.receipt.guestDiner')) }}
                  </p>
                  <p class="text-[11px] text-warm-400 capitalize flex items-center gap-1.5 mt-0.5">
                    <span v-if="order.type === 'dine_in'" class="inline-flex items-center gap-1 text-blue-600 dark:text-blue-400 font-medium">
                      <UtensilsCrossed :size="12" />
                      {{ $t('kitchenPage.tableNum', { name: order.table?.name ?? order.table_id ?? 'N/A' }) }}
                    </span>
                    <span v-else-if="order.type === 'pickup'" class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400 font-medium">
                      <ShoppingBag :size="12" />
                      {{ $t('kitchenPage.takeout') }}
                    </span>
                    <span v-else-if="order.type === 'delivery'" class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-medium">
                      <Truck :size="12" />
                      {{ $t('kitchenPage.delivery') }}
                    </span>
                    <span v-else class="inline-flex items-center gap-1 text-purple-600 dark:text-purple-400 font-medium">
                      <BedDouble :size="12" />
                      {{ $t('cashierPage.filterRoomService') }}
                    </span>
                  </p>
                </td>

                <!-- Payment Method with Icons -->
                <td class="px-4 py-3.5">
                  <span class="capitalize text-xs font-semibold text-warm-700 dark:text-warm-300 inline-flex items-center gap-1.5">
                    <DollarSign v-if="order.payment_method === 'cash'" :size="13" class="text-green-500" />
                    <Smartphone v-else-if="['telebirr'].includes(order.payment_method)" :size="13" class="text-blue-500" />
                    <Building2 v-else-if="['cbe_birr'].includes(order.payment_method)" :size="13" class="text-amber-500" />
                    <CreditCard v-else-if="order.payment_method === 'card'" :size="13" class="text-cyan-500" />
                    <BedDouble v-else-if="order.payment_method === 'room_charge'" :size="13" class="text-purple-500" />
                    <Split v-else-if="order.payment_method === 'split'" :size="13" class="text-indigo-500" />
                    <Wallet v-else :size="13" class="text-warm-400" />
                    <span>{{ order.payment_method || 'Cash' }}</span>
                  </span>
                </td>

                <!-- Amount Due -->
                <td class="px-4 py-3.5 font-mono font-bold text-sm text-primary-600 dark:text-primary-400">
                  ETB {{ Number(order.total).toFixed(2) }}
                </td>

                <!-- Payment Status Badge with Icons -->
                <td class="px-4 py-3.5">
                  <span
                    v-if="order.payment_status === 'paid'"
                    class="badge badge-green text-[11px] font-semibold inline-flex items-center gap-1"
                  >
                    <CheckCircle :size="11" /> {{ $t('trackingPage.paidBadge') }}
                  </span>
                  <span
                    v-else-if="order.payment_status === 'refunded'"
                    class="badge badge-red text-[11px] font-semibold inline-flex items-center gap-1"
                  >
                    <RotateCcw :size="11" /> Refunded
                  </span>
                  <span
                    v-else
                    class="badge badge-orange text-[11px] font-semibold inline-flex items-center gap-1"
                  >
                    <Clock :size="11" class="animate-pulse" /> {{ $t('trackingPage.unpaidBadge') }}
                  </span>
                </td>

                <!-- Cashier Actions -->
                <td class="px-4 py-3.5 text-right space-x-1.5 whitespace-nowrap">
                  <!-- Settle Payment Button (If Pending) -->
                  <template v-if="order.payment_status === 'pending'">
                    <button
                      @click="openPaymentModal(order)"
                      class="btn-primary py-1.5 px-3 text-xs bg-green-600 hover:bg-green-700 inline-flex items-center gap-1 shadow-2xs"
                    >
                      <DollarSign :size="13" /> {{ $t('cashierPage.settlePayment') }}
                    </button>
                    <button
                      @click="openSplitModal(order)"
                      class="btn-outline py-1.5 px-2.5 text-xs inline-flex items-center gap-1"
                      :title="$t('cashierPage.splitBill')"
                    >
                      <Split :size="13" />
                    </button>
                    <button
                      @click="openVoidModal(order)"
                      class="btn-ghost py-1.5 px-2 text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30"
                      :title="$t('cashierPage.voidOrder')"
                    >
                      <Trash2 :size="13" />
                    </button>
                  </template>

                  <!-- Receipt & Refund (If Paid) -->
                  <template v-else-if="order.payment_status === 'paid'">
                    <button
                      @click="openReceipt(order)"
                      class="btn-outline py-1.5 px-3 text-xs inline-flex items-center gap-1"
                    >
                      <Printer :size="13" /> {{ $t('cashierPage.viewReceipt') }}
                    </button>
                    <button
                      @click="openRefundModal(order)"
                      class="btn-ghost py-1.5 px-2 text-xs text-warm-400 hover:text-red-500"
                      :title="$t('cashierPage.refundOrder')"
                    >
                      {{ $t('cashierPage.refundOrder') }}
                    </button>
                  </template>

                  <!-- If Refunded -->
                  <template v-else>
                    <button
                      @click="openReceipt(order)"
                      class="btn-ghost py-1.5 px-2.5 text-xs inline-flex items-center gap-1 text-warm-500"
                    >
                      <Printer :size="13" /> {{ $t('cashierPage.viewReceipt') }}
                    </button>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="text-center py-16 text-warm-400">
          <Receipt :size="48" class="mx-auto mb-3 opacity-30" />
          <p class="font-medium text-sm">{{ $t('cashierPage.noOrdersFound') }}</p>
        </div>
      </div>

    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <!-- TAB 2: QUICK POS REGISTER (COUNTER BILLING)                              -->
    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <div v-else-if="activeTab === 'pos'" class="p-4 sm:p-5">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
      <!-- Left: Dish Catalog & Search (7 Cols) -->
      <div class="lg:col-span-7 space-y-4">
        <!-- Categories & Search Bar -->
        <div class="card p-4 space-y-3">
          <div class="relative">
            <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
            <input
              v-model="menuSearch"
              type="text"
              class="input pl-9 pr-8 text-xs py-2 w-full"
              :placeholder="$t('cashierPage.searchMenu')"
            />
            <button
              v-if="menuSearch"
              @click="menuSearch = ''"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-warm-400 hover:text-warm-600 dark:hover:text-warm-200"
            >
              <X :size="14" />
            </button>
          </div>

          <!-- Category Pills with Icons -->
          <div class="flex items-center gap-1.5 overflow-x-auto pb-1">
            <button
              @click="selectedCategory = 'all'"
              :class="[
                'px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors inline-flex items-center gap-1.5',
                selectedCategory === 'all'
                  ? 'bg-primary-500 text-white shadow-2xs'
                  : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-warm-200 dark:hover:bg-warm-700'
              ]"
            >
              <SlidersHorizontal :size="12" />
              <span>{{ $t('cashierPage.allCategories') }}</span>
            </button>
            <button
              v-for="cat in categories"
              :key="cat.id"
              @click="selectedCategory = cat.id"
              :class="[
                'px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors inline-flex items-center gap-1.5',
                selectedCategory === cat.id
                  ? 'bg-primary-500 text-white shadow-2xs'
                  : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-warm-200 dark:hover:bg-warm-700'
              ]"
            >
              <UtensilsCrossed :size="12" />
              <span>{{ cat.name }}</span>
            </button>
          </div>
        </div>

        <!-- Dishes Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[600px] overflow-y-auto pr-1">
          <div
            v-for="dish in filteredDishes"
            :key="dish.id"
            @click="addToTicket(dish)"
            class="card p-3 cursor-pointer hover:border-primary-500 hover:shadow-md transition-all active:scale-[0.98] flex flex-col justify-between group"
          >
            <div>
              <p class="font-bold text-xs text-warm-900 dark:text-white line-clamp-1 group-hover:text-primary-600 dark:group-hover:text-primary-400">
                {{ dish.name }}
              </p>
              <p class="text-[10px] text-warm-400 uppercase tracking-wider mt-0.5">{{ dish.category_name }}</p>
            </div>
            <div class="flex items-center justify-between mt-3 pt-2 border-t border-warm-100 dark:border-warm-800">
              <span class="font-mono text-xs font-bold text-primary-600 dark:text-primary-400">
                ETB {{ Number(dish.price).toFixed(2) }}
              </span>
              <button class="p-1 rounded-lg bg-primary-50 dark:bg-primary-950/40 text-primary-600 hover:bg-primary-500 hover:text-white transition-colors">
                <Plus :size="13" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Active Register Ticket (5 Cols) -->
      <div class="lg:col-span-5 card p-5 space-y-4 sticky top-20 shadow-card">
        <div class="flex items-center justify-between border-b border-warm-100 dark:border-warm-800 pb-3">
          <h2 class="font-heading font-bold text-base text-warm-900 dark:text-white flex items-center gap-1.5">
            <Receipt :size="18" class="text-primary-500" /> {{ $t('cashierPage.posTicket') }}
          </h2>
          <span class="badge badge-orange text-[10px]">{{ ticketItems.length }} items</span>
        </div>

        <!-- Dining Channel Selector -->
        <div class="grid grid-cols-3 gap-1.5 p-1 bg-warm-100 dark:bg-warm-800 rounded-xl text-xs font-semibold">
          <button
            @click="posOrderType = 'dine_in'"
            :class="['py-1.5 rounded-lg transition-colors text-center', posOrderType === 'dine_in' ? 'bg-white dark:bg-warm-700 text-primary-600 dark:text-primary-300 shadow-2xs font-bold' : 'text-warm-600 dark:text-warm-400']"
          >
            {{ $t('cashierPage.dineIn') }}
          </button>
          <button
            @click="posOrderType = 'pickup'"
            :class="['py-1.5 rounded-lg transition-colors text-center', posOrderType === 'pickup' ? 'bg-white dark:bg-warm-700 text-primary-600 dark:text-primary-300 shadow-2xs font-bold' : 'text-warm-600 dark:text-warm-400']"
          >
            {{ $t('cashierPage.takeaway') }}
          </button>
          <button
            @click="posOrderType = 'delivery'"
            :class="['py-1.5 rounded-lg transition-colors text-center', posOrderType === 'delivery' ? 'bg-white dark:bg-warm-700 text-primary-600 dark:text-primary-300 shadow-2xs font-bold' : 'text-warm-600 dark:text-warm-400']"
          >
            {{ $t('cashierPage.delivery') }}
          </button>
        </div>

        <!-- Table Picker (if Dine In) -->
        <div v-if="posOrderType === 'dine_in'" class="space-y-1">
          <select v-model="posTableId" class="input text-xs py-1.5 w-full">
            <option value="">-- {{ $t('cashierPage.selectTable') }} --</option>
            <option v-for="table in tables" :key="table.id" :value="table.id">
              {{ table.name }} (Capacity: {{ table.capacity }} • {{ table.status }})
            </option>
          </select>
        </div>

        <!-- Optional Guest Info Inputs -->
        <div class="grid grid-cols-2 gap-2">
          <input
            v-model="posCustomerName"
            type="text"
            class="input text-xs py-1.5"
            :placeholder="$t('cashierPage.customerNamePlaceholder')"
          />
          <input
            v-model="posCustomerPhone"
            type="text"
            class="input text-xs py-1.5"
            :placeholder="$t('cashierPage.customerPhonePlaceholder')"
          />
        </div>

        <!-- Ticket Line Items List -->
        <div v-if="ticketItems.length" class="space-y-2 max-h-56 overflow-y-auto pr-1 divide-y divide-warm-100 dark:divide-warm-800">
          <div
            v-for="(item, idx) in ticketItems"
            :key="idx"
            class="pt-2 flex items-center justify-between text-xs"
          >
            <div class="flex-1 pr-2">
              <p class="font-semibold text-warm-900 dark:text-white">{{ item.name }}</p>
              <p class="text-[10px] text-warm-400 font-mono">ETB {{ Number(item.price).toFixed(2) }} ea</p>
            </div>
            <div class="flex items-center gap-1.5">
              <button @click="updateItemQuantity(idx, -1)" class="w-6 h-6 rounded bg-warm-100 dark:bg-warm-800 flex items-center justify-center hover:bg-warm-200">
                <Minus :size="11" />
              </button>
              <span class="w-5 text-center font-bold text-xs">{{ item.quantity }}</span>
              <button @click="updateItemQuantity(idx, 1)" class="w-6 h-6 rounded bg-warm-100 dark:bg-warm-800 flex items-center justify-center hover:bg-warm-200">
                <Plus :size="11" />
              </button>
              <span class="font-mono font-bold text-xs w-16 text-right text-warm-900 dark:text-white">
                ETB {{ (Number(item.price) * item.quantity).toFixed(2) }}
              </span>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-8 text-warm-400 border border-dashed border-warm-200 dark:border-warm-800 rounded-xl">
          <ShoppingBag :size="32" class="mx-auto mb-2 opacity-30" />
          <p class="text-xs font-semibold text-warm-600 dark:text-warm-300">{{ $t('cashierPage.emptyTicket') }}</p>
          <p class="text-[11px] text-warm-400 mt-0.5">{{ $t('cashierPage.emptyTicketPrompt') }}</p>
        </div>

        <!-- Financial Breakdown -->
        <div class="space-y-1.5 text-xs pt-3 border-t border-warm-100 dark:border-warm-800 text-warm-600 dark:text-warm-400">
          <div class="flex justify-between">
            <span>{{ $t('cartPage.subtotal') }}:</span>
            <span class="font-mono">ETB {{ ticketSubtotal.toFixed(2) }}</span>
          </div>
          <div v-if="posOrderType === 'dine_in'" class="flex justify-between text-warm-500">
            <span>{{ $t('cashierPage.receiptServiceCharge') }}:</span>
            <span class="font-mono">ETB {{ ticketServiceCharge.toFixed(2) }}</span>
          </div>
          <div class="flex justify-between text-warm-500">
            <span>{{ $t('cashierPage.receiptVat') }}:</span>
            <span class="font-mono">ETB {{ ticketTax.toFixed(2) }}</span>
          </div>
          <div v-if="Number(posDiscount) > 0" class="flex justify-between text-green-600 font-semibold">
            <span>{{ $t('cashierPage.discount') }}:</span>
            <span class="font-mono">-ETB {{ Number(posDiscount).toFixed(2) }}</span>
          </div>
          <div class="flex justify-between items-center pt-2 border-t border-warm-200 dark:border-warm-800 font-bold text-base text-warm-900 dark:text-white">
            <span>{{ $t('cartPage.grandTotal') }}:</span>
            <span class="font-mono text-primary-600 dark:text-primary-400 text-lg">
              ETB {{ ticketGrandTotal.toFixed(2) }}
            </span>
          </div>
        </div>

        <!-- POS Action Buttons -->
        <div class="space-y-2 pt-2">
          <button
            @click="handleCreatePosOrder(true)"
            :disabled="!ticketItems.length || processingPayment"
            class="btn-primary w-full justify-center py-2.5 text-xs font-bold bg-green-600 hover:bg-green-700 shadow-md inline-flex items-center gap-1.5"
          >
            <DollarSign :size="15" /> {{ $t('cashierPage.payCompleteBtn', { total: ticketGrandTotal.toFixed(2) }) }}
          </button>

          <div class="flex gap-2">
            <button
              @click="handleCreatePosOrder(false)"
              :disabled="!ticketItems.length || processingPayment"
              class="btn-outline flex-1 justify-center py-2 text-xs font-semibold"
            >
              {{ $t('cashierPage.holdOrderBtn') }}
            </button>
            <button
              @click="clearTicket"
              :disabled="!ticketItems.length"
              class="btn-ghost py-2 px-3 text-xs text-warm-400 hover:text-red-500"
            >
              <Trash2 :size="14" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <!-- TAB 3: SHIFT HISTORY & Z-REPORTS                                         -->
    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <div v-else-if="activeTab === 'shifts'" class="p-4 sm:p-5 space-y-4">
      <div>
        <h2 class="font-heading font-bold text-base text-warm-900 dark:text-white mb-4 flex items-center gap-2">
          <Clock :size="18" class="text-primary-500" /> {{ $t('cashierPage.shiftHistoryBtn') }}
        </h2>

        <!-- Shift History Search & Filter Bar with Icons -->
        <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between mb-4">
          <div class="relative w-full sm:w-72">
            <Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
            <input
              v-model="shiftSearchQuery"
              type="text"
              class="input pl-9 pr-8 text-xs py-1.5 w-full"
              placeholder="Search shift #, cashier, notes..."
            />
            <button
              v-if="shiftSearchQuery"
              @click="shiftSearchQuery = ''"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-warm-400 hover:text-warm-600"
            >
              <X :size="13" />
            </button>
          </div>

          <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
            <button
              @click="shiftVarianceFilter = 'all'"
              :class="[
                'px-2.5 py-1.5 rounded-xl text-xs font-semibold inline-flex items-center gap-1 transition-all whitespace-nowrap',
                shiftVarianceFilter === 'all'
                  ? 'bg-primary-500 text-white shadow-2xs'
                  : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-warm-200 dark:hover:bg-warm-700'
              ]"
            >
              <Clock :size="12" /> All Shifts ({{ shiftsHistory.length }})
            </button>

            <button
              @click="shiftVarianceFilter = 'exact'"
              :class="[
                'px-2.5 py-1.5 rounded-xl text-xs font-semibold inline-flex items-center gap-1 transition-all whitespace-nowrap',
                shiftVarianceFilter === 'exact'
                  ? 'bg-green-600 text-white shadow-2xs'
                  : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-warm-200 dark:hover:bg-warm-700'
              ]"
            >
              <CheckCircle :size="12" class="text-green-500" /> Balanced
            </button>

            <button
              @click="shiftVarianceFilter = 'discrepancy'"
              :class="[
                'px-2.5 py-1.5 rounded-xl text-xs font-semibold inline-flex items-center gap-1 transition-all whitespace-nowrap',
                shiftVarianceFilter === 'discrepancy'
                  ? 'bg-amber-600 text-white shadow-2xs'
                  : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-warm-200 dark:hover:bg-warm-700'
              ]"
            >
              <AlertCircle :size="12" class="text-amber-500" /> Discrepancies
            </button>
          </div>
        </div>

        <div v-if="filteredShiftsHistory.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-warm-50 dark:bg-warm-800/60 text-warm-600 dark:text-warm-300 text-xs uppercase">
              <tr>
                <th class="px-4 py-3 text-left font-semibold">Shift #</th>
                <th class="px-4 py-3 text-left font-semibold">Cashier</th>
                <th class="px-4 py-3 text-left font-semibold">Time Window</th>
                <th class="px-4 py-3 text-left font-semibold">Opening Float</th>
                <th class="px-4 py-3 text-left font-semibold">Total Revenue</th>
                <th class="px-4 py-3 text-left font-semibold">Variance</th>
                <th class="px-4 py-3 text-right font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-warm-100 dark:divide-warm-800">
              <tr v-for="sh in filteredShiftsHistory" :key="sh.id" class="hover:bg-warm-50/50 dark:hover:bg-warm-850">
                <td class="px-4 py-3 font-mono font-bold text-xs">#{{ sh.id }}</td>
                <td class="px-4 py-3 font-medium text-xs">{{ sh.user?.name }}</td>
                <td class="px-4 py-3 text-xs text-warm-400">
                  {{ new Date(sh.opened_at).toLocaleDateString() }}
                  ({{ new Date(sh.opened_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }} - {{ sh.closed_at ? new Date(sh.closed_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'Open' }})
                </td>
                <td class="px-4 py-3 font-mono text-xs">ETB {{ Number(sh.opening_float).toFixed(2) }}</td>
                <td class="px-4 py-3 font-mono text-xs font-bold text-primary-600 dark:text-primary-400">
                  ETB {{ Number(sh.total_sales).toFixed(2) }}
                </td>
                <td class="px-4 py-3 font-mono text-xs font-semibold">
                  <span :class="Number(sh.variance) === 0 ? 'text-green-600' : Number(sh.variance) < 0 ? 'text-red-500' : 'text-blue-500'">
                    {{ Number(sh.variance) >= 0 ? '+' : '' }}ETB {{ Number(sh.variance || 0).toFixed(2) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <button
                    @click="openZReport(sh)"
                    class="btn-outline py-1 px-2.5 text-xs inline-flex items-center gap-1"
                  >
                    <FileText :size="12" /> Z-Report
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="text-center py-12 text-warm-400">
          <FileText :size="36" class="mx-auto mb-2 opacity-30" />
          <p class="text-xs">No closed shift records found.</p>
        </div>
      </div>
    </div>
  </div>

    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <!-- MODAL: MULTI-CHANNEL PAYMENT SETTLEMENT                                  -->
    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <div v-if="showPaymentModal && selectedOrder" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-lg space-y-5 bg-white dark:bg-warm-900 border text-warm-900 dark:text-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-warm-100 dark:border-warm-800 pb-3">
          <div>
            <h3 class="font-heading font-bold text-lg flex items-center gap-2">
              <DollarSign :size="20" class="text-green-500" /> {{ $t('cashierPage.settlementModalTitle') }}
            </h3>
            <p class="text-xs text-warm-400">
              {{ $t('cashierPage.settlementSubtitle', { ulid: selectedOrder.ulid.slice(-8).toUpperCase(), total: Number(selectedOrder.total).toFixed(2) }) }}
            </p>
          </div>
          <button @click="showPaymentModal = false" class="text-warm-400 hover:text-warm-600">
            <X :size="18" />
          </button>
        </div>

        <!-- Big Amount Display -->
        <div class="p-3 rounded-xl bg-warm-50 dark:bg-warm-800/60 text-center">
          <p class="text-xs text-warm-500">{{ $t('cashierPage.totalCol') }}</p>
          <p class="font-mono text-3xl font-extrabold text-primary-600 dark:text-primary-400">
            ETB {{ Number(selectedOrder.total).toFixed(2) }}
          </p>
        </div>

        <!-- Payment Method Tabs -->
        <div>
          <label class="block text-xs font-bold text-warm-700 dark:text-warm-300 mb-2">{{ $t('cashierPage.chooseMethod') }}</label>
          <div class="grid grid-cols-3 gap-2">
            <button
              v-for="m in [
                { id: 'cash', label: $t('cashierPage.methodCash'), icon: DollarSign },
                { id: 'telebirr', label: $t('cashierPage.methodTelebirr'), icon: Smartphone },
                { id: 'cbe_birr', label: $t('cashierPage.methodCbeBirr'), icon: Smartphone },
                { id: 'card', label: $t('cashierPage.methodCard'), icon: CreditCard },
                { id: 'room_charge', label: $t('cashierPage.methodRoomCharge'), icon: BedDouble },
                { id: 'split', label: $t('cashierPage.methodSplit'), icon: Split },
              ]"
              :key="m.id"
              type="button"
              @click="paymentMethod = m.id"
              :class="[
                'p-2.5 rounded-xl border text-left transition-all text-xs font-semibold flex flex-col justify-between h-16',
                paymentMethod === m.id
                  ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-950/30 text-primary-700 dark:text-primary-300 shadow-2xs'
                  : 'border-warm-200 dark:border-warm-800 text-warm-700 dark:text-warm-300 hover:border-warm-300'
              ]"
            >
              <component :is="m.icon" :size="16" :class="paymentMethod === m.id ? 'text-primary-500' : 'text-warm-400'" />
              <span class="truncate leading-tight">{{ m.label }}</span>
            </button>
          </div>
        </div>

        <!-- Channel Specific Inputs -->
        <!-- Cash Tendered & Change -->
        <div v-if="paymentMethod === 'cash'" class="space-y-3 p-3.5 rounded-xl bg-warm-50 dark:bg-warm-800/40 border border-warm-100 dark:border-warm-800">
          <div>
            <div class="flex justify-between items-center mb-1">
              <label class="text-xs font-bold text-warm-700 dark:text-warm-300">{{ $t('cashierPage.amountTendered') }}</label>
              <span class="text-xs font-mono font-bold text-primary-600 dark:text-primary-400">
                Change: ETB {{ changeDue.toFixed(2) }}
              </span>
            </div>
            <input
              v-model.number="amountTendered"
              type="number"
              step="1"
              min="0"
              class="input text-base font-mono font-bold w-full"
            />
          </div>

          <!-- Quick Cash Buttons -->
          <div class="flex items-center gap-1.5 overflow-x-auto">
            <button
              type="button"
              @click="setQuickCash(Number(selectedOrder.total))"
              class="px-2.5 py-1 rounded-lg bg-white dark:bg-warm-700 border text-xs font-bold hover:border-primary-500"
            >
              {{ $t('cashierPage.exactCash') }}
            </button>
            <button
              v-for="plus in [50, 100, 200, 500, 1000]"
              :key="plus"
              type="button"
              @click="addQuickCash(plus)"
              class="px-2 py-1 rounded-lg bg-white dark:bg-warm-700 border text-xs font-semibold hover:border-primary-500 font-mono"
            >
              +{{ plus }}
            </button>
          </div>
        </div>

        <!-- Mobile Reference Code (Telebirr / CBE / Card) -->
        <div v-else-if="['telebirr', 'cbe_birr', 'card'].includes(paymentMethod)" class="space-y-2">
          <label class="block text-xs font-bold text-warm-700 dark:text-warm-300">Transaction Reference / Auth Code</label>
          <input
            v-model="paymentTxnRef"
            type="text"
            class="input text-xs font-mono w-full"
            :placeholder="$t('cashierPage.mobileRefPlaceholder')"
          />
        </div>

        <!-- Hotel Room Number -->
        <div v-else-if="paymentMethod === 'room_charge'" class="space-y-2">
          <label class="block text-xs font-bold text-warm-700 dark:text-warm-300">Hotel Guest Suite / Room #</label>
          <input
            v-model="paymentRoomNum"
            type="text"
            class="input text-xs font-mono w-full"
            :placeholder="$t('cashierPage.roomNumberPlaceholder')"
          />
        </div>

        <!-- Split Payment Breakdown -->
        <div v-else-if="paymentMethod === 'split'" class="space-y-3 p-3 rounded-xl bg-warm-50 dark:bg-warm-800/40">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] font-bold text-warm-700 dark:text-warm-300 mb-1">{{ $t('cashierPage.splitCashAmount') }}</label>
              <input v-model.number="splitCashPart" type="number" class="input text-xs font-mono w-full" />
            </div>
            <div>
              <label class="block text-[11px] font-bold text-warm-700 dark:text-warm-300 mb-1">{{ $t('cashierPage.splitDigitalAmount') }}</label>
              <input v-model.number="splitDigitalPart" type="number" class="input text-xs font-mono w-full" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <select v-model="splitDigitalMethod" class="input text-xs py-1.5">
              <option value="telebirr">Telebirr</option>
              <option value="cbe_birr">CBE Birr</option>
              <option value="card">Card POS</option>
            </select>
            <input v-model="paymentTxnRef" type="text" class="input text-xs font-mono" placeholder="Ref code" />
          </div>
        </div>

        <div class="flex gap-2 pt-2 border-t border-warm-100 dark:border-warm-800">
          <button
            @click="handleConfirmPayment"
            :disabled="processingPayment"
            class="btn-primary flex-1 justify-center py-2.5 text-xs font-bold bg-green-600 hover:bg-green-700 shadow-md inline-flex items-center gap-1.5"
          >
            <CheckCircle :size="15" /> {{ $t('cashierPage.confirmAndPrintBtn') }}
          </button>
          <button @click="showPaymentModal = false" class="btn-outline py-2.5 px-4 text-xs font-semibold">
            {{ $t('cashierPage.cancel') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <!-- MODAL: BILL SPLITTING CALCULATOR                                         -->
    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <div v-if="showSplitModal && selectedOrder" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-md space-y-4 bg-white dark:bg-warm-900 border text-warm-900 dark:text-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-warm-100 dark:border-warm-800 pb-3">
          <div>
            <h3 class="font-heading font-bold text-base flex items-center gap-2">
              <Split :size="18" class="text-primary-500" /> {{ $t('cashierPage.splitModalTitle') }}
            </h3>
            <p class="text-xs text-warm-400">Order #{{ selectedOrder.ulid.slice(-8).toUpperCase() }} • Total: ETB {{ Number(selectedOrder.total).toFixed(2) }}</p>
          </div>
          <button @click="showSplitModal = false" class="text-warm-400 hover:text-warm-600">
            <X :size="16" />
          </button>
        </div>

        <div class="space-y-3">
          <label class="block text-xs font-bold text-warm-700 dark:text-warm-300">{{ $t('cashierPage.numberOfGuests') }}</label>
          <div class="grid grid-cols-5 gap-1.5">
            <button
              v-for="n in [2, 3, 4, 5, 6]"
              :key="n"
              type="button"
              @click="updateSplitWays(n)"
              :class="['py-2 rounded-xl text-xs font-bold border transition-colors', splitWays === n ? 'bg-primary-500 text-white border-primary-500' : 'bg-warm-100 dark:bg-warm-800 text-warm-800 dark:text-warm-200 border-warm-200 dark:border-warm-700']"
            >
              {{ n }} diners
            </button>
          </div>

          <!-- Per person share display -->
          <div class="p-4 rounded-xl bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 text-center space-y-1">
            <p class="text-xs text-warm-500 uppercase font-semibold">{{ $t('cashierPage.perPersonShare') }}</p>
            <p class="font-mono text-2xl font-extrabold text-primary-600 dark:text-primary-400">
              ETB {{ Number(splitBillData?.per_person || (Number(selectedOrder.total) / splitWays)).toFixed(2) }}
            </p>
            <p class="text-[11px] text-warm-400">x {{ splitWays }} equal shares = ETB {{ Number(selectedOrder.total).toFixed(2) }}</p>
          </div>
        </div>

        <div class="flex gap-2 pt-2 border-t border-warm-100 dark:border-warm-800">
          <button @click="triggerPrint" class="btn-primary flex-1 justify-center py-2 text-xs inline-flex items-center gap-1.5">
            <Printer :size="14" /> {{ $t('cashierPage.printSplitSlipsBtn') }}
          </button>
          <button @click="showSplitModal = false" class="btn-outline py-2 px-4 text-xs">
            {{ $t('common.close') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <!-- MODAL: THERMAL POS FISCAL RECEIPT / Z-REPORT                             -->
    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <div v-if="showReceiptModal && (selectedOrder || zReportData || receiptMode === 'x_report')" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-sm space-y-4 bg-white dark:bg-warm-900 border text-warm-900 dark:text-white shadow-2xl">
        <!-- Thermal Printable Paper Area -->
        <div id="thermal-receipt" class="font-mono text-xs space-y-2 border border-dashed border-warm-300 dark:border-warm-700 p-4 bg-white text-black rounded-lg">
          <!-- Standard Order Receipt -->
          <template v-if="receiptMode === 'order' && selectedOrder">
            <div class="text-center border-b border-dashed border-warm-300 pb-2.5">
              <h3 class="font-bold text-sm tracking-wider uppercase">{{ APP_NAME }}</h3>
              <p class="text-[10px] text-warm-600">{{ $t('cashierPage.receiptTin') }}</p>
              <p class="text-[10px] text-warm-500 mt-0.5">{{ new Date(selectedOrder.created_at).toLocaleString() }}</p>
            </div>

            <div class="flex justify-between text-[11px] pt-1">
              <span>{{ $t('cashierPage.receiptOrderRef') }}: #{{ selectedOrder.ulid.slice(-8).toUpperCase() }}</span>
              <span class="capitalize font-bold">{{ selectedOrder.type }}</span>
            </div>

            <div class="flex justify-between text-[11px]">
              <span>{{ $t('cashierPage.receiptCustomer') }}: {{ selectedOrder.user?.name ?? 'Guest Diner' }}</span>
              <span v-if="selectedOrder.table">{{ $t('cashierPage.receiptTable') }}: {{ selectedOrder.table.name }}</span>
            </div>

            <hr class="border-dashed border-warm-300 my-1.5" />

            <!-- Items -->
            <div class="space-y-1">
              <div v-for="item in selectedOrder.items" :key="item.id" class="flex justify-between text-[11px]">
                <span class="truncate pr-1">{{ item.quantity }}x {{ item.name_snapshot }}</span>
                <span class="font-mono whitespace-nowrap">ETB {{ (Number(item.unit_price || item.price_snapshot) * item.quantity).toFixed(2) }}</span>
              </div>
            </div>

            <hr class="border-dashed border-warm-300 my-1.5" />

            <!-- Totals -->
            <div class="space-y-1 text-right text-[11px]">
              <div class="flex justify-between">
                <span>{{ $t('cashierPage.receiptSubtotal') }}:</span>
                <span class="font-mono">ETB {{ Number(selectedOrder.subtotal || selectedOrder.total).toFixed(2) }}</span>
              </div>
              <div v-if="selectedOrder.service_charge" class="flex justify-between text-warm-600">
                <span>{{ $t('cashierPage.receiptServiceCharge') }}:</span>
                <span class="font-mono">ETB {{ Number(selectedOrder.service_charge).toFixed(2) }}</span>
              </div>
              <div v-if="selectedOrder.tax" class="flex justify-between text-warm-600">
                <span>{{ $t('cashierPage.receiptVat') }}:</span>
                <span class="font-mono">ETB {{ Number(selectedOrder.tax).toFixed(2) }}</span>
              </div>
              <div v-if="selectedOrder.discount" class="flex justify-between text-green-700 font-bold">
                <span>{{ $t('cashierPage.receiptDiscount') }}:</span>
                <span class="font-mono">-ETB {{ Number(selectedOrder.discount).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between font-bold text-sm pt-1 border-t border-dashed border-warm-300">
                <span>{{ $t('cashierPage.receiptTotal') }}:</span>
                <span class="font-mono">ETB {{ Number(selectedOrder.total).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between text-[10px] text-warm-600 pt-1">
                <span>{{ $t('cashierPage.receiptPaymentMethod') }}:</span>
                <span class="uppercase font-bold">{{ selectedOrder.payment_method }} ({{ selectedOrder.payment_status }})</span>
              </div>
            </div>

            <div class="text-center text-[10px] text-warm-500 pt-3 border-t border-dashed border-warm-300 mt-2">
              <p>{{ $t('cashierPage.receiptThankYou') }}</p>
            </div>
          </template>

          <!-- Z-Report or X-Report Format -->
          <template v-else>
            <div class="text-center border-b border-dashed border-warm-300 pb-2">
              <h3 class="font-bold text-sm uppercase">{{ APP_NAME }}</h3>
              <p class="font-bold text-xs uppercase mt-0.5">{{ receiptMode === 'z_report' ? 'FINAL Z-REPORT (SHIFT CLOSE)' : 'MID-SHIFT X-REPORT' }}</p>
              <p class="text-[10px] text-warm-500">{{ new Date().toLocaleString() }}</p>
            </div>

            <div class="space-y-1 text-[11px] pt-1">
              <div class="flex justify-between">
                <span>Cashier:</span>
                <span class="font-bold">{{ zReportData?.cashier_name || auth.user?.name }}</span>
              </div>
              <div class="flex justify-between">
                <span>Opening Float:</span>
                <span class="font-mono">ETB {{ Number(zReportData?.opening_float || currentShiftData.summary?.opening_float || 0).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Cash Sales:</span>
                <span class="font-mono">ETB {{ Number(zReportData?.cash_sales || currentShiftData.summary?.cash_sales || 0).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Digital / Mobile:</span>
                <span class="font-mono">ETB {{ Number(zReportData?.digital_sales || currentShiftData.summary?.digital_sales || 0).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Room Charges:</span>
                <span class="font-mono">ETB {{ Number(zReportData?.room_charge_sales || currentShiftData.summary?.room_charge || 0).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between font-bold border-t border-dashed border-warm-300 pt-1 text-xs">
                <span>Total Settled Revenue:</span>
                <span class="font-mono">ETB {{ Number(zReportData?.total_sales || currentShiftData.summary?.total_sales || 0).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between pt-1">
                <span>Expected Till Cash:</span>
                <span class="font-mono font-bold">ETB {{ Number(zReportData?.expected_cash || currentShiftData.summary?.expected_cash || 0).toFixed(2) }}</span>
              </div>
              <div v-if="zReportData" class="flex justify-between font-bold">
                <span>Counted Cash:</span>
                <span class="font-mono">ETB {{ Number(zReportData.closing_cash_counted).toFixed(2) }}</span>
              </div>
              <div v-if="zReportData" class="flex justify-between font-bold">
                <span>Variance (Discrepancy):</span>
                <span :class="Number(zReportData.variance) === 0 ? 'text-green-700' : 'text-red-700'">
                  {{ Number(zReportData.variance) >= 0 ? '+' : '' }}ETB {{ Number(zReportData.variance).toFixed(2) }}
                </span>
              </div>
            </div>

            <div class="text-center text-[10px] text-warm-500 pt-3 border-t border-dashed border-warm-300 mt-2">
              <p>Cashier Signature: _______________________</p>
            </div>
          </template>
        </div>

        <div class="flex gap-2">
          <button @click="triggerPrint" class="btn-primary flex-1 justify-center py-2 text-xs inline-flex items-center gap-1.5">
            <Printer :size="14" /> {{ $t('trackingPage.receipt.printPdf') }}
          </button>
          <button @click="showReceiptModal = false" class="btn-outline flex-1 justify-center py-2 text-xs">
            {{ $t('common.close') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <!-- MODAL: OPEN SHIFT                                                        -->
    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <div v-if="showOpenShiftModal" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-sm space-y-4 bg-white dark:bg-warm-900 border text-warm-900 dark:text-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-warm-100 dark:border-warm-800 pb-3">
          <h3 class="font-heading font-bold text-base flex items-center gap-2">
            <Unlock :size="18" class="text-green-500" /> {{ $t('cashierPage.openShiftModalTitle') }}
          </h3>
          <button @click="showOpenShiftModal = false" class="text-warm-400 hover:text-warm-600">
            <X :size="16" />
          </button>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-warm-700 dark:text-warm-300 mb-1">{{ $t('cashierPage.openingFloatLabel') }}</label>
            <input v-model.number="openShiftFloat" type="number" step="10" min="0" class="input font-mono font-bold text-sm w-full" />
            <p class="text-[10px] text-warm-400 mt-1">{{ $t('cashierPage.openingFloatHelp') }}</p>
          </div>

          <div>
            <label class="block font-bold text-warm-700 dark:text-warm-300 mb-1">{{ $t('cashierPage.openingNotesLabel') }}</label>
            <textarea v-model="openShiftNotes" rows="2" class="input text-xs w-full" placeholder="Optional shift observations..."></textarea>
          </div>
        </div>

        <div class="flex gap-2 pt-2 border-t border-warm-100 dark:border-warm-800">
          <button
            @click="handleOpenShift"
            :disabled="shiftActionLoading"
            class="btn-primary flex-1 justify-center py-2 text-xs font-bold bg-green-600 hover:bg-green-700"
          >
            {{ $t('cashierPage.openShiftSubmitBtn') }}
          </button>
          <button @click="showOpenShiftModal = false" class="btn-outline py-2 px-3 text-xs">
            {{ $t('cashierPage.cancel') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <!-- MODAL: CLOSE SHIFT (Z-REPORT)                                            -->
    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <div v-if="showCloseShiftModal" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-md space-y-4 bg-white dark:bg-warm-900 border text-warm-900 dark:text-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-warm-100 dark:border-warm-800 pb-3">
          <h3 class="font-heading font-bold text-base flex items-center gap-2">
            <Lock :size="18" class="text-red-500" /> {{ $t('cashierPage.closeShiftModalTitle') }}
          </h3>
          <button @click="showCloseShiftModal = false" class="text-warm-400 hover:text-warm-600">
            <X :size="16" />
          </button>
        </div>

        <!-- Shift Expected Cash -->
        <div class="p-3.5 rounded-xl bg-warm-50 dark:bg-warm-800/50 space-y-2 text-xs">
          <div class="flex justify-between">
            <span class="text-warm-500">Expected Till Cash:</span>
            <span class="font-mono font-bold text-sm text-primary-600 dark:text-primary-400">
              ETB {{ Number(currentShiftData.summary?.expected_cash || 0).toFixed(2) }}
            </span>
          </div>
          <div class="flex justify-between text-warm-500">
            <span>Total Sales Settled:</span>
            <span class="font-mono">ETB {{ Number(currentShiftData.summary?.total_sales || 0).toFixed(2) }}</span>
          </div>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-warm-700 dark:text-warm-300 mb-1">{{ $t('cashierPage.closingCashCountedLabel') }}</label>
            <input v-model.number="closingCashCounted" type="number" step="1" min="0" class="input font-mono font-bold text-base w-full" />
            <p class="text-[10px] text-warm-400 mt-0.5">{{ $t('cashierPage.closingCashCountedHelp') }}</p>
          </div>

          <!-- Real-Time Variance -->
          <div class="p-3 rounded-xl border flex items-center justify-between font-semibold text-xs"
               :class="closingVariance === 0 ? 'bg-green-50 dark:bg-green-950/30 border-green-200 text-green-700 dark:text-green-300' : closingVariance < 0 ? 'bg-red-50 dark:bg-red-950/30 border-red-200 text-red-700 dark:text-red-300' : 'bg-blue-50 dark:bg-blue-950/30 border-blue-200 text-blue-700 dark:text-blue-300'">
            <span>{{ $t('cashierPage.varianceLabel') }}:</span>
            <span class="font-mono text-sm font-bold">
              {{ closingVariance === 0 ? $t('cashierPage.varianceExact') : closingVariance < 0 ? $t('cashierPage.varianceShort', { amount: Math.abs(closingVariance).toFixed(2) }) : $t('cashierPage.varianceOver', { amount: closingVariance.toFixed(2) }) }}
            </span>
          </div>

          <div>
            <label class="block font-bold text-warm-700 dark:text-warm-300 mb-1">{{ $t('cashierPage.closingNotesLabel') }}</label>
            <textarea v-model="closingShiftNotes" rows="2" class="input text-xs w-full" placeholder="Closing observations, discrepancies explanations..."></textarea>
          </div>
        </div>

        <div class="flex gap-2 pt-2 border-t border-warm-100 dark:border-warm-800">
          <button
            @click="handleCloseShift"
            :disabled="shiftActionLoading"
            class="btn-primary flex-1 justify-center py-2.5 text-xs font-bold bg-red-600 hover:bg-red-700"
          >
            {{ $t('cashierPage.closeShiftSubmitBtn') }}
          </button>
          <button @click="showCloseShiftModal = false" class="btn-outline py-2 px-3 text-xs">
            {{ $t('cashierPage.cancel') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <!-- MODAL: DRAWER CASH MOVEMENT (PAY IN / PAY OUT / DROP)                    -->
    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <div v-if="showDrawerActionModal" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-sm space-y-4 bg-white dark:bg-warm-900 border text-warm-900 dark:text-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-warm-100 dark:border-warm-800 pb-3">
          <h3 class="font-heading font-bold text-base flex items-center gap-2">
            <Wallet :size="18" class="text-primary-500" /> {{ $t('cashierPage.drawerActionModalTitle') }}
          </h3>
          <button @click="showDrawerActionModal = false" class="text-warm-400 hover:text-warm-600">
            <X :size="16" />
          </button>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-bold text-warm-700 dark:text-warm-300 mb-1">{{ $t('cashierPage.actionType') }}</label>
            <select v-model="drawerActionType" class="input text-xs w-full">
              <option value="pay_in">{{ $t('cashierPage.actionPayIn') }}</option>
              <option value="pay_out">{{ $t('cashierPage.actionPayOut') }}</option>
              <option value="drop">{{ $t('cashierPage.actionDrop') }}</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-warm-700 dark:text-warm-300 mb-1">{{ $t('cashierPage.actionAmount') }}</label>
            <input v-model.number="drawerActionAmount" type="number" step="10" min="1" class="input font-mono font-bold text-sm w-full" />
          </div>

          <div>
            <label class="block font-bold text-warm-700 dark:text-warm-300 mb-1">{{ $t('cashierPage.actionReason') }}</label>
            <input
              v-model="drawerActionReason"
              type="text"
              class="input text-xs w-full"
              :placeholder="$t('cashierPage.actionReasonPlaceholder')"
            />
          </div>
        </div>

        <div class="flex gap-2 pt-2 border-t border-warm-100 dark:border-warm-800">
          <button
            @click="handleDrawerAction"
            :disabled="shiftActionLoading || !drawerActionReason.trim() || Number(drawerActionAmount) <= 0"
            class="btn-primary flex-1 justify-center py-2 text-xs font-bold"
          >
            {{ $t('cashierPage.submitDrawerActionBtn') }}
          </button>
          <button @click="showDrawerActionModal = false" class="btn-outline py-2 px-3 text-xs">
            {{ $t('cashierPage.cancel') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <!-- MODAL: VOID ORDER                                                        -->
    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <div v-if="showVoidModal && selectedOrder" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-sm space-y-4 bg-white dark:bg-warm-900 border text-warm-900 dark:text-white shadow-2xl">
        <h3 class="font-heading font-bold text-base text-red-600 flex items-center gap-2">
          <Trash2 :size="18" /> {{ $t('cashierPage.voidModalTitle', { ulid: selectedOrder.ulid.slice(-6).toUpperCase() }) }}
        </h3>
        <p class="text-xs text-warm-500">{{ $t('cashierPage.voidPrompt') }}</p>

        <textarea
          v-model="actionReason"
          rows="3"
          class="input text-xs w-full"
          :placeholder="$t('cashierPage.voidReasonPlaceholder')"
        ></textarea>

        <div class="flex gap-2 pt-2 border-t border-warm-100 dark:border-warm-800">
          <button
            @click="handleConfirmVoid"
            :disabled="actionLoading || !actionReason.trim()"
            class="btn-primary flex-1 justify-center py-2 text-xs font-bold bg-red-600 hover:bg-red-700"
          >
            {{ $t('cashierPage.confirmVoidBtn') }}
          </button>
          <button @click="showVoidModal = false" class="btn-outline py-2 px-3 text-xs">
            {{ $t('cashierPage.cancel') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <!-- MODAL: REFUND ORDER                                                      -->
    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <div v-if="showRefundModal && selectedOrder" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-sm space-y-4 bg-white dark:bg-warm-900 border text-warm-900 dark:text-white shadow-2xl">
        <h3 class="font-heading font-bold text-base text-warm-900 dark:text-white flex items-center gap-2">
          <AlertCircle :size="18" class="text-amber-500" /> {{ $t('cashierPage.refundModalTitle', { ulid: selectedOrder.ulid.slice(-6).toUpperCase() }) }}
        </h3>
        <p class="text-xs text-warm-500">
          {{ $t('cashierPage.refundPrompt', { amount: Number(selectedOrder.total).toFixed(2) }) }}
        </p>

        <textarea
          v-model="actionReason"
          rows="3"
          class="input text-xs w-full"
          :placeholder="$t('cashierPage.refundReasonPlaceholder')"
        ></textarea>

        <div class="flex gap-2 pt-2 border-t border-warm-100 dark:border-warm-800">
          <button
            @click="handleConfirmRefund"
            :disabled="actionLoading || !actionReason.trim()"
            class="btn-primary flex-1 justify-center py-2 text-xs font-bold bg-amber-600 hover:bg-amber-700"
          >
            {{ $t('cashierPage.confirmRefundBtn') }}
          </button>
          <button @click="showRefundModal = false" class="btn-outline py-2 px-3 text-xs">
            {{ $t('cashierPage.cancel') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@media print {
  /* Hide every part of the application except the thermal receipt slip */
  body * {
    visibility: hidden !important;
  }
  #thermal-receipt, #thermal-receipt * {
    visibility: visible !important;
  }
  #thermal-receipt {
    position: fixed !important;
    left: 0 !important;
    top: 0 !important;
    width: 80mm !important;
    padding: 4mm !important;
    border: none !important;
    margin: 0 !important;
    font-size: 11px !important;
    color: black !important;
    background: white !important;
  }
}
</style>
