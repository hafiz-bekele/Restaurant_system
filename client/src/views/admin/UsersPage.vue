<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { Plus, Trash2, X, Key, Shield, ShieldCheck, Check, Search, Sliders, Users, AlertCircle, CheckCircle, RefreshCw, Pencil } from '@lucide/vue'
import TableSkeleton from '../../components/ui/TableSkeleton.vue'

const { t } = useI18n()
const ui = useUiStore()
const qc = useQueryClient()

const activeTab = ref('directory') // 'directory' | 'matrix'
const search = ref('')
const roleFilter = ref('')
const dutyFilter = ref('') // '' | '1' | '0'
const showModal = ref(false)
const showEditModal = ref(false)
const showPermissionsModal = ref(false)
const saving = ref(false)
const permSearch = ref('')

const editUserForm = ref({
  id: null,
  name: '',
  email: '',
  phone: '',
  password: '',
  role: 'waiter',
  section: '',
  status: 'active',
  on_duty: true,
})

function openEditUserModal(user) {
  selectedUser.value = user
  const userRole = user.roles?.[0] || 'waiter'
  editUserForm.value = {
    id: user.id,
    name: user.name,
    email: user.email,
    phone: user.phone || '',
    password: '',
    role: userRole,
    section: user.section || sectionPresets[userRole]?.[0] || '',
    status: typeof user.status === 'object' ? user.status.value : user.status || 'active',
    on_duty: Boolean(user.on_duty),
  }
  showEditModal.value = true
}

async function updateUserDetails() {
  if (!editUserForm.value.id) return
  saving.value = true
  try {
    const payload = {
      name: editUserForm.value.name,
      email: editUserForm.value.email,
      phone: editUserForm.value.phone || undefined,
      role: editUserForm.value.role,
      section: editUserForm.value.section || undefined,
      status: editUserForm.value.status,
      on_duty: editUserForm.value.on_duty,
    }
    if (editUserForm.value.password) {
      payload.password = editUserForm.value.password
    }
    await adminApi.updateUser(editUserForm.value.id, payload)
    qc.invalidateQueries({ queryKey: ['admin', 'users'] })
    ui.toast('Staff member details updated successfully', 'success')
    showEditModal.value = false
  } catch (e) {
    const errors = e.response?.data?.errors
    ui.toast(errors ? Object.values(errors).flat()[0] : 'Failed to update user', 'error')
  } finally {
    saving.value = false
  }
}

// Work section / Station / Desk presets per operational role
const sectionPresets = {
  waiter: [
    'Indoor Dining (Tables 1-12)',
    'Garden Patio (Tables 13-24)',
    'Sky Terrace & Lounge',
    'VIP Dining Suite',
    'Private Banquet Room',
  ],
  reception: [
    'Main Lobby Reception Desk',
    'Boutique Suites Concierge',
    'Night Audit & Reservations Desk',
    'Executive Check-In Lounge',
  ],
  manager: [
    'General Operations Manager',
    'Food & Beverage (F&B) Director',
    'Front Office & Floor Manager',
    'Night Shift Duty Manager',
  ],
  kitchen: [
    'Executive Head Chef Station',
    'Hot Line & Charcoal Grill',
    'Traditional Buna & Stew Station',
    'Pastry & Cold Prep Station',
  ],
  cashier: [
    'Main Dining POS Terminal',
    'Sky Terrace POS Register',
    'Delivery & Takeout Counter',
  ],
  delivery: [
    'Bole & Airport Zone Driver',
    'Kazanchis & Downtown Driver',
    'Express Hotel Courier',
  ]
}

// Selected user for editing permissions
const selectedUser = ref(null)
const editForm = ref({
  role: '',
  status: 'active',
  on_duty: false,
  section: '',
  permissions: [],
})

const newUserForm = ref({
  name: '',
  email: '',
  phone: '',
  password: '',
  role: 'waiter',
  section: 'Indoor Dining (Tables 1-12)',
  on_duty: true,
})

