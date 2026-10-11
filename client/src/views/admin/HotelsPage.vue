<script setup>
import { ref } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { Building2, Plus, Edit2, Trash2, X, CheckCircle, Globe, Phone, Mail, MapPin } from '@lucide/vue'

const ui = useUiStore()
const qc = useQueryClient()

const search = ref('')
const showModal = ref(false)
const saving = ref(false)
const editingHotel = ref(null)

const form = ref({
  name: '',
  currency: 'ETB',
  vat_rate: 15.00,
  service_charge_rate: 10.00,
  phone: '',
  email: '',
  address: '',
  is_active: true,
})

const { data: hotelsData, isLoading } = useQuery({
  queryKey: ['admin', 'hotels', search],
  queryFn: () => adminApi.hotels({ search: search.value || undefined }).then(r => r.data.data),
})

const hotels = () => hotelsData.value ?? []

function openCreateModal() {
  editingHotel.value = null
  form.value = {
    name: '',
    currency: 'ETB',
    vat_rate: 15.00,
    service_charge_rate: 10.00,
    phone: '',
    email: '',
    address: '',
    is_active: true,
  }
  showModal.value = true
}

function openEditModal(hotel) {
  editingHotel.value = hotel
  form.value = {
    name: hotel.name,
    currency: hotel.currency || 'ETB',
    vat_rate: Number(hotel.vat_rate) || 15.00,
    service_charge_rate: Number(hotel.service_charge_rate) || 10.00,
    phone: hotel.phone || '',
    email: hotel.email || '',
    address: hotel.address || '',
    is_active: !!hotel.is_active,
  }
  showModal.value = true
}

async function saveHotel() {
  saving.value = true
  try {
    if (editingHotel.value) {
      await adminApi.updateHotel(editingHotel.value.id, form.value)
      ui.toast('Hotel property updated', 'success')
    } else {
      await adminApi.createHotel(form.value)
      ui.toast('New hotel tenant registered', 'success')
    }
    qc.invalidateQueries({ queryKey: ['admin', 'hotels'] })
    showModal.value = false
  } catch (e) {
    ui.toast(e.response?.data?.message || 'Failed to save hotel', 'error')
  } finally {
    saving.value = false
  }
}

