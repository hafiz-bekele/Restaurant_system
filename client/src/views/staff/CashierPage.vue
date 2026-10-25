<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { staffApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { useAuthStore } from '../../stores/auth'
import {
  CreditCard, Banknote, Printer, CheckCircle, RefreshCw, AlertCircle,
  ShoppingBag, BedDouble, Receipt, Plus, Minus, Trash2, Search, X,
  Clock, User, Phone, Tag, Sparkles, ShieldCheck, ArrowRight,
  Lock, Unlock, TrendingUp, Wallet, Smartphone, Split, FileText,
  Filter, SlidersHorizontal, UtensilsCrossed, Truck, Calendar,
  Building2, ArrowUpDown, RotateCcw, Layers, ChevronDown, ChevronUp, Globe, CalendarCheck,
  AlertTriangle, Check
} from '@lucide/vue'
import TableSkeleton from '../../components/ui/TableSkeleton.vue'
import { APP_NAME } from '../../config'

const { t } = useI18n()
const ui = useUiStore()
const auth = useAuthStore()

// ── State ─────────────────────────────────────────────────────────────────
const loading = ref(true)
const activeTab = ref('pos') // 'pos', 'orders', 'shifts'
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

// ── Currency & Formatting Helpers ─────────────────────────────────────────
function formatETB(amount) {
  const num = Number(amount || 0)
  return 'ETB ' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const lastClosedShift = computed(() => {
  return shiftsHistory.value.find(s => s.status === 'closed') || shiftsHistory.value[0] || null
})

const lastShiftCaption = computed(() => {
  if (!lastClosedShift.value) return t('cashierPage.noPreviousShift')
  const dt = new Date(lastClosedShift.value.closed_at || lastClosedShift.value.opened_at)
  const dateStr = dt.toLocaleDateString([], { day: '2-digit', month: 'short', year: 'numeric' })
  const total = formatETB(lastClosedShift.value.total_sales || lastClosedShift.value.closing_cash_counted || 0)
  return t('cashierPage.lastShiftCaption', { date: dateStr, total })
})

function isZeroCard(val) {
  return !currentShiftData.value.has_active_shift || Number(val || 0) === 0
}

function canRefund(order) {
  if (!currentShiftData.value.has_active_shift) return false
  if (order.payment_status !== 'paid') return false
  return true
}

function getRefundDisabledReason(order) {
  if (!currentShiftData.value.has_active_shift) return t('cashierPage.tooltipRefundUnavailable')
  if (order.payment_status === 'refunded') return t('cashierPage.tooltipAlreadyRefunded')
  if (order.payment_status !== 'paid') return t('cashierPage.tooltipOnlySettledCanRefund')
  return t('cashierPage.refundOrder')
}

// ── Order Details Side Panel Drawer ───────────────────────────────────────
const showOrderDrawer = ref(false)
const drawerOrder = ref(null)

function openOrderDrawer(order) {
  drawerOrder.value = order
  showOrderDrawer.value = true
}

function switchTab(tab) {
  if (tab === 'pos' && !currentShiftData.value.has_active_shift) {
    ui.toast(t('cashierPage.tooltipOpenShift'), 'warning')
    return
  }
  activeTab.value = tab
  if (tab === 'shifts') {
    loadShiftHistory()
  }
}

// ── Orders Multi-Dimensional Filter & Search ──────────────────────────────
const statusFilter = ref('all') // 'all', 'pending', 'paid', 'refunded'
const typeFilter = ref('all') // 'all', 'dine_in', 'pickup', 'delivery', 'room_service'
const methodFilter = ref('all') // 'all', 'cash', 'telebirr', 'cbe_birr', 'card', 'room_charge'
const dateWindow = ref('current_shift') // 'current_shift', 'today', 'all', 'custom'
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
    dateWindow.value !== 'current_shift' ||
    Boolean(customDate.value) ||
    sortBy.value !== 'newest' ||
    Boolean(searchQuery.value.trim())
})

const activeFiltersCount = computed(() => {
  let count = 0
  if (statusFilter.value !== 'all') count++
  if (typeFilter.value !== 'all') count++
  if (methodFilter.value !== 'all') count++
  if (dateWindow.value !== 'current_shift') count++
  if (customDate.value) count++
  if (sortBy.value !== 'newest') count++
  if (searchQuery.value.trim()) count++
  return count
})