// Permission definitions with descriptions
const permissionGroups = [
  {
    category: 'Orders & Service Operations',
    permissions: [
      { id: 'orders.view', label: 'View Live Orders', desc: 'Monitor active and completed dining orders' },
      { id: 'orders.create', label: 'Create Orders', desc: 'Ring up table and manual telephone orders' },
      { id: 'orders.update_status', label: 'Progress Workflow Status', desc: 'Update order from preparing to ready and served' },
      { id: 'orders.reassign_waiter', label: 'Reassign Waiter', desc: 'Transfer order to another floor server' },
      { id: 'orders.cancel', label: 'Cancel & Void Orders', desc: 'Cancel customer orders with justification' },
    ]
  },
  {
    category: 'Kitchen Display System (KDS)',
    permissions: [
      { id: 'kitchen.view_kds', label: 'Access KDS Screen', desc: 'Real-time kitchen prep board with audio alerts' },
      { id: 'kitchen.update_prep', label: 'Mark Dish Prep Ready', desc: 'Update item status from preparing to ready' },
      { id: 'kitchen.86_stock', label: '86 Item Availability', desc: 'Instantly toggle dish stock off digital menu' },
    ]
  },
  {
    category: 'Cashier POS & Fiscal Billing',
    permissions: [
      { id: 'cashier.view_pos', label: 'Cashier POS Terminal', desc: 'Access billing and pending bill ledger' },
      { id: 'cashier.settle_payment', label: 'Confirm & Settle Bills', desc: 'Confirm cash, card, and room-charge bills' },
      { id: 'cashier.reconcile_shift', label: 'Daily Shift Balance', desc: 'Reconcile drawer revenue at end of shift' },
      { id: 'cashier.print_receipt', label: 'Print Fiscal Receipts', desc: 'Trigger thermal ESC/POS bill receipt print' },
    ]
  },
  {
    category: 'Front Desk, Tables & Rooms',
    permissions: [
      { id: 'reception.view_floor', label: 'View Floor Plan', desc: 'Inspect table statuses and dining occupancy' },
      { id: 'reception.manage_tables', label: 'Seat & Release Tables', desc: 'Assign walk-in guests and clear tables' },
      { id: 'reception.manage_rooms', label: 'Manage Hotel Suites', desc: 'Check in/out hotel room guests' },
      { id: 'reservations.manage', label: 'Table Reservations', desc: 'Confirm and manage dining bookings' },
    ]
  },
  {
    category: 'Menu & Stock Inventory',
    permissions: [
      { id: 'menu.view', label: 'Browse Menu Catalog', desc: 'View categories, dishes, and variants' },
      { id: 'menu.manage', label: 'Manage Dishes & Prices', desc: 'Create and edit food items and categories' },
      { id: 'inventory.view', label: 'View Stock Inventory', desc: 'Inspect ingredient stock levels and alerts' },
      { id: 'inventory.manage', label: 'Stock Adjustments', desc: 'Record movements and purchase orders' },
    ]
  },
  {
    category: 'System Governance & Administration',
    permissions: [
      { id: 'users.view', label: 'View Staff Directory', desc: 'Inspect staff accounts and credentials' },
      { id: 'users.create', label: 'Create Staff Accounts', desc: 'Onboard new staff into the hotel system' },
      { id: 'users.edit', label: 'Edit Staff Accounts', desc: 'Update staff contact details and passwords' },
      { id: 'users.manage_permissions', label: 'Assign Roles & Permissions', desc: 'Grant and revoke operational capabilities' },
      { id: 'reports.view', label: 'Sales & Revenue Reports', desc: 'Inspect VAT, service fee, and profit analytics' },
      { id: 'audit.view', label: 'Security Audit Trail', desc: 'Review immutable security and action logs' },
      { id: 'settings.manage', label: 'Hotel Configuration', desc: 'Manage currency, VAT rate, and property profile' },
    ]
  }
]

// Role Presets (Default Capabilities)
const rolePresets = {
  'super-admin': permissionGroups.flatMap(g => g.permissions.map(p => p.id)),
  'admin': permissionGroups.flatMap(g => g.permissions.map(p => p.id)),
  'manager': [
    'orders.view', 'orders.create', 'orders.update_status', 'orders.reassign_waiter', 'orders.cancel',
    'kitchen.view_kds', 'kitchen.update_prep', 'kitchen.86_stock',
    'cashier.view_pos',
    'reception.view_floor', 'reception.manage_tables', 'reception.manage_rooms', 'reservations.manage',
    'menu.view', 'menu.manage', 'inventory.view',
    'users.view', 'users.create', 'users.edit', 'reports.view',
  ],
  'cashier': [
    'orders.view', 'orders.update_status',
    'cashier.view_pos', 'cashier.settle_payment', 'cashier.reconcile_shift', 'cashier.print_receipt',
  ],
  'reception': [
    'orders.view',
    'reception.view_floor', 'reception.manage_tables', 'reception.manage_rooms', 'reservations.manage',
  ],
  'waiter': [
    'orders.view', 'orders.create',
    'reception.view_floor',
  ],
  'kitchen': [
    'orders.view',
    'kitchen.view_kds', 'kitchen.update_prep', 'kitchen.86_stock',
  ],
  'delivery': [
    'orders.view', 'orders.update_status',
  ],
  'customer': [
    'orders.view', 'menu.view',
  ]
}

const { data: rolesData } = useQuery({
  queryKey: ['admin', 'roles'],
  queryFn:  () => adminApi.roles().then(r => r.data.data),
  staleTime: 600000,
})

const { data: usersData, isLoading } = useQuery({
  queryKey: ['admin', 'users', search, roleFilter, dutyFilter],
  queryFn:  () => adminApi.users({
    search:  search.value  || undefined,
    role:    roleFilter.value || undefined,
    on_duty: dutyFilter.value !== '' ? dutyFilter.value : undefined,
  }).then(r => r.data),
  keepPreviousData: true,
})

const users = () => usersData.value?.data ?? []

function openNewUserModal(defaultRole = 'waiter') {
  newUserForm.value = {
    name: '',
    email: '',
    phone: '',
    password: '',
    role: defaultRole,
    section: sectionPresets[defaultRole]?.[0] || 'Main Floor',
    on_duty: true,
  }
  showModal.value = true
}

function handleRoleChange(newRole) {
  newUserForm.value.role = newRole
  if (sectionPresets[newRole]?.length) {
    newUserForm.value.section = sectionPresets[newRole][0]
  }
}

async function toggleUserDuty(user) {
  try {
    await adminApi.toggleUserDuty(user.id)
    qc.invalidateQueries({ queryKey: ['admin', 'users'] })
    ui.toast(
      t('adminUsers.toastDutyToggled', {
        name: user.name,
        status: !user.on_duty ? t('adminUsers.onDutyStatus') : t('adminUsers.offShiftStatus'),
      }),
      'success'
    )
  } catch {
    ui.toast(t('adminUsers.toastFailed'), 'error')
  }
}

