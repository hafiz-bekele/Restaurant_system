<script setup>
import { ref, computed } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { useI18n } from 'vue-i18n'
import {
  Plus,
  X,
  AlertTriangle,
  Package,
  Truck,
  Pencil,
  Trash2,
  Sliders,
  DollarSign,
  Boxes,
  History,
  TrendingDown,
  TrendingUp,
  RefreshCw,
} from '@lucide/vue'
import TableSkeleton from '../../components/ui/TableSkeleton.vue'
import StatCardSkeleton from '../../components/ui/StatCardSkeleton.vue'

const { t } = useI18n()
const ui = useUiStore()
const qc = useQueryClient()

const tab          = ref('ingredients') // 'ingredients' | 'suppliers' | 'movements'
const lowStockOnly = ref(false)
const search       = ref('')
const saving       = ref(false)

// Modals
const showIngModal     = ref(false)
const showEditIngModal = ref(false)
const showAdjModal     = ref(false)
const showSupModal     = ref(false)
const showEditSupModal = ref(false)

// Selected entities
const selectedIng = ref(null)
const selectedSup = ref(null)

// Forms
const ingForm     = ref({ name: '', unit: 'kg', stock: 0, reorder_level: 1, cost: 0, supplier_id: '' })
const editIngForm = ref({ name: '', unit: 'kg', reorder_level: 1, cost: 0, supplier_id: '' })
const adjForm     = ref({ delta: 0, reason: 'purchase' })
const supForm     = ref({ name: '', contact_name: '', phone: '', email: '', address: '' })
const editSupForm = ref({ name: '', contact_name: '', phone: '', email: '', address: '' })

// 1. Inventory Summary Stats Query
const { data: summaryData, isLoading: summaryLoading } = useQuery({
  queryKey: ['admin', 'inventory-summary'],
  queryFn:  () => adminApi.inventorySummary().then(r => r.data.data),
})

// 2. Suppliers Query
const { data: suppliersData, isLoading: supLoading } = useQuery({
  queryKey: ['admin', 'suppliers'],
  queryFn:  () => adminApi.suppliers().then(r => r.data.data),
  staleTime: 60000,
})

// 3. Ingredients Query
const { data: ingredientsData, isLoading: ingLoading } = useQuery({
  queryKey: ['admin', 'ingredients', lowStockOnly, search],
  queryFn:  () => adminApi.ingredients({
    low_stock: lowStockOnly.value || undefined,
    search: search.value || undefined,
  }).then(r => r.data),
  keepPreviousData: true,
})
const ingredients = () => ingredientsData.value?.data ?? []

// 4. Stock Movements Query
const { data: movementsData, isLoading: movLoading } = useQuery({
  queryKey: ['admin', 'inventory-movements', tab],
  queryFn:  () => adminApi.stockMovements().then(r => r.data),
  enabled:  computed(() => tab.value === 'movements'),
})
const movements = () => movementsData.value?.data ?? []

// Invalidation helper
function refreshAll() {
  qc.invalidateQueries({ queryKey: ['admin', 'inventory-summary'] })
  qc.invalidateQueries({ queryKey: ['admin', 'ingredients'] })
  qc.invalidateQueries({ queryKey: ['admin', 'suppliers'] })
  qc.invalidateQueries({ queryKey: ['admin', 'inventory-movements'] })
}

// Adjust Stock Modal Handlers
function openAdjust(ing) {
  selectedIng.value = ing
  adjForm.value = { delta: 0, reason: 'purchase' }
  showAdjModal.value = true
}

async function adjustStock() {
  saving.value = true
  try {
    await adminApi.adjustStock(selectedIng.value.id, adjForm.value)
    refreshAll()
    ui.toast(t('adminInventory.toastStockAdjusted'), 'success')
    showAdjModal.value = false
  } catch (e) {
    ui.toast(e.response?.data?.message ?? t('adminInventory.toastFailed'), 'error')
  } finally {
    saving.value = false
  }
}

// Add Ingredient
function openAddIngredient() {
  ingForm.value = { name: '', unit: 'kg', stock: 0, reorder_level: 1, cost: 0, supplier_id: '' }
  showIngModal.value = true
}

async function createIngredient() {
  saving.value = true
  try {
    const payload = { ...ingForm.value, supplier_id: ingForm.value.supplier_id || undefined }
    await adminApi.createIngredient(payload)
    refreshAll()
    ui.toast(t('adminInventory.toastIngAdded'), 'success')
    showIngModal.value = false
  } catch (e) {
    ui.toast(e.response?.data?.message ?? t('adminInventory.toastFailed'), 'error')
  } finally {
    saving.value = false
  }
}

