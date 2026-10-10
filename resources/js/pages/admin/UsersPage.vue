<script setup>
import { ref } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { Plus, Trash2, X, User } from '@lucide/vue'

const ui = useUiStore()
const qc = useQueryClient()

const search = ref('')
const roleFilter = ref('')
const showModal = ref(false)
const saving = ref(false)

const form = ref({ name: '', email: '', phone: '', password: '', role: 'waiter' })

const { data: rolesData } = useQuery({
  queryKey: ['admin', 'roles'],
  queryFn:  () => adminApi.roles().then(r => r.data.data),
  staleTime: 600000,
})

const { data, isLoading } = useQuery({
  queryKey: ['admin', 'users', search, roleFilter],
  queryFn:  () => adminApi.users({
    search:  search.value  || undefined,
    role:    roleFilter.value || undefined,
  }).then(r => r.data),
  keepPreviousData: true,
})

const users = () => data.value?.data ?? []

function openModal() {
  form.value = { name: '', email: '', phone: '', password: '', role: 'waiter' }
  showModal.value = true
}

async function createUser() {
  saving.value = true
  try {
    await adminApi.createUser(form.value)
    qc.invalidateQueries({ queryKey: ['admin', 'users'] })
    ui.toast('User created', 'success')
    showModal.value = false
  } catch (e) {
    const errors = e.response?.data?.errors
    ui.toast(errors ? Object.values(errors).flat()[0] : 'Failed to create user', 'error')
  } finally {
    saving.value = false
  }
}

async function updateStatus(user, status) {
  await adminApi.updateUser(user.id, { status })
  qc.invalidateQueries({ queryKey: ['admin', 'users'] })
  ui.toast('Status updated')
}

async function deleteUser(user) {
  if (!confirm(`Delete ${user.name}?`)) return
  await adminApi.deleteUser(user.id)
  qc.invalidateQueries({ queryKey: ['admin', 'users'] })
  ui.toast('User deleted')
}

const statusColors = { active: 'badge-green', inactive: 'badge-orange', blocked: 'badge-red' }
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white">Users</h1>
      <button @click="openModal" class="btn-primary gap-2"><Plus :size="16" /> New User</button>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-6">
      <input v-model="search" type="search" placeholder="Search name or email…" class="input w-64" />
      <select v-model="roleFilter" class="input w-auto">
        <option value="">All Roles</option>
        <option v-for="r in (rolesData ?? [])" :key="r.id" :value="r.name">{{ r.name }}</option>
      </select>
    </div>

    <div v-if="isLoading" class="space-y-3">
      <div v-for="i in 5" :key="i" class="skeleton h-14 rounded-xl"></div>
    </div>

    <div v-else class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-warm-50 dark:bg-warm-800">
          <tr class="text-left text-warm-600 dark:text-warm-400">
            <th class="px-5 py-3 font-medium">Name</th>
            <th class="px-5 py-3 font-medium">Email</th>
            <th class="px-5 py-3 font-medium">Role</th>
            <th class="px-5 py-3 font-medium">Status</th>
            <th class="px-5 py-3 font-medium">Points</th>
            <th class="px-5 py-3 font-medium">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-warm-50 dark:divide-warm-800">
          <tr v-for="user in users()" :key="user.id" class="hover:bg-warm-50 dark:hover:bg-warm-800/50 transition-colors">
            <td class="px-5 py-3 font-medium text-warm-900 dark:text-white flex items-center gap-2">
              <div class="w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-xs font-bold text-primary-600 dark:text-primary-400 shrink-0">
                {{ user.name?.[0]?.toUpperCase() }}
              </div>
              {{ user.name }}
            </td>
            <td class="px-5 py-3 text-warm-500">{{ user.email }}</td>
            <td class="px-5 py-3">
              <span v-for="role in user.roles" :key="role" class="badge badge-blue mr-1">{{ role }}</span>
            </td>
            <td class="px-5 py-3">
              <select
                :value="typeof user.status === 'object' ? user.status.value : user.status"
                @change="e => updateStatus(user, e.target.value)"
                class="input text-xs py-1 px-2 w-auto"
              >
                <option value="active">active</option>
                <option value="inactive">inactive</option>
                <option value="blocked">blocked</option>
              </select>
            </td>
            <td class="px-5 py-3 text-warm-500">{{ user.loyalty_points ?? 0 }}</td>
            <td class="px-5 py-3">
              <button @click="deleteUser(user)" class="text-red-400 hover:text-red-600 p-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" :aria-label="`Delete ${user.name}`">
                <Trash2 :size="16" />
              </button>
            </td>
          </tr>
          <tr v-if="!users().length">
            <td colspan="6" class="px-5 py-10 text-center text-warm-400">No users found</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="data?.meta" class="flex items-center justify-between mt-4 text-sm text-warm-500">
      <span>{{ data.meta.total }} total</span>
      <span>Page {{ data.meta.current_page }} of {{ data.meta.last_page }}</span>
    </div>

    <!-- Create modal -->
    <Teleport to="body">
      <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-200" leave-to-class="opacity-0" leave-active-class="transition-opacity duration-150">
        <div v-if="showModal" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="showModal = false">
          <div class="card w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-5">
              <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">New User</h2>
              <button @click="showModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>
            <form @submit.prevent="createUser" class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Full Name</label>
                <input v-model="form.name" type="text" required class="input" placeholder="John Doe" />
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Email</label>
                <input v-model="form.email" type="email" required class="input" placeholder="user@example.com" />
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Phone</label>
                <input v-model="form.phone" type="tel" class="input" placeholder="+1 555 000 0000" />
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Password</label>
                <input v-model="form.password" type="password" required minlength="8" class="input" placeholder="Min. 8 characters" />
              </div>
              <div>
                <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Role</label>
                <select v-model="form.role" class="input">
                  <option v-for="r in (rolesData ?? [])" :key="r.id" :value="r.name">{{ r.name }}</option>
                </select>
              </div>
              <div class="flex gap-3 pt-2">
                <button type="button" @click="showModal = false" class="btn-outline flex-1">Cancel</button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1">{{ saving ? 'Creating…' : 'Create User' }}</button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