async function createUser() {
  saving.value = true
  try {
    await adminApi.createUser(newUserForm.value)
    qc.invalidateQueries({ queryKey: ['admin', 'users'] })
    ui.toast(
      t('adminUsers.toastOnboarded', {
        name: newUserForm.value.name,
        role: newUserForm.value.role,
      }),
      'success'
    )
    showModal.value = false
  } catch (e) {
    const errors = e.response?.data?.errors
    ui.toast(errors ? Object.values(errors).flat()[0] : t('adminUsers.toastFailed'), 'error')
  } finally {
    saving.value = false
  }
}

function openPermissionsModal(user) {
  selectedUser.value = user
  const userRole = user.roles?.[0] || 'waiter'
  editForm.value = {
    role: userRole,
    status: typeof user.status === 'object' ? user.status.value : user.status || 'active',
    on_duty: Boolean(user.on_duty),
    section: user.section || '',
    permissions: Array.isArray(user.permissions) ? [...user.permissions] : (rolePresets[userRole] || []),
  }
  permSearch.value = ''
  showPermissionsModal.value = true
}

function applyRoleDefaultPreset() {
  const currentRole = editForm.value.role
  if (rolePresets[currentRole]) {
    editForm.value.permissions = [...rolePresets[currentRole]]
    ui.toast(t('adminUsers.toastDefaultApplied', { role: currentRole }), 'success')
  }
}

function togglePermission(permId) {
  const idx = editForm.value.permissions.indexOf(permId)
  if (idx > -1) {
    editForm.value.permissions.splice(idx, 1)
  } else {
    editForm.value.permissions.push(permId)
  }
}

function isPermChecked(permId) {
  return editForm.value.permissions.includes(permId)
}

async function savePermissionsAndRole() {
  if (!selectedUser.value) return
  saving.value = true
  try {
    await adminApi.updateUser(selectedUser.value.id, {
      role: editForm.value.role,
      status: editForm.value.status,
      on_duty: editForm.value.on_duty,
      section: editForm.value.section || undefined,
      permissions: editForm.value.permissions,
    })
    qc.invalidateQueries({ queryKey: ['admin', 'users'] })
    ui.toast(t('adminUsers.toastPermsUpdated', { name: selectedUser.value.name }), 'success')
    showPermissionsModal.value = false
  } catch (e) {
    const errors = e.response?.data?.errors
    ui.toast(errors ? Object.values(errors).flat()[0] : t('adminUsers.toastFailed'), 'error')
  } finally {
    saving.value = false
  }
}

async function updateStatus(user, status) {
  await adminApi.updateUser(user.id, { status })
  qc.invalidateQueries({ queryKey: ['admin', 'users'] })
  ui.toast(t('adminUsers.toastStatusUpdated', { name: user.name }), 'success')
}

async function deleteUser(user) {
  if (!confirm(t('adminUsers.confirmDeactivate', { name: user.name }))) return
  await adminApi.deleteUser(user.id)
  qc.invalidateQueries({ queryKey: ['admin', 'users'] })
  ui.toast(t('adminUsers.toastUserRemoved'))
}

const filteredPermissionGroups = computed(() => {
  if (!permSearch.value) return permissionGroups
  const q = permSearch.value.toLowerCase()
  return permissionGroups
    .map(g => ({
      category: g.category,
      permissions: g.permissions.filter(p => p.id.toLowerCase().includes(q) || p.label.toLowerCase().includes(q) || p.desc.toLowerCase().includes(q))
    }))
    .filter(g => g.permissions.length > 0)
})

