<script setup>
import { ref } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { AlertTriangle, X } from '@lucide/vue'

const ui = useUiStore()
const qc = useQueryClient()

const statusFilter   = ref('')
const categoryFilter = ref('')
const saving         = ref(false)
const selected       = ref(null)
const showModal      = ref(false)
const resolutionNote = ref('')
const newStatus      = ref('')

const statusOptions   = ['', 'open', 'investigating', 'resolved', 'dismissed']
const categoryOptions = ['', 'late_food', 'cold_dish', 'missing_item', 'staff_behavior', 'other']

const { data, isLoading } = useQuery({
  queryKey: ['admin', 'complaints', statusFilter, categoryFilter],
  queryFn:  () => adminApi.complaints({ status: statusFilter.value || undefined, category: categoryFilter.value || undefined }).then(r => r.data),
  keepPreviousData: true,
  refetchInterval: 30000,
})

const tickets = () => data.value?.data ?? []

const statusColors = { open: 'badge-red', investigating: 'badge-orange', resolved: 'badge-green', dismissed: 'badge' }

function openTicket(ticket) {
  selected.value    = ticket
  newStatus.value   = ticket.status
  resolutionNote.value = ticket.resolution_note ?? ''
  showModal.value   = true
}

async function saveTicket() {
  saving.value = true
  try {
    await adminApi.updateComplaint(selected.value.id, { status: newStatus.value, resolution_note: resolutionNote.value })
    qc.invalidateQueries({ queryKey: ['admin', 'complaints'] })
    ui.toast('Ticket updated', 'success')
    showModal.value = false
  } catch { ui.toast('Failed to update ticket', 'error') }
  finally { saving.value = false }
}

function formatDate(dt) {
  return dt ? new Date(dt).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' }) : '—'
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
        <AlertTriangle :size="22" class="text-orange-500" /> Complaints
      </h1>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-6">
      <select v-model="statusFilter" class="input w-auto">
        <option v-for="s in statusOptions" :key="s" :value="s">{{ s || 'All Statuses' }}</option>
      </select>
      <select v-model="categoryFilter" class="input w-auto">
        <option v-for="c in categoryOptions" :key="c" :value="c">{{ c ? c.replace('_', ' ') : 'All Categories' }}</option>
      </select>
    </div>

    <div v-if="isLoading" class="space-y-3">
      <div v-for="i in 5" :key="i" class="skeleton h-16 rounded-xl"></div>
    </div>

    <div v-else class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-warm-50 dark:bg-warm-800">
          <tr class="text-left text-warm-600 dark:text-warm-400">
            <th class="px-5 py-3 font-medium">Ticket</th>
            <th class="px-5 py-3 font-medium">Category</th>
            <th class="px-5 py-3 font-medium">Subject</th>
            <th class="px-5 py-3 font-medium">Guest</th>
            <th class="px-5 py-3 font-medium">Status</th>
            <th class="px-5 py-3 font-medium">Created</th>
            <th class="px-5 py-3 font-medium">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-warm-50 dark:divide-warm-800">
          <tr v-for="t in tickets()" :key="t.id" class="hover:bg-warm-50 dark:hover:bg-warm-800/50 transition-colors">
            <td class="px-5 py-3 font-mono text-xs font-bold text-primary-600 dark:text-primary-400">{{ t.ticket_number }}</td>
            <td class="px-5 py-3 capitalize text-warm-500 text-xs">{{ t.category?.replace('_', ' ') }}</td>
            <td class="px-5 py-3 font-medium text-warm-900 dark:text-white max-w-xs truncate">{{ t.subject }}</td>
            <td class="px-5 py-3 text-warm-500 text-xs">{{ t.user?.name ?? 'Guest' }}</td>
            <td class="px-5 py-3">
              <span :class="['badge', statusColors[t.status] ?? 'badge']">{{ t.status }}</span>
            </td>
            <td class="px-5 py-3 text-warm-400 text-xs whitespace-nowrap">{{ formatDate(t.created_at) }}</td>
            <td class="px-5 py-3">
              <button @click="openTicket(t)" class="btn-ghost text-xs py-1 px-3">Manage</button>
            </td>
          </tr>
          <tr v-if="!tickets().length">
            <td colspan="7" class="px-5 py-10 text-center text-warm-400">No complaints found</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="data?.meta" class="flex items-center justify-between mt-4 text-sm text-warm-500">
      <span>{{ data.meta.total }} tickets</span>
      <span>Page {{ data.meta.current_page }} of {{ data.meta.last_page }}</span>
    </div>

    <!-- Manage modal -->
    <Teleport to="body">
      <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-200" leave-to-class="opacity-0" leave-active-class="transition-opacity duration-150">
        <div v-if="showModal" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="showModal = false">
          <div class="card w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-4">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">{{ selected?.ticket_number }}</h2>
              <button @click="showModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <p class="text-sm font-medium text-warm-700 dark:text-warm-300 mb-1">{{ selected?.subject }}</p>
            <p class="text-xs text-warm-400 capitalize mb-4">{{ selected?.category?.replace('_', ' ') }} · {{ selected?.user?.name ?? 'Guest' }}</p>
            <p v-if="selected?.description" class="text-sm text-warm-600 dark:text-warm-400 bg-warm-50 dark:bg-warm-800 rounded-xl p-3 mb-4">{{ selected.description }}</p>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Status</label>
                <select v-model="newStatus" class="input">
                  <option v-for="s in ['open','investigating','resolved','dismissed']" :key="s" :value="s">{{ s }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Resolution Note</label>
                <textarea v-model="resolutionNote" rows="3" class="input resize-none" placeholder="What action was taken?"></textarea>
              </div>
              <div class="flex gap-3 pt-2">
                <button @click="showModal = false" class="btn-outline flex-1">Cancel</button>
                <button @click="saveTicket" :disabled="saving" class="btn-primary flex-1">{{ saving ? 'Saving…' : 'Save Changes' }}</button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