async function deleteHotel(hotel) {
  if (!confirm(`Are you sure you want to delete "${hotel.name}"? This removes tenant scoping for this property.`)) return
  try {
    await adminApi.deleteHotel(hotel.id)
    qc.invalidateQueries({ queryKey: ['admin', 'hotels'] })
    ui.toast('Hotel deleted', 'success')
  } catch {
    ui.toast('Failed to delete hotel', 'error')
  }
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
          <Building2 :size="24" class="text-primary-500" />
          Multi-Tenant Properties (Hotels)
        </h1>
        <p class="text-sm text-warm-500 dark:text-warm-400 mt-1">
          Super Admin Global Tenant Configuration & Scoping Engine (REQ-1.x)
        </p>
      </div>
      <button @click="openCreateModal" class="btn-primary gap-2 self-start sm:self-auto">
        <Plus :size="16" /> Register New Hotel
      </button>
    </div>

    <!-- Search bar -->
    <div class="mb-6">
      <input
        v-model="search"
        type="search"
        placeholder="Search hotel name or email..."
        class="input w-full max-w-sm"
      />
    </div>

    <!-- Hotels Grid -->
    <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="i in 3" :key="i" class="skeleton h-56 rounded-2xl"></div>
    </div>

    <div v-else-if="hotels().length === 0" class="card text-center py-12">
      <Building2 :size="48" class="mx-auto text-warm-300 dark:text-warm-700 mb-3" />
      <h3 class="text-lg font-bold text-warm-800 dark:text-warm-200">No properties found</h3>
      <p class="text-sm text-warm-500 mt-1">Register your first hotel tenant to begin multi-property operations.</p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div
        v-for="hotel in hotels()"
        :key="hotel.id"
        class="card hover:shadow-card-hover transition-all duration-300 flex flex-col justify-between"
      >
        <div>
          <!-- Card Header -->
          <div class="flex items-start justify-between gap-3 mb-3">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-xl bg-primary-100 dark:bg-primary-950/60 flex items-center justify-center text-primary-600 font-bold text-lg">
                {{ hotel.name.substring(0, 2).toUpperCase() }}
              </div>
              <div>
                <h3 class="font-bold text-warm-900 dark:text-white leading-tight">{{ hotel.name }}</h3>
                <span class="text-xs font-mono text-warm-400">ID: {{ hotel.id }} | {{ hotel.slug }}</span>
              </div>
            </div>
            <span :class="['badge text-xs', hotel.is_active ? 'badge-green' : 'badge-red']">
              {{ hotel.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>

          <!-- Financial Configurations (REQ-1.4) -->
          <div class="grid grid-cols-3 gap-2 p-3 bg-warm-50 dark:bg-warm-850 rounded-xl mb-4 text-center">
            <div>
              <span class="text-[10px] text-warm-400 uppercase font-semibold">Currency</span>
              <p class="text-sm font-bold text-warm-800 dark:text-warm-200">{{ hotel.currency }}</p>
            </div>
            <div>
              <span class="text-[10px] text-warm-400 uppercase font-semibold">VAT Tax</span>
              <p class="text-sm font-bold text-primary-600 dark:text-primary-400">{{ Number(hotel.vat_rate).toFixed(1) }}%</p>
            </div>
            <div>
              <span class="text-[10px] text-warm-400 uppercase font-semibold">Service Fee</span>
              <p class="text-sm font-bold text-primary-600 dark:text-primary-400">{{ Number(hotel.service_charge_rate).toFixed(1) }}%</p>
            </div>
          </div>

          <!-- Operational Stats -->
          <div class="space-y-1.5 text-xs text-warm-600 dark:text-warm-400 mb-4">
            <div v-if="hotel.phone" class="flex items-center gap-2">
              <Phone :size="13" class="text-warm-400" />
              <span>{{ hotel.phone }}</span>
            </div>
            <div v-if="hotel.email" class="flex items-center gap-2">
              <Mail :size="13" class="text-warm-400" />
              <span>{{ hotel.email }}</span>
            </div>
            <div v-if="hotel.address" class="flex items-center gap-2">
              <MapPin :size="13" class="text-warm-400 shrink-0" />
              <span class="truncate">{{ hotel.address }}</span>
            </div>
          </div>

          <!-- Sub-counts -->
          <div class="flex items-center justify-between pt-3 border-t border-warm-100 dark:border-warm-800 text-[11px] text-warm-500 font-medium">
            <span>{{ hotel.rooms_count ?? 0 }} Suites</span>
            <span>{{ hotel.users_count ?? 0 }} Staff</span>
            <span>{{ hotel.orders_count ?? 0 }} Orders</span>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2 pt-4 mt-4 border-t border-warm-100 dark:border-warm-800">
          <button @click="openEditModal(hotel)" class="btn-secondary flex-1 py-1.5 text-xs justify-center gap-1.5">
            <Edit2 :size="14" /> Edit
          </button>
          <button @click="deleteHotel(hotel)" class="btn-ghost text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40 p-2 rounded-xl" title="Delete Hotel">
            <Trash2 :size="15" />
          </button>
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
      <div class="card w-full max-w-lg shadow-float">
        <div class="flex items-center justify-between pb-4 border-b border-warm-100 dark:border-warm-800 mb-4">
          <h2 class="font-heading text-lg font-bold text-warm-900 dark:text-white flex items-center gap-2">
            <Building2 :size="20" class="text-primary-500" />
            {{ editingHotel ? 'Edit Hotel Tenant' : 'Register New Hotel Tenant' }}
          </h2>
          <button @click="showModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
        </div>

        <form @submit.prevent="saveHotel" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">Hotel / Property Name *</label>
            <input v-model="form.name" type="text" required class="input" placeholder="e.g. Grand Palace Hotel & Resort" />
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">Currency</label>
              <select v-model="form.currency" class="input">
                <option value="ETB">ETB (Birr)</option>
                <option value="USD">USD ($)</option>
                <option value="EUR">EUR (€)</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">VAT Rate (%)</label>
              <input v-model.number="form.vat_rate" type="number" step="0.01" min="0" max="100" class="input" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">Service Fee (%)</label>
              <input v-model.number="form.service_charge_rate" type="number" step="0.01" min="0" max="100" class="input" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">Phone</label>
              <input v-model="form.phone" type="text" class="input" placeholder="+251 91 123 4567" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">Email</label>
              <input v-model="form.email" type="email" class="input" placeholder="info@hotel.com" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">Physical Address</label>
            <textarea v-model="form.address" rows="2" class="input" placeholder="Bole Sub-City, Addis Ababa, Ethiopia"></textarea>
          </div>

          <div class="flex items-center gap-2 pt-2">
            <input id="isActiveCheckbox" v-model="form.is_active" type="checkbox" class="rounded text-primary-600 focus:ring-primary-500" />
            <label for="isActiveCheckbox" class="text-xs font-semibold text-warm-800 dark:text-warm-200">Property is active and accepting bookings</label>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t border-warm-100 dark:border-warm-800">
            <button type="button" @click="showModal = false" class="btn-ghost">Cancel</button>
            <button type="submit" :disabled="saving" class="btn-primary">
              {{ saving ? 'Saving Property...' : (editingHotel ? 'Save Changes' : 'Create Property') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