function resetFilters() {
  statusFilter.value = 'all'
  typeFilter.value = 'all'
  methodFilter.value = 'all'
  dateWindow.value = 'current_shift'
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

    // 4. Date window filter (Default: Current Shift)
    if (dateWindow.value === 'current_shift') {
      if (currentShiftData.value.has_active_shift && currentShiftData.value.shift?.opened_at) {
        const shiftStart = new Date(currentShiftData.value.shift.opened_at).getTime()
        const orderTime = new Date(order.created_at).getTime()
        if (orderTime < shiftStart) return false
      }
    } else if (dateWindow.value === 'today') {
      const todayStr = new Date().toISOString().slice(0, 10)
      const orderDateStr = new Date(order.created_at).toISOString().slice(0, 10)
      if (todayStr !== orderDateStr) return false
    } else if (dateWindow.value === 'custom' && customDate.value) {
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
    const [ordersRes, summaryRes, shiftRes, historyRes] = await Promise.all([
      staffApi.cashierOrders(),
      staffApi.cashierSummary(),
      staffApi.cashierCurrentShift(),
      staffApi.cashierShiftHistory().catch(() => ({ data: { data: [] } })),
    ])
    orders.value = ordersRes.data.data ?? []
    summary.value = summaryRes.data.data ?? summary.value
    currentShiftData.value = shiftRes.data.data ?? currentShiftData.value
    if (historyRes?.data?.data) {
      shiftsHistory.value = historyRes.data.data
    }
    // If shift is closed and active tab is pos, switch to orders
    if (!currentShiftData.value.has_active_shift && activeTab.value === 'pos') {
      activeTab.value = 'orders'
    }
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
  <div class="max-w-7xl mx-auto space-y-4">
    <!-- Section 1: Command Hub Header (Title, Shift Status Chip, Single Primary Action, Refresh Icon) -->
    <div class="card p-4 sm:p-5 bg-white dark:bg-warm-900 border border-warm-200/90 dark:border-warm-800/90 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-card">
      <div class="flex items-center gap-3.5">
        <div class="p-2.5 rounded-2xl bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 shrink-0 border border-warm-200/80 dark:border-warm-700/80">
          <CreditCard :size="22" />
        </div>
        <div>
          <div class="flex items-center gap-2.5 flex-wrap">
            <h1 class="font-heading text-lg sm:text-xl font-bold text-warm-900 dark:text-white">
              {{ $t('cashierPage.title') }}
            </h1>
            <!-- Open shift: Compact status chip with green dot, opened time, cashier name, float -->
            <span
              v-if="currentShiftData.has_active_shift"
              class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-xs font-medium bg-green-50 dark:bg-green-950/40 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-800"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
              {{
                $t('cashierPage.shiftOpenChip', {
                  time: new Date(currentShiftData.shift?.opened_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                  name: currentShiftData.shift?.user?.name || auth.user?.name || 'Cashier',
                  float: formatETB(currentShiftData.shift?.opening_float || 0)
                })
              }}
            </span>
          </div>
          <p class="text-xs text-warm-500 dark:text-warm-400 mt-1">
            {{ $t('cashierPage.subtitle') }}
          </p>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2 shrink-0 self-start sm:self-auto flex-wrap">
        <!-- Open shift secondary till actions -->
        <template v-if="currentShiftData.has_active_shift">
          <button
            @click="showDrawerActionModal = true"
            class="btn-outline text-xs py-2 px-3 inline-flex items-center gap-1.5 font-medium rounded-xl border-warm-300 dark:border-warm-700 text-warm-700 dark:text-warm-300 hover:bg-warm-100 dark:hover:bg-warm-800 min-h-[40px] cursor-pointer"
          >
            <Wallet :size="14" />
            <span>{{ $t('cashierPage.drawerActionBtn') }}</span>
          </button>
          <button
            @click="openXReport"
            class="btn-ghost text-xs py-2 px-3 inline-flex items-center gap-1.5 font-medium rounded-xl text-warm-600 dark:text-warm-300 hover:bg-warm-100 dark:hover:bg-warm-800 min-h-[40px] cursor-pointer"
          >
            <FileText :size="14" />
            <span>{{ $t('cashierPage.xReportBtn') }}</span>
          </button>
          <!-- Close Shift secondary button -->
          <button
            @click="closingCashCounted = currentShiftData.summary?.expected_cash || 0; showCloseShiftModal = true"
            class="btn-outline text-xs py-2 px-3 inline-flex items-center gap-1.5 text-warm-700 dark:text-warm-300 border-warm-300 dark:border-warm-700 hover:bg-red-50 dark:hover:bg-red-950/30 hover:text-red-600 dark:hover:text-red-400 hover:border-red-300 font-medium rounded-xl min-h-[40px] cursor-pointer"
          >
            <Lock :size="14" />
            <span>{{ $t('cashierPage.closeShiftSecondary') }}</span>
          </button>
          <!-- Single primary action (orange): + New Sale -->
          <button
            @click="switchTab('pos')"
            class="btn-primary text-xs py-2 px-3.5 inline-flex items-center gap-1.5 bg-primary-500 hover:bg-primary-600 text-white font-semibold rounded-xl shadow-xs min-h-[40px] cursor-pointer"
          >
            <Plus :size="14" />
            <span>{{ $t('cashierPage.newSale') }}</span>
          </button>
        </template>

        <!-- Closed shift primary action (orange): Open Register Shift -->
        <button
          v-else
          @click="showOpenShiftModal = true"
          class="btn-primary text-xs py-2 px-4 inline-flex items-center gap-1.5 bg-primary-500 hover:bg-primary-600 text-white font-semibold rounded-xl shadow-xs min-h-[40px] cursor-pointer"
        >
          <Unlock :size="14" />
          <span>{{ $t('cashierPage.openShiftBtn') }}</span>
        </button>

        <!-- Refresh orders icon button with tooltip and aria-label (Touch target >= 44px) -->
        <button
          @click="fetchData"
          class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-xl text-warm-500 hover:text-warm-800 dark:hover:text-warm-200 hover:bg-warm-100 dark:hover:bg-warm-800 transition-colors cursor-pointer"
          :title="$t('cashierPage.refreshOrders')"
          :aria-label="$t('cashierPage.refreshOrders')"
        >
          <RefreshCw :size="16" :class="loading ? 'animate-spin' : ''" />
        </button>
      </div>
    </div>

    <!-- Closed Shift Full-Width Amber Banner -->
    <div
      v-if="!currentShiftData.has_active_shift"
      class="rounded-xl border border-amber-300/90 dark:border-amber-700/60 bg-amber-50 dark:bg-amber-950/50 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-2xs"
      role="alert"
    >
      <div class="flex items-start sm:items-center gap-3.5">
        <div class="p-2.5 rounded-xl bg-amber-200/80 dark:bg-amber-900/60 text-amber-800 dark:text-amber-200 shrink-0">
          <AlertTriangle :size="20" />
        </div>
        <div>
          <h2 class="text-sm font-bold text-amber-900 dark:text-amber-100">
            {{ $t('cashierPage.registerClosedTitle') }}
          </h2>
          <p class="text-xs text-amber-800 dark:text-amber-200/90 mt-0.5">
            {{ $t('cashierPage.registerClosedBanner') }}
          </p>
        </div>
      </div>
      <button
        @click="showOpenShiftModal = true"
        class="btn-primary text-xs py-2 px-4 inline-flex items-center justify-center gap-2 bg-primary-500 hover:bg-primary-600 text-white font-semibold rounded-xl shadow-xs shrink-0 min-h-[40px] cursor-pointer"
      >
        <Unlock :size="14" />
        <span>{{ $t('cashierPage.openShiftBtn') }}</span>
      </button>
    </div>

    <!-- Section 2: Summary Cards (Exact required order, neutral styling, reduced emphasis for zero, ETB format, primary text color, caption when closed) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3">
      <!-- 1. Total settled (largest, emphasized) -->
      <div
        :class="[
          'card p-4 bg-white dark:bg-warm-900 border border-warm-200/90 dark:border-warm-800/90 rounded-xl transition-all shadow-2xs',
          isZeroCard(summary.grand_total) ? 'opacity-65' : ''
        ]"
      >
        <span class="text-xs font-medium text-warm-600 dark:text-warm-400 flex items-center justify-between">
          <span>{{ $t('cashierPage.totalSettled') }}</span>
          <TrendingUp :size="14" class="text-warm-400" />
        </span>
        <p class="text-xl sm:text-2xl font-bold font-mono tabular-nums text-warm-900 dark:text-white mt-1.5">
          <template v-if="currentShiftData.has_active_shift">
            {{ formatETB(summary.grand_total || 0) }}
          </template>
          <template v-else>
            —
          </template>
        </p>
        <p v-if="!currentShiftData.has_active_shift" class="text-[11px] text-warm-500 dark:text-warm-400 mt-1">
          {{ lastShiftCaption }}
        </p>
        <p v-else class="text-[11px] text-warm-400 mt-1">
          {{ summary.orders_count || 0 }} orders settled
        </p>
      </div>

      <!-- 2. Cash in till -->
      <div
        :class="[
          'card p-4 bg-white dark:bg-warm-900 border border-warm-200/90 dark:border-warm-800/90 rounded-xl transition-all shadow-2xs',
          isZeroCard(currentShiftData.summary?.expected_cash || summary.cash_total) ? 'opacity-65' : ''
        ]"
      >
        <span class="text-xs font-medium text-warm-600 dark:text-warm-400 flex items-center justify-between">
          <span>{{ $t('cashierPage.cashInDrawer') }}</span>
          <Banknote :size="14" class="text-warm-400" />
        </span>
        <p class="text-base sm:text-lg font-semibold font-mono tabular-nums text-warm-900 dark:text-white mt-1.5">
          <template v-if="currentShiftData.has_active_shift">
            {{ formatETB(currentShiftData.summary?.expected_cash || summary.cash_total || 0) }}
          </template>
          <template v-else>
            —
          </template>
        </p>
        <p v-if="!currentShiftData.has_active_shift" class="text-[11px] text-warm-500 dark:text-warm-400 mt-1">
          {{ lastShiftCaption }}
        </p>
        <p v-else class="text-[11px] text-warm-400 mt-1">
          Physical register till
        </p>
      </div>

      <!-- 3. Telebirr & mobile pay -->
      <div
        :class="[
          'card p-4 bg-white dark:bg-warm-900 border border-warm-200/90 dark:border-warm-800/90 rounded-xl transition-all shadow-2xs',
          isZeroCard(summary.telebirr_total || summary.digital_total) ? 'opacity-65' : ''
        ]"
      >
        <span class="text-xs font-medium text-warm-600 dark:text-warm-400 flex items-center justify-between">
          <span>{{ $t('cashierPage.digitalCollected') }}</span>
          <Smartphone :size="14" class="text-warm-400" />
        </span>
        <p class="text-base sm:text-lg font-semibold font-mono tabular-nums text-warm-900 dark:text-white mt-1.5">
          <template v-if="currentShiftData.has_active_shift">
            {{ formatETB(summary.telebirr_total || summary.digital_total || 0) }}
          </template>
          <template v-else>
            —
          </template>
        </p>
        <p v-if="!currentShiftData.has_active_shift" class="text-[11px] text-warm-500 dark:text-warm-400 mt-1">
          {{ lastShiftCaption }}
        </p>
        <p v-else class="text-[11px] text-warm-400 mt-1">
          Mobile QR / USSD
        </p>
      </div>

      <!-- 4. CBE Birr -->
      <div
        :class="[
          'card p-4 bg-white dark:bg-warm-900 border border-warm-200/90 dark:border-warm-800/90 rounded-xl transition-all shadow-2xs',
          isZeroCard(summary.cbe_total) ? 'opacity-65' : ''
        ]"
      >
        <span class="text-xs font-medium text-warm-600 dark:text-warm-400 flex items-center justify-between">
          <span>{{ $t('cashierPage.cbeCollected') }}</span>
          <Building2 :size="14" class="text-warm-400" />
        </span>
        <p class="text-base sm:text-lg font-semibold font-mono tabular-nums text-warm-900 dark:text-white mt-1.5">
          <template v-if="currentShiftData.has_active_shift">
            {{ formatETB(summary.cbe_total || 0) }}
          </template>
          <template v-else>
            —
          </template>
        </p>
        <p v-if="!currentShiftData.has_active_shift" class="text-[11px] text-warm-500 dark:text-warm-400 mt-1">
          {{ lastShiftCaption }}
        </p>
        <p v-else class="text-[11px] text-warm-400 mt-1">
          Bank mobile wallet
        </p>
      </div>

      <!-- 5. Card / POS -->
      <div
        :class="[
          'card p-4 bg-white dark:bg-warm-900 border border-warm-200/90 dark:border-warm-800/90 rounded-xl transition-all shadow-2xs',
          isZeroCard(summary.card_total) ? 'opacity-65' : ''
        ]"
      >
        <span class="text-xs font-medium text-warm-600 dark:text-warm-400 flex items-center justify-between">
          <span>{{ $t('cashierPage.cardCollected') }}</span>
          <CreditCard :size="14" class="text-warm-400" />
        </span>
        <p class="text-base sm:text-lg font-semibold font-mono tabular-nums text-warm-900 dark:text-white mt-1.5">
          <template v-if="currentShiftData.has_active_shift">
            {{ formatETB(summary.card_total || 0) }}
          </template>
          <template v-else>
            —
          </template>
        </p>
        <p v-if="!currentShiftData.has_active_shift" class="text-[11px] text-warm-500 dark:text-warm-400 mt-1">
          {{ lastShiftCaption }}
        </p>
        <p v-else class="text-[11px] text-warm-400 mt-1">
          POS terminal swiped
        </p>
      </div>

      <!-- 6. Hotel Folio (Room Charges) -->
      <div
        :class="[
          'card p-4 bg-white dark:bg-warm-900 border border-warm-200/90 dark:border-warm-800/90 rounded-xl transition-all shadow-2xs',
          isZeroCard(summary.room_charge_total) ? 'opacity-65' : ''
        ]"
      >
        <span class="text-xs font-medium text-warm-600 dark:text-warm-400 flex items-center justify-between">
          <span>{{ $t('cashierPage.roomChargeCollected') }}</span>
          <BedDouble :size="14" class="text-warm-400" />
        </span>
        <p class="text-base sm:text-lg font-semibold font-mono tabular-nums text-warm-900 dark:text-white mt-1.5">
          <template v-if="currentShiftData.has_active_shift">
            {{ formatETB(summary.room_charge_total || 0) }}
          </template>
          <template v-else>
            —
          </template>
        </p>
        <p v-if="!currentShiftData.has_active_shift" class="text-[11px] text-warm-500 dark:text-warm-400 mt-1">
          {{ lastShiftCaption }}
        </p>
        <p v-else class="text-[11px] text-warm-400 mt-1">
          Room guest folio bills
        </p>
      </div>
    </div>

    <!-- Section 3: Master Workstation Console Container -->
    <div class="card bg-white dark:bg-warm-900 border border-warm-200/90 dark:border-warm-800/90 rounded-xl overflow-hidden shadow-card">
      <!-- Underlined Navigation Tabs: Quick POS Register (1st), Orders & Settlement (2nd), Shift History & Z-Reports (3rd) -->
      <div class="border-b border-warm-200 dark:border-warm-800 bg-white dark:bg-warm-900 px-4 sm:px-6">
        <nav class="flex items-center gap-6 overflow-x-auto scrollbar-none" aria-label="Workstation tabs">
          <!-- Tab 1: Quick POS Register (1st task, disabled with tooltip if closed) -->
          <button
            @click="switchTab('pos')"
            :disabled="!currentShiftData.has_active_shift"
            :title="!currentShiftData.has_active_shift ? $t('cashierPage.tooltipOpenShift') : ''"
            :class="[
              'py-3.5 text-xs sm:text-sm font-semibold inline-flex items-center gap-2 border-b-2 transition-all cursor-pointer min-h-[44px] whitespace-nowrap',
              activeTab === 'pos'
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-warm-600 dark:text-warm-400 hover:text-warm-900 dark:hover:text-warm-200 hover:border-warm-300 dark:hover:border-warm-700',
              !currentShiftData.has_active_shift ? 'opacity-50 cursor-not-allowed' : ''
            ]"
          >
            <ShoppingBag :size="16" />
            <span>{{ $t('cashierPage.tabs.pos') }}</span>
            <Lock v-if="!currentShiftData.has_active_shift" :size="13" class="text-amber-500" />
            <span
              v-else-if="ticketItems.length > 0"
              class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-primary-500 text-white shadow-2xs"
            >
              {{ ticketItems.length }}
            </span>
          </button>

          <!-- Tab 2: Orders & Settlement -->
          <button
            @click="switchTab('orders')"
            :class="[
              'py-3.5 text-xs sm:text-sm font-semibold inline-flex items-center gap-2 border-b-2 transition-all cursor-pointer min-h-[44px] whitespace-nowrap',
              activeTab === 'orders'
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-warm-600 dark:text-warm-400 hover:text-warm-900 dark:hover:text-warm-200 hover:border-warm-300 dark:hover:border-warm-700'
            ]"
          >
            <Receipt :size="16" />
            <span>{{ $t('cashierPage.tabs.orders') }}</span>
            <span
              v-if="unpaidOrdersCount > 0"
              class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-amber-500 text-white shadow-2xs"
            >
              {{ unpaidOrdersCount }}
            </span>
          </button>

          <!-- Tab 3: Shift History & Z-Reports -->
          <button
            @click="switchTab('shifts')"
            :class="[
              'py-3.5 text-xs sm:text-sm font-semibold inline-flex items-center gap-2 border-b-2 transition-all cursor-pointer min-h-[44px] whitespace-nowrap',
              activeTab === 'shifts'
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-warm-600 dark:text-warm-400 hover:text-warm-900 dark:hover:text-warm-200 hover:border-warm-300 dark:hover:border-warm-700'
            ]"
          >
            <FileText :size="16" />
            <span>{{ $t('cashierPage.tabs.shifts') }}</span>
          </button>
        </nav>
      </div>

      <!-- ───────────────────────────────────────────────────────────────────────── -->
      <!-- TAB: ORDERS & BILL SETTLEMENT                                             -->
      <!-- ───────────────────────────────────────────────────────────────────────── -->
      <div v-if="activeTab === 'orders'">
        <!-- Filter Bar: Segmented Status Controls + Date Range Selector + Shortened Search -->
        <div class="p-3 sm:px-4 bg-warm-50/70 dark:bg-warm-850/60 border-b border-warm-200/80 dark:border-warm-800/80 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
          <!-- Segmented Status Control with Count on Every Option -->
          <div class="inline-flex p-1 rounded-xl bg-warm-200/60 dark:bg-warm-800 border border-warm-200 dark:border-warm-700/70 gap-1 overflow-x-auto max-w-full">
            <!-- All -->
            <button
              @click="statusFilter = 'all'"
              :class="[
                'px-3 py-1.5 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5 transition-all cursor-pointer whitespace-nowrap min-h-[36px]',
                statusFilter === 'all'
                  ? 'bg-white dark:bg-warm-700 text-warm-900 dark:text-white shadow-xs font-bold'
                  : 'text-warm-600 dark:text-warm-400 hover:text-warm-900 dark:hover:text-white'
              ]"
            >
              <span>{{ $t('cashierPage.filterAll') }}</span>
              <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-warm-100 dark:bg-warm-600 text-warm-700 dark:text-warm-200">
                {{ orders.length }}
              </span>
            </button>

            <!-- Unpaid / Action needed -->
            <button
              @click="statusFilter = 'pending'"
              :class="[
                'px-3 py-1.5 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5 transition-all cursor-pointer whitespace-nowrap min-h-[36px]',
                statusFilter === 'pending'
                  ? 'bg-amber-500 text-white shadow-xs font-bold'
                  : 'text-warm-600 dark:text-warm-400 hover:text-warm-900 dark:hover:text-white'
              ]"
            >
              <Clock :size="13" />
              <span>{{ $t('cashierPage.filterPending') }}</span>
              <span :class="['px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold', statusFilter === 'pending' ? 'bg-amber-600 text-white' : 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300']">
                {{ unpaidOrdersCount }}
              </span>
            </button>

            <!-- Paid -->
            <button
              @click="statusFilter = 'paid'"
              :class="[
                'px-3 py-1.5 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5 transition-all cursor-pointer whitespace-nowrap min-h-[36px]',
                statusFilter === 'paid'
                  ? 'bg-green-600 text-white shadow-xs font-bold'
                  : 'text-warm-600 dark:text-warm-400 hover:text-warm-900 dark:hover:text-white'
              ]"
            >
              <Check :size="13" />
              <span>{{ $t('cashierPage.filterPaid') }}</span>
              <span :class="['px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold', statusFilter === 'paid' ? 'bg-green-700 text-white' : 'bg-green-100 dark:bg-green-950/60 text-green-800 dark:text-green-300']">
                {{ paidOrdersCount }}
              </span>
            </button>

            <!-- Refunded / Void -->
            <button
              @click="statusFilter = 'refunded'"
              :class="[
                'px-3 py-1.5 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5 transition-all cursor-pointer whitespace-nowrap min-h-[36px]',
                statusFilter === 'refunded'
                  ? 'bg-warm-900 dark:bg-white text-white dark:text-warm-900 shadow-xs font-bold'
                  : 'text-warm-600 dark:text-warm-400 hover:text-warm-900 dark:hover:text-white'
              ]"
            >
              <RotateCcw :size="13" />
              <span>{{ $t('cashierPage.filterRefunded') }}</span>
              <span :class="['px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold', statusFilter === 'refunded' ? 'bg-warm-700 dark:bg-warm-200 text-white dark:text-warm-900' : 'bg-warm-200/80 dark:bg-warm-600 text-warm-700 dark:text-warm-200']">
                {{ refundedOrdersCount }}
              </span>
            </button>
          </div>

          <!-- Date Range Filter + Shortened Search + Advanced Filter Toggle -->
          <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
            <!-- Date Range Selector (default: current shift) -->
            <div class="relative shrink-0">
              <select
                v-model="dateWindow"
                class="bg-white dark:bg-warm-800 border border-warm-200/90 dark:border-warm-700/80 text-warm-800 dark:text-warm-200 text-xs font-medium rounded-xl py-2 pl-3 pr-8 shadow-2xs focus:outline-none focus:ring-2 focus:ring-primary-500/20 min-h-[40px] cursor-pointer"
                aria-label="Filter orders by date range"
              >
                <option value="current_shift">{{ $t('cashierPage.currentShiftDateFilter') }}</option>
                <option value="today">{{ $t('cashierPage.dateToday') }}</option>
                <option value="all">{{ $t('cashierPage.dateAll') }}</option>
                <option value="custom">{{ $t('cashierPage.dateCustom') }}</option>
              </select>
            </div>

            <!-- Custom Date Picker if selected -->
            <input
              v-if="dateWindow === 'custom'"
              v-model="customDate"
              type="date"
              class="bg-white dark:bg-warm-800 border border-warm-200/90 dark:border-warm-700/80 text-warm-800 dark:text-warm-200 text-xs font-medium rounded-xl py-1.5 px-2.5 shadow-2xs min-h-[40px]"
            />

            <!-- Shortened Search Box (Never clipped) -->
            <div class="relative flex-1 sm:w-60 min-w-[190px]">
              <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
              <input
                v-model="searchQuery"
                type="text"
                class="w-full bg-white dark:bg-warm-800 border border-warm-200/90 dark:border-warm-700/80 text-warm-900 dark:text-white rounded-xl pl-9 pr-8 text-xs py-2 placeholder:text-warm-400 shadow-2xs focus:outline-none focus:ring-2 focus:ring-primary-500/20 min-h-[40px]"
                :placeholder="$t('cashierPage.searchPlaceholderShort')"
                aria-label="Search order, guest, phone"
              />
              <button
                v-if="searchQuery"
                @click="searchQuery = ''"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-warm-400 hover:text-warm-600 dark:hover:text-warm-200 p-1"
                aria-label="Clear search"
              >
                <X :size="13" />
              </button>
            </div>

            <!-- Advanced Filters Button -->
            <button
              @click="showAdvancedFilters = !showAdvancedFilters"
              :class="[
                'px-3 py-2 rounded-xl text-xs font-medium inline-flex items-center gap-1.5 border transition-all cursor-pointer min-h-[40px] shrink-0',
                showAdvancedFilters || hasActiveFilters
                  ? 'bg-warm-900 text-white dark:bg-white dark:text-warm-900 border-transparent font-semibold shadow-xs'
                  : 'bg-white dark:bg-warm-800 text-warm-700 dark:text-warm-300 border-warm-200/90 dark:border-warm-700/80 hover:bg-warm-50 dark:hover:bg-warm-750'
              ]"
              aria-label="Toggle advanced filters"
            >
              <SlidersHorizontal :size="13" />
              <span class="hidden sm:inline">{{ $t('cashierPage.filtersTitle') }}</span>
              <span
                v-if="activeFiltersCount > 0"
                class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-primary-500 text-white font-mono"
              >
                {{ activeFiltersCount }}
              </span>
              <ChevronDown :size="12" :class="['transition-transform duration-200', showAdvancedFilters ? 'rotate-180' : '']" />
            </button>

            <!-- Reset Filters -->
            <button
              v-if="hasActiveFilters"
              @click="resetFilters"
              class="p-2 text-warm-500 hover:text-red-500 transition-colors shrink-0"
              :title="$t('cashierPage.clearAll')"
              aria-label="Clear all filters"
            >
              <RotateCcw :size="14" />
            </button>
          </div>
        </div>

        <!-- Collapsible Multi-Dimensional Filter Controls Panel -->
        <transition name="filter-slide">
          <div
            v-if="showAdvancedFilters"
            class="bg-warm-50/80 dark:bg-warm-900/80 border-b border-warm-200/80 dark:border-warm-800/80 backdrop-blur-xs shadow-inner overflow-hidden"
          >
            <div class="px-4 py-2 bg-warm-100/70 dark:bg-warm-800/70 border-b border-warm-200/60 dark:border-warm-700/60 flex items-center justify-between text-xs">
              <div class="flex items-center gap-2 font-bold text-warm-700 dark:text-warm-200">
                <SlidersHorizontal :size="13" class="text-warm-500" />
                <span>{{ $t('cashierPage.filtersTitle') }}</span>
              </div>
              <button
                @click="showAdvancedFilters = false"
                class="px-2.5 py-1 rounded-lg text-warm-600 dark:text-warm-300 hover:text-warm-900 dark:hover:text-white hover:bg-warm-200/70 dark:hover:bg-warm-700/70 transition-colors inline-flex items-center gap-1 text-[11px] font-bold cursor-pointer"
              >
                <span>Collapse</span>
                <ChevronUp :size="13" />
              </button>
            </div>

            <div class="p-4 sm:p-5 max-h-[65vh] sm:max-h-[380px] overflow-y-auto filter-scroll space-y-4">
              <!-- Service Channel -->
              <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-2">
                  {{ $t('cashierPage.filterType') }}
                </label>
                <div class="flex items-center gap-1.5 overflow-x-auto filter-scroll pb-1 sm:flex-wrap">
                  <button
                    v-for="opt in [
                      { id: 'all', label: $t('cashierPage.filterAllTypes'), icon: Globe },
                      { id: 'dine_in', label: $t('cashierPage.filterDineIn'), icon: UtensilsCrossed },
                      { id: 'pickup', label: $t('cashierPage.filterPickup'), icon: ShoppingBag },
                      { id: 'delivery', label: $t('cashierPage.filterDelivery'), icon: Truck },
                      { id: 'room_service', label: $t('cashierPage.filterRoomService'), icon: BedDouble },
                    ]"
                    :key="opt.id"
                    type="button"
                    @click="typeFilter = opt.id"
                    :class="[
                      'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all inline-flex items-center gap-1.5 cursor-pointer shadow-2xs shrink-0',
                      typeFilter === opt.id
                        ? 'bg-warm-900 text-white dark:bg-white dark:text-warm-900 font-bold'
                        : 'bg-white dark:bg-warm-800 text-warm-700 dark:text-warm-300 border border-warm-200 dark:border-warm-700 hover:bg-warm-100'
                    ]"
                  >
                    <component :is="opt.icon" :size="13" />
                    <span>{{ opt.label }}</span>
                  </button>
                </div>
              </div>

              <!-- Payment Method with Banknote -->
              <div class="pt-3 border-t border-warm-200/60 dark:border-warm-800/60">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-2">
                  {{ $t('cashierPage.filterMethod') }}
                </label>
                <div class="flex items-center gap-1.5 overflow-x-auto filter-scroll pb-1 sm:flex-wrap">
                  <button
                    v-for="opt in [
                      { id: 'all', label: $t('cashierPage.filterAllMethods'), icon: Wallet },
                      { id: 'cash', label: $t('cashierPage.filterCashOnly'), icon: Banknote },
                      { id: 'telebirr', label: $t('cashierPage.filterTelebirrOnly'), icon: Smartphone },
                      { id: 'cbe_birr', label: $t('cashierPage.filterCbeOnly'), icon: Building2 },
                      { id: 'card', label: $t('cashierPage.filterCardOnly'), icon: CreditCard },
                      { id: 'room_charge', label: $t('cashierPage.filterRoomOnly'), icon: BedDouble },
                    ]"
                    :key="opt.id"
                    type="button"
                    @click="methodFilter = opt.id"
                    :class="[
                      'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all inline-flex items-center gap-1.5 cursor-pointer shadow-2xs shrink-0',
                      methodFilter === opt.id
                        ? 'bg-warm-900 text-white dark:bg-white dark:text-warm-900 font-bold'
                        : 'bg-white dark:bg-warm-800 text-warm-700 dark:text-warm-300 border border-warm-200 dark:border-warm-700 hover:bg-warm-100'
                    ]"
                  >
                    <component :is="opt.icon" :size="13" />
                    <span>{{ opt.label }}</span>
                  </button>
                </div>
              </div>

              <!-- Sorting -->
              <div class="pt-3 border-t border-warm-200/60 dark:border-warm-800/60">
                <label class="block text-[11px] font-bold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-2">
                  {{ $t('cashierPage.filterSort') }}
                </label>
                <div class="flex items-center gap-1.5 overflow-x-auto filter-scroll pb-1 sm:flex-wrap">
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
                      'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all inline-flex items-center gap-1.5 cursor-pointer shadow-2xs shrink-0',
                      sortBy === opt.id
                        ? 'bg-warm-900 text-white dark:bg-white dark:text-warm-900 font-bold'
                        : 'bg-white dark:bg-warm-800 text-warm-700 dark:text-warm-300 border border-warm-200 dark:border-warm-700 hover:bg-warm-100'
                    ]"
                  >
                    <span>{{ opt.label }}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </transition>

        <!-- Orders Table (Responsive with inner scroll container, clickable rows opening drawer) -->
        <TableSkeleton v-if="loading" :rows="6" :cols="6" />

        <div v-else-if="filteredOrders.length" class="overflow-x-auto w-full">
          <table class="w-full min-w-[760px] text-sm text-left">
            <!-- Headers: 12px, medium weight, normal letter spacing, darker grey -->
            <thead class="bg-warm-50 dark:bg-warm-800/60 border-b border-warm-200 dark:border-warm-800 text-warm-600 dark:text-warm-400 text-xs font-medium tracking-normal normal-case">
              <tr>
                <th class="px-4 py-3 text-left font-medium">{{ $t('cashierPage.orderCol') }}</th>
                <th class="px-4 py-3 text-left font-medium">{{ $t('cashierPage.tableOrTypeCol') }}</th>
                <th class="px-4 py-3 text-left font-medium">{{ $t('cashierPage.paymentMethodCol') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ $t('cashierPage.orderTotal') }}</th>
                <th class="px-4 py-3 text-left font-medium">{{ $t('cashierPage.statusCol') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ $t('cashierPage.actionsCol') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-warm-100 dark:divide-warm-800">
              <tr
                v-for="order in filteredOrders"
                :key="order.ulid"
                @click="openOrderDrawer(order)"
                class="hover:bg-warm-50/80 dark:hover:bg-warm-850/80 transition-colors cursor-pointer group"
              >
                <!-- Order: reference in monospace, time in secondary text (contrast >= 4.5:1), items & cashier name -->
                <td class="px-4 py-3.5">
                  <div class="flex items-center gap-2">
                    <span class="font-mono text-xs font-bold text-warm-900 dark:text-white">#{{ order.ulid.slice(-8).toUpperCase() }}</span>
                    <span class="text-xs text-warm-500 dark:text-warm-400">
                      {{ new Date(order.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                    </span>
                  </div>
                  <p class="text-xs text-warm-600 dark:text-warm-400 mt-0.5">
                    {{ order.items?.length ?? 0 }} items · {{ order.waiter?.name || order.cashier?.name || 'Cashier' }}
                  </p>
                </td>

                <!-- Guest / Table with channel icons -->
                <td class="px-4 py-3.5">
                  <p class="font-medium text-warm-900 dark:text-white text-xs">
                    {{ order.user?.name ?? (order.notes?.match(/Guest:\s*([^,\n]+)/)?.[1] || $t('trackingPage.receipt.guestDiner')) }}
                  </p>
                  <p class="text-xs text-warm-500 dark:text-warm-400 capitalize flex items-center gap-1.5 mt-0.5">
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

                <!-- Method with Banknote for cash (never dollar sign!) -->
                <td class="px-4 py-3.5">
                  <span class="capitalize text-xs font-medium text-warm-700 dark:text-warm-300 inline-flex items-center gap-1.5">
                    <Banknote v-if="order.payment_method === 'cash'" :size="14" class="text-warm-600 dark:text-warm-400" />
                    <Smartphone v-else-if="['telebirr'].includes(order.payment_method)" :size="14" class="text-blue-500" />
                    <Building2 v-else-if="['cbe_birr'].includes(order.payment_method)" :size="14" class="text-amber-500" />
                    <CreditCard v-else-if="order.payment_method === 'card'" :size="14" class="text-cyan-500" />
                    <BedDouble v-else-if="order.payment_method === 'room_charge'" :size="14" class="text-purple-500" />
                    <Split v-else-if="order.payment_method === 'split'" :size="14" class="text-indigo-500" />
                    <Wallet v-else :size="14" class="text-warm-400" />
                    <span>{{ order.payment_method || 'Cash' }}</span>
                  </span>
                </td>

                <!-- Total: Right-aligned with tabular numerals, primary text color (NOT orange!) -->
                <td class="px-4 py-3.5 text-right font-mono tabular-nums text-sm font-semibold text-warm-900 dark:text-white whitespace-nowrap">
                  {{ formatETB(order.total) }}
                </td>

                <!-- Payment Status Badges (Icon + Text) -->
                <td class="px-4 py-3.5">
                  <!-- Paid: Green icon + text -->
                  <span
                    v-if="order.payment_status === 'paid'"
                    class="badge badge-green text-xs font-medium inline-flex items-center gap-1 py-1 px-2.5"
                  >
                    <Check :size="12" />
                    <span>{{ $t('trackingPage.paidBadge') }}</span>
                  </span>
                  <!-- Refunded: Red/Grey icon + text -->
                  <span
                    v-else-if="order.payment_status === 'refunded'"
                    class="badge badge-gray text-xs font-medium inline-flex items-center gap-1 py-1 px-2.5 text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/60"
                  >
                    <RotateCcw :size="12" />
                    <span>{{ $t('cashierPage.refundedBadge') }}</span>
                  </span>
                  <!-- Unpaid: Amber icon + text -->
                  <span
                    v-else
                    class="badge badge-amber text-xs font-medium inline-flex items-center gap-1 py-1 px-2.5 text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800"
                  >
                    <Clock :size="12" />
                    <span>{{ $t('trackingPage.unpaidBadge') }}</span>
                  </span>
                </td>

                <!-- Actions: Print receipt, Refund outline danger button, Settle payment (disabled if shift closed) -->
                <td class="px-4 py-3.5 text-right space-x-1.5 whitespace-nowrap" @click.stop>
                  <!-- Settle Payment Button (If Pending) -->
                  <template v-if="order.payment_status === 'pending'">
                    <button
                      @click.stop="currentShiftData.has_active_shift && openPaymentModal(order)"
                      :disabled="!currentShiftData.has_active_shift"
                      :title="!currentShiftData.has_active_shift ? $t('cashierPage.tooltipOpenShift') : $t('cashierPage.settlePayment')"
                      :class="[
                        'py-1.5 px-3 text-xs font-medium inline-flex items-center gap-1.5 rounded-lg text-white shadow-2xs transition-colors min-h-[36px]',
                        currentShiftData.has_active_shift
                          ? 'bg-green-600 hover:bg-green-700 cursor-pointer'
                          : 'bg-warm-400 dark:bg-warm-700 opacity-60 cursor-not-allowed'
                      ]"
                      aria-label="Settle payment"
                    >
                      <Banknote :size="13" />
                      <span>{{ $t('cashierPage.settlePayment') }}</span>
                    </button>
                    <button
                      @click.stop="currentShiftData.has_active_shift && openSplitModal(order)"
                      :disabled="!currentShiftData.has_active_shift"
                      :title="!currentShiftData.has_active_shift ? $t('cashierPage.tooltipOpenShift') : $t('cashierPage.splitBill')"
                      class="btn-outline py-1.5 px-2 text-xs inline-flex items-center rounded-lg min-h-[36px] disabled:opacity-40 disabled:cursor-not-allowed"
                      aria-label="Split bill"
                    >
                      <Split :size="13" />
                    </button>
                    <button
                      @click.stop="openVoidModal(order)"
                      class="btn-ghost py-1.5 px-2 text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 rounded-lg min-h-[36px]"
                      :title="$t('cashierPage.voidOrder')"
                      aria-label="Void order"
                    >
                      <Trash2 :size="13" />
                    </button>
                  </template>

                  <!-- Receipt & Refund (If Paid) -->
                  <template v-else-if="order.payment_status === 'paid'">
                    <button
                      @click.stop="openReceipt(order)"
                      class="btn-outline py-1.5 px-2.5 text-xs inline-flex items-center gap-1.5 rounded-lg border-warm-300 dark:border-warm-700 text-warm-700 dark:text-warm-300 hover:bg-warm-100 dark:hover:bg-warm-800 min-h-[36px] cursor-pointer"
                      :title="$t('cashierPage.printReceipt')"
                      aria-label="Print receipt"
                    >
                      <Printer :size="13" />
                      <span>{{ $t('cashierPage.printReceipt') }}</span>
                    </button>
                    <button
                      @click.stop="canRefund(order) && openRefundModal(order)"
                      :disabled="!canRefund(order)"
                      :title="getRefundDisabledReason(order)"
                      :class="[
                        'py-1.5 px-2.5 text-xs font-medium inline-flex items-center gap-1 rounded-lg border transition-colors min-h-[36px]',
                        canRefund(order)
                          ? 'text-red-600 border-red-200 dark:border-red-900/50 hover:bg-red-50 dark:hover:bg-red-950/30 hover:border-red-300 cursor-pointer'
                          : 'text-warm-400 dark:text-warm-600 border-warm-200 dark:border-warm-800 opacity-50 cursor-not-allowed'
                      ]"
                      aria-label="Refund order"
                    >
                      <RotateCcw :size="12" />
                      <span>{{ $t('cashierPage.refundOrder') }}</span>
                    </button>
                  </template>

                  <!-- If Refunded -->
                  <template v-else>
                    <button
                      @click.stop="openReceipt(order)"
                      class="btn-outline py-1.5 px-2.5 text-xs inline-flex items-center gap-1.5 rounded-lg text-warm-600 dark:text-warm-400 min-h-[36px] cursor-pointer"
                      :title="$t('cashierPage.printReceipt')"
                      aria-label="Print receipt"
                    >
                      <Printer :size="13" />
                      <span>{{ $t('cashierPage.printReceipt') }}</span>
                    </button>
                    <button
                      disabled
                      :title="getRefundDisabledReason(order)"
                      class="py-1.5 px-2.5 text-xs font-medium inline-flex items-center gap-1 rounded-lg border border-warm-200 dark:border-warm-800 text-warm-400 opacity-50 cursor-not-allowed min-h-[36px]"
                    >
                      <RotateCcw :size="12" />
                      <span>{{ $t('cashierPage.refundOrder') }}</span>
                    </button>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Friendly empty state -->
        <div v-else class="text-center py-16 text-warm-500 dark:text-warm-400">
          <div class="w-12 h-12 rounded-2xl bg-warm-100 dark:bg-warm-800 flex items-center justify-center mx-auto mb-3 text-warm-400">
            <Receipt :size="24" />
          </div>
          <p class="font-medium text-sm text-warm-800 dark:text-warm-200">
            {{ dateWindow === 'current_shift' ? $t('cashierPage.noOrdersInShift') : $t('cashierPage.noOrdersFound') }}
          </p>
          <p class="text-xs text-warm-400 mt-1">
            {{ dateWindow === 'current_shift' ? 'New orders placed during this shift will appear here automatically.' : 'Try adjusting your search or filters.' }}
          </p>
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
              <span class="font-mono text-xs font-bold text-warm-900 dark:text-white tabular-nums">
                ETB {{ Number(dish.price).toFixed(2) }}
              </span>
              <button class="p-1 rounded-lg bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-warm-200 dark:hover:bg-warm-700 transition-colors">
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
            <span class="font-mono text-warm-900 dark:text-white tabular-nums font-bold text-lg">
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
            <Banknote :size="15" /> {{ $t('cashierPage.payCompleteBtn', { total: ticketGrandTotal.toFixed(2) }) }}
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
                <td class="px-4 py-3 font-mono text-xs font-bold text-warm-900 dark:text-white tabular-nums">
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
              <Banknote :size="20" class="text-warm-700 dark:text-warm-300" /> {{ $t('cashierPage.settlementModalTitle') }}
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
          <p class="font-mono text-3xl font-extrabold text-warm-900 dark:text-white tabular-nums">
            {{ formatETB(selectedOrder.total) }}
          </p>
        </div>

        <!-- Payment Method Tabs -->
        <div>
          <label class="block text-xs font-bold text-warm-700 dark:text-warm-300 mb-2">{{ $t('cashierPage.chooseMethod') }}</label>
          <div class="grid grid-cols-3 gap-2">
            <button
              v-for="m in [
                { id: 'cash', label: $t('cashierPage.methodCash'), icon: Banknote },
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
              <span class="text-xs font-mono font-bold text-warm-900 dark:text-white tabular-nums">
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
          <div class="p-4 rounded-xl bg-warm-50 dark:bg-warm-800/60 border border-warm-200 dark:border-warm-700 text-center space-y-1">
            <p class="text-xs text-warm-500 uppercase font-semibold">{{ $t('cashierPage.perPersonShare') }}</p>
            <p class="font-mono text-2xl font-extrabold text-warm-900 dark:text-white tabular-nums">
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
            <span class="font-mono font-bold text-sm text-warm-900 dark:text-white tabular-nums">
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
    <!-- MODAL: REFUND ORDER CONFIRMATION (DANGER STYLE WITH REQUIRED REASON)       -->
    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <div v-if="showRefundModal && selectedOrder" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-md space-y-4 bg-white dark:bg-warm-900 border border-warm-200 dark:border-warm-800 text-warm-900 dark:text-white shadow-2xl rounded-2xl">
        <div class="flex items-start gap-3.5">
          <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-950/60 text-red-600 dark:text-red-400 flex items-center justify-center shrink-0">
            <AlertTriangle :size="20" />
          </div>
          <div>
            <h3 class="font-heading font-bold text-base text-warm-900 dark:text-white">
              {{ $t('cashierPage.refundConfirmTitle', { ulid: selectedOrder.ulid.slice(-8).toUpperCase() }) }}
            </h3>
            <p class="text-xs text-warm-500 dark:text-warm-400 mt-1">
              {{ $t('cashierPage.refundConfirmSubtitle', { amount: formatETB(selectedOrder.total) }) }}
            </p>
          </div>
        </div>

        <div class="p-3 rounded-xl bg-red-50/70 dark:bg-red-950/30 border border-red-200/80 dark:border-red-900/40 text-xs text-red-800 dark:text-red-300">
          {{ $t('cashierPage.refundWarning') }}
        </div>

        <div>
          <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">
            {{ $t('cashierPage.refundReasonLabel') }} <span class="text-red-500">*</span>
          </label>
          <textarea
            v-model="actionReason"
            rows="3"
            class="input text-xs w-full bg-white dark:bg-warm-800 border-warm-300 dark:border-warm-700 focus:border-red-500 focus:ring-red-500/20"
            :placeholder="$t('cashierPage.refundReasonPlaceholder')"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-warm-100 dark:border-warm-800">
          <button
            @click="showRefundModal = false"
            class="btn-outline py-2 px-3.5 text-xs font-medium rounded-xl"
          >
            {{ $t('cashierPage.cancel') }}
          </button>
          <button
            @click="handleConfirmRefund"
            :disabled="actionLoading || !actionReason.trim()"
            class="btn-primary py-2 px-4 text-xs font-bold bg-red-600 hover:bg-red-700 text-white rounded-xl shadow-xs disabled:opacity-50 inline-flex items-center gap-1.5 cursor-pointer"
          >
            <RotateCcw v-if="!actionLoading" :size="13" />
            <span v-if="actionLoading">Processing...</span>
            <span v-else>{{ $t('cashierPage.confirmRefundBtn') }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <!-- ORDER DETAILS SIDE PANEL DRAWER                                          -->
    <!-- ───────────────────────────────────────────────────────────────────────── -->
    <teleport to="body">
      <div v-if="showOrderDrawer && drawerOrder" class="fixed inset-0 z-50 overflow-hidden">
        <!-- Backdrop -->
        <div
          @click="showOrderDrawer = false"
          class="fixed inset-0 bg-black/50 backdrop-blur-xs transition-opacity"
        ></div>

        <!-- Slide-over Drawer -->
        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
          <aside class="w-screen max-w-md bg-white dark:bg-warm-900 shadow-2xl flex flex-col border-l border-warm-200 dark:border-warm-800 text-warm-900 dark:text-white">
            <!-- Drawer Header -->
            <div class="p-4 sm:p-5 border-b border-warm-200 dark:border-warm-800 flex items-center justify-between">
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="font-heading font-bold text-base">
                    #{{ drawerOrder.ulid.slice(-8).toUpperCase() }}
                  </h3>
                  <span
                    v-if="drawerOrder.payment_status === 'paid'"
                    class="badge badge-green text-xs font-semibold inline-flex items-center gap-1"
                  >
                    <Check :size="11" /> {{ $t('trackingPage.paidBadge') }}
                  </span>
                  <span
                    v-else-if="drawerOrder.payment_status === 'refunded'"
                    class="badge badge-gray text-xs font-semibold inline-flex items-center gap-1 text-red-700 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/60"
                  >
                    <RotateCcw :size="11" /> {{ $t('cashierPage.refundedBadge') }}
                  </span>
                  <span
                    v-else
                    class="badge badge-amber text-xs font-semibold inline-flex items-center gap-1 text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800"
                  >
                    <Clock :size="11" /> {{ $t('trackingPage.unpaidBadge') }}
                  </span>
                </div>
                <p class="text-xs text-warm-500 mt-0.5">
                  {{ new Date(drawerOrder.created_at).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) }}
                </p>
              </div>
              <button
                @click="showOrderDrawer = false"
                class="p-2 rounded-xl text-warm-400 hover:text-warm-600 dark:hover:text-warm-200 hover:bg-warm-100 dark:hover:bg-warm-800 transition-colors"
                aria-label="Close drawer"
              >
                <X :size="18" />
              </button>
            </div>

            <!-- Drawer Body -->
            <div class="p-4 sm:p-5 flex-1 overflow-y-auto space-y-4 text-xs">
              <!-- Customer & Channel Details -->
              <div class="p-3.5 rounded-xl bg-warm-50 dark:bg-warm-800/60 border border-warm-200/80 dark:border-warm-700/60 space-y-2">
                <div class="flex justify-between">
                  <span class="text-warm-500">{{ $t('cashierPage.customerCol') }}:</span>
                  <span class="font-semibold">{{ drawerOrder.user?.name ?? (drawerOrder.notes?.match(/Guest:\s*([^,\n]+)/)?.[1] || $t('trackingPage.receipt.guestDiner')) }}</span>
                </div>
                <div v-if="drawerOrder.user?.phone" class="flex justify-between">
                  <span class="text-warm-500">Phone:</span>
                  <span class="font-mono">{{ drawerOrder.user.phone }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-warm-500">Service:</span>
                  <span class="font-semibold capitalize flex items-center gap-1">
                    <span v-if="drawerOrder.type === 'dine_in'">Dine In (Table {{ drawerOrder.table?.name ?? drawerOrder.table_id ?? 'N/A' }})</span>
                    <span v-else-if="drawerOrder.type === 'pickup'">Takeaway</span>
                    <span v-else-if="drawerOrder.type === 'delivery'">Delivery</span>
                    <span v-else>Room Service</span>
                  </span>
                </div>
                <div v-if="drawerOrder.waiter" class="flex justify-between">
                  <span class="text-warm-500">Staff / Waiter:</span>
                  <span class="font-semibold">{{ drawerOrder.waiter.name }}</span>
                </div>
              </div>

              <!-- Line Items Breakdown -->
              <div>
                <h4 class="font-bold text-xs uppercase tracking-wider text-warm-500 mb-2">Order Items ({{ drawerOrder.items?.length || 0 }})</h4>
                <div class="divide-y divide-warm-100 dark:divide-warm-800 border border-warm-200/80 dark:border-warm-700/60 rounded-xl overflow-hidden">
                  <div
                    v-for="item in drawerOrder.items"
                    :key="item.id"
                    class="p-3 flex items-center justify-between bg-white dark:bg-warm-850"
                  >
                    <div>
                      <p class="font-semibold text-warm-900 dark:text-white">{{ item.dish?.name || item.name || 'Dish Item' }}</p>
                      <p class="text-[11px] text-warm-500 font-mono">{{ item.quantity }} × {{ formatETB(item.price) }}</p>
                    </div>
                    <p class="font-mono font-bold text-warm-900 dark:text-white">{{ formatETB(Number(item.price) * item.quantity) }}</p>
                  </div>
                </div>
              </div>

              <!-- Financial Breakdown -->
              <div class="p-3.5 rounded-xl bg-warm-50 dark:bg-warm-800/60 border border-warm-200/80 dark:border-warm-700/60 space-y-1.5 font-mono">
                <div class="flex justify-between text-warm-600 dark:text-warm-400">
                  <span>Subtotal</span>
                  <span>{{ formatETB(drawerOrder.subtotal || drawerOrder.total) }}</span>
                </div>
                <div v-if="drawerOrder.tax_amount" class="flex justify-between text-warm-600 dark:text-warm-400">
                  <span>Tax / VAT (15%)</span>
                  <span>{{ formatETB(drawerOrder.tax_amount) }}</span>
                </div>
                <div v-if="drawerOrder.service_charge" class="flex justify-between text-warm-600 dark:text-warm-400">
                  <span>Service Charge</span>
                  <span>{{ formatETB(drawerOrder.service_charge) }}</span>
                </div>
                <div v-if="drawerOrder.discount_amount" class="flex justify-between text-emerald-600">
                  <span>Discount</span>
                  <span>- {{ formatETB(drawerOrder.discount_amount) }}</span>
                </div>
                <div class="flex justify-between pt-2 border-t border-warm-200 dark:border-warm-700 font-bold text-sm text-warm-900 dark:text-white">
                  <span>Total</span>
                  <span>{{ formatETB(drawerOrder.total) }}</span>
                </div>
              </div>

              <!-- Payment Details -->
              <div class="p-3.5 rounded-xl bg-warm-50 dark:bg-warm-800/60 border border-warm-200/80 dark:border-warm-700/60 space-y-2">
                <div class="flex justify-between">
                  <span class="text-warm-500">Method:</span>
                  <span class="font-semibold capitalize">{{ drawerOrder.payment_method || 'Cash' }}</span>
                </div>
                <div v-if="drawerOrder.payment?.transaction_reference" class="flex justify-between">
                  <span class="text-warm-500">Txn Ref:</span>
                  <span class="font-mono">{{ drawerOrder.payment.transaction_reference }}</span>
                </div>
                <div v-if="drawerOrder.notes" class="pt-1 border-t border-warm-200 dark:border-warm-700">
                  <span class="text-warm-500 block mb-1">Notes:</span>
                  <p class="text-warm-700 dark:text-warm-300 italic">{{ drawerOrder.notes }}</p>
                </div>
              </div>
            </div>

            <!-- Drawer Footer Actions -->
            <div class="p-4 border-t border-warm-200 dark:border-warm-800 flex items-center gap-2">
              <button
                @click="openReceipt(drawerOrder)"
                class="btn-outline flex-1 py-2 text-xs font-semibold inline-flex items-center justify-center gap-1.5 min-h-[40px]"
              >
                <Printer :size="14" /> {{ $t('cashierPage.printReceipt') }}
              </button>
              <button
                v-if="drawerOrder.payment_status === 'pending'"
                @click="currentShiftData.has_active_shift && (showOrderDrawer = false, openPaymentModal(drawerOrder))"
                :disabled="!currentShiftData.has_active_shift"
                class="btn-primary flex-1 py-2 text-xs font-semibold bg-green-600 hover:bg-green-700 disabled:opacity-50 inline-flex items-center justify-center gap-1.5 min-h-[40px]"
              >
                <Banknote :size="14" /> {{ $t('cashierPage.settlePayment') }}
              </button>
              <button
                v-else-if="drawerOrder.payment_status === 'paid'"
                @click="canRefund(drawerOrder) && (showOrderDrawer = false, openRefundModal(drawerOrder))"
                :disabled="!canRefund(drawerOrder)"
                class="btn-outline flex-1 py-2 text-xs font-semibold text-red-600 border-red-200 dark:border-red-900/40 hover:bg-red-50 dark:hover:bg-red-950/30 disabled:opacity-40 inline-flex items-center justify-center gap-1.5 min-h-[40px]"
              >
                <RotateCcw :size="14" /> {{ $t('cashierPage.refundOrder') }}
              </button>
            </div>
          </aside>
        </div>
      </div>
    </teleport>
  </div>
</template>

<style scoped>
/* ───────────────────────────────────────────────────────────────────────── */
/* FILTER ACCORDION SLIDE IN / DOWN / UP TRANSITIONS                         */
/* ───────────────────────────────────────────────────────────────────────── */
.filter-slide-enter-active {
  transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
  max-height: 850px;
  opacity: 1;
  overflow: hidden;
  transform: translateY(0);
}

.filter-slide-leave-active {
  transition: all 0.28s cubic-bezier(0.4, 0, 0.2, 1);
  max-height: 850px;
  opacity: 1;
  overflow: hidden;
  transform: translateY(0);
}

.filter-slide-enter-from,
.filter-slide-leave-to {
  max-height: 0 !important;
  opacity: 0 !important;
  overflow: hidden !important;
  transform: translateY(-14px);
  padding-top: 0 !important;
  padding-bottom: 0 !important;
  margin-top: 0 !important;
  margin-bottom: 0 !important;
  border-width: 0 !important;
}

/* Active Filter Summary Bar Slide Down / Up */
.filter-tags-enter-active {
  transition: all 0.25s ease-out;
  max-height: 140px;
  opacity: 1;
  overflow: hidden;
}

.filter-tags-leave-active {
  transition: all 0.2s ease-in;
  max-height: 140px;
  opacity: 1;
  overflow: hidden;
}

.filter-tags-enter-from,
.filter-tags-leave-to {
  max-height: 0 !important;
  opacity: 0 !important;
  overflow: hidden !important;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
  border-width: 0 !important;
}

/* Custom sleek slim scrollbar for filter drawer and horizontal ribbons */
.filter-scroll {
  scrollbar-width: thin;
  scrollbar-color: rgba(156, 163, 175, 0.4) transparent;
}
.filter-scroll::-webkit-scrollbar {
  width: 5px;
  height: 5px;
}
.filter-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.filter-scroll::-webkit-scrollbar-thumb {
  background: rgba(156, 163, 175, 0.35);
  border-radius: 9999px;
}
.filter-scroll::-webkit-scrollbar-thumb:hover {
  background: rgba(156, 163, 175, 0.65);
}

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
