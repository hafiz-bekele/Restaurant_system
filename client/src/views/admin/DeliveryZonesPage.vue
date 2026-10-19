<script setup>
import { ref } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { useI18n } from 'vue-i18n'
import { Plus, Trash2, X, Truck } from '@lucide/vue'
import TableSkeleton from '../../components/ui/TableSkeleton.vue'

const { t } = useI18n()
const ui = useUiStore()
const qc = useQueryClient()

const showModal = ref(false)
const saving    = ref(false)
const editing   = ref(null)
const form = ref({ name: '', fee: 0, min_order: '', eta_minutes: 30, radius_km: '', is_active: true })

const { data, isLoading } = useQuery({
  queryKey: ['admin', 'delivery-zones'],
  queryFn:  () => adminApi.deliveryZones().then(r => r.data.data),
  staleTime: 60000,
})

function openCreate() {
  editing.value = null
  form.value = { name: '', fee: 0, min_order: '', eta_minutes: 30, radius_km: '', is_active: true }
  showModal.value = true
}

function openEdit(zone) {
  editing.value = zone
  form.value = { name: zone.name, fee: zone.fee, min_order: zone.min_order ?? '', eta_minutes: zone.eta_minutes ?? 30, radius_km: zone.radius_km ?? '', is_active: zone.is_active }
  showModal.value = true
}

async function save() {
  saving.value = true
  try {
    const payload = { ...form.value, min_order: form.value.min_order || undefined, radius_km: form.value.radius_km || undefined }
    if (editing.value) {
      await adminApi.updateZone(editing.value.id, payload)
      ui.toast(t('adminDeliveryZones.toastUpdated'), 'success')
    } else {
      await adminApi.createZone(payload)
      ui.toast(t('adminDeliveryZones.toastCreated'), 'success')
    }
    qc.invalidateQueries({ queryKey: ['admin', 'delivery-zones'] })
    showModal.value = false
  } catch (e) {
    ui.toast(e.response?.data?.message ?? t('adminDeliveryZones.toastFailed'), 'error')
  } finally { saving.value = false }
}

async function deleteZone(zone) {
  if (!confirm(t('adminDeliveryZones.confirmDelete', { name: zone.name }))) return
  await adminApi.deleteZone(zone.id)
  qc.invalidateQueries({ queryKey: ['admin', 'delivery-zones'] })
  ui.toast(t('adminDeliveryZones.toastDeleted'))
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
        <Truck :size="22" class="text-primary-500" /> {{ $t('adminDeliveryZones.title') }}
      </h1>
      <button @click="openCreate" class="btn-primary gap-2"><Plus :size="16" /> {{ $t('adminDeliveryZones.newZoneBtn') }}</button>
    </div>

    <TableSkeleton v-if="isLoading" :rows="4" :columns="6" />

    <div v-else class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-warm-50 dark:bg-warm-800">
          <tr class="text-left text-warm-600 dark:text-warm-400">
            <th class="px-5 py-3 font-medium">{{ $t('adminDeliveryZones.thZoneName') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminDeliveryZones.thFee') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminDeliveryZones.thMinOrder') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminDeliveryZones.thEta') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminDeliveryZones.thActive') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminDeliveryZones.thActions') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-warm-50 dark:divide-warm-800">
          <tr v-for="zone in (data ?? [])" :key="zone.id" class="hover:bg-warm-50 dark:hover:bg-warm-800/50 transition-colors">
            <td class="px-5 py-3 font-medium text-warm-900 dark:text-white">{{ zone.name }}</td>
            <td class="px-5 py-3 font-semibold text-primary-600 dark:text-primary-400">ETB {{ Number(zone.fee).toFixed(2) }}</td>
            <td class="px-5 py-3 text-warm-500">{{ zone.min_order ? 'ETB ' + Number(zone.min_order).toFixed(2) : '—' }}</td>
            <td class="px-5 py-3 text-warm-500">{{ zone.eta_minutes ? zone.eta_minutes + ' min' : '—' }}</td>
            <td class="px-5 py-3">
              <span :class="['badge', zone.is_active ? 'badge-green' : 'badge-red']">{{ zone.is_active ? $t('adminDeliveryZones.activeBadge') : $t('adminDeliveryZones.inactiveBadge') }}</span>
            </td>
            <td class="px-5 py-3 flex items-center gap-2">
              <button @click="openEdit(zone)" class="btn-ghost text-xs py-1 px-3">{{ $t('adminDeliveryZones.editBtn') }}</button>
              <button @click="deleteZone(zone)" class="text-red-400 hover:text-red-600 p-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                <Trash2 :size="15" />
              </button>
            </td>
          </tr>
          <tr v-if="!(data ?? []).length">
            <td colspan="6" class="px-5 py-10 text-center text-warm-400">{{ $t('adminDeliveryZones.noZonesFound') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create/Edit modal -->
    <Teleport to="body">
      <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-200" leave-to-class="opacity-0" leave-active-class="transition-opacity duration-150">
        <div v-if="showModal" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="showModal = false">
          <div class="card w-full max-w-sm p-6">
            <div class="flex items-center justify-between mb-5">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">{{ editing ? $t('adminDeliveryZones.modalEditTitle') : $t('adminDeliveryZones.modalAddTitle') }}</h2>
              <button @click="showModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <form @submit.prevent="save" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminDeliveryZones.zoneName') }}</label>
                <input v-model="form.name" type="text" required class="input" :placeholder="$t('adminDeliveryZones.zoneNamePlaceholder')" />
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminDeliveryZones.deliveryFee') }}</label>
                  <input v-model.number="form.fee" type="number" min="0" step="0.01" required class="input" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminDeliveryZones.etaMinutes') }}</label>
                  <input v-model.number="form.eta_minutes" type="number" min="1" class="input" />
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminDeliveryZones.minOrderAmount') }}</label>
                  <input v-model="form.min_order" type="number" min="0" class="input" placeholder="0" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminDeliveryZones.radiusKm') }}</label>
                  <input v-model="form.radius_km" type="number" min="0" step="0.1" class="input" placeholder="optional" />
                </div>
              </div>
              <label class="flex items-center gap-2 text-sm font-medium text-warm-700 dark:text-warm-300 cursor-pointer">
                <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded accent-primary-500" />
                {{ $t('adminDeliveryZones.activeImmediately') }}
              </label>
              <div class="flex gap-3 pt-2">
                <button type="button" @click="showModal = false" class="btn-outline flex-1">{{ $t('adminDeliveryZones.cancel') }}</button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1">{{ saving ? $t('adminDeliveryZones.saving') : editing ? $t('adminDeliveryZones.saveZone') : $t('adminDeliveryZones.saveZone') }}</button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
