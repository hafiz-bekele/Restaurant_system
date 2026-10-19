<script setup>
import { ref } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useI18n } from 'vue-i18n'
import { Shield, Search } from '@lucide/vue'
import TableSkeleton from '../../components/ui/TableSkeleton.vue'

const { t } = useI18n()

const action  = ref('')
const userId  = ref('')
const page    = ref(1)

const { data, isLoading } = useQuery({
  queryKey: ['admin', 'audit', action, userId, page],
  queryFn:  () => adminApi.auditLog({
    action:  action.value  || undefined,
    user_id: userId.value  || undefined,
    page:    page.value,
  }).then(r => r.data),
  keepPreviousData: true,
})

const logs = () => data.value?.data ?? []

function actionColor(act) {
  if (act?.includes('delete') || act?.includes('cancel')) return 'badge-red'
  if (act?.includes('create') || act?.includes('place'))  return 'badge-green'
  if (act?.includes('update') || act?.includes('adjust')) return 'badge-orange'
  return 'badge-blue'
}

function formatDate(dt) {
  return dt ? new Date(dt).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' }) : '—'
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
        <Shield :size="22" class="text-primary-500" /> {{ $t('adminAudit.title') }}
      </h1>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-6">
      <div class="relative">
        <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
        <input v-model="action" type="search" :placeholder="$t('adminAudit.filterActionPlaceholder')" class="input pl-9 w-52" />
      </div>
      <input v-model="userId" type="number" :placeholder="$t('adminAudit.userIdPlaceholder')" class="input w-32" />
    </div>

    <TableSkeleton v-if="isLoading" :rows="8" :columns="4" />

    <div v-else class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-warm-50 dark:bg-warm-800">
          <tr class="text-left text-warm-600 dark:text-warm-400">
            <th class="px-5 py-3 font-medium">{{ $t('adminAudit.thAction') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminAudit.thPerformedBy') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminAudit.thDetails') }}</th>
            <th class="px-5 py-3 font-medium">{{ $t('adminAudit.thDate') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-warm-50 dark:divide-warm-800">
          <tr v-for="log in logs()" :key="log.id" class="hover:bg-warm-50 dark:hover:bg-warm-800/50 transition-colors">
            <td class="px-5 py-3">
              <span :class="['badge', actionColor(log.action)]">{{ log.action }}</span>
            </td>
            <td class="px-5 py-3 text-warm-700 dark:text-warm-300">
              {{ log.causer?.name ?? $t('adminAudit.systemUser') }}
              <span v-if="log.causer?.email" class="block text-xs text-warm-400">{{ log.causer.email }}</span>
            </td>
            <td class="px-5 py-3 text-warm-500 font-mono text-xs max-w-xs truncate">
              {{ log.properties ? JSON.stringify(log.properties) : '—' }}
            </td>
            <td class="px-5 py-3 text-warm-400 whitespace-nowrap text-xs">{{ formatDate(log.created_at) }}</td>
          </tr>
          <tr v-if="!logs().length">
            <td colspan="4" class="px-5 py-10 text-center text-warm-400">{{ $t('adminAudit.noEntriesFound') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="data?.meta" class="flex items-center justify-between mt-4 text-sm text-warm-500">
      <span>{{ $t('adminAudit.entriesCount', { total: data.meta.total }) }}</span>
      <div class="flex items-center gap-2">
        <button :disabled="page <= 1" @click="page--" class="btn-ghost text-xs py-1 px-3 disabled:opacity-40">{{ $t('adminAudit.prevBtn') }}</button>
        <span>{{ data.meta.current_page }} / {{ data.meta.last_page }}</span>
        <button :disabled="page >= (data.meta.last_page ?? 1)" @click="page++" class="btn-ghost text-xs py-1 px-3 disabled:opacity-40">{{ $t('adminAudit.nextBtn') }}</button>
      </div>
    </div>
  </div>
</template>
