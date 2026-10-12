<script setup>
import { ref } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { Plus, X, AlertTriangle, Package, Truck } from '@lucide/vue'
import TableSkeleton from '../../components/ui/TableSkeleton.vue'

const ui = useUiStore()
const qc = useQueryClient()

const tab          = ref('ingredients') // 'ingredients' | 'suppliers'
const lowStockOnly = ref(false)
const search       = ref('')
const showIngModal = ref(false)
const showAdjModal = ref(false)
const showSupModal = ref(false)
const saving       = ref(false)
const selectedIng  = ref(null)

const ingForm = ref({ name: '', unit: 'kg', stock: 0, reorder_level: 1, cost: 0, supplier_id: '' })
const adjForm = ref({ delta: 0, reason: 'purchase' })
const supForm = ref({ name: '', contact_name: '', phone: '', email: '', address: '' })

const { data: suppliersData } = useQuery({
  queryKey: ['admin', 'suppliers'],
  queryFn:  () => adminApi.suppliers().then(r => r.data.data),
  staleTime: 300000,
})

const { data: ingredientsData, isLoading: ingLoading } = useQuery({
  queryKey: ['admin', 'ingredients', lowStockOnly, search],
  queryFn:  () => adminApi.ingredients({ low_stock: lowStockOnly.value || undefined, search: search.value || undefined }).then(r => r.data),
  keepPreviousData: true,
})
const ingredients = () => ingredientsData.value?.data ?? []

function openAdjust(ing) {
  selectedIng.value = ing
  adjForm.value = { delta: 0, reason: 'purchase' }
  showAdjModal.value = true
}

async function createIngredient() {
  saving.value = true
  try {
    const payload = { ...ingForm.value, supplier_id: ingForm.value.supplier_id || undefined }
    await adminApi.createIngredient(payload)
    qc.invalidateQueries({ queryKey: ['admin', 'ingredients'] })
    ui.toast('Ingredient added', 'success')
    showIngModal.value = false
  } catch (e) {
    ui.toast(e.response?.data?.message ?? 'Failed', 'error')
  } finally {
    saving.value = false
  }
}

async function adjustStock() {
  saving.value = true
  try {
    await adminApi.adjustStock(selectedIng.value.id, adjForm.value)
    qc.invalidateQueries({ queryKey: ['admin', 'ingredients'] })
    ui.toast('Stock adjusted', 'success')
    showAdjModal.value = false
  } catch (e) {
    ui.toast(e.response?.data?.message ?? 'Failed', 'error')
  } finally {
    saving.value = false
  }
}

