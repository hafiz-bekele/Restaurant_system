<script setup>
import { ref, computed } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi, staffApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { useI18n } from 'vue-i18n'
import { ShoppingBag, UserCheck, Utensils, RefreshCw, X, Search, Plus, Trash2, CheckCircle } from '@lucide/vue'
import TableSkeleton from '../../components/ui/TableSkeleton.vue'
import LoadingState from '../../components/ui/LoadingState.vue'
import AppButton from '../../components/ui/AppButton.vue'

const { t } = useI18n()
const ui = useUiStore()
const qc = useQueryClient()

const status = ref('')
const search = ref('')
const selectedOrder = ref(null)
const showReassignModal = ref(false)
const selectedWaiterId = ref('')
const reassigning = ref(false)

// Create order state
const showCreateModal = ref(false)
const selectedTableId = ref('')
const guestName = ref('')
const guestPhone = ref('')
const newOrderItems = ref([])
const newOrderNotes = ref('')
const dishSearch = ref('')
const creatingOrder = ref(false)

const { data: ordersData, isLoading, isError, error, refetch } = useQuery({
  queryKey: ['admin', 'orders', status],
  queryFn: () => adminApi.orders({ status: status.value || undefined }).then(r => r.data),
  refetchInterval: 15000,
})

const { data: waitersData } = useQuery({
  queryKey: ['admin', 'waiters-list'],
  queryFn: () => adminApi.users({ role: 'waiter' }).then(r => r.data.data),
})

const { data: tablesData } = useQuery({
  queryKey: ['admin', 'tables-list'],
  queryFn: () => adminApi.tables().then(r => r.data.data),
})

const { data: dishesData } = useQuery({
  queryKey: ['admin', 'dishes-list'],
  queryFn: () => adminApi.dishes().then(r => r.data.data),
})

const orders = () => {
  const list = ordersData.value?.data ?? []
  if (!search.value) return list
  const q = search.value.toLowerCase()
  return list.filter(o =>
    o.ulid?.toLowerCase().includes(q) ||
    o.user?.name?.toLowerCase().includes(q) ||
    o.table?.name?.toLowerCase().includes(q)
  )
}

const waiters = () => waitersData.value ?? []
const tables = () => tablesData.value ?? []
const dishes = () => dishesData.value ?? []

const filteredDishes = computed(() => {
  const list = dishes().filter(d => d.is_available)
  if (!dishSearch.value) return list
  const q = dishSearch.value.toLowerCase()
  return list.filter(d => d.name.toLowerCase().includes(q))
})

const statusOptions = ['', 'pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled']

async function updateStatus(id, newStatus) {
  try {
    await adminApi.updateOrder(id, { status: newStatus })
    qc.invalidateQueries({ queryKey: ['admin', 'orders'] })
    ui.toast(t('adminOrders.statusUpdatedToast'), 'success')
  } catch {
    ui.toast(t('adminOrders.failedToUpdateStatus'), 'error')
  }
}

function openReassign(order) {
  selectedOrder.value = order
  selectedWaiterId.value = order.waiter?.id ? String(order.waiter.id) : ''
  showReassignModal.value = true
}

async function submitReassign() {
  if (!selectedWaiterId.value) return
  reassigning.value = true
  try {
    await adminApi.reassignWaiter(selectedOrder.value.ulid, selectedWaiterId.value)
    qc.invalidateQueries({ queryKey: ['admin', 'orders'] })
    ui.toast(t('adminOrders.reassignedSuccessToast'), 'success')
    showReassignModal.value = false
  } catch (e) {
    ui.toast(e.response?.data?.message || t('adminOrders.failedToReassign'), 'error')
  } finally {
    reassigning.value = false
  }
}

// Create Order Functions
function openCreateOrder() {
  selectedTableId.value = tables()[0]?.id || ''
  guestName.value = ''
  guestPhone.value = ''
  newOrderItems.value = []
  newOrderNotes.value = ''
  dishSearch.value = ''
  showCreateModal.value = true
}

function addItemToNewOrder(dish) {
  const existing = newOrderItems.value.find(i => i.dish_id === dish.id)
  if (existing) {
    existing.quantity++
  } else {
    newOrderItems.value.push({
      dish_id: dish.id,
      name: dish.name,
      price: Number(dish.price),
      quantity: 1,
    })
  }
}

function updateItemQty(index, delta) {
  newOrderItems.value[index].quantity += delta
  if (newOrderItems.value[index].quantity <= 0) {
    newOrderItems.value.splice(index, 1)
  }
}

const subtotal = computed(() => {
  return newOrderItems.value.reduce((sum, item) => sum + item.price * item.quantity, 0)
})
const serviceCharge = computed(() => {
  return Math.round(subtotal.value * 0.10 * 100) / 100
})
const vatAmount = computed(() => {
  const basis = subtotal.value + serviceCharge.value
  return Math.round(basis * 0.15 * 100) / 100
})
const grandTotal = computed(() => {
  return subtotal.value + serviceCharge.value + vatAmount.value
})

