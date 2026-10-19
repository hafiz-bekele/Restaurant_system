<script setup>
import { ref } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { useI18n } from 'vue-i18n'
import { Plus, Trash2, X, Tag } from '@lucide/vue'
import TableSkeleton from '../../components/ui/TableSkeleton.vue'

const { t } = useI18n()
const ui = useUiStore()
const qc = useQueryClient()

const showModal = ref(false)
const saving    = ref(false)
const form = ref({ code: '', type: 'percent', value: 10, min_order: '', max_uses: '', starts_at: '', ends_at: '', is_active: true })

const { data, isLoading } = useQuery({
  queryKey: ['admin', 'coupons'],
  queryFn:  () => adminApi.coupons().then(r => r.data),
})
const coupons = () => data.value?.data ?? []

function openModal() {
  form.value = { code: '', type: 'percent', value: 10, min_order: '', max_uses: '', starts_at: '', ends_at: '', is_active: true }
  showModal.value = true
}

async function createCoupon() {
  saving.value = true
  try {
    const payload = {
      ...form.value,
      min_order: form.value.min_order || undefined,
      max_uses:  form.value.max_uses  || undefined,
      starts_at: form.value.starts_at || undefined,
      ends_at:   form.value.ends_at   || undefined,
    }
    await adminApi.createCoupon(payload)
    qc.invalidateQueries({ queryKey: ['admin', 'coupons'] })
    ui.toast(t('adminCoupons.toastCreated'), 'success')
    showModal.value = false
  } catch (e) {
    const errors = e.response?.data?.errors
    ui.toast(errors ? Object.values(errors).flat()[0] : t('adminCoupons.toastFailedCreate'), 'error')
  } finally {
    saving.value = false
  }
}

async function toggleActive(coupon) {
  await adminApi.updateCoupon(coupon.id, { is_active: !coupon.is_active })
  qc.invalidateQueries({ queryKey: ['admin', 'coupons'] })
  ui.toast(coupon.is_active ? t('adminCoupons.toastDeactivated') : t('adminCoupons.toastActivated'))
}

async function deleteCoupon(coupon) {
  if (!confirm(t('adminCoupons.confirmDelete', { code: coupon.code }))) return
  await adminApi.deleteCoupon(coupon.id)
  qc.invalidateQueries({ queryKey: ['admin', 'coupons'] })
  ui.toast(t('adminCoupons.toastDeleted'))
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
        <Tag :size="22" class="text-primary-500" /> {{ $t('adminCoupons.title') }}
      </h1>
      <button @click="openModal" class="btn-primary gap-2"><Plus :size="16" /> {{ $t('adminCoupons.newCouponBtn') }}</button>
    </div>

    <TableSkeleton v-if="isLoading" :rows="5" :columns="7" />

    <div v-else class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-warm-50 dark:bg-warm-800">
          <tr class="text-left text-warm-600 dark:text-warm-400">
            <th class="px-5 py-3 font-medium">{{ $t('adminCoupons.thCode') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminCoupons.thType') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminCoupons.thValue') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminCoupons.thUsed') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminCoupons.thExpires') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminCoupons.thActive') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminCoupons.thActions') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-warm-50 dark:divide-warm-800">
          <tr v-for="coupon in coupons()" :key="coupon.id" class="hover:bg-warm-50 dark:hover:bg-warm-800/50 transition-colors">
            <td class="px-5 py-3 font-mono font-bold text-warm-900 dark:text-white">{{ coupon.code }}</td>
            <td class="px-5 py-3 text-warm-500 capitalize">{{ coupon.type }}</td>
            <td class="px-5 py-3 font-semibold text-primary-600 dark:text-primary-400">
              {{ coupon.type === 'percent' ? `${coupon.value}%` : coupon.type === 'fixed' ? `ETB ${coupon.value}` : $t('adminCoupons.freeDelivery') }}
            </td>
            <td class="px-5 py-3 text-warm-500">{{ coupon.usages_count ?? 0 }} / {{ coupon.max_uses ?? '∞' }}</td>
            <td class="px-5 py-3 text-warm-500 text-xs">{{ coupon.ends_at ? new Date(coupon.ends_at).toLocaleDateString() : '—' }}</td>
            <td class="px-5 py-3">
              <button
                @click="toggleActive(coupon)"
                :class="['w-10 h-5 rounded-full transition-colors relative', coupon.is_active ? 'bg-green-500' : 'bg-warm-300 dark:bg-warm-600']"
                :aria-label="coupon.is_active ? 'Deactivate' : 'Activate'"
              >
                <span :class="['absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform', coupon.is_active ? 'translate-x-5' : 'translate-x-0.5']"></span>
              </button>
            </td>
            <td class="px-5 py-3">
              <button @click="deleteCoupon(coupon)" class="text-red-400 hover:text-red-600 p-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" :aria-label="`Delete ${coupon.code}`">
                <Trash2 :size="16" />
              </button>
            </td>
          </tr>
          <tr v-if="!coupons().length">
            <td colspan="7" class="px-5 py-10 text-center text-warm-400">{{ $t('adminCoupons.noCouponsFound') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create modal -->
    <Teleport to="body">
      <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-200" leave-to-class="opacity-0" leave-active-class="transition-opacity duration-150">
        <div v-if="showModal" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="showModal = false">
          <div class="card w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-5">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">{{ $t('adminCoupons.modalTitle') }}</h2>
              <button @click="showModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <form @submit.prevent="createCoupon" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminCoupons.couponCode') }}</label>
                <input v-model="form.code" type="text" class="input uppercase" placeholder="SUMMER20" />
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminCoupons.discountType') }}</label>
                  <select v-model="form.type" class="input">
                    <option value="percent">{{ $t('adminCoupons.typePercent') }}</option>
                    <option value="fixed">{{ $t('adminCoupons.typeFixed') }}</option>
                    <option value="free_delivery">{{ $t('adminCoupons.typeFreeDelivery') }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminCoupons.discountValue') }}</label>
                  <input v-model.number="form.value" type="number" min="0" step="0.01" :disabled="form.type === 'free_delivery'" class="input" />
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminCoupons.minOrder') }}</label>
                  <input v-model="form.min_order" type="number" min="0" class="input" placeholder="0" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminCoupons.maxUses') }}</label>
                  <input v-model="form.max_uses" type="number" min="1" class="input" placeholder="Unlimited" />
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminCoupons.startsAt') }}</label>
                  <input v-model="form.starts_at" type="date" class="input" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminCoupons.endsAt') }}</label>
                  <input v-model="form.ends_at" type="date" class="input" />
                </div>
              </div>
              <label class="flex items-center gap-2 text-sm font-medium text-warm-700 dark:text-warm-300 cursor-pointer">
                <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded accent-primary-500" />
                {{ $t('adminCoupons.isActive') }}
              </label>
              <div class="flex gap-3 pt-2">
                <button type="button" @click="showModal = false" class="btn-outline flex-1">{{ $t('adminCoupons.cancel') }}</button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1">{{ saving ? $t('adminCoupons.creating') : $t('adminCoupons.createCoupon') }}</button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