// Edit Ingredient
function openEditIngredient(ing) {
  selectedIng.value = ing
  editIngForm.value = {
    name:          ing.name,
    unit:          ing.unit,
    reorder_level: ing.reorder_level,
    cost:          ing.cost,
    supplier_id:   ing.supplier?.id ?? ing.supplier_id ?? '',
  }
  showEditIngModal.value = true
}

async function updateIngredient() {
  saving.value = true
  try {
    const payload = {
      ...editIngForm.value,
      supplier_id: editIngForm.value.supplier_id || null,
    }
    await adminApi.updateIngredient(selectedIng.value.id, payload)
    refreshAll()
    ui.toast(t('adminInventory.toastIngUpdated'), 'success')
    showEditIngModal.value = false
  } catch (e) {
    ui.toast(e.response?.data?.message ?? t('adminInventory.toastFailed'), 'error')
  } finally {
    saving.value = false
  }
}

// Delete Ingredient
async function deleteIngredient(ing) {
  if (!confirm(t('adminInventory.confirmDeleteIng', { name: ing.name }))) return
  try {
    await adminApi.deleteIngredient(ing.id)
    refreshAll()
    ui.toast(t('adminInventory.toastIngDeleted'), 'success')
  } catch (e) {
    ui.toast(e.response?.data?.message ?? t('adminInventory.toastFailed'), 'error')
  }
}

// Add Supplier
function openAddSupplier() {
  supForm.value = { name: '', contact_name: '', phone: '', email: '', address: '' }
  showSupModal.value = true
}

async function createSupplier() {
  saving.value = true
  try {
    await adminApi.createSupplier(supForm.value)
    refreshAll()
    ui.toast(t('adminInventory.toastSupAdded'), 'success')
    showSupModal.value = false
  } catch (e) {
    ui.toast(e.response?.data?.message ?? t('adminInventory.toastFailed'), 'error')
  } finally {
    saving.value = false
  }
}

// Edit Supplier
function openEditSupplier(sup) {
  selectedSup.value = sup
  editSupForm.value = {
    name:         sup.name,
    contact_name: sup.contact_name ?? '',
    phone:        sup.phone ?? '',
    email:        sup.email ?? '',
    address:      sup.address ?? '',
  }
  showEditSupModal.value = true
}

async function updateSupplier() {
  saving.value = true
  try {
    await adminApi.updateSupplier(selectedSup.value.id, editSupForm.value)
    refreshAll()
    ui.toast(t('adminInventory.toastSupUpdated'), 'success')
    showEditSupModal.value = false
  } catch (e) {
    ui.toast(e.response?.data?.message ?? t('adminInventory.toastFailed'), 'error')
  } finally {
    saving.value = false
  }
}

// Delete Supplier
async function deleteSupplier(sup) {
  if (!confirm(t('adminInventory.confirmDeleteSup', { name: sup.name }))) return
  try {
    await adminApi.deleteSupplier(sup.id)
    refreshAll()
    ui.toast(t('adminInventory.toastSupDeleted'), 'success')
  } catch (e) {
    ui.toast(e.response?.data?.message ?? t('adminInventory.toastFailed'), 'error')
  }
}