async function createSupplier() {
  saving.value = true
  try {
    await adminApi.createSupplier(supForm.value)
    qc.invalidateQueries({ queryKey: ['admin', 'suppliers'] })
    ui.toast('Supplier added', 'success')
    showSupModal.value = false
  } catch (e) {
    ui.toast(e.response?.data?.message ?? 'Failed', 'error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
        <Package :size="22" class="text-primary-500" /> Inventory
      </h1>
      <div class="flex gap-2">
        <button v-if="tab === 'ingredients'" @click="showIngModal = true" class="btn-primary gap-2 text-sm"><Plus :size="15" /> Ingredient</button>
        <button v-if="tab === 'suppliers'"   @click="showSupModal = true" class="btn-primary gap-2 text-sm"><Plus :size="15" /> Supplier</button>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-6 bg-warm-100 dark:bg-warm-800 p-1 rounded-xl w-fit">
      <button @click="tab = 'ingredients'" :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors', tab === 'ingredients' ? 'bg-white dark:bg-gray-900 shadow text-warm-900 dark:text-white' : 'text-warm-500 hover:text-warm-700 dark:hover:text-warm-300']">
        Ingredients
      </button>
      <button @click="tab = 'suppliers'" :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors', tab === 'suppliers' ? 'bg-white dark:bg-gray-900 shadow text-warm-900 dark:text-white' : 'text-warm-500 hover:text-warm-700 dark:hover:text-warm-300']">
        Suppliers
      </button>
    </div>

    <!-- Ingredients tab -->
    <div v-if="tab === 'ingredients'">
      <div class="flex flex-wrap gap-3 mb-5">
        <input v-model="search" type="search" placeholder="Search ingredients…" class="input w-60" />
        <label class="flex items-center gap-2 text-sm font-medium text-warm-700 dark:text-warm-300 cursor-pointer px-4 py-2 rounded-xl bg-warm-100 dark:bg-warm-800 hover:bg-warm-200 dark:hover:bg-warm-700 transition-colors">
          <input v-model="lowStockOnly" type="checkbox" class="accent-red-500" />
          <AlertTriangle :size="14" class="text-red-500" /> Low stock only
        </label>
      </div>

      <TableSkeleton v-if="ingLoading" :rows="6" :cols="7" />

      <div v-else class="card overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-warm-50 dark:bg-warm-800">
            <tr class="text-left text-warm-600 dark:text-warm-400">
              <th class="px-5 py-3 font-medium">Name</th>
              <th class="px-5 py-3 font-medium">Unit</th>
              <th class="px-5 py-3 font-medium">Stock</th>
              <th class="px-5 py-3 font-medium">Reorder At</th>
              <th class="px-5 py-3 font-medium">Cost/Unit</th>
              <th class="px-5 py-3 font-medium">Supplier</th>
              <th class="px-5 py-3 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-50 dark:divide-warm-800">
            <tr v-for="ing in ingredients()" :key="ing.id" :class="['hover:bg-warm-50 dark:hover:bg-warm-800/50 transition-colors', ing.is_low_stock ? 'bg-red-50/50 dark:bg-red-900/10' : '']">
              <td class="px-5 py-3 font-medium text-warm-900 dark:text-white">
                <span v-if="ing.is_low_stock" class="mr-1.5 text-red-500"><AlertTriangle :size="13" class="inline" /></span>
                {{ ing.name }}
              </td>
              <td class="px-5 py-3 text-warm-500">{{ ing.unit }}</td>
              <td class="px-5 py-3 font-semibold" :class="ing.is_low_stock ? 'text-red-500' : 'text-warm-900 dark:text-white'">{{ ing.stock }}</td>
              <td class="px-5 py-3 text-warm-500">{{ ing.reorder_level }}</td>
              <td class="px-5 py-3 text-warm-500">${{ Number(ing.cost).toFixed(2) }}</td>
              <td class="px-5 py-3 text-warm-500 text-xs">{{ ing.supplier?.name ?? '—' }}</td>
              <td class="px-5 py-3">
                <button @click="openAdjust(ing)" class="text-xs btn-outline py-1 px-3">Adjust</button>
              </td>
            </tr>
            <tr v-if="!ingredients().length">
              <td colspan="7" class="px-5 py-10 text-center text-warm-400">No ingredients found</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Suppliers tab -->
    <div v-else>
      <TableSkeleton v-if="!suppliersData" :rows="5" :cols="4" />
      <div v-else class="card overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-warm-50 dark:bg-warm-800">
            <tr class="text-left text-warm-600 dark:text-warm-400">
              <th class="px-5 py-3 font-medium">Name</th>
              <th class="px-5 py-3 font-medium">Contact</th>
              <th class="px-5 py-3 font-medium">Phone</th>
              <th class="px-5 py-3 font-medium">Email</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-50 dark:divide-warm-800">
            <tr v-for="sup in (suppliersData ?? [])" :key="sup.id" class="hover:bg-warm-50 dark:hover:bg-warm-800/50 transition-colors">
              <td class="px-5 py-3 font-medium text-warm-900 dark:text-white flex items-center gap-2"><Truck :size="14" class="text-warm-400" />{{ sup.name }}</td>
              <td class="px-5 py-3 text-warm-500">{{ sup.contact_name ?? '—' }}</td>
              <td class="px-5 py-3 text-warm-500">{{ sup.phone ?? '—' }}</td>
              <td class="px-5 py-3 text-warm-500">{{ sup.email ?? '—' }}</td>
            </tr>
            <tr v-if="!suppliersData?.length">
              <td colspan="4" class="px-5 py-10 text-center text-warm-400">No suppliers yet</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add Ingredient modal -->
    <Teleport to="body">
      <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-200" leave-to-class="opacity-0" leave-active-class="transition-opacity duration-150">
        <div v-if="showIngModal" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="showIngModal = false">
          <div class="card w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-5">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">New Ingredient</h2>
              <button @click="showIngModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <form @submit.prevent="createIngredient" class="space-y-4">
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Name</label>
                  <input v-model="ingForm.name" type="text" required class="input" placeholder="Tomatoes" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Unit</label>
                  <input v-model="ingForm.unit" type="text" required class="input" placeholder="kg" />
                </div>
              </div>
              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Stock</label>
                  <input v-model.number="ingForm.stock" type="number" min="0" step="0.001" required class="input" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Reorder At</label>
                  <input v-model.number="ingForm.reorder_level" type="number" min="0" step="0.001" required class="input" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Cost ($)</label>
                  <input v-model.number="ingForm.cost" type="number" min="0" step="0.01" required class="input" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Supplier (optional)</label>
                <select v-model="ingForm.supplier_id" class="input">
                  <option value="">None</option>
                  <option v-for="s in (suppliersData ?? [])" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>
              <div class="flex gap-3 pt-2">
                <button type="button" @click="showIngModal = false" class="btn-outline flex-1">Cancel</button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1">{{ saving ? 'Adding…' : 'Add Ingredient' }}</button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Adjust stock modal -->
    <Teleport to="body">
      <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-200" leave-to-class="opacity-0" leave-active-class="transition-opacity duration-150">
        <div v-if="showAdjModal" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="showAdjModal = false">
          <div class="card w-full max-w-sm p-6">
            <div class="flex items-center justify-between mb-5">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">Adjust Stock — {{ selectedIng?.name }}</h2>
              <button @click="showAdjModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <p class="text-sm text-warm-500 mb-4">Current: <strong class="text-warm-900 dark:text-white">{{ selectedIng?.stock }} {{ selectedIng?.unit }}</strong></p>
            <form @submit.prevent="adjustStock" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Delta (positive = add, negative = remove)</label>
                <input v-model.number="adjForm.delta" type="number" step="0.001" required class="input" placeholder="e.g. 10 or -2.5" />
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Reason</label>
                <select v-model="adjForm.reason" class="input">
                  <option value="purchase">Purchase</option>
                  <option value="waste">Waste</option>
                  <option value="adjustment">Manual Adjustment</option>
                </select>
              </div>
              <div class="flex gap-3 pt-2">
                <button type="button" @click="showAdjModal = false" class="btn-outline flex-1">Cancel</button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1">{{ saving ? 'Saving…' : 'Apply' }}</button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Add Supplier modal -->
    <Teleport to="body">
      <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-200" leave-to-class="opacity-0" leave-active-class="transition-opacity duration-150">
        <div v-if="showSupModal" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="showSupModal = false">
          <div class="card w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-5">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">New Supplier</h2>
              <button @click="showSupModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <form @submit.prevent="createSupplier" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Company Name</label>
                <input v-model="supForm.name" type="text" required class="input" placeholder="Fresh Farms Ltd." />
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Contact Name</label>
                  <input v-model="supForm.contact_name" type="text" class="input" placeholder="John Smith" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Phone</label>
                  <input v-model="supForm.phone" type="tel" class="input" placeholder="+251 911 000 000" />
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Email</label>
                <input v-model="supForm.email" type="email" class="input" placeholder="supplier@example.com" />
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Address</label>
                <textarea v-model="supForm.address" rows="2" class="input resize-none" placeholder="123 Farm Road…"></textarea>
              </div>
              <div class="flex gap-3 pt-2">
                <button type="button" @click="showSupModal = false" class="btn-outline flex-1">Cancel</button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1">{{ saving ? 'Adding…' : 'Add Supplier' }}</button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