async function submitNewOrder() {
  if (!selectedTableId.value || !newOrderItems.value.length) return
  creatingOrder.value = true
  try {
    const notesParts = []
    if (guestName.value.trim()) notesParts.push(`Customer: ${guestName.value.trim()}`)
    if (guestPhone.value.trim()) notesParts.push(`Phone: ${guestPhone.value.trim()}`)
    if (newOrderNotes.value.trim()) notesParts.push(newOrderNotes.value.trim())
    const combinedNotes = notesParts.length ? notesParts.join(' | ') : undefined

    await staffApi.createWaiterOrder({
      table_id: selectedTableId.value,
      items: newOrderItems.value.map(i => ({ dish_id: i.dish_id, quantity: i.quantity })),
      notes: combinedNotes,
    })
    qc.invalidateQueries({ queryKey: ['admin', 'orders'] })
    ui.toast(t('adminOrders.orderCreatedToast'), 'success')
    showCreateModal.value = false
  } catch (err) {
    ui.toast(err.response?.data?.message || t('adminOrders.failedToCreateOrder'), 'error')
  } finally {
    creatingOrder.value = false
  }
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
          <ShoppingBag :size="24" class="text-primary-500" />
          {{ $t('adminOrders.title') }}
        </h1>
        <p class="text-sm text-warm-500 dark:text-warm-400 mt-1">
          {{ $t('adminOrders.subtitle') }}
        </p>
      </div>

      <!-- Action Buttons & Filters -->
      <div class="flex flex-wrap items-center gap-3">
        <button
          @click="openCreateOrder"
          class="btn-primary text-xs gap-1.5 py-2 px-3.5 shadow-sm"
        >
          <Plus :size="15" /> {{ $t('adminOrders.inHousePhoneOrderBtn') }}
        </button>

        <div class="relative">
          <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
          <input
            v-model="search"
            type="search"
            :placeholder="$t('adminOrders.searchPlaceholder')"
            class="input pl-9 w-44 sm:w-56 text-xs"
          />
        </div>

        <select v-model="status" class="input w-auto text-xs py-2 font-medium">
          <option v-for="s in statusOptions" :key="s" :value="s">{{ s ? s.toUpperCase() : $t('adminOrders.allStatuses') }}</option>
        </select>
      </div>
    </div>

    <!-- Orders LoadingState -->
    <LoadingState
      :loading="isLoading"
      :error="isError ? error : null"
      :empty="orders().length === 0 && !isLoading"
      :empty-title="$t('adminOrders.noOrdersMatch')"
      empty-description="No customer or dining orders match your active filter criteria."
      skeleton-type="table"
      :table-columns="8"
      @retry="refetch"
    >
      <div class="card overflow-x-auto shadow-sm">
        <table class="w-full text-sm text-left">
          <thead class="bg-warm-50 dark:bg-warm-850 text-warm-600 dark:text-warm-400 text-xs uppercase font-semibold">
          <tr>
            <th class="px-5 py-3.5">{{ $t('adminOrders.thRef') }}</th>
            <th class="px-5 py-3.5">{{ $t('adminOrders.thTypeLocation') }}</th>
            <th class="px-5 py-3.5">{{ $t('adminOrders.thCustomer') }}</th>
            <th class="px-5 py-3.5">{{ $t('adminOrders.thItems') }}</th>
            <th class="px-5 py-3.5">{{ $t('adminOrders.thAssignedWaiter') }}</th>
            <th class="px-5 py-3.5">{{ $t('adminOrders.thTotal') }}</th>
            <th class="px-5 py-3.5">{{ $t('adminOrders.thStatus') }}</th>
            <th class="px-5 py-3.5 text-right">{{ $t('adminOrders.thActions') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-warm-100 dark:divide-warm-800">
          <tr
            v-for="order in orders()"
            :key="order.ulid"
            class="hover:bg-warm-50/70 dark:hover:bg-warm-800/40 transition-colors"
          >
            <!-- Reference -->
            <td class="px-5 py-3.5">
              <span class="font-mono font-bold text-xs text-primary-600 dark:text-primary-400">
                #{{ order.ulid.slice(-6).toUpperCase() }}
              </span>
              <p class="text-[11px] text-warm-400">{{ new Date(order.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</p>
            </td>

            <!-- Type & Table -->
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-1.5 font-medium text-warm-800 dark:text-warm-200 text-xs">
                <span class="capitalize">{{ order.type }}</span>
                <span v-if="order.table" class="badge badge-orange text-[10px]">{{ order.table.name }} ({{ order.table.area || 'Floor' }})</span>
              </div>
            </td>

            <!-- Customer -->
            <td class="px-5 py-3.5 text-xs text-warm-700 dark:text-warm-300">
              <p class="font-semibold">{{ order.user?.name ?? $t('adminOrders.walkInGuest') }}</p>
              <p class="text-[11px] text-warm-400">{{ order.user?.email ?? $t('adminOrders.tableQrScan') }}</p>
            </td>

            <!-- Items count -->
            <td class="px-5 py-3.5 text-xs text-warm-600 dark:text-warm-400">
              {{ $t('adminOrders.itemsCount', { count: order.items?.length ?? 1 }) }}
            </td>

            <!-- Assigned Waiter -->
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-2">
                <span v-if="order.waiter" class="badge badge-green text-xs flex items-center gap-1">
                  <UserCheck :size="12" /> {{ order.waiter.name }}
                </span>
                <span v-else class="text-xs text-warm-400 italic">{{ $t('adminOrders.unassigned') }}</span>
                <button
                  @click="openReassign(order)"
                  class="btn-ghost p-1 text-[11px] text-primary-600 font-semibold hover:underline"
                  :title="$t('adminOrders.changeBtn')"
                >
                  {{ $t('adminOrders.changeBtn') }}
                </button>
              </div>
            </td>

            <!-- Total -->
            <td class="px-5 py-3.5 font-bold text-warm-900 dark:text-white">
              ETB {{ Number(order.total).toFixed(2) }}
            </td>

            <!-- Status Badge -->
            <td class="px-5 py-3.5">
              <span
                :class="[
                  'badge text-xs capitalize',
                  order.status === 'delivered' ? 'badge-green' :
                  order.status === 'ready' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' :
                  order.status === 'cancelled' ? 'badge-red' : 'badge-orange'
                ]"
              >
                {{ order.status }}
              </span>
            </td>

            <!-- Status Switcher -->
            <td class="px-5 py-3.5 text-right">
              <select
                class="input text-xs py-1 px-2.5 w-auto"
                :value="order.status"
                @change="e => updateStatus(order.ulid, e.target.value)"
              >
                <option v-for="s in ['pending','confirmed','preparing','ready','out_for_delivery','delivered','cancelled']" :key="s" :value="s">
                  {{ s }}
                </option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </LoadingState>

    <!-- In-House / Phone Order Modal for Admin/Manager -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
      <div class="card w-full max-w-2xl shadow-float max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between pb-3 border-b border-warm-100 dark:border-warm-800 mb-3">
          <div>
            <h3 class="font-heading font-bold text-lg text-warm-900 dark:text-white flex items-center gap-2">
              <ShoppingBag :size="18" class="text-primary-500" />
              {{ $t('adminOrders.modalTitle') }}
            </h3>
            <p class="text-xs text-warm-500">{{ $t('adminOrders.modalDesc') }}</p>
          </div>
          <button @click="showCreateModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1 overflow-y-auto pr-1">
          <!-- Left: Customer Details & Menu Item Picker -->
          <div class="space-y-3">
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">{{ $t('adminOrders.customerGuest') }}</label>
                <input v-model="guestName" type="text" :placeholder="$t('adminOrders.customerPlaceholder')" class="input text-xs" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">{{ $t('adminOrders.phoneNumber') }}</label>
                <input v-model="guestPhone" type="tel" placeholder="+251..." class="input text-xs" />
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">{{ $t('adminOrders.targetTable') }}</label>
              <select v-model="selectedTableId" class="input text-xs">
                <option v-for="t in tables()" :key="t.id" :value="t.id">
                  {{ t.name }} ({{ t.area || 'Floor' }}, {{ t.capacity }} seats)
                </option>
              </select>
            </div>

            <div class="relative">
              <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
              <input
                v-model="dishSearch"
                type="search"
                :placeholder="$t('adminOrders.searchMenuPlaceholder')"
                class="input pl-8 text-xs"
              />
            </div>

            <div class="max-h-60 overflow-y-auto space-y-1.5 pr-1">
              <div
                v-for="d in filteredDishes"
                :key="d.id"
                @click="addItemToNewOrder(d)"
                class="flex items-center justify-between p-2 rounded-xl bg-warm-50 dark:bg-warm-850 hover:bg-primary-50 dark:hover:bg-primary-950/40 cursor-pointer transition-colors border border-warm-100 dark:border-warm-800"
              >
                <div>
                  <p class="font-semibold text-xs text-warm-900 dark:text-white">{{ d.name }}</p>
                  <p class="text-[11px] text-warm-400">ETB {{ Number(d.price).toFixed(2) }}</p>
                </div>
                <button type="button" class="btn-primary p-1 rounded-lg text-[10px]"><Plus :size="12" /></button>
              </div>
            </div>
          </div>

          <!-- Right: Order Summary & Financials -->
          <div class="flex flex-col justify-between bg-warm-50 dark:bg-warm-900 p-3.5 rounded-2xl border border-warm-200 dark:border-warm-800">
            <div>
              <h4 class="font-bold text-xs uppercase text-warm-600 dark:text-warm-400 mb-2">{{ $t('adminOrders.selectedItems', { count: newOrderItems.length }) }}</h4>

              <div v-if="!newOrderItems.length" class="text-center py-8 text-xs text-warm-400 italic">
                {{ $t('adminOrders.clickCatalogPrompt') }}
              </div>

              <div v-else class="space-y-2 max-h-48 overflow-y-auto pr-1">
                <div
                  v-for="(item, idx) in newOrderItems"
                  :key="item.dish_id"
                  class="flex items-center justify-between text-xs bg-white dark:bg-warm-850 p-2 rounded-xl shadow-xs"
                >
                  <div class="flex-1 truncate mr-2">
                    <p class="font-semibold text-warm-900 dark:text-white truncate">{{ item.name }}</p>
                    <span class="text-[10px] text-warm-400">ETB {{ (item.price * item.quantity).toFixed(2) }}</span>
                  </div>
                  <div class="flex items-center gap-1.5 shrink-0">
                    <button type="button" @click="updateItemQty(idx, -1)" class="w-5 h-5 rounded bg-warm-200 dark:bg-warm-700 font-bold text-xs flex items-center justify-center">-</button>
                    <span class="font-bold w-4 text-center">{{ item.quantity }}</span>
                    <button type="button" @click="updateItemQty(idx, 1)" class="w-5 h-5 rounded bg-warm-200 dark:bg-warm-700 font-bold text-xs flex items-center justify-center">+</button>
                  </div>
                </div>
              </div>

              <div class="mt-3">
                <input v-model="newOrderNotes" type="text" :placeholder="$t('adminOrders.kitchenNotesPlaceholder')" class="input text-xs" />
              </div>
            </div>

            <!-- Financial Breakdown -->
            <div class="pt-3 mt-3 border-t border-warm-200 dark:border-warm-800 space-y-1 text-xs">
              <div class="flex justify-between text-warm-500">
                <span>{{ $t('adminOrders.subtotal') }}</span>
                <span>ETB {{ subtotal.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between text-warm-500">
                <span>{{ $t('adminOrders.serviceFee') }}</span>
                <span>ETB {{ serviceCharge.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between text-warm-500">
                <span>{{ $t('adminOrders.vat') }}</span>
                <span>ETB {{ vatAmount.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between font-bold text-sm text-warm-900 dark:text-white pt-1 border-t border-warm-200 dark:border-warm-800">
                <span>{{ $t('adminOrders.total') }}</span>
                <span class="text-primary-600 dark:text-primary-400">ETB {{ grandTotal.toFixed(2) }}</span>
              </div>

              <AppButton
                @click="submitNewOrder"
                :loading="creatingOrder"
                :loading-text="$t('adminOrders.dispatchingBtn')"
                :disabled="!newOrderItems.length"
                full-width
                class="text-xs py-2.5 mt-2 shadow-md"
              >
                {{ $t('adminOrders.createDispatchBtn') }}
              </AppButton>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Waiter Reassign Modal -->
    <div v-if="showReassignModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
      <div class="card w-full max-w-md shadow-float">
        <div class="flex items-center justify-between pb-3 border-b border-warm-100 dark:border-warm-800 mb-4">
          <h3 class="font-heading font-bold text-warm-900 dark:text-white flex items-center gap-2">
            <UserCheck :size="18" class="text-primary-500" />
            {{ $t('adminOrders.reassignModalTitle', { ref: selectedOrder?.ulid?.slice(-6).toUpperCase() }) }}
          </h3>
          <button @click="showReassignModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
        </div>

        <p class="text-xs text-warm-600 dark:text-warm-400 mb-4">
          {{ $t('adminOrders.reassignModalPrompt') }}
        </p>

        <form @submit.prevent="submitReassign" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">{{ $t('adminOrders.selectWaiterLabel') }}</label>
            <select v-model="selectedWaiterId" required class="input">
              <option value="" disabled>{{ $t('adminOrders.selectWaiterDefault') }}</option>
              <option v-for="w in waiters()" :key="w.id" :value="String(w.id)">
                {{ w.name }} ({{ w.email }})
              </option>
            </select>
          </div>

          <div class="flex justify-end gap-3 pt-3 border-t border-warm-100 dark:border-warm-800">
            <button type="button" @click="showReassignModal = false" class="btn-ghost">{{ $t('adminOrders.cancelBtn') }}</button>
            <AppButton
              type="submit"
              :loading="reassigning"
              :loading-text="$t('adminOrders.reassigningBtn')"
            >
              {{ $t('adminOrders.confirmReassignBtn') }}
            </AppButton>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