function formatDate(isoStr) {
  if (!isoStr) return '—'
  const d = new Date(isoStr)
  return d.toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2.5">
          <div class="p-2 rounded-xl bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400">
            <Package :size="24" />
          </div>
          {{ $t('adminInventory.title') }}
        </h1>
        <p class="text-sm text-warm-500 mt-1">
          Catalog ingredients, track reorder levels, suppliers, and audited stock intake.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          v-if="tab === 'ingredients'"
          @click="openAddIngredient"
          class="btn-primary gap-2 text-sm shadow-md"
        >
          <Plus :size="16" /> {{ $t('adminInventory.addIngredient') }}
        </button>
        <button
          v-if="tab === 'suppliers'"
          @click="openAddSupplier"
          class="btn-primary gap-2 text-sm shadow-md"
        >
          <Plus :size="16" /> {{ $t('adminInventory.addSupplier') }}
        </button>
      </div>
    </div>

    <!-- Summary Stats Bar -->
    <div v-if="summaryLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <StatCardSkeleton v-for="i in 4" :key="i" />
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Items -->
      <div class="card p-5 border border-warm-100 dark:border-warm-800 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-semibold uppercase tracking-wider text-warm-500">{{ $t('adminInventory.statTotalItems') }}</span>
          <div class="p-2 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-500">
            <Boxes :size="18" />
          </div>
        </div>
        <p class="font-heading text-3xl font-bold text-warm-900 dark:text-white">
          {{ summaryData?.total_items ?? 0 }}
        </p>
        <p class="text-xs text-warm-400 mt-1">Across all pantry & bar categories</p>
      </div>

      <!-- Low Stock Alerts -->
      <div
        @click="lowStockOnly = !lowStockOnly; tab = 'ingredients'"
        class="card p-5 border cursor-pointer transition-all hover:shadow-md"
        :class="summaryData?.low_stock_count > 0 ? 'border-red-200 dark:border-red-900/40 bg-red-50/20 dark:bg-red-950/10' : 'border-warm-100 dark:border-warm-800'"
      >
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-semibold uppercase tracking-wider text-warm-500">{{ $t('adminInventory.statLowStock') }}</span>
          <div class="p-2 rounded-xl" :class="summaryData?.low_stock_count > 0 ? 'bg-red-100 dark:bg-red-900/30 text-red-600' : 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-500'">
            <AlertTriangle :size="18" />
          </div>
        </div>
        <div class="flex items-baseline gap-2">
          <p class="font-heading text-3xl font-bold" :class="summaryData?.low_stock_count > 0 ? 'text-red-600 dark:text-red-400' : 'text-warm-900 dark:text-white'">
            {{ summaryData?.low_stock_count ?? 0 }}
          </p>
          <span v-if="summaryData?.low_stock_count > 0" class="text-xs font-medium text-red-500 animate-pulse">Needs Restock</span>
        </div>
        <p class="text-xs text-warm-400 mt-1">Click to filter critical items</p>
      </div>

      <!-- Inventory Valuation -->
      <div class="card p-5 border border-warm-100 dark:border-warm-800 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-semibold uppercase tracking-wider text-warm-500">{{ $t('adminInventory.statValuation') }}</span>
          <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-500">
            <DollarSign :size="18" />
          </div>
        </div>
        <p class="font-heading text-3xl font-bold text-warm-900 dark:text-white">
          ETB {{ Number(summaryData?.total_valuation ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
        </p>
        <p class="text-xs text-warm-400 mt-1">Current total warehouse value</p>
      </div>

      <!-- Active Suppliers -->
      <div class="card p-5 border border-warm-100 dark:border-warm-800 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
          <span class="text-xs font-semibold uppercase tracking-wider text-warm-500">{{ $t('adminInventory.statSuppliers') }}</span>
          <div class="p-2 rounded-xl bg-amber-50 dark:bg-amber-900/20 text-amber-500">
            <Truck :size="18" />
          </div>
        </div>
        <p class="font-heading text-3xl font-bold text-warm-900 dark:text-white">
          {{ summaryData?.suppliers_count ?? 0 }}
        </p>
        <p class="text-xs text-warm-400 mt-1">Direct vetted vendors</p>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-warm-200 dark:border-warm-800 pb-3">
      <div class="flex gap-1.5 bg-warm-100 dark:bg-warm-800/60 p-1 rounded-xl">
        <button
          @click="tab = 'ingredients'"
          :class="[
            'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all',
            tab === 'ingredients'
              ? 'bg-white dark:bg-warm-900 text-warm-900 dark:text-white shadow-sm'
              : 'text-warm-500 hover:text-warm-700 dark:hover:text-warm-300'
          ]"
        >
          <Boxes :size="16" />
          {{ $t('adminInventory.tabIngredients') }}
        </button>

        <button
          @click="tab = 'suppliers'"
          :class="[
            'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all',
            tab === 'suppliers'
              ? 'bg-white dark:bg-warm-900 text-warm-900 dark:text-white shadow-sm'
              : 'text-warm-500 hover:text-warm-700 dark:hover:text-warm-300'
          ]"
        >
          <Truck :size="16" />
          {{ $t('adminInventory.tabSuppliers') }}
          <span v-if="suppliersData?.length" class="text-xs px-1.5 py-0.5 rounded-full bg-warm-200 dark:bg-warm-700 text-warm-700 dark:text-warm-300">
            {{ suppliersData.length }}
          </span>
        </button>

        <button
          @click="tab = 'movements'"
          :class="[
            'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all',
            tab === 'movements'
              ? 'bg-white dark:bg-warm-900 text-warm-900 dark:text-white shadow-sm'
              : 'text-warm-500 hover:text-warm-700 dark:hover:text-warm-300'
          ]"
        >
          <History :size="16" />
          {{ $t('adminInventory.tabMovements') }}
        </button>
      </div>

      <!-- Quick refresh -->
      <button
        @click="refreshAll"
        class="btn-ghost text-xs gap-1.5 text-warm-500 hover:text-warm-800 dark:hover:text-warm-200"
        title="Refresh data"
      >
        <RefreshCw :size="14" /> Refresh
      </button>
    </div>

    <!-- 1. Ingredients Tab -->
    <div v-if="tab === 'ingredients'" class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
          <input
            v-model="search"
            type="search"
            :placeholder="$t('adminInventory.searchPlaceholder')"
            class="input w-64 text-sm"
          />
          <label class="flex items-center gap-2 text-sm font-medium text-warm-700 dark:text-warm-300 cursor-pointer px-3.5 py-2 rounded-xl bg-warm-100 dark:bg-warm-800/80 hover:bg-warm-200 dark:hover:bg-warm-700 transition-colors">
            <input v-model="lowStockOnly" type="checkbox" class="accent-red-500 rounded" />
            <AlertTriangle :size="14" class="text-red-500" />
            {{ $t('adminInventory.lowStockOnly') }}
          </label>
        </div>
      </div>

      <TableSkeleton v-if="ingLoading" :rows="6" :cols="8" />

      <div v-else class="card overflow-x-auto border border-warm-100 dark:border-warm-800">
        <table class="w-full text-sm">
          <thead class="bg-warm-50/75 dark:bg-warm-800/50 border-b border-warm-100 dark:border-warm-800">
            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-warm-500 dark:text-warm-400">
              <th class="px-5 py-3.5">{{ $t('adminInventory.thItem') }}</th>
              <th class="px-5 py-3.5">{{ $t('adminInventory.thUnit') }}</th>
              <th class="px-5 py-3.5">{{ $t('adminInventory.thStock') }}</th>
              <th class="px-5 py-3.5">{{ $t('adminInventory.thReorderLevel') }}</th>
              <th class="px-5 py-3.5">{{ $t('adminInventory.thCost') }}</th>
              <th class="px-5 py-3.5">Total Value</th>
              <th class="px-5 py-3.5">{{ $t('adminInventory.thSupplier') }}</th>
              <th class="px-5 py-3.5 text-right">{{ $t('adminInventory.thActions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-100 dark:divide-warm-800">
            <tr
              v-for="ing in ingredients()"
              :key="ing.id"
              :class="[
                'hover:bg-warm-50/60 dark:hover:bg-warm-800/40 transition-colors',
                ing.is_low_stock ? 'bg-red-50/40 dark:bg-red-950/15' : ''
              ]"
            >
              <!-- Name & Alert -->
              <td class="px-5 py-3.5 font-medium text-warm-900 dark:text-white">
                <div class="flex items-center gap-2">
                  <span
                    v-if="ing.is_low_stock"
                    class="inline-flex items-center gap-1 text-[11px] font-bold text-red-600 bg-red-100 dark:bg-red-900/40 px-2 py-0.5 rounded-full"
                    title="Stock is at or below reorder threshold"
                  >
                    <AlertTriangle :size="12" /> Low Stock
                  </span>
                  <span>{{ ing.name }}</span>
                </div>
              </td>

              <!-- Unit -->
              <td class="px-5 py-3.5 text-warm-500">{{ ing.unit }}</td>

              <!-- Current Stock -->
              <td class="px-5 py-3.5 font-bold" :class="ing.is_low_stock ? 'text-red-600 dark:text-red-400' : 'text-warm-900 dark:text-white'">
                {{ Number(ing.stock).toLocaleString('en-US') }} {{ ing.unit }}
              </td>

              <!-- Reorder Level -->
              <td class="px-5 py-3.5 text-warm-500 text-xs">
                &le; {{ ing.reorder_level }} {{ ing.unit }}
              </td>

              <!-- Unit Cost -->
              <td class="px-5 py-3.5 text-warm-700 dark:text-warm-300 font-mono text-xs">
                ETB {{ Number(ing.cost).toFixed(2) }}
              </td>

              <!-- Total Valuation -->
              <td class="px-5 py-3.5 text-warm-700 dark:text-warm-300 font-semibold font-mono text-xs">
                ETB {{ (Number(ing.stock) * Number(ing.cost)).toFixed(2) }}
              </td>

              <!-- Supplier -->
              <td class="px-5 py-3.5 text-warm-500 text-xs">
                <span v-if="ing.supplier" class="inline-flex items-center gap-1.5">
                  <Truck :size="13" class="text-warm-400" /> {{ ing.supplier.name }}
                </span>
                <span v-else class="text-warm-400 italic">—</span>
              </td>

              <!-- Action Buttons -->
              <td class="px-5 py-3.5 text-right">
                <div class="inline-flex items-center gap-1.5">
                  <button
                    @click="openAdjust(ing)"
                    class="btn-outline text-xs py-1 px-2.5 gap-1.5 hover:border-primary-500 hover:text-primary-600"
                    title="Adjust stock quantity"
                  >
                    <Sliders :size="13" /> {{ $t('adminInventory.adjustBtn') }}
                  </button>
                  <button
                    @click="openEditIngredient(ing)"
                    class="btn-ghost p-1.5 rounded-lg text-warm-500 hover:text-warm-800 dark:hover:text-warm-200"
                    title="Edit ingredient info"
                  >
                    <Pencil :size="14" />
                  </button>
                  <button
                    @click="deleteIngredient(ing)"
                    class="btn-ghost p-1.5 rounded-lg text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20"
                    title="Delete ingredient"
                  >
                    <Trash2 :size="14" />
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="!ingredients().length">
              <td colspan="8" class="px-5 py-12 text-center text-warm-400">
                <Package :size="36" class="mx-auto mb-2 opacity-40" />
                <p class="font-medium">{{ $t('adminInventory.noIngredientsFound') }}</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 2. Suppliers Tab -->
    <div v-else-if="tab === 'suppliers'" class="space-y-4">
      <TableSkeleton v-if="supLoading" :rows="5" :cols="6" />

      <div v-else class="card overflow-x-auto border border-warm-100 dark:border-warm-800">
        <table class="w-full text-sm">
          <thead class="bg-warm-50/75 dark:bg-warm-800/50 border-b border-warm-100 dark:border-warm-800">
            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-warm-500 dark:text-warm-400">
              <th class="px-5 py-3.5">{{ $t('adminInventory.thSupName') }}</th>
              <th class="px-5 py-3.5">{{ $t('adminInventory.thContactPerson') }}</th>
              <th class="px-5 py-3.5">{{ $t('adminInventory.thPhone') }}</th>
              <th class="px-5 py-3.5">{{ $t('adminInventory.thEmail') }}</th>
              <th class="px-5 py-3.5">{{ $t('adminInventory.thAssociatedItems') }}</th>
              <th class="px-5 py-3.5 text-right">{{ $t('adminInventory.thActions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-100 dark:divide-warm-800">
            <tr
              v-for="sup in (suppliersData ?? [])"
              :key="sup.id"
              class="hover:bg-warm-50/60 dark:hover:bg-warm-800/40 transition-colors"
            >
              <!-- Name & Address -->
              <td class="px-5 py-3.5">
                <div class="font-medium text-warm-900 dark:text-white flex items-center gap-2">
                  <div class="p-1.5 rounded-lg bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400">
                    <Truck :size="15" />
                  </div>
                  <span>{{ sup.name }}</span>
                </div>
                <p v-if="sup.address" class="text-xs text-warm-400 pl-8 mt-0.5">{{ sup.address }}</p>
              </td>

              <!-- Contact Person -->
              <td class="px-5 py-3.5 text-warm-700 dark:text-warm-300 font-medium">
                {{ sup.contact_name || '—' }}
              </td>

              <!-- Phone -->
              <td class="px-5 py-3.5 text-warm-600 dark:text-warm-400 font-mono text-xs">
                {{ sup.phone || '—' }}
              </td>

              <!-- Email -->
              <td class="px-5 py-3.5 text-warm-600 dark:text-warm-400 text-xs">
                <a v-if="sup.email" :href="`mailto:${sup.email}`" class="text-primary-600 dark:text-primary-400 hover:underline">
                  {{ sup.email }}
                </a>
                <span v-else>—</span>
              </td>

              <!-- Supplied ingredients count -->
              <td class="px-5 py-3.5">
                <span class="inline-flex items-center gap-1 text-xs px-2.5 py-0.5 rounded-full bg-warm-100 dark:bg-warm-800 font-medium text-warm-700 dark:text-warm-300">
                  <Boxes :size="12" /> {{ sup.ingredients_count ?? 0 }} items
                </span>
              </td>

              <!-- Actions -->
              <td class="px-5 py-3.5 text-right">
                <div class="inline-flex items-center gap-1.5">
                  <button
                    @click="openEditSupplier(sup)"
                    class="btn-ghost p-1.5 rounded-lg text-warm-500 hover:text-warm-800 dark:hover:text-warm-200"
                    title="Edit supplier"
                  >
                    <Pencil :size="14" />
                  </button>
                  <button
                    @click="deleteSupplier(sup)"
                    class="btn-ghost p-1.5 rounded-lg text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20"
                    title="Delete supplier"
                  >
                    <Trash2 :size="14" />
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="!suppliersData?.length">
              <td colspan="6" class="px-5 py-12 text-center text-warm-400">
                <Truck :size="36" class="mx-auto mb-2 opacity-40" />
                <p class="font-medium">{{ $t('adminInventory.noSuppliersFound') }}</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 3. Movements Tab -->
    <div v-else-if="tab === 'movements'" class="space-y-4">
      <TableSkeleton v-if="movLoading" :rows="6" :cols="5" />

      <div v-else class="card overflow-x-auto border border-warm-100 dark:border-warm-800">
        <table class="w-full text-sm">
          <thead class="bg-warm-50/75 dark:bg-warm-800/50 border-b border-warm-100 dark:border-warm-800">
            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-warm-500 dark:text-warm-400">
              <th class="px-5 py-3.5">{{ $t('adminInventory.thItem') }}</th>
              <th class="px-5 py-3.5">{{ $t('adminInventory.thDelta') }}</th>
              <th class="px-5 py-3.5">{{ $t('adminInventory.thReason') }}</th>
              <th class="px-5 py-3.5 text-right">{{ $t('adminInventory.thDate') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-100 dark:divide-warm-800">
            <tr
              v-for="m in movements()"
              :key="m.id"
              class="hover:bg-warm-50/60 dark:hover:bg-warm-800/40 transition-colors"
            >
              <!-- Ingredient Name & Unit -->
              <td class="px-5 py-3.5 font-medium text-warm-900 dark:text-white">
                <div class="flex items-center gap-2">
                  <Package :size="14" class="text-warm-400" />
                  <span>{{ m.ingredient_name }}</span>
                  <span class="text-xs text-warm-400">({{ m.ingredient_unit }})</span>
                </div>
              </td>

              <!-- Quantity Delta -->
              <td class="px-5 py-3.5 font-bold font-mono">
                <span
                  class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs"
                  :class="m.delta >= 0 ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400' : 'bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400'"
                >
                  <component :is="m.delta >= 0 ? TrendingUp : TrendingDown" :size="13" />
                  {{ m.delta > 0 ? `+${m.delta}` : m.delta }} {{ m.ingredient_unit }}
                </span>
              </td>

              <!-- Reason -->
              <td class="px-5 py-3.5 text-warm-600 dark:text-warm-400 text-xs">
                <span class="capitalize px-2 py-0.5 rounded bg-warm-100 dark:bg-warm-800 font-medium">
                  {{ m.reason }}
                </span>
              </td>

              <!-- Date & Time -->
              <td class="px-5 py-3.5 text-right text-xs text-warm-500 font-mono">
                {{ formatDate(m.created_at) }}
              </td>
            </tr>

            <tr v-if="!movements().length">
              <td colspan="4" class="px-5 py-12 text-center text-warm-400">
                <History :size="36" class="mx-auto mb-2 opacity-40" />
                <p class="font-medium">{{ $t('adminInventory.noMovementsFound') }}</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modals -->

    <!-- Add Ingredient Modal -->
    <Teleport to="body">
      <Transition
        enter-from-class="opacity-0"
        enter-active-class="transition-opacity duration-200"
        leave-to-class="opacity-0"
        leave-active-class="transition-opacity duration-150"
      >
        <div
          v-if="showIngModal"
          class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
          @click.self="showIngModal = false"
        >
          <div class="card w-full max-w-md p-6 shadow-2xl border border-warm-200 dark:border-warm-700">
            <div class="flex items-center justify-between mb-5">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white flex items-center gap-2">
                <Package :size="18" class="text-primary-500" />
                {{ $t('adminInventory.modalAddIngTitle') }}
              </h2>
              <button @click="showIngModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <form @submit.prevent="createIngredient" class="space-y-4">
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                    {{ $t('adminInventory.ingName') }}
                  </label>
                  <input v-model="ingForm.name" type="text" required class="input" placeholder="e.g. Olive Oil" />
                </div>
                <div>
                  <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                    {{ $t('adminInventory.unit') }}
                  </label>
                  <input v-model="ingForm.unit" type="text" required class="input" placeholder="kg, L, bunch, bottle" />
                </div>
              </div>

              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                    {{ $t('adminInventory.initialStock') }}
                  </label>
                  <input v-model.number="ingForm.stock" type="number" min="0" step="0.001" required class="input font-mono" />
                </div>
                <div>
                  <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                    {{ $t('adminInventory.reorderLevel') }}
                  </label>
                  <input v-model.number="ingForm.reorder_level" type="number" min="0" step="0.001" required class="input font-mono" />
                </div>
                <div>
                  <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                    Unit Cost (ETB)
                  </label>
                  <input v-model.number="ingForm.cost" type="number" min="0" step="0.01" required class="input font-mono" />
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                  {{ $t('adminInventory.selectSupplier') }}
                </label>
                <select v-model="ingForm.supplier_id" class="input">
                  <option value="">None (Local / Direct Market)</option>
                  <option v-for="s in (suppliersData ?? [])" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>

              <div class="flex gap-3 pt-3">
                <button type="button" @click="showIngModal = false" class="btn-outline flex-1">
                  {{ $t('adminInventory.cancel') }}
                </button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1 shadow-md">
                  {{ saving ? $t('adminInventory.saving') : $t('adminInventory.saveIng') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Edit Ingredient Modal -->
    <Teleport to="body">
      <Transition
        enter-from-class="opacity-0"
        enter-active-class="transition-opacity duration-200"
        leave-to-class="opacity-0"
        leave-active-class="transition-opacity duration-150"
      >
        <div
          v-if="showEditIngModal"
          class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
          @click.self="showEditIngModal = false"
        >
          <div class="card w-full max-w-md p-6 shadow-2xl border border-warm-200 dark:border-warm-700">
            <div class="flex items-center justify-between mb-5">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white flex items-center gap-2">
                <Pencil :size="18" class="text-primary-500" />
                {{ $t('adminInventory.modalEditIngTitle', { name: selectedIng?.name }) }}
              </h2>
              <button @click="showEditIngModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <form @submit.prevent="updateIngredient" class="space-y-4">
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                    {{ $t('adminInventory.ingName') }}
                  </label>
                  <input v-model="editIngForm.name" type="text" required class="input" />
                </div>
                <div>
                  <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                    {{ $t('adminInventory.unit') }}
                  </label>
                  <input v-model="editIngForm.unit" type="text" required class="input" />
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                    {{ $t('adminInventory.reorderLevel') }}
                  </label>
                  <input v-model.number="editIngForm.reorder_level" type="number" min="0" step="0.001" required class="input font-mono" />
                </div>
                <div>
                  <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                    Unit Cost (ETB)
                  </label>
                  <input v-model.number="editIngForm.cost" type="number" min="0" step="0.01" required class="input font-mono" />
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                  {{ $t('adminInventory.selectSupplier') }}
                </label>
                <select v-model="editIngForm.supplier_id" class="input">
                  <option value="">None (Direct Market)</option>
                  <option v-for="s in (suppliersData ?? [])" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>

              <div class="flex gap-3 pt-3">
                <button type="button" @click="showEditIngModal = false" class="btn-outline flex-1">
                  {{ $t('adminInventory.cancel') }}
                </button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1 shadow-md">
                  {{ saving ? $t('adminInventory.saving') : $t('adminInventory.saveChanges') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Adjust Stock Modal -->
    <Teleport to="body">
      <Transition
        enter-from-class="opacity-0"
        enter-active-class="transition-opacity duration-200"
        leave-to-class="opacity-0"
        leave-active-class="transition-opacity duration-150"
      >
        <div
          v-if="showAdjModal"
          class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
          @click.self="showAdjModal = false"
        >
          <div class="card w-full max-w-sm p-6 shadow-2xl border border-warm-200 dark:border-warm-700">
            <div class="flex items-center justify-between mb-4">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white flex items-center gap-2">
                <Sliders :size="18" class="text-primary-500" />
                {{ $t('adminInventory.modalAdjustTitle', { name: selectedIng?.name }) }}
              </h2>
              <button @click="showAdjModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>

            <!-- Current Stock Badge -->
            <div class="p-3 rounded-xl bg-warm-100 dark:bg-warm-800/70 mb-4 flex items-center justify-between">
              <span class="text-xs text-warm-500 font-medium">Current Stock</span>
              <span class="text-sm font-bold text-warm-900 dark:text-white font-mono">
                {{ selectedIng?.stock }} {{ selectedIng?.unit }}
              </span>
            </div>

            <form @submit.prevent="adjustStock" class="space-y-4">
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                  {{ $t('adminInventory.adjustQuantity') }}
                </label>
                <input
                  v-model.number="adjForm.delta"
                  type="number"
                  step="0.001"
                  required
                  class="input font-mono font-bold"
                  placeholder="e.g. 10 or -2.5"
                />
                <p class="text-[11px] text-warm-400 mt-1">
                  {{ $t('adminInventory.adjustQuantityHint') }}
                </p>
                <div v-if="adjForm.delta !== 0" class="mt-2 text-xs font-medium text-warm-600 dark:text-warm-300">
                  New calculated stock:
                  <span class="font-bold text-primary-600 dark:text-primary-400 font-mono">
                    {{ (Number(selectedIng?.stock ?? 0) + Number(adjForm.delta)).toFixed(3) }} {{ selectedIng?.unit }}
                  </span>
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                  {{ $t('adminInventory.adjustReason') }}
                </label>
                <select v-model="adjForm.reason" class="input">
                  <option value="purchase">{{ $t('adminInventory.reasonPurchase') }}</option>
                  <option value="waste">{{ $t('adminInventory.reasonWastage') }}</option>
                  <option value="adjustment">{{ $t('adminInventory.reasonCorrection') }}</option>
                  <option value="usage">{{ $t('adminInventory.reasonUsage') }}</option>
                </select>
              </div>

              <div class="flex gap-3 pt-3">
                <button type="button" @click="showAdjModal = false" class="btn-outline flex-1">
                  {{ $t('adminInventory.cancel') }}
                </button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1 shadow-md">
                  {{ saving ? $t('adminInventory.saving') : $t('adminInventory.saveAdjust') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Add Supplier Modal -->
    <Teleport to="body">
      <Transition
        enter-from-class="opacity-0"
        enter-active-class="transition-opacity duration-200"
        leave-to-class="opacity-0"
        leave-active-class="transition-opacity duration-150"
      >
        <div
          v-if="showSupModal"
          class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
          @click.self="showSupModal = false"
        >
          <div class="card w-full max-w-md p-6 shadow-2xl border border-warm-200 dark:border-warm-700">
            <div class="flex items-center justify-between mb-5">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white flex items-center gap-2">
                <Truck :size="18" class="text-primary-500" />
                {{ $t('adminInventory.modalAddSupTitle') }}
              </h2>
              <button @click="showSupModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <form @submit.prevent="createSupplier" class="space-y-4">
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                  {{ $t('adminInventory.supName') }}
                </label>
                <input v-model="supForm.name" type="text" required class="input" placeholder="e.g. Highland Fresh Farms" />
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                    {{ $t('adminInventory.contactPerson') }}
                  </label>
                  <input v-model="supForm.contact_name" type="text" class="input" placeholder="Selamawit T." />
                </div>
                <div>
                  <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                    {{ $t('adminInventory.phone') }}
                  </label>
                  <input v-model="supForm.phone" type="tel" class="input" placeholder="+251 91 123 4567" />
                </div>
              </div>
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                  {{ $t('adminInventory.email') }}
                </label>
                <input v-model="supForm.email" type="email" class="input" placeholder="contact@supplier.et" />
              </div>
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                  {{ $t('adminInventory.address') }}
                </label>
                <textarea v-model="supForm.address" rows="2" class="input resize-none" placeholder="Debre Zeit Road, Addis Ababa"></textarea>
              </div>
              <div class="flex gap-3 pt-3">
                <button type="button" @click="showSupModal = false" class="btn-outline flex-1">
                  {{ $t('adminInventory.cancel') }}
                </button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1 shadow-md">
                  {{ saving ? $t('adminInventory.saving') : $t('adminInventory.saveSup') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Edit Supplier Modal -->
    <Teleport to="body">
      <Transition
        enter-from-class="opacity-0"
        enter-active-class="transition-opacity duration-200"
        leave-to-class="opacity-0"
        leave-active-class="transition-opacity duration-150"
      >
        <div
          v-if="showEditSupModal"
          class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4"
          @click.self="showEditSupModal = false"
        >
          <div class="card w-full max-w-md p-6 shadow-2xl border border-warm-200 dark:border-warm-700">
            <div class="flex items-center justify-between mb-5">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white flex items-center gap-2">
                <Pencil :size="18" class="text-primary-500" />
                {{ $t('adminInventory.modalEditSupTitle', { name: selectedSup?.name }) }}
              </h2>
              <button @click="showEditSupModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <form @submit.prevent="updateSupplier" class="space-y-4">
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                  {{ $t('adminInventory.supName') }}
                </label>
                <input v-model="editSupForm.name" type="text" required class="input" />
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                    {{ $t('adminInventory.contactPerson') }}
                  </label>
                  <input v-model="editSupForm.contact_name" type="text" class="input" />
                </div>
                <div>
                  <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                    {{ $t('adminInventory.phone') }}
                  </label>
                  <input v-model="editSupForm.phone" type="tel" class="input" />
                </div>
              </div>
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                  {{ $t('adminInventory.email') }}
                </label>
                <input v-model="editSupForm.email" type="email" class="input" />
              </div>
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-warm-600 dark:text-warm-300 mb-1.5">
                  {{ $t('adminInventory.address') }}
                </label>
                <textarea v-model="editSupForm.address" rows="2" class="input resize-none"></textarea>
              </div>
              <div class="flex gap-3 pt-3">
                <button type="button" @click="showEditSupModal = false" class="btn-outline flex-1">
                  {{ $t('adminInventory.cancel') }}
                </button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1 shadow-md">
                  {{ saving ? $t('adminInventory.saving') : $t('adminInventory.saveChanges') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
