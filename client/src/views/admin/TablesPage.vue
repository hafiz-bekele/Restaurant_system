<script setup>
import { ref } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import api from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { Plus, Trash2, X, QrCode, RefreshCw } from '@lucide/vue'

const ui = useUiStore()
const qc = useQueryClient()

const areaFilter = ref('')
const showModal  = ref(false)
const saving     = ref(false)
const form = ref({ name: '', capacity: 2, area: '' })

const { data: areasData } = useQuery({
  queryKey: ['admin', 'tables', 'areas'],
  queryFn:  () => adminApi.tableAreas().then(r => r.data.data),
  staleTime: 300000,
})

const { data, isLoading } = useQuery({
  queryKey: ['admin', 'tables', areaFilter],
  queryFn:  () => adminApi.tables({ area: areaFilter.value || undefined }).then(r => r.data.data),
  keepPreviousData: true,
})

const tables = () => data.value ?? []

function openModal() {
  form.value = { name: '', capacity: 2, area: '' }
  showModal.value = true
}

async function createTable() {
  saving.value = true
  try {
    await adminApi.createTable(form.value)
    qc.invalidateQueries({ queryKey: ['admin', 'tables'] })
    ui.toast('Table created', 'success')
    showModal.value = false
  } catch (e) {
    const errors = e.response?.data?.errors
    ui.toast(errors ? Object.values(errors).flat()[0] : 'Failed to create table', 'error')
  } finally {
    saving.value = false
  }
}

async function regenerateQr(table) {
  await adminApi.regenerateQr(table.id)
  qc.invalidateQueries({ queryKey: ['admin', 'tables'] })
  ui.toast('QR token regenerated')
}

async function deleteTable(table) {
  if (!confirm(`Delete table "${table.name}"?`)) return
  await adminApi.deleteTable(table.id)
  qc.invalidateQueries({ queryKey: ['admin', 'tables'] })
  ui.toast('Table deleted')
}

async function downloadQr(table) {
  try {
    const res = await api.get(`/admin/tables/${table.id}/qr`, { responseType: 'blob' })
    const url  = URL.createObjectURL(res.data)
    const link = document.createElement('a')
    link.href     = url
    link.download = `table-${table.name}-qr.png`
    link.click()
    URL.revokeObjectURL(url)
  } catch {
    ui.toast('Failed to download QR code', 'error')
  }
}

const statusColors = { available: 'badge-green', occupied: 'badge-red', reserved: 'badge-orange', inactive: 'badge' }
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white">Tables</h1>
      <button @click="openModal" class="btn-primary gap-2"><Plus :size="16" /> New Table</button>
    </div>

    <!-- Area filter -->
    <div class="flex flex-wrap gap-2 mb-6">
      <button @click="areaFilter = ''" :class="['px-4 py-1.5 rounded-full text-sm font-medium transition-colors', !areaFilter ? 'bg-primary-500 text-white' : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-warm-200']">
        All Areas
      </button>
      <button v-for="area in (areasData ?? [])" :key="area"
        @click="areaFilter = areaFilter === area ? '' : area"
        :class="['px-4 py-1.5 rounded-full text-sm font-medium transition-colors', areaFilter === area ? 'bg-primary-500 text-white' : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-warm-200']"
      >{{ area }}</button>
    </div>

    <div v-if="isLoading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
      <div v-for="i in 8" :key="i" class="card p-4 space-y-3 border border-warm-100 dark:border-warm-800">
        <div class="flex items-start justify-between">
          <div class="space-y-1.5">
            <div class="skeleton h-4 w-20 rounded"></div>
            <div class="skeleton h-3 w-28 rounded"></div>
          </div>
          <div class="skeleton h-5 w-14 !rounded-full"></div>
        </div>
        <div class="flex items-center gap-2 pt-2 border-t border-warm-100 dark:border-warm-800">
          <div class="skeleton h-6 w-6 rounded-lg"></div>
          <div class="skeleton h-6 w-6 rounded-lg"></div>
          <div class="skeleton h-6 w-6 rounded-lg ml-auto"></div>
        </div>
      </div>
    </div>

    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
      <div v-for="table in tables()" :key="table.id" class="card p-4 flex flex-col gap-2">
        <div class="flex items-start justify-between">
          <div>
            <p class="font-heading font-bold text-warm-900 dark:text-white">{{ table.name }}</p>
            <p class="text-xs text-warm-500">{{ table.area || 'No area' }} · {{ table.capacity }} seats</p>
          </div>
          <span :class="['badge', statusColors[table.status] ?? 'badge']">{{ table.status }}</span>
        </div>
        <div class="flex items-center gap-1 mt-auto pt-2 border-t border-warm-100 dark:border-warm-800">
          <button @click="downloadQr(table)" class="btn-ghost p-1.5 rounded-lg text-xs flex items-center gap-1 text-warm-500 hover:text-primary-600" title="Download QR">
            <QrCode :size="14" />
          </button>
          <button @click="regenerateQr(table)" class="btn-ghost p-1.5 rounded-lg text-xs flex items-center gap-1 text-warm-500 hover:text-blue-600" title="Regenerate QR">
            <RefreshCw :size="14" />
          </button>
          <button @click="deleteTable(table)" class="ml-auto text-red-400 hover:text-red-600 p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" :aria-label="`Delete ${table.name}`">
            <Trash2 :size="14" />
          </button>
        </div>
      </div>
      <div v-if="!tables().length" class="col-span-full card p-10 text-center text-warm-400">No tables found</div>
    </div>

    <!-- Create modal -->
    <Teleport to="body">
      <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-200" leave-to-class="opacity-0" leave-active-class="transition-opacity duration-150">
        <div v-if="showModal" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="showModal = false">
          <div class="card w-full max-w-sm p-6">
            <div class="flex items-center justify-between mb-5">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">New Table</h2>
              <button @click="showModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <form @submit.prevent="createTable" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Table Name</label>
                <input v-model="form.name" type="text" required class="input" placeholder="T-01" />
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Capacity</label>
                <input v-model.number="form.capacity" type="number" min="1" max="50" required class="input" />
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Area (optional)</label>
                <input v-model="form.area" type="text" class="input" placeholder="Indoor, Terrace…" />
              </div>
              <div class="flex gap-3 pt-2">
                <button type="button" @click="showModal = false" class="btn-outline flex-1">Cancel</button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1">{{ saving ? 'Creating…' : 'Create Table' }}</button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