function roleBadgeClass(role) {
  switch (role) {
    case 'super-admin': return 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 border-purple-300'
    case 'admin':       return 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border-emerald-300'
    case 'manager':     return 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border-blue-300'
    case 'cashier':     return 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 border-green-300'
    case 'reception':   return 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 border-indigo-300'
    case 'waiter':      return 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 border-amber-300'
    case 'kitchen':     return 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 border-orange-300'
    default:            return 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-300'
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2.5">
          <ShieldCheck :size="26" class="text-primary-500" />
          {{ $t('adminUsers.title') }}
        </h1>
        <p class="text-xs text-warm-500 dark:text-warm-400 mt-1">
          {{ $t('adminUsers.subtitle') }}
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button @click="openNewUserModal" class="btn-primary text-xs gap-1.5 py-2 px-3.5 shadow-sm">
          <Plus :size="15" /> {{ $t('adminUsers.addStaffBtn') }}
        </button>
      </div>
    </div>

    <!-- Governance Banner -->
    <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
      <div class="flex items-center gap-2.5 text-amber-800 dark:text-amber-200">
        <Shield :size="18" class="text-amber-500 shrink-0" />
        <div>
          <span class="font-bold">{{ $t('adminUsers.bannerPrincipleTitle') }}</span>
          {{ $t('adminUsers.bannerPrincipleText') }}
        </div>
      </div>
      <div class="flex gap-2">
        <button
          @click="activeTab = 'directory'"
          :class="['px-3 py-1.5 rounded-xl font-semibold transition-all text-xs', activeTab === 'directory' ? 'bg-primary-600 text-white shadow-xs' : 'bg-white dark:bg-warm-850 text-warm-700 dark:text-warm-300 border border-warm-200 dark:border-warm-700']"
        >
          {{ $t('adminUsers.tabDirectory') }}
        </button>
        <button
          @click="activeTab = 'matrix'"
          :class="['px-3 py-1.5 rounded-xl font-semibold transition-all text-xs', activeTab === 'matrix' ? 'bg-primary-600 text-white shadow-xs' : 'bg-white dark:bg-warm-850 text-warm-700 dark:text-warm-300 border border-warm-200 dark:border-warm-700']"
        >
          {{ $t('adminUsers.tabMatrix') }}
        </button>
      </div>
    </div>

    <!-- TAB 1: Staff Directory & Access -->
    <div v-if="activeTab === 'directory'" class="space-y-4">
      <!-- Search & Quick Filters -->
      <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex flex-wrap items-center gap-3">
            <div class="relative">
              <Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
              <input
                v-model="search"
                type="search"
                :placeholder="$t('adminUsers.searchPlaceholder')"
                class="input pl-9 w-60 sm:w-72 text-xs"
              />
            </div>
            <select v-model="roleFilter" class="input w-auto text-xs py-2">
              <option value="">{{ $t('adminUsers.filterAllRoles') }}</option>
              <option v-for="r in (rolesData ?? [])" :key="r.id" :value="r.name">{{ r.name }}</option>
            </select>
            <select v-model="dutyFilter" class="input w-auto text-xs py-2">
              <option value="">{{ $t('adminUsers.filterAllDuty') }}</option>
              <option value="1">{{ $t('adminUsers.filterOnDuty') }}</option>
              <option value="0">{{ $t('adminUsers.filterOffDuty') }}</option>
            </select>
          </div>
          <p class="text-xs text-warm-500 font-medium">{{ $t('adminUsers.staffCount', { count: users().length }) }}</p>
        </div>

        <!-- Quick Role Switcher Pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
          <button
            type="button"
            @click="roleFilter = ''"
            :class="['px-3 py-1 rounded-xl font-medium transition-all', roleFilter === '' ? 'bg-primary-600 text-white shadow-xs' : 'bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400 hover:bg-warm-200']"
          >
            {{ $t('adminUsers.filterAllStaff') }}
          </button>
          <button
            type="button"
            @click="roleFilter = 'manager'"
            :class="['px-3 py-1 rounded-xl font-medium transition-all', roleFilter === 'manager' ? 'bg-primary-600 text-white shadow-xs' : 'bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400 hover:bg-warm-200']"
          >
            {{ $t('adminUsers.filterManagers') }}
          </button>
          <button
            type="button"
            @click="roleFilter = 'reception'"
            :class="['px-3 py-1 rounded-xl font-medium transition-all', roleFilter === 'reception' ? 'bg-primary-600 text-white shadow-xs' : 'bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400 hover:bg-warm-200']"
          >
            {{ $t('adminUsers.filterReceptionists') }}
          </button>
          <button
            type="button"
            @click="roleFilter = 'waiter'"
            :class="['px-3 py-1 rounded-xl font-medium transition-all', roleFilter === 'waiter' ? 'bg-primary-600 text-white shadow-xs' : 'bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400 hover:bg-warm-200']"
          >
            {{ $t('adminUsers.filterWaiters') }}
          </button>
          <button
            type="button"
            @click="roleFilter = 'kitchen'"
            :class="['px-3 py-1 rounded-xl font-medium transition-all', roleFilter === 'kitchen' ? 'bg-primary-600 text-white shadow-xs' : 'bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400 hover:bg-warm-200']"
          >
            {{ $t('adminUsers.filterChefs') }}
          </button>
          <button
            type="button"
            @click="roleFilter = 'cashier'"
            :class="['px-3 py-1 rounded-xl font-medium transition-all', roleFilter === 'cashier' ? 'bg-primary-600 text-white shadow-xs' : 'bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400 hover:bg-warm-200']"
          >
            {{ $t('adminUsers.filterCashiers') }}
          </button>
        </div>
      </div>

      <!-- Loading Skeleton -->
      <TableSkeleton v-if="isLoading" :rows="6" :cols="5" />

      <!-- Staff Table -->
      <div v-else class="card overflow-x-auto shadow-sm">
        <table class="w-full text-sm text-left">
          <thead class="bg-warm-50 dark:bg-warm-850 text-warm-600 dark:text-warm-400 text-xs uppercase font-semibold">
            <tr>
              <th class="px-5 py-3.5">{{ $t('adminUsers.thStaffMember') }}</th>
              <th class="px-5 py-3.5">{{ $t('adminUsers.thRole') }}</th>
              <th class="px-5 py-3.5">{{ $t('adminUsers.thPermissions') }}</th>
              <th class="px-5 py-3.5">{{ $t('adminUsers.thShiftSection') }}</th>
              <th class="px-5 py-3.5">{{ $t('adminUsers.thStatus') }}</th>
              <th class="px-5 py-3.5 text-right">{{ $t('adminUsers.thAccessControls') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-100 dark:divide-warm-800">
            <tr
              v-for="user in users()"
              :key="user.id"
              class="hover:bg-warm-50/70 dark:hover:bg-warm-800/40 transition-colors"
            >
              <!-- Staff Member -->
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center text-xs font-bold text-primary-600 dark:text-primary-400 shrink-0">
                    {{ user.name?.[0]?.toUpperCase() }}
                  </div>
                  <div>
                    <p class="font-semibold text-xs text-warm-900 dark:text-white">{{ user.name }}</p>
                    <p class="text-[11px] text-warm-400">{{ user.email }}</p>
                  </div>
                </div>
              </td>

              <!-- Role -->
              <td class="px-5 py-3.5">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="role in user.roles"
                    :key="role"
                    :class="['text-[11px] font-semibold px-2 py-0.5 rounded-md border capitalize inline-block', roleBadgeClass(role)]"
                  >
                    {{ role }}
                  </span>
                </div>
              </td>

              <!-- Permissions Granted -->
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-2">
                  <span class="font-mono text-xs font-bold text-warm-800 dark:text-warm-200">
                    {{ user.permissions?.length ?? 0 }}
                  </span>
                  <span class="text-[11px] text-warm-400">{{ $t('adminUsers.capabilitiesCount') }}</span>
                  <button
                    @click="openPermissionsModal(user)"
                    class="text-xs text-primary-600 dark:text-primary-400 hover:underline flex items-center gap-1 font-medium ml-1"
                  >
                    <Key :size="12" /> {{ $t('adminUsers.btnEdit') }}
                  </button>
                </div>
              </td>

              <!-- Shift / Section -->
              <td class="px-5 py-3.5 text-xs">
                <div class="space-y-1.5">
                  <button
                    @click="toggleUserDuty(user)"
                    type="button"
                    :class="[
                      'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border transition-all hover:scale-105 active:scale-95 cursor-pointer',
                      user.on_duty
                        ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800'
                        : 'bg-warm-100 dark:bg-warm-800 text-warm-500 border-warm-200 dark:border-warm-700'
                    ]"
                    :title="$t('adminUsers.toggleDutyTitle', { name: user.name })"
                  >
                    <span :class="['w-2 h-2 rounded-full', user.on_duty ? 'bg-emerald-500 animate-pulse' : 'bg-warm-400']"></span>
                    <span>{{ user.on_duty ? $t('adminUsers.onDutyBadge') : $t('adminUsers.offDutyBadge') }}</span>
                  </button>

                  <div v-if="user.section" class="text-[11px] font-medium text-warm-700 dark:text-warm-300 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary-500 shrink-0"></span>
                    <span class="truncate max-w-[180px]">{{ user.section }}</span>
                  </div>
                  <div v-else class="text-[10px] text-warm-400 italic">
                    {{ $t('adminUsers.generalUnassigned') }}
                  </div>
                </div>
              </td>

              <!-- Status -->
              <td class="px-5 py-3.5">
                <select
                  :value="typeof user.status === 'object' ? user.status.value : user.status"
                  @change="e => updateStatus(user, e.target.value)"
                  class="input text-xs py-1 px-2 w-auto font-medium"
                >
                  <option value="active">{{ $t('adminUsers.statusActive') }}</option>
                  <option value="inactive">{{ $t('adminUsers.statusInactive') }}</option>
                  <option value="blocked">{{ $t('adminUsers.statusBlocked') }}</option>
                </select>
              </td>

              <!-- Access Controls -->
              <td class="px-5 py-3.5 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button
                    @click="openEditUserModal(user)"
                    class="btn-outline text-xs py-1.5 px-2.5 gap-1 shadow-xs"
                    title="Edit user details"
                  >
                    <Pencil :size="13" />
                    <span>Edit</span>
                  </button>
                  <button
                    @click="openPermissionsModal(user)"
                    class="btn-secondary text-xs py-1.5 px-2.5 gap-1 shadow-xs"
                    :title="$t('adminUsers.btnManageAccess')"
                  >
                    <Sliders :size="13" />
                    <span>{{ $t('adminUsers.btnManageAccess') }}</span>
                  </button>
                  <button
                    @click="deleteUser(user)"
                    class="text-warm-400 hover:text-red-500 p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                    :aria-label="`Delete ${user.name}`"
                  >
                    <Trash2 :size="15" />
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="!users().length">
              <td colspan="6" class="px-5 py-12 text-center text-warm-400 text-xs">
                {{ $t('adminUsers.noUsersFound') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 2: RBAC Matrix Overview -->
    <div v-else class="card p-6 space-y-6">
      <div>
        <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">{{ $t('adminUsers.matrixTitle') }}</h2>
        <p class="text-xs text-warm-500 dark:text-warm-400 mt-0.5">{{ $t('adminUsers.matrixSubtitle') }}</p>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-xs text-left border-collapse">
          <thead>
            <tr class="bg-warm-100 dark:bg-warm-850 text-warm-700 dark:text-warm-300 border-b border-warm-200 dark:border-warm-800">
              <th class="p-3 font-bold">{{ $t('adminUsers.thCapability') }}</th>
              <th class="p-3 font-bold text-center">Super Admin</th>
              <th class="p-3 font-bold text-center">Admin</th>
              <th class="p-3 font-bold text-center">Manager</th>
              <th class="p-3 font-bold text-center">Cashier</th>
              <th class="p-3 font-bold text-center">Reception</th>
              <th class="p-3 font-bold text-center">Waiter</th>
              <th class="p-3 font-bold text-center">Kitchen</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-100 dark:divide-warm-800">
            <template v-for="group in permissionGroups" :key="group.category">
              <tr class="bg-warm-50 dark:bg-warm-900/60 font-bold text-primary-700 dark:text-primary-300">
                <td colspan="8" class="p-2.5 text-xs uppercase tracking-wider">{{ group.category }}</td>
              </tr>
              <tr v-for="p in group.permissions" :key="p.id" class="hover:bg-warm-50/50 dark:hover:bg-warm-800/20">
                <td class="p-3">
                  <p class="font-semibold text-warm-900 dark:text-white">{{ p.label }}</p>
                  <p class="text-[10px] text-warm-400 font-mono">{{ p.id }}</p>
                </td>
                <td class="p-3 text-center"><Check class="w-4 h-4 text-emerald-500 mx-auto" /></td>
                <td class="p-3 text-center"><Check class="w-4 h-4 text-emerald-500 mx-auto" /></td>
                <td class="p-3 text-center">
                  <Check v-if="rolePresets['manager']?.includes(p.id)" class="w-4 h-4 text-emerald-500 mx-auto" />
                  <span v-else class="text-warm-300">—</span>
                </td>
                <td class="p-3 text-center">
                  <Check v-if="rolePresets['cashier']?.includes(p.id)" class="w-4 h-4 text-emerald-500 mx-auto" />
                  <span v-else class="text-warm-300">—</span>
                </td>
                <td class="p-3 text-center">
                  <Check v-if="rolePresets['reception']?.includes(p.id)" class="w-4 h-4 text-emerald-500 mx-auto" />
                  <span v-else class="text-warm-300">—</span>
                </td>
                <td class="p-3 text-center">
                  <Check v-if="rolePresets['waiter']?.includes(p.id)" class="w-4 h-4 text-emerald-500 mx-auto" />
                  <span v-else class="text-warm-300">—</span>
                </td>
                <td class="p-3 text-center">
                  <Check v-if="rolePresets['kitchen']?.includes(p.id)" class="w-4 h-4 text-emerald-500 mx-auto" />
                  <span v-else class="text-warm-300">—</span>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MODAL 1: Edit Permissions & Role (The Core Governance Tool) -->
    <div v-if="showPermissionsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
      <div class="card w-full max-w-3xl shadow-float max-h-[90vh] flex flex-col">
        <!-- Modal Header -->
        <div class="flex items-center justify-between pb-3.5 border-b border-warm-100 dark:border-warm-800 mb-3.5">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center text-primary-600 dark:text-primary-400 font-bold text-sm">
              {{ selectedUser?.name?.[0]?.toUpperCase() }}
            </div>
            <div>
              <h3 class="font-heading font-bold text-lg text-warm-900 dark:text-white flex items-center gap-2">
                <Sliders :size="18" class="text-primary-500" />
                {{ $t('adminUsers.modalEditTitle', { name: selectedUser?.name }) }}
              </h3>
              <p class="text-xs text-warm-500 dark:text-warm-400">{{ $t('adminUsers.modalEditSubtitle', { email: selectedUser?.email }) }}</p>
            </div>
          </div>
          <button @click="showPermissionsModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
        </div>

        <!-- Modal Body -->
        <div class="space-y-4 flex-1 overflow-y-auto p-1 pr-2">
          <!-- Role & Presets Row -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3.5 rounded-2xl bg-warm-100/70 dark:bg-warm-900/50 border border-warm-200 dark:border-warm-800">
            <div>
              <label class="block text-xs font-bold text-warm-700 dark:text-warm-300 mb-1">{{ $t('adminUsers.role') }}</label>
              <select v-model="editForm.role" class="input text-xs">
                <option v-for="r in (rolesData ?? [])" :key="r.id" :value="r.name">{{ r.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold text-warm-700 dark:text-warm-300 mb-1">{{ $t('adminUsers.status') }}</label>
              <select v-model="editForm.status" class="input text-xs">
                <option value="active">{{ $t('adminUsers.statusActive') }}</option>
                <option value="inactive">{{ $t('adminUsers.statusInactive') }}</option>
                <option value="blocked">{{ $t('adminUsers.statusBlocked') }}</option>
              </select>
            </div>

            <div class="flex flex-col justify-end">
              <button
                type="button"
                @click="applyRoleDefaultPreset"
                class="btn-secondary text-xs py-2 px-3 gap-1.5 justify-center shadow-xs"
                :title="$t('adminUsers.applyDefaults', { role: editForm.role })"
              >
                <RefreshCw :size="13" />
                <span>{{ $t('adminUsers.applyDefaults', { role: editForm.role }) }}</span>
              </button>
            </div>
          </div>

          <!-- Work Section / Station / Desk & Shift Duty Details -->
          <div class="p-3.5 rounded-2xl bg-warm-50 dark:bg-warm-800/40 border border-warm-200 dark:border-warm-700 space-y-2 text-xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center">
              <label class="font-bold text-warm-900 dark:text-warm-100 cursor-pointer flex items-center gap-2">
                <input v-model="editForm.on_duty" type="checkbox" class="w-4 h-4 rounded text-primary-600 focus:ring-primary-500" />
                <span>{{ $t('adminUsers.staffOnDutyCheckbox') }}</span>
              </label>

              <div>
                <input
                  v-model="editForm.section"
                  type="text"
                  :placeholder="$t('adminUsers.sectionStation')"
                  class="input text-xs"
                />
              </div>
            </div>

            <!-- Quick Station presets for the role -->
            <div v-if="sectionPresets[editForm.role]?.length" class="flex flex-wrap items-center gap-1 pt-1">
              <span class="text-[10px] text-warm-400 font-semibold uppercase tracking-wider mr-1">{{ $t('adminUsers.stationPresets') }}</span>
              <button
                v-for="preset in sectionPresets[editForm.role]"
                :key="preset"
                type="button"
                @click="editForm.section = preset"
                :class="[
                  'text-[10px] px-2 py-0.5 rounded-lg border transition-colors',
                  editForm.section === preset
                    ? 'bg-primary-100 dark:bg-primary-900/50 border-primary-400 text-primary-700 dark:text-primary-300 font-bold'
                    : 'bg-white dark:bg-warm-850 border-warm-200 dark:border-warm-700 text-warm-600 dark:text-warm-400 hover:bg-warm-100'
                ]"
              >
                {{ preset }}
              </button>
            </div>
          </div>

          <!-- Search Permissions -->
          <div class="flex items-center justify-between gap-3">
            <div class="relative flex-1">
              <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
              <input
                v-model="permSearch"
                type="search"
                :placeholder="$t('adminUsers.searchPermsPlaceholder')"
                class="input pl-8 text-xs w-full"
              />
            </div>
            <span class="text-xs font-semibold text-primary-600 dark:text-primary-400 shrink-0">
              {{ $t('adminUsers.permsSelected', { count: editForm.permissions.length }) }}
            </span>
          </div>

          <!-- Permissions Checkbox Matrix -->
          <div class="space-y-4">
            <div
              v-for="group in filteredPermissionGroups"
              :key="group.category"
              class="border border-warm-200 dark:border-warm-700 rounded-2xl p-4 bg-white dark:bg-gray-900"
            >
              <h4 class="font-bold text-xs uppercase tracking-wider text-warm-600 dark:text-warm-400 mb-3 flex items-center justify-between">
                <span>{{ group.category }}</span>
                <span :class="['text-[10px] font-semibold px-2 py-0.5 rounded-full', group.permissions.filter(p => isPermChecked(p.id)).length > 0 ? 'bg-primary-100 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400' : 'text-warm-400 bg-warm-100 dark:bg-warm-800']">
                  {{ $t('adminUsers.enabledCount', { enabled: group.permissions.filter(p => isPermChecked(p.id)).length, total: group.permissions.length }) }}
                </span>
              </h4>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div
                  v-for="p in group.permissions"
                  :key="p.id"
                  @click="togglePermission(p.id)"
                  :class="[
                    'flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition-all select-none',
                    isPermChecked(p.id)
                      ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/40 shadow-sm'
                      : 'border-warm-200 dark:border-warm-700 hover:border-primary-300 hover:bg-warm-50 dark:hover:bg-warm-800/60'
                  ]"
                >
                  <!-- Custom checkbox indicator -->
                  <div :class="[
                    'w-4 h-4 rounded shrink-0 mt-0.5 border-2 flex items-center justify-center transition-all',
                    isPermChecked(p.id)
                      ? 'bg-primary-500 border-primary-500'
                      : 'border-warm-300 dark:border-warm-600 bg-white dark:bg-warm-900'
                  ]">
                    <svg v-if="isPermChecked(p.id)" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 10 8" aria-hidden="true">
                      <path d="M1 4l2.5 2.5L9 1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p :class="['text-xs font-semibold leading-tight', isPermChecked(p.id) ? 'text-primary-700 dark:text-primary-300' : 'text-warm-900 dark:text-warm-100']">
                      {{ p.label }}
                    </p>
                    <p class="text-[11px] text-warm-500 dark:text-warm-400 leading-tight mt-0.5">{{ p.desc }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="pt-3.5 mt-3.5 border-t border-warm-100 dark:border-warm-800 flex items-center justify-between gap-3">
          <p class="text-[11px] text-warm-400">
            {{ $t('adminUsers.changesEffectNote') }}
          </p>
          <div class="flex gap-2">
            <button type="button" @click="showPermissionsModal = false" class="btn-outline text-xs py-2 px-3.5">
              {{ $t('adminUsers.cancel') }}
            </button>
            <button
              type="button"
              @click="savePermissionsAndRole"
              :disabled="saving"
              class="btn-primary text-xs py-2 px-4 shadow-md font-semibold"
            >
              {{ saving ? $t('adminUsers.savingChanges') : $t('adminUsers.savePermsBtn') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 2: Onboard New Staff Account -->
    <Teleport to="body">
      <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-200" leave-to-class="opacity-0" leave-active-class="transition-opacity duration-150">
        <div v-if="showModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4" @click.self="showModal = false">
          <div class="card w-full max-w-md p-6 shadow-float">
            <div class="flex items-center justify-between mb-5">
              <div>
                <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">{{ $t('adminUsers.modalAddTitle') }}</h2>
                <p class="text-xs text-warm-500">{{ $t('adminUsers.modalAddSubtitle') }}</p>
              </div>
              <button @click="showModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>

            <form @submit.prevent="createUser" class="space-y-4">
              <div>
                <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">{{ $t('adminUsers.fullName') }}</label>
                <input v-model="newUserForm.name" type="text" required class="input text-xs" placeholder="e.g. Samuel Bekele" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">{{ $t('adminUsers.email') }}</label>
                <input v-model="newUserForm.email" type="email" required class="input text-xs" placeholder="samuel@restaurant.com" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">{{ $t('adminUsers.phone') }}</label>
                <input v-model="newUserForm.phone" type="tel" class="input text-xs" placeholder="+251 911 000 000" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">{{ $t('adminUsers.password') }}</label>
                <input v-model="newUserForm.password" type="password" required minlength="8" class="input text-xs" placeholder="Min. 8 characters" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">{{ $t('adminUsers.role') }}</label>
                <select
                  :value="newUserForm.role"
                  @change="e => handleRoleChange(e.target.value)"
                  class="input text-xs font-medium"
                >
                  <option v-for="r in (rolesData ?? [])" :key="r.id" :value="r.name">{{ r.name }}</option>
                </select>
              </div>

              <!-- Work Section / Desk / Station Assignment -->
              <div>
                <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">
                  {{ $t('adminUsers.sectionStation') }}
                </label>
                <input
                  v-model="newUserForm.section"
                  type="text"
                  class="input text-xs"
                  placeholder="e.g. Indoor Tables 1-12, Main Lobby Desk..."
                />
                <!-- Quick clickable station presets -->
                <div v-if="sectionPresets[newUserForm.role]?.length" class="mt-1.5 flex flex-wrap gap-1">
                  <button
                    v-for="preset in sectionPresets[newUserForm.role]"
                    :key="preset"
                    type="button"
                    @click="newUserForm.section = preset"
                    :class="[
                      'text-[10px] px-2 py-0.5 rounded-lg border transition-colors',
                      newUserForm.section === preset
                        ? 'bg-primary-100 dark:bg-primary-900/50 border-primary-400 text-primary-700 dark:text-primary-300 font-bold'
                        : 'bg-warm-100 dark:bg-warm-800 border-warm-200 dark:border-warm-700 text-warm-600 dark:text-warm-400 hover:bg-warm-200'
                    ]"
                  >
                    {{ preset }}
                  </button>
                </div>
              </div>

              <!-- Shift Duty Status Checkbox -->
              <div class="p-2.5 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800">
                <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-emerald-900 dark:text-emerald-200">
                  <input
                    v-model="newUserForm.on_duty"
                    type="checkbox"
                    class="w-4 h-4 rounded text-primary-600 focus:ring-primary-500"
                  />
                  <span>{{ $t('adminUsers.staffOnDutyAddCheckbox') }}</span>
                </label>
              </div>

              <div class="flex gap-3 pt-3 border-t border-warm-100 dark:border-warm-800">
                <button type="button" @click="showModal = false" class="btn-outline flex-1 text-xs">{{ $t('adminUsers.cancel') }}</button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1 text-xs py-2.5 font-semibold">
                  {{ saving ? $t('adminUsers.onboardingBtn') : $t('adminUsers.createAssignBtn') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- MODAL 3: Edit User Details (Full Profile & Role Editor) -->
    <Teleport to="body">
      <Transition enter-from-class="opacity-0" enter-active-class="transition-opacity duration-200" leave-to-class="opacity-0" leave-active-class="transition-opacity duration-150">
        <div v-if="showEditModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4" @click.self="showEditModal = false">
          <div class="card w-full max-w-lg p-6 shadow-float">
            <div class="flex items-center justify-between mb-5 pb-3 border-b border-warm-100 dark:border-warm-800">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-primary-100 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400 flex items-center justify-center">
                  <Pencil :size="16" />
                </div>
                <div>
                  <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">Edit Staff Profile</h2>
                  <p class="text-xs text-warm-500">Update account credentials, operational role and station</p>
                </div>
              </div>
              <button @click="showEditModal = false" class="btn-ghost p-1.5 rounded-lg"><X :size="18" /></button>
            </div>

            <form @submit.prevent="updateUserDetails" class="space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">Full Name</label>
                  <input v-model="editUserForm.name" type="text" required class="input text-xs" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">Email Address</label>
                  <input v-model="editUserForm.email" type="email" required class="input text-xs" />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">Phone Number</label>
                  <input v-model="editUserForm.phone" type="tel" class="input text-xs" placeholder="+251 9XXXXXXXX" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">
                    Reset Password
                    <span class="text-[10px] text-warm-400 font-normal">(Leave blank to keep)</span>
                  </label>
                  <input v-model="editUserForm.password" type="password" minlength="8" class="input text-xs" placeholder="New password" />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">Assigned Role</label>
                  <select v-model="editUserForm.role" class="input text-xs font-medium">
                    <option v-for="r in (rolesData ?? [])" :key="r.id" :value="r.name">{{ r.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">Account Status</label>
                  <select v-model="editUserForm.status" class="input text-xs font-medium">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="blocked">Blocked</option>
                  </select>
                </div>
              </div>

              <!-- Station / Section -->
              <div>
                <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1">
                  Assigned Floor Section / Desk
                </label>
                <input
                  v-model="editUserForm.section"
                  type="text"
                  class="input text-xs"
                  placeholder="e.g. Indoor Tables 1-12, Cashier POS 1..."
                />
                <div v-if="sectionPresets[editUserForm.role]?.length" class="mt-1.5 flex flex-wrap gap-1">
                  <button
                    v-for="preset in sectionPresets[editUserForm.role]"
                    :key="preset"
                    type="button"
                    @click="editUserForm.section = preset"
                    :class="[
                      'text-[10px] px-2 py-0.5 rounded-lg border transition-colors',
                      editUserForm.section === preset
                        ? 'bg-primary-100 dark:bg-primary-900/50 border-primary-400 text-primary-700 dark:text-primary-300 font-bold'
                        : 'bg-warm-100 dark:bg-warm-800 border-warm-200 dark:border-warm-700 text-warm-600 dark:text-warm-400 hover:bg-warm-200'
                    ]"
                  >
                    {{ preset }}
                  </button>
                </div>
              </div>

              <!-- Shift On-duty Checkbox -->
              <div class="p-2.5 rounded-xl bg-warm-100/70 dark:bg-warm-850 border border-warm-200 dark:border-warm-700">
                <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-warm-800 dark:text-warm-200">
                  <input
                    v-model="editUserForm.on_duty"
                    type="checkbox"
                    class="w-4 h-4 rounded text-primary-600 focus:ring-primary-500"
                  />
                  <span>Currently On Duty / Active on Shift</span>
                </label>
              </div>

              <div class="flex gap-3 pt-3 border-t border-warm-100 dark:border-warm-800">
                <button type="button" @click="showEditModal = false" class="btn-outline flex-1 text-xs">Cancel</button>
                <button type="submit" :disabled="saving" class="btn-primary flex-1 text-xs py-2.5 font-semibold">
                  {{ saving ? 'Saving Changes...' : 'Save User Profile' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
