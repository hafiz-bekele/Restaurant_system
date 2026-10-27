<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  LayoutDashboard,
  ShoppingBag,
  CreditCard,
  ChefHat,
  Calendar,
  Grid3x3,
  BedDouble,
  Sparkles,
  UtensilsCrossed,
  Package,
  Tag,
  Users,
  Star,
  AlertTriangle,
  Truck,
  Building2,
  FileText,
  Shield,
  BarChart2,
  Settings,
  Pin,
  PinOff,
  ChevronDown,
  ChevronRight,
  ChevronLeft,
  Search,
  Plus,
  Bell,
  Sun,
  Moon,
  Globe,
  HelpCircle,
  Lock,
  LogOut,
  Check,
  X,
  ExternalLink,
  ArrowUp,
  ArrowDown
} from 'lucide-vue-next'
import { useAuthStore } from '../../stores/auth'
import { useUiStore } from '../../stores/ui'
import { APP_NAME } from '../../config'
import { adminApi, staffApi } from '../../services/api'
import CommandPalette from './CommandPalette.vue'
import KeyboardShortcutsModal from './KeyboardShortcutsModal.vue'
import LockScreenModal from './LockScreenModal.vue'

const props = withDefaults(defineProps<{
  mobileOpen?: boolean
}>(), {
  mobileOpen: false,
})

const emit = defineEmits<{
  (e: 'update:mobileOpen', value: boolean): void
  (e: 'collapse-change', isCollapsed: boolean): void
}>()

const { t, locale } = useI18n()
const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const ui = useUiStore()

// ── 1. Sidebar Dimensions & Hover-Expand State ───────────────────────────
const isMobile = ref(window.innerWidth < 1024)
const isPinned = ref(false)
const isHovered = ref(false)

function onSidebarMouseEnter() {
  if (!isMobile.value) {
    isHovered.value = true
  }
}

function onSidebarMouseLeave() {
  if (!isMobile.value) {
    if (showNewMenu.value || showBranchMenu.value || showProfileMenu.value || showNotifications.value) {
      return
    }
    isHovered.value = false
  }
}

// Expanded when mobile drawer is open, or desktop pinned open, or mouse is hovering
const isExpanded = computed(() => {
  if (isMobile.value) return props.mobileOpen
  return isPinned.value || isHovered.value
})

const isCollapsed = computed(() => !isExpanded.value)

function checkResize() {
  const mobile = window.innerWidth < 1024
  if (mobile !== isMobile.value) {
    isMobile.value = mobile
    if (mobile) {
      emit('update:mobileOpen', false)
    }
  }
}

function toggleCollapse() {
  if (isMobile.value) return
  isPinned.value = !isPinned.value
  isHovered.value = isPinned.value
  try {
    localStorage.setItem('taste_sidebar_pinned', String(isPinned.value))
    localStorage.setItem('taste_sidebar_collapsed', String(!isPinned.value))
  } catch {}
  emit('collapse-change', !isPinned.value)
}

// ── 2. Modals state ───────────────────────────────────────────────────────
const commandPaletteRef = ref<InstanceType<typeof CommandPalette> | null>(null)
const shortcutsModalRef = ref<InstanceType<typeof KeyboardShortcutsModal> | null>(null)
const lockScreenModalRef = ref<InstanceType<typeof LockScreenModal> | null>(null)

const showCommandPalette = ref(false)
const showShortcutsModal = ref(false)
const showLockScreen = ref(false)
const showNewMenu = ref(false)
const showBranchMenu = ref(false)
const showProfileMenu = ref(false)
const showNotifications = ref(false)
const showShiftSignOutWarning = ref(false)

const newMenuRef = ref<HTMLElement | null>(null)
const branchMenuRef = ref<HTMLElement | null>(null)
const profileMenuRef = ref<HTMLElement | null>(null)
const notificationsRef = ref<HTMLElement | null>(null)

// ── 3. Branch / Property Switcher ─────────────────────────────────────────
interface Branch {
  id: string | number
  name: string
  code: string
  location: string
  active?: boolean
}

const defaultBranches: Branch[] = [
  { id: 'addis-main', name: 'Addis Ababa Flagship', code: 'AA-01', location: 'Kazanchis, Addis Ababa' },
  { id: 'bole-express', name: 'Bole Airport Express', code: 'AA-02', location: 'Bole Medhanialem, Addis Ababa' },
  { id: 'hawassa-resort', name: 'Hawassa Lake Resort', code: 'HW-01', location: 'Lake Front, Hawassa' },
]

const branches = ref<Branch[]>(defaultBranches)
const activeBranchId = ref(localStorage.getItem('taste_active_branch') || 'addis-main')
const branchSearch = ref('')

const activeBranch = computed(() => {
  return branches.value.find(b => b.id === activeBranchId.value) || branches.value[0]
})

const filteredBranches = computed(() => {
  const q = branchSearch.value.trim().toLowerCase()
  if (!q) return branches.value
  return branches.value.filter(b => b.name.toLowerCase().includes(q) || b.code.toLowerCase().includes(q))
})

function selectBranch(branch: Branch) {
  activeBranchId.value = String(branch.id)
  try {
    localStorage.setItem('taste_active_branch', String(branch.id))
  } catch {}
  showBranchMenu.value = false
  ui.toast(`Switched to ${branch.name}`, 'info', 2000)
}

// ── 4. Live Badges & Status Polling ───────────────────────────────────────
export type NumericBadgeKey = 'ordersPending' | 'reservationsToday' | 'complaintsOpen' | 'lowStockItems' | 'kitchenActiveTickets'

interface LiveCounts {
  ordersPending: number
  reservationsToday: number
  complaintsOpen: number
  lowStockItems: number
  kitchenActiveTickets: number
  shiftOpen: boolean
}

const liveCounts = ref<LiveCounts>({
  ordersPending: 0,
  reservationsToday: 0,
  complaintsOpen: 0,
  lowStockItems: 0,
  kitchenActiveTickets: 0,
  shiftOpen: false,
})

function getBadgeCount(key?: NumericBadgeKey): number {
  if (!key) return 0
  return liveCounts.value[key] || 0
}

const isBadgesLoading = ref(true)
let pollTimer: any = null

async function fetchLiveBadges() {
  if (!auth.isAuthenticated) return

  try {
    // Run badge checks with skipLoading to avoid triggering the global topbar loader
    const promises: Promise<any>[] = []

    // 1. Pending orders count
    if (auth.hasPermission('orders.view')) {
      promises.push(
        adminApi.orders({ status: 'pending' })
          .then(res => {
            const list = res.data?.data || res.data || []
            liveCounts.value.ordersPending = Array.isArray(list) ? list.length : (res.data?.total || 0)
          }).catch(() => {})
      )
    }

    // 2. Today's reservations
    if (auth.hasPermission('reservations.manage')) {
      promises.push(
        adminApi.todayReservations()
          .then(res => {
            const list = res.data?.data || res.data || []
            liveCounts.value.reservationsToday = Array.isArray(list) ? list.length : 0
          }).catch(() => {})
      )
    }

    // 3. Open complaints count
    if (auth.isAdmin) {
      promises.push(
        adminApi.complaints({ status: 'open' })
          .then(res => {
            const list = res.data?.data || res.data || []
            liveCounts.value.complaintsOpen = Array.isArray(list) ? list.length : 0
          }).catch(() => {})
      )
    }

    // 4. Low stock inventory
    if (auth.hasPermission('inventory.view')) {
      promises.push(
        adminApi.lowStock()
          .then(res => {
            const list = res.data?.data || res.data || []
            liveCounts.value.lowStockItems = Array.isArray(list) ? list.length : 0
          }).catch(() => {})
      )
    }

    // 5. Kitchen active tickets
    if (auth.hasPermission('kitchen.view_kds')) {
      promises.push(
        staffApi.kitchenTickets()
          .then(res => {
            const list = res.data?.data || res.data || []
            liveCounts.value.kitchenActiveTickets = Array.isArray(list) ? list.length : 0
          }).catch(() => {})
      )
    }

    // 6. Cashier shift status
    if (auth.hasPermission('cashier.view_pos')) {
      promises.push(
        staffApi.cashierCurrentShift()
          .then(res => {
            liveCounts.value.shiftOpen = !!res.data?.data?.has_active_shift
          }).catch(() => {})
      )
    }

    await Promise.allSettled(promises)
  } catch (err) {
    // Silently fall back
  } finally {
    isBadgesLoading.value = false
  }
}

// ── 5. Collapsible Groups State (localStorage) ────────────────────────────
const collapsedGroups = ref<Record<string, boolean>>({})

function loadCollapsedGroups() {
  try {
    const saved = localStorage.getItem('taste_collapsed_groups')
    if (saved) {
      collapsedGroups.value = JSON.parse(saved)
    }
  } catch {}
}

function toggleGroup(groupKey: string) {
  collapsedGroups.value[groupKey] = !collapsedGroups.value[groupKey]
  try {
    localStorage.setItem('taste_collapsed_groups', JSON.stringify(collapsedGroups.value))
  } catch {}
}

// ── 6. Pinned Favorites State (localStorage) ──────────────────────────────
const defaultPinnedIds = ['/admin', '/admin/orders', '/staff/cashier']
const pinnedIds = ref<string[]>([])

function loadPinned() {
  try {
    const saved = localStorage.getItem('taste_pinned_items')
    if (saved) {
      pinnedIds.value = JSON.parse(saved)
    } else {
      pinnedIds.value = [...defaultPinnedIds]
    }
  } catch {
    pinnedIds.value = [...defaultPinnedIds]
  }
}

function savePinned() {
  try {
    localStorage.setItem('taste_pinned_items', JSON.stringify(pinnedIds.value))
  } catch {}
}

function togglePin(itemId: string) {
  const idx = pinnedIds.value.indexOf(itemId)
  if (idx >= 0) {
    pinnedIds.value.splice(idx, 1)
  } else {
    pinnedIds.value.push(itemId)
  }
  savePinned()
}

function movePinned(idx: number, dir: -1 | 1) {
  const target = idx + dir
  if (target < 0 || target >= pinnedIds.value.length) return
  const item = pinnedIds.value[idx]
  pinnedIds.value.splice(idx, 1)
  pinnedIds.value.splice(target, 0, item)
  savePinned()
}

// ── 7. Nav Item Interface & Data Definition ───────────────────────────────
export interface SubItem {
  id: string
  to: string
  label: string
}

export interface NavItemDef {
  id: string
  to: string
  label: string
  icon: any
  badgeKey?: NumericBadgeKey
  badgeType?: 'critical' | 'neutral'
  isShiftDot?: boolean
  subItems?: SubItem[]
  permission?: string
  superAdminOnly?: boolean
  adminOnly?: boolean
}

export interface NavGroupDef {
  key: string
  label: string
  items: NavItemDef[]
}

const rawGroups = computed<NavGroupDef[]>(() => [
  {
    key: 'overview',
    label: t('sidebar.groups.overview'),
    items: [
      {
        id: '/admin',
        to: '/admin',
        label: t('sidebar.nav.dashboard'),
        icon: LayoutDashboard,
      },
      {
        id: '/admin/reports',
        to: '/admin/reports',
        label: t('sidebar.nav.analytics'),
        icon: BarChart2,
        permission: 'reports.view',
      },
    ],
  },
  {
    key: 'operations',
    label: t('sidebar.groups.operations'),
    items: [
      {
        id: '/admin/orders',
        to: '/admin/orders',
        label: t('sidebar.nav.orders'),
        icon: ShoppingBag,
        badgeKey: 'ordersPending',
        badgeType: 'critical',
        permission: 'orders.view',
        subItems: [
          { id: 'orders-live', to: '/admin/orders', label: t('sidebar.nav.ordersLive') },
          { id: 'orders-history', to: '/admin/orders?status=completed', label: t('sidebar.nav.ordersHistory') },
          { id: 'orders-refunds', to: '/staff/cashier', label: t('sidebar.nav.ordersRefunds') },
        ],
      },
      {
        id: '/staff/cashier',
        to: '/staff/cashier',
        label: t('sidebar.nav.cashierPos'),
        icon: CreditCard,
        isShiftDot: true,
        permission: 'cashier.view_pos',
      },
      {
        id: '/staff/kitchen',
        to: '/staff/kitchen',
        label: t('sidebar.nav.kitchenDisplay'),
        icon: ChefHat,
        badgeKey: 'kitchenActiveTickets',
        badgeType: 'neutral',
        permission: 'kitchen.view_kds',
      },
      {
        id: '/admin/reservations',
        to: '/admin/reservations',
        label: t('sidebar.nav.reservations'),
        icon: Calendar,
        badgeKey: 'reservationsToday',
        badgeType: 'neutral',
        permission: 'reservations.manage',
      },
      {
        id: '/admin/tables',
        to: '/admin/tables',
        label: t('sidebar.nav.tables'),
        icon: Grid3x3,
        permission: 'reception.manage_tables',
      },
      {
        id: '/admin/rooms',
        to: '/admin/rooms',
        label: t('sidebar.nav.rooms'),
        icon: BedDouble,
        permission: 'reception.manage_rooms',
        subItems: [
          { id: 'rooms-all', to: '/admin/rooms', label: t('sidebar.nav.roomsAll') },
          { id: 'rooms-clean', to: '/admin/rooms?tab=housekeeping', label: t('sidebar.nav.housekeeping') },
        ],
      },
      {
        id: '/admin/rooms-housekeeping',
        to: '/admin/rooms?tab=housekeeping',
        label: t('sidebar.nav.housekeeping'),
        icon: Sparkles,
        permission: 'reception.manage_rooms',
      },
    ],
  },
  {
    key: 'catalogue',
    label: t('sidebar.groups.catalogue'),
    items: [
      {
        id: '/admin/menu',
        to: '/admin/menu',
        label: t('sidebar.nav.menu'),
        icon: UtensilsCrossed,
        permission: 'menu.view',
      },
      {
        id: '/admin/inventory',
        to: '/admin/inventory',
        label: t('sidebar.nav.inventory'),
        icon: Package,
        badgeKey: 'lowStockItems',
        badgeType: 'critical',
        permission: 'inventory.view',
        subItems: [
          { id: 'inv-stock', to: '/admin/inventory', label: t('sidebar.nav.inventoryStock') },
          { id: 'inv-suppliers', to: '/admin/inventory?tab=suppliers', label: t('sidebar.nav.inventorySuppliers') },
          { id: 'inv-moves', to: '/admin/inventory?tab=movements', label: t('sidebar.nav.inventoryMovements') },
        ],
      },
      {
        id: '/admin/coupons',
        to: '/admin/coupons',
        label: t('sidebar.nav.coupons'),
        icon: Tag,
        permission: 'menu.manage',
      },
    ],
  },
  {
    key: 'guests',
    label: t('sidebar.groups.guests'),
    items: [
      {
        id: '/admin/users-crm',
        to: '/admin/users?role=customer',
        label: t('sidebar.nav.guests'),
        icon: Users,
        permission: 'users.view',
      },
      {
        id: '/admin/reviews',
        to: '/admin/reviews',
        label: t('sidebar.nav.reviews'),
        icon: Star,
      },
      {
        id: '/admin/complaints',
        to: '/admin/complaints',
        label: t('sidebar.nav.complaints'),
        icon: AlertTriangle,
        badgeKey: 'complaintsOpen',
        badgeType: 'critical',
      },
    ],
  },
  {
    key: 'logistics',
    label: t('sidebar.groups.logistics'),
    items: [
      {
        id: '/admin/delivery-zones',
        to: '/admin/delivery-zones',
        label: t('sidebar.nav.deliveries'),
        icon: Truck,
      },
      {
        id: '/admin/suppliers',
        to: '/admin/inventory?tab=suppliers',
        label: t('sidebar.nav.suppliers'),
        icon: Building2,
        permission: 'inventory.view',
      },
      {
        id: '/admin/purchase-orders',
        to: '/admin/inventory?tab=movements',
        label: t('sidebar.nav.purchaseOrders'),
        icon: FileText,
        permission: 'inventory.manage',
      },
    ],
  },
  {
    key: 'admin',
    label: t('sidebar.groups.admin'),
    items: [
      {
        id: '/admin/users',
        to: '/admin/users',
        label: t('sidebar.nav.staffRoles'),
        icon: Shield,
        adminOnly: true,
      },
      {
        id: '/admin/reports-admin',
        to: '/admin/reports',
        label: t('sidebar.nav.reports'),
        icon: BarChart2,
        permission: 'reports.view',
      },
      {
        id: '/admin/hotels',
        to: '/admin/hotels',
        label: t('sidebar.nav.hotels'),
        icon: Building2,
        superAdminOnly: true,
      },
      {
        id: '/admin/audit',
        to: '/admin/audit',
        label: t('sidebar.nav.audit'),
        icon: Shield,
        adminOnly: true,
        permission: 'audit.view',
      },
      {
        id: '/admin/settings',
        to: '/admin/settings',
        label: t('sidebar.nav.settings'),
        icon: Settings,
        adminOnly: true,
        permission: 'settings.manage',
      },
    ],
  },
])

// Role and permission filtered navigation groups
const isSuperAdmin = computed(() => auth.hasRole('super-admin'))
const isManager = computed(() => auth.hasRole('manager') && !auth.hasRole('admin') && !isSuperAdmin.value)

function isItemPermitted(item: NavItemDef): boolean {
  if (item.superAdminOnly && !isSuperAdmin.value) return false
  if (item.adminOnly && isManager.value) return false
  if (item.permission && !auth.hasPermission(item.permission)) return false
  return true
}

const visibleGroups = computed(() => {
  return rawGroups.value
    .map(group => ({
      ...group,
      items: group.items.filter(isItemPermitted),
    }))
    .filter(group => group.items.length > 0) // Hide any empty group
})

// Flattened permitted items map for Pinned favorites
const allPermittedItemsMap = computed(() => {
  const map = new Map<string, NavItemDef>()
  for (const g of rawGroups.value) {
    for (const item of g.items) {
      if (isItemPermitted(item)) {
        map.set(item.id, item)
      }
    }
  }
  return map
})

const pinnedItems = computed(() => {
  return pinnedIds.value
    .map(id => allPermittedItemsMap.value.get(id))
    .filter((item): item is NavItemDef => !!item)
})

// Sub-items toggle state
const expandedSubItems = ref<Record<string, boolean>>({})

function toggleSubItems(itemId: string) {
  expandedSubItems.value[itemId] = !expandedSubItems.value[itemId]
}

// ── 8. Active Item Highlight & Auto-scroll ────────────────────────────────
function isItemActive(to: string): boolean {
  const cleanTo = to.split('?')[0]
  if (cleanTo === '/admin') {
    return route.path === '/admin'
  }
  return route.path === cleanTo || route.path.startsWith(cleanTo + '/')
}

const navScrollRef = ref<HTMLElement | null>(null)

function scrollActiveIntoView() {
  nextTick(() => {
    if (!navScrollRef.value) return
    const activeEl = navScrollRef.value.querySelector('[aria-current="page"]') as HTMLElement
    if (activeEl) {
      activeEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
    }
  })
}

watch(() => route.fullPath, () => {
  scrollActiveIntoView()
  if (isMobile.value) {
    emit('update:mobileOpen', false)
  }
})

// ── 9. Keyboard Shortcuts & Global Handlers ───────────────────────────────
function onKeydown(e: KeyboardEvent) {
  // If typing in an input or textarea, skip
  const active = document.activeElement
  if (active) {
    const tag = active.tagName.toLowerCase()
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || (active as HTMLElement).isContentEditable) {
      return
    }
  }

  // Toggle collapse with [ key
  if (e.key === '[' && !e.metaKey && !e.ctrlKey && !e.altKey) {
    e.preventDefault()
    toggleCollapse()
  }
}

// ── 10. Click Outside Menu Dismissals ─────────────────────────────────────
function handleClickOutside(event: MouseEvent) {
  const target = event.target as Node
  if (newMenuRef.value && !newMenuRef.value.contains(target)) {
    showNewMenu.value = false
  }
  if (branchMenuRef.value && !branchMenuRef.value.contains(target)) {
    showBranchMenu.value = false
  }
  if (profileMenuRef.value && !profileMenuRef.value.contains(target)) {
    showProfileMenu.value = false
  }
  if (notificationsRef.value && !notificationsRef.value.contains(target)) {
    showNotifications.value = false
  }
}

// ── 11. Profile Actions & Safe Logout ─────────────────────────────────────
async function handleSignOutClick() {
  showProfileMenu.value = false
  if (liveCounts.value.shiftOpen) {
    showShiftSignOutWarning.value = true
    return
  }
  await performSignOut()
}

async function performSignOut() {
  showShiftSignOutWarning.value = false
  await auth.logout()
  router.push('/login')
}

// ── 12. Lifecycle Hooks ───────────────────────────────────────────────────
onMounted(() => {
  window.addEventListener('resize', checkResize)
  window.addEventListener('keydown', onKeydown)
  document.addEventListener('click', handleClickOutside)

  loadCollapsedGroups()
  loadPinned()

  try {
    const savedPinned = localStorage.getItem('taste_sidebar_pinned')
    const savedCollapse = localStorage.getItem('taste_sidebar_collapsed')
    if (savedPinned !== null) {
      isPinned.value = savedPinned === 'true'
    } else if (savedCollapse !== null) {
      isPinned.value = savedCollapse !== 'true'
    } else {
      isPinned.value = false // Defaults to auto-expand on mouse hover!
    }
  } catch {}

  fetchLiveBadges()
  pollTimer = setInterval(fetchLiveBadges, 30000)
  scrollActiveIntoView()
})

onUnmounted(() => {
  window.removeEventListener('resize', checkResize)
  window.removeEventListener('keydown', onKeydown)
  document.removeEventListener('click', handleClickOutside)
  if (pollTimer) clearInterval(pollTimer)
})
</script>

<template>
  <!-- ═══════════════════════════════════════════════════════════════════════
       MOBILE BACKDROP OVERLAY (<1024px)
  ════════════════════════════════════════════════════════════════════════ -->
  <Transition
    enter-active-class="transition-opacity duration-200 ease-out"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition-opacity duration-150 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="isMobile && mobileOpen"
      class="fixed inset-0 z-40 bg-black/60 backdrop-blur-xs lg:hidden"
      @click="$emit('update:mobileOpen', false)"
      aria-hidden="true"
    />
  </Transition>

  <!-- ═══════════════════════════════════════════════════════════════════════
       SIDEBAR ASIDE CONTAINER (Hover-Expandable, White / Pitch Black)
       - Expanded: 264px (w-[264px])
       - Collapsed: 72px (w-[72px])
       - Expands on mouse hover when collapsed!
  ════════════════════════════════════════════════════════════════════════ -->
  <aside
    @mouseenter="onSidebarMouseEnter"
    @mouseleave="onSidebarMouseLeave"
    :class="[
      'flex flex-col z-50 select-none bg-white dark:bg-[#0c0d12] border-r border-warm-200/90 dark:border-white/10 transition-[width,transform,box-shadow] duration-200 ease-in-out will-change-[width,transform]',
      isMobile
        ? ['fixed inset-y-0 left-0 w-[264px] shadow-2xl', mobileOpen ? 'translate-x-0' : '-translate-x-full']
        : ['relative shrink-0', isExpanded ? 'w-[264px]' : 'w-[72px]', !isPinned && isHovered ? 'shadow-2xl' : ''],
    ]"
    role="region"
    aria-label="Sidebar Navigation"
  >

    <!-- ── 1. HEADER & BRAND BLOCK ──────────────────────────────────────── -->
    <div class="h-16 px-3 border-b border-warm-200/80 dark:border-white/10 flex items-center justify-between shrink-0 gap-2">
      <!-- Brand Block / Branch Dropdown Trigger -->
      <div class="relative flex-1 min-w-0" ref="branchMenuRef">
        <button
          @click="showBranchMenu = !showBranchMenu"
          :class="[
            'w-full flex items-center gap-2.5 p-1.5 rounded-xl hover:bg-warm-100 dark:hover:bg-neutral-900 transition-colors text-left group cursor-pointer focus-visible:ring-2 focus-visible:ring-primary-500 focus:outline-none',
            isCollapsed && !isMobile ? 'justify-center' : ''
          ]"
          :title="isCollapsed && !isMobile ? `${APP_NAME} (${activeBranch.name})` : undefined"
          :aria-expanded="showBranchMenu"
          aria-haspopup="true"
        >
          <!-- Logo Badge -->
          <div class="w-8 h-8 rounded-xl bg-primary-500 flex items-center justify-center shrink-0 shadow-md shadow-primary-500/25">
            <ChefHat :size="18" class="text-white" />
          </div>

          <!-- Brand name & Branch info (Expanded mode) -->
          <div v-if="!isCollapsed || isMobile" class="flex-1 min-w-0">
            <div class="flex items-center gap-1">
              <span class="font-heading font-bold text-sm text-warm-900 dark:text-white leading-tight truncate">
                {{ APP_NAME }}
              </span>
              <ChevronDown :size="13" class="text-warm-400 dark:text-neutral-500 group-hover:text-warm-700 dark:group-hover:text-neutral-300 transition-transform shrink-0" :class="[showBranchMenu ? 'rotate-180' : '']" />
            </div>
            <p class="text-[11px] font-medium text-warm-500 dark:text-neutral-400 leading-tight truncate mt-0.5">
              {{ activeBranch.name }}
            </p>
          </div>
        </button>

        <!-- Branch / Workspace Switcher Dropdown Popover -->
        <Transition
          enter-active-class="transition-all duration-150 ease-out origin-top-left"
          enter-from-class="opacity-0 scale-95 -translate-y-1"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition-all duration-100 ease-in origin-top-left"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-95 -translate-y-1"
        >
          <div
            v-if="showBranchMenu"
            class="absolute top-full left-0 mt-2 w-64 bg-white dark:bg-[#12131a] rounded-2xl border border-warm-200/90 dark:border-white/10 shadow-2xl z-50 p-2 overflow-hidden"
          >
            <!-- Branch Search -->
            <div class="px-2 pb-2 pt-1 border-b border-warm-100 dark:border-white/5">
              <div class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-warm-100/70 dark:bg-neutral-850 text-xs">
                <Search :size="13" class="text-warm-400 dark:text-neutral-500 shrink-0" />
                <input
                  v-model="branchSearch"
                  type="text"
                  class="bg-transparent border-0 text-warm-900 dark:text-white placeholder:text-warm-400 dark:placeholder:text-neutral-500 focus:outline-none w-full text-xs"
                  :placeholder="$t('sidebar.searchBranches')"
                />
              </div>
            </div>

            <!-- Branch List -->
            <div class="py-1 max-h-48 overflow-y-auto space-y-0.5">
              <button
                v-for="branch in filteredBranches"
                :key="branch.id"
                @click="selectBranch(branch)"
                class="w-full flex items-center justify-between gap-2 px-2.5 py-2 rounded-xl text-left hover:bg-warm-100 dark:hover:bg-neutral-850 transition-colors group cursor-pointer"
              >
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-warm-900 dark:text-white truncate">
                    {{ branch.name }}
                  </p>
                  <p class="text-[10px] text-warm-500 dark:text-neutral-400 truncate">
                    {{ branch.location }}
                  </p>
                </div>
                <Check
                  v-if="branch.id === activeBranchId"
                  :size="14"
                  class="text-primary-500 shrink-0"
                />
              </button>
            </div>

            <!-- Manage Properties Action (If Super Admin) -->
            <div v-if="auth.hasRole('super-admin')" class="pt-1.5 mt-1 border-t border-warm-100 dark:border-white/5">
              <RouterLink
                to="/admin/hotels"
                @click="showBranchMenu = false"
                class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl text-xs font-medium text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-950/40 transition-colors"
              >
                <Building2 :size="14" />
                <span>{{ $t('sidebar.addBranch') }}</span>
              </RouterLink>
            </div>
          </div>
        </Transition>
      </div>

      <!-- Desktop Collapse/Expand Toggle Button (Shortcut [) -->
      <button
        v-if="!isMobile"
        @click.stop="toggleCollapse"
        class="p-1.5 rounded-xl text-warm-400 dark:text-neutral-400 hover:text-warm-800 dark:hover:text-white hover:bg-warm-100 dark:hover:bg-neutral-900 transition-colors shrink-0 focus-visible:ring-2 focus-visible:ring-primary-500 focus:outline-none cursor-pointer"
        :title="isPinned ? 'Sidebar pinned open (Click to enable auto-collapse on mouse leave)' : 'Pin sidebar open (Keep open permanently)'"
        :aria-label="isPinned ? 'Unpin Sidebar' : 'Pin Sidebar'"
      >
        <ChevronLeft v-if="isPinned" :size="17" />
        <ChevronRight v-else :size="17" />
      </button>

      <!-- Mobile Close Drawer Button -->
      <button
        v-if="isMobile"
        @click="$emit('update:mobileOpen', false)"
        class="p-1.5 rounded-xl text-warm-400 dark:text-neutral-400 hover:text-warm-800 dark:hover:text-white hover:bg-warm-100 dark:hover:bg-neutral-900 transition-colors shrink-0"
        aria-label="Close sidebar drawer"
      >
        <X :size="18" />
      </button>
    </div>

    <!-- ── 2. SEARCH TRIGGER & QUICK ACTIONS (+ NEW) ────────────────────── -->
    <div class="px-3 pt-3 pb-2 space-y-2 shrink-0 border-b border-warm-100 dark:border-white/5">
      <!-- Search Input Trigger Button (Opens Command Palette Cmd+K) -->
      <button
        @click="showCommandPalette = true"
        :class="[
          'w-full flex items-center rounded-xl bg-warm-100/80 dark:bg-neutral-900 text-warm-500 dark:text-neutral-400 hover:bg-warm-200/70 dark:hover:bg-neutral-850 hover:text-warm-900 dark:hover:text-white transition-all group focus-visible:ring-2 focus-visible:ring-primary-500 focus:outline-none cursor-pointer',
          isCollapsed && !isMobile ? 'h-10 justify-center px-0' : 'h-9 px-2.5 justify-between gap-2'
        ]"
        :title="isCollapsed && !isMobile ? `${$t('sidebar.searchPlaceholder')} (⌘K)` : undefined"
        aria-label="Search or jump to page"
      >
        <div class="flex items-center gap-2 min-w-0">
          <Search :size="15" class="shrink-0 text-warm-400 dark:text-neutral-500 group-hover:text-primary-500 transition-colors" />
          <span v-if="!isCollapsed || isMobile" class="text-xs font-normal truncate">
            {{ $t('sidebar.searchPlaceholder') }}
          </span>
        </div>
        <kbd v-if="!isCollapsed || isMobile" class="hidden sm:inline-flex items-center px-1.5 py-0.5 text-[10px] font-mono font-medium text-warm-400 dark:text-neutral-500 bg-white dark:bg-neutral-800 border border-warm-200 dark:border-white/10 rounded shadow-2xs">
          ⌘K
        </kbd>
      </button>

      <!-- Quick Action "+ New" Button & Dropdown -->
      <div class="relative" ref="newMenuRef">
        <button
          @click="showNewMenu = !showNewMenu"
          :class="[
            'w-full flex items-center justify-center font-semibold text-xs rounded-xl shadow-xs transition-all active:scale-[0.98] cursor-pointer focus-visible:ring-2 focus-visible:ring-primary-500 focus:outline-none',
            'bg-primary-500 hover:bg-primary-600 text-white shadow-primary-500/20',
            isCollapsed && !isMobile ? 'h-10 px-0' : 'h-9 px-3 gap-1.5'
          ]"
          :title="isCollapsed && !isMobile ? $t('sidebar.quickCreate') : undefined"
          :aria-expanded="showNewMenu"
        >
          <Plus :size="16" class="shrink-0" />
          <span v-if="!isCollapsed || isMobile">{{ $t('sidebar.newButton') }}</span>
          <ChevronDown v-if="!isCollapsed || isMobile" :size="13" class="ml-auto opacity-80" :class="[showNewMenu ? 'rotate-180' : '']" />
        </button>

        <!-- "+ New" Dropdown Menu -->
        <Transition
          enter-active-class="transition-all duration-150 ease-out origin-top-left"
          enter-from-class="opacity-0 scale-95 -translate-y-1"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition-all duration-100 ease-in origin-top-left"
          leave-from-class="opacity-100 scale-100 translate-y-0"
          leave-to-class="opacity-0 scale-95 -translate-y-1"
        >
          <div
            v-if="showNewMenu"
            :class="[
              'absolute mt-2 w-56 bg-white dark:bg-[#12131a] rounded-2xl border border-warm-200/90 dark:border-white/10 shadow-2xl z-50 p-1.5 space-y-0.5',
              isCollapsed && !isMobile ? 'left-full ml-2 top-0' : 'top-full left-0'
            ]"
          >
            <!-- New Order -->
            <RouterLink
              v-if="auth.hasPermission('cashier.view_pos') || auth.hasPermission('orders.create')"
              to="/staff/cashier"
              @click="showNewMenu = false"
              class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-warm-700 dark:text-neutral-200 hover:bg-primary-50 dark:hover:bg-primary-950/40 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
            >
              <ShoppingBag :size="15" class="text-primary-500 shrink-0" />
              <span>{{ $t('sidebar.quickActions.newOrder') }}</span>
            </RouterLink>

            <!-- New Reservation -->
            <RouterLink
              v-if="auth.hasPermission('reservations.manage')"
              to="/admin/reservations"
              @click="showNewMenu = false"
              class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-warm-700 dark:text-neutral-200 hover:bg-primary-50 dark:hover:bg-primary-950/40 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
            >
              <Calendar :size="15" class="text-amber-500 shrink-0" />
              <span>{{ $t('sidebar.quickActions.newReservation') }}</span>
            </RouterLink>

            <!-- Open Shift -->
            <RouterLink
              v-if="auth.hasPermission('cashier.view_pos')"
              to="/staff/cashier"
              @click="showNewMenu = false"
              class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-warm-700 dark:text-neutral-200 hover:bg-primary-50 dark:hover:bg-primary-950/40 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
            >
              <CreditCard :size="15" class="text-emerald-500 shrink-0" />
              <span>{{ $t('sidebar.quickActions.openShift') }}</span>
            </RouterLink>

            <!-- New Dish -->
            <RouterLink
              v-if="auth.hasPermission('menu.manage')"
              to="/admin/menu"
              @click="showNewMenu = false"
              class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-warm-700 dark:text-neutral-200 hover:bg-primary-50 dark:hover:bg-primary-950/40 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
            >
              <UtensilsCrossed :size="15" class="text-orange-500 shrink-0" />
              <span>{{ $t('sidebar.quickActions.newDish') }}</span>
            </RouterLink>

            <!-- New Table -->
            <RouterLink
              v-if="auth.hasPermission('reception.manage_tables')"
              to="/admin/tables"
              @click="showNewMenu = false"
              class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-warm-700 dark:text-neutral-200 hover:bg-primary-50 dark:hover:bg-primary-950/40 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
            >
              <Grid3x3 :size="15" class="text-blue-500 shrink-0" />
              <span>{{ $t('sidebar.quickActions.newTable') }}</span>
            </RouterLink>

            <!-- Book Room -->
            <RouterLink
              v-if="auth.hasPermission('reception.manage_rooms')"
              to="/admin/rooms"
              @click="showNewMenu = false"
              class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-warm-700 dark:text-neutral-200 hover:bg-primary-50 dark:hover:bg-primary-950/40 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
            >
              <BedDouble :size="15" class="text-indigo-500 shrink-0" />
              <span>{{ $t('sidebar.quickActions.bookRoom') }}</span>
            </RouterLink>

            <!-- Log Complaint -->
            <RouterLink
              to="/complaints/new"
              @click="showNewMenu = false"
              class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-warm-700 dark:text-neutral-200 hover:bg-primary-50 dark:hover:bg-primary-950/40 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
            >
              <AlertTriangle :size="15" class="text-rose-500 shrink-0" />
              <span>{{ $t('sidebar.quickActions.logComplaint') }}</span>
            </RouterLink>
          </div>
        </Transition>
      </div>
    </div>

    <!-- ── 3. MAIN NAVIGATION SCROLL AREA ──────────────────────────────── -->
    <!-- Top and Bottom overflow fade shadow indicators -->
    <div class="relative flex-1 min-h-0 flex flex-col">
      <nav
        ref="navScrollRef"
        class="flex-1 overflow-y-auto overflow-x-hidden py-2 px-2 space-y-4 sidebar-scroll focus:outline-none"
        aria-label="Main Navigation"
      >
        <!-- ── PINNED SECTION (Top, User-customizable favorites) ───────── -->
        <div v-if="pinnedItems.length > 0" class="space-y-0.5">
          <!-- Pinned Header -->
          <div
            v-if="!isCollapsed || isMobile"
            class="flex items-center justify-between px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-warm-600 dark:text-neutral-400 select-none"
          >
            <span class="flex items-center gap-1.5">
              <Pin :size="12" class="text-primary-500" />
              {{ $t('sidebar.groups.pinned') }}
            </span>
            <span class="text-[10px] font-mono text-warm-400 dark:text-neutral-500">
              {{ pinnedItems.length }}
            </span>
          </div>
          <div v-else class="my-1 mx-2 border-t border-warm-200/60 dark:border-white/5" />

          <!-- Pinned Item Links -->
          <div
            v-for="(item, pIdx) in pinnedItems"
            :key="'pinned-' + item.id"
            class="relative group"
          >
            <RouterLink
              :to="item.to"
              :aria-current="isItemActive(item.to) ? 'page' : undefined"
              :class="[
                'relative flex items-center h-10 rounded-xl text-sm font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-primary-500 focus:outline-none',
                isCollapsed && !isMobile ? 'justify-center px-0' : 'px-3 gap-3',
                isItemActive(item.to)
                  ? 'bg-primary-500/10 text-primary-600 dark:text-primary-400 font-semibold'
                  : 'text-warm-700 dark:text-neutral-300 hover:bg-warm-100 dark:hover:bg-neutral-900 hover:text-warm-900 dark:hover:text-white'
              ]"
            >
              <!-- Left Accent Bar on Active -->
              <span
                v-if="isItemActive(item.to)"
                class="absolute left-0 inset-y-1.5 w-[3px] bg-primary-500 rounded-r-full"
              />

              <!-- Item Icon (neutral, orange only when active) -->
              <div class="relative shrink-0 flex items-center justify-center">
                <component
                  :is="item.icon"
                  :size="20"
                  :stroke-width="1.75"
                  :class="[
                    'transition-colors',
                    isItemActive(item.to)
                      ? 'text-primary-500 dark:text-primary-400'
                      : 'text-warm-400 dark:text-neutral-400 group-hover:text-warm-800 dark:group-hover:text-neutral-200'
                  ]"
                />

                <!-- Collapsed mode badge dot -->
                <span
                  v-if="isCollapsed && !isMobile && item.badgeKey && getBadgeCount(item.badgeKey) > 0"
                  :class="[
                    'absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full ring-2 ring-white dark:ring-[#0c0d12]',
                    item.badgeType === 'critical' ? 'bg-red-500' : 'bg-primary-500'
                  ]"
                />
              </div>

              <!-- Label (Expanded) -->
              <span v-if="!isCollapsed || isMobile" class="flex-1 truncate">
                {{ item.label }}
              </span>

              <!-- Live Badge (Expanded) -->
              <span
                v-if="(!isCollapsed || isMobile) && item.badgeKey && getBadgeCount(item.badgeKey) > 0"
                :class="[
                  'px-1.5 py-0.5 rounded-full text-[10px] font-bold font-mono tracking-tight shrink-0',
                  item.badgeType === 'critical'
                    ? 'bg-red-500 text-white'
                    : 'bg-warm-200 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300'
                ]"
              >
                {{ getBadgeCount(item.badgeKey) > 99 ? '99+' : getBadgeCount(item.badgeKey) }}
              </span>

              <!-- Pin Reorder / Unpin Buttons on Hover (Expanded) -->
              <div
                v-if="!isCollapsed || isMobile"
                class="hidden group-hover:flex items-center gap-0.5 shrink-0 pl-1"
                @click.prevent.stop
              >
                <button
                  v-if="pIdx > 0"
                  @click="movePinned(pIdx, -1)"
                  class="p-1 rounded hover:bg-warm-200 dark:hover:bg-neutral-800 text-warm-400 hover:text-warm-700 dark:hover:text-neutral-200 transition-colors"
                  :title="$t('sidebar.reorderUp')"
                >
                  <ArrowUp :size="11" />
                </button>
                <button
                  v-if="pIdx < pinnedItems.length - 1"
                  @click="movePinned(pIdx, 1)"
                  class="p-1 rounded hover:bg-warm-200 dark:hover:bg-neutral-800 text-warm-400 hover:text-warm-700 dark:hover:text-neutral-200 transition-colors"
                  :title="$t('sidebar.reorderDown')"
                >
                  <ArrowDown :size="11" />
                </button>
                <button
                  @click="togglePin(item.id)"
                  class="p-1 rounded hover:bg-warm-200 dark:hover:bg-neutral-800 text-primary-500 transition-colors"
                  :title="$t('sidebar.unpinItem')"
                >
                  <PinOff :size="12" />
                </button>
              </div>
            </RouterLink>

            <!-- Collapsed Hover Flyout -->
            <div
              v-if="isCollapsed && !isMobile"
              class="hidden group-hover:flex absolute left-full top-0 ml-2 z-50 px-3 py-1.5 rounded-xl bg-warm-900 dark:bg-neutral-900 text-white text-xs font-medium whitespace-nowrap shadow-xl items-center gap-2 pointer-events-none"
            >
              <span>{{ item.label }}</span>
              <span
                v-if="item.badgeKey && getBadgeCount(item.badgeKey) > 0"
                class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-primary-500 text-white"
              >
                {{ getBadgeCount(item.badgeKey) > 99 ? '99+' : getBadgeCount(item.badgeKey) }}
              </span>
            </div>
          </div>
        </div>

        <!-- ── MAIN GROUPS (Overview, Operations, Catalogue, Guests, Logistics, Admin) ── -->
        <template v-for="group in visibleGroups" :key="group.key">
          <div class="space-y-0.5">
            <!-- Group Header (Collapsible with Chevron) -->
            <div
              v-if="!isCollapsed || isMobile"
              @click="toggleGroup(group.key)"
              class="flex items-center justify-between px-3 py-1 cursor-pointer select-none group/hdr rounded-lg hover:bg-warm-50 dark:hover:bg-neutral-900/40 transition-colors"
              :aria-expanded="!collapsedGroups[group.key]"
            >
              <span class="text-[11px] font-bold uppercase tracking-wider text-warm-600 dark:text-neutral-400 group-hover/hdr:text-warm-900 dark:group-hover/hdr:text-white transition-colors">
                {{ group.label }}
              </span>
              <ChevronDown
                :size="13"
                :class="[
                  'text-warm-400 dark:text-neutral-500 group-hover/hdr:text-warm-700 dark:group-hover/hdr:text-neutral-300 transition-transform duration-200',
                  collapsedGroups[group.key] ? '-rotate-90' : 'rotate-0'
                ]"
              />
            </div>
            <!-- Collapsed rail group separator -->
            <div v-else class="my-2 mx-2 border-t border-warm-200/60 dark:border-white/5" />

            <!-- Group Items (Hidden when group is collapsed on desktop/mobile) -->
            <div v-show="isCollapsed && !isMobile || !collapsedGroups[group.key]" class="space-y-0.5">
              <div
                v-for="item in group.items"
                :key="item.id"
                class="relative group"
              >
                <!-- Main Nav Item Link -->
                <RouterLink
                  :to="item.to"
                  :aria-current="isItemActive(item.to) ? 'page' : undefined"
                  :class="[
                    'relative flex items-center h-10 rounded-xl text-sm font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-primary-500 focus:outline-none',
                    isCollapsed && !isMobile ? 'justify-center px-0' : 'px-3 gap-3',
                    isItemActive(item.to)
                      ? 'bg-primary-500/10 text-primary-600 dark:text-primary-400 font-semibold'
                      : 'text-warm-700 dark:text-neutral-300 hover:bg-warm-100 dark:hover:bg-neutral-900 hover:text-warm-900 dark:hover:text-white'
                  ]"
                >
                  <!-- Left Accent Bar on Active Item -->
                  <span
                    v-if="isItemActive(item.to)"
                    class="absolute left-0 inset-y-1.5 w-[3px] bg-primary-500 rounded-r-full"
                  />

                  <!-- Item Icon -->
                  <div class="relative shrink-0 flex items-center justify-center">
                    <component
                      :is="item.icon"
                      :size="20"
                      :stroke-width="1.75"
                      :class="[
                        'transition-colors',
                        isItemActive(item.to)
                          ? 'text-primary-500 dark:text-primary-400'
                          : 'text-warm-400 dark:text-neutral-400 group-hover:text-warm-800 dark:group-hover:text-neutral-200'
                      ]"
                    />

                    <!-- Cashier POS Shift Status Dot (Green = open, Grey = closed) -->
                    <span
                      v-if="item.isShiftDot"
                      :class="[
                        'absolute -bottom-0.5 -right-0.5 w-2 h-2 rounded-full ring-2 ring-white dark:ring-[#0c0d12]',
                        liveCounts.shiftOpen ? 'bg-emerald-500 ring-emerald-500/20' : 'bg-neutral-400 dark:bg-neutral-600'
                      ]"
                      :title="liveCounts.shiftOpen ? $t('sidebar.status.shiftOpen') : $t('sidebar.status.shiftClosed')"
                    />

                    <!-- Collapsed Icon Rail Badges -->
                    <span
                      v-if="isCollapsed && !isMobile && item.badgeKey && getBadgeCount(item.badgeKey) > 0"
                      :class="[
                        'absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full ring-2 ring-white dark:ring-[#0c0d12]',
                        item.badgeType === 'critical' ? 'bg-red-500' : 'bg-primary-500'
                      ]"
                    />
                  </div>

                  <!-- Item Label (Expanded) -->
                  <span v-if="!isCollapsed || isMobile" class="flex-1 truncate">
                    {{ item.label }}
                  </span>

                  <!-- Cashier POS Shift Status Badge (Expanded) -->
                  <span
                    v-if="(!isCollapsed || isMobile) && item.isShiftDot"
                    :class="[
                      'flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-medium shrink-0',
                      liveCounts.shiftOpen
                        ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60'
                        : 'bg-warm-100 dark:bg-neutral-800 text-warm-500 dark:text-neutral-400'
                    ]"
                  >
                    <span :class="['w-1.5 h-1.5 rounded-full', liveCounts.shiftOpen ? 'bg-emerald-500 animate-pulse' : 'bg-neutral-400']" />
                    <span>{{ liveCounts.shiftOpen ? 'Open' : 'Closed' }}</span>
                  </span>

                  <!-- Live Count Badge (Expanded) -->
                  <span
                    v-if="(!isCollapsed || isMobile) && item.badgeKey && getBadgeCount(item.badgeKey) > 0"
                    :class="[
                      'px-1.5 py-0.5 rounded-full text-[10px] font-bold font-mono tracking-tight shrink-0',
                      item.badgeType === 'critical'
                        ? 'bg-red-500 text-white shadow-2xs'
                        : 'bg-warm-200 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300'
                    ]"
                  >
                    {{ getBadgeCount(item.badgeKey) > 99 ? '99+' : getBadgeCount(item.badgeKey) }}
                  </span>

                  <!-- Pin/Unpin Action Button on Hover (Expanded) -->
                  <button
                    v-if="!isCollapsed || isMobile"
                    @click.prevent.stop="togglePin(item.id)"
                    :class="[
                      'hidden group-hover:flex items-center justify-center p-1 rounded-md text-warm-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-warm-200/60 dark:hover:bg-neutral-800 transition-colors shrink-0',
                      pinnedIds.includes(item.id) ? 'text-primary-500 !flex' : ''
                    ]"
                    :title="pinnedIds.includes(item.id) ? $t('sidebar.unpinItem') : $t('sidebar.pinItem')"
                  >
                    <Pin :size="13" :class="pinnedIds.includes(item.id) ? 'fill-primary-500' : ''" />
                  </button>

                  <!-- Sub-items Toggle Chevron (Expanded) -->
                  <button
                    v-if="(!isCollapsed || isMobile) && item.subItems && item.subItems.length > 0"
                    @click.prevent.stop="toggleSubItems(item.id)"
                    class="p-0.5 rounded text-warm-400 hover:text-warm-700 dark:hover:text-neutral-200"
                  >
                    <ChevronDown
                      :size="13"
                      :class="['transition-transform duration-150', expandedSubItems[item.id] ? 'rotate-180' : '']"
                    />
                  </button>
                </RouterLink>

                <!-- Nested Sub-items (Expanded Mode) -->
                <div
                  v-if="(!isCollapsed || isMobile) && item.subItems && (expandedSubItems[item.id] || isItemActive(item.to))"
                  class="ml-8 pl-2 my-1 border-l border-warm-200 dark:border-white/10 space-y-0.5"
                >
                  <RouterLink
                    v-for="sub in item.subItems"
                    :key="sub.id"
                    :to="sub.to"
                    :class="[
                      'block px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors truncate',
                      route.fullPath === sub.to
                        ? 'text-primary-600 dark:text-primary-400 bg-primary-500/10 font-semibold'
                        : 'text-warm-600 dark:text-neutral-400 hover:text-warm-900 dark:hover:text-white hover:bg-warm-100/60 dark:hover:bg-neutral-900/60'
                    ]"
                  >
                    {{ sub.label }}
                  </RouterLink>
                </div>

                <!-- Collapsed Icon Rail Flyout Popover on Hover -->
                <div
                  v-if="isCollapsed && !isMobile"
                  class="hidden group-hover:flex flex-col absolute left-full top-0 ml-2 z-50 w-52 p-2 rounded-2xl bg-white dark:bg-[#12131a] border border-warm-200/90 dark:border-white/10 shadow-2xl space-y-1"
                >
                  <div class="flex items-center justify-between px-2 py-1 border-b border-warm-100 dark:border-white/5">
                    <span class="text-xs font-bold text-warm-900 dark:text-white truncate">
                      {{ item.label }}
                    </span>
                    <span
                      v-if="item.badgeKey && getBadgeCount(item.badgeKey) > 0"
                      :class="[
                        'px-1.5 py-0.2 rounded-full text-[10px] font-bold text-white',
                        item.badgeType === 'critical' ? 'bg-red-500' : 'bg-primary-500'
                      ]"
                    >
                      {{ getBadgeCount(item.badgeKey) }}
                    </span>
                  </div>

                  <!-- Quick sub-items list in flyout -->
                  <div v-if="item.subItems && item.subItems.length > 0" class="space-y-0.5 pt-1">
                    <RouterLink
                      v-for="sub in item.subItems"
                      :key="'flyout-' + sub.id"
                      :to="sub.to"
                      class="block px-2 py-1.5 rounded-lg text-xs text-warm-700 dark:text-neutral-300 hover:bg-primary-50 dark:hover:bg-primary-950/40 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                    >
                      {{ sub.label }}
                    </RouterLink>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </nav>
    </div>

    <!-- ── 4. FIXED FOOTER & PROFILE CARD ──────────────────────────────── -->
    <div class="border-t border-warm-200/80 dark:border-white/10 shrink-0 p-2 relative" ref="profileMenuRef">
      <!-- Profile Menu Floating Popover -->
      <Transition
        enter-active-class="transition-all duration-150 ease-out origin-bottom-left"
        enter-from-class="opacity-0 scale-95 translate-y-1"
        enter-to-class="opacity-100 scale-100 translate-y-0"
        leave-active-class="transition-all duration-100 ease-in origin-bottom-left"
        leave-from-class="opacity-100 scale-100 translate-y-0"
        leave-to-class="opacity-0 scale-95 translate-y-1"
      >
        <div
          v-if="showProfileMenu"
          :class="[
            'absolute bottom-full mb-2 w-64 bg-white dark:bg-[#12131a] rounded-2xl border border-warm-200/90 dark:border-white/10 shadow-2xl z-50 p-1.5 space-y-1',
            isCollapsed && !isMobile ? 'left-full ml-2' : 'left-2'
          ]"
        >
          <!-- User Info Banner -->
          <div class="px-3 py-2 border-b border-warm-100 dark:border-white/5">
            <p class="text-xs font-bold text-warm-900 dark:text-white truncate">
              {{ auth.user?.name }}
            </p>
            <p class="text-[11px] text-warm-500 dark:text-neutral-400 font-mono truncate">
              {{ auth.user?.email }}
            </p>
          </div>

          <!-- Menu Links -->
          <RouterLink
            to="/profile"
            @click="showProfileMenu = false"
            class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-warm-700 dark:text-neutral-200 hover:bg-warm-100 dark:hover:bg-neutral-850 transition-colors"
          >
            <Users :size="15" class="text-warm-400" />
            <span>{{ $t('sidebar.profile') }}</span>
          </RouterLink>

          <RouterLink
            v-if="auth.isAdmin"
            to="/admin/settings"
            @click="showProfileMenu = false"
            class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-warm-700 dark:text-neutral-200 hover:bg-warm-100 dark:hover:bg-neutral-850 transition-colors"
          >
            <Settings :size="15" class="text-warm-400" />
            <span>{{ $t('sidebar.settings') }}</span>
          </RouterLink>

          <!-- Theme Toggle -->
          <button
            @click="ui.toggleDarkMode"
            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium text-warm-700 dark:text-neutral-200 hover:bg-warm-100 dark:hover:bg-neutral-850 transition-colors cursor-pointer"
          >
            <div class="flex items-center gap-2.5">
              <Sun v-if="ui.darkMode" :size="15" class="text-amber-400" />
              <Moon v-else :size="15" class="text-slate-400" />
              <span>{{ $t('sidebar.theme') }}</span>
            </div>
            <span class="text-[10px] uppercase font-mono text-warm-400 dark:text-neutral-500">
              {{ ui.darkMode ? 'Dark' : 'Light' }}
            </span>
          </button>

          <!-- Language Switcher -->
          <button
            @click="locale = (locale === 'en' ? 'am' : 'en')"
            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium text-warm-700 dark:text-neutral-200 hover:bg-warm-100 dark:hover:bg-neutral-850 transition-colors cursor-pointer"
          >
            <div class="flex items-center gap-2.5">
              <Globe :size="15" class="text-warm-400" />
              <span>{{ $t('sidebar.language') }}</span>
            </div>
            <span class="text-[11px] font-semibold text-primary-600 dark:text-primary-400">
              {{ locale === 'en' ? 'አማርኛ' : 'English' }}
            </span>
          </button>

          <!-- Keyboard Shortcuts Cheat-sheet -->
          <button
            @click="showShortcutsModal = true; showProfileMenu = false"
            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium text-warm-700 dark:text-neutral-200 hover:bg-warm-100 dark:hover:bg-neutral-850 transition-colors cursor-pointer"
          >
            <div class="flex items-center gap-2.5">
              <HelpCircle :size="15" class="text-warm-400" />
              <span>{{ $t('sidebar.shortcuts') }}</span>
            </div>
            <kbd class="px-1.5 py-0.5 text-[10px] font-mono text-warm-400 dark:text-neutral-500 bg-warm-100 dark:bg-neutral-800 rounded">?</kbd>
          </button>

          <!-- Lock Screen Option -->
          <button
            @click="showLockScreen = true; showProfileMenu = false"
            class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-warm-700 dark:text-neutral-200 hover:bg-warm-100 dark:hover:bg-neutral-850 transition-colors cursor-pointer"
          >
            <Lock :size="15" class="text-amber-500" />
            <span>{{ $t('sidebar.lockScreen') }}</span>
          </button>

          <!-- Sign Out Action -->
          <div class="pt-1 mt-1 border-t border-warm-100 dark:border-white/5">
            <button
              @click="handleSignOutClick"
              class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 transition-colors cursor-pointer"
            >
              <LogOut :size="15" />
              <span>{{ $t('sidebar.signOut') }}</span>
            </button>
          </div>
        </div>
      </Transition>

      <!-- Profile Bottom Card Trigger Button -->
      <div class="flex items-center gap-1.5">
        <button
          @click="showProfileMenu = !showProfileMenu"
          :class="[
            'flex-1 flex items-center rounded-xl p-1.5 hover:bg-warm-100 dark:hover:bg-neutral-900 transition-colors text-left group cursor-pointer focus-visible:ring-2 focus-visible:ring-primary-500 focus:outline-none min-w-0',
            isCollapsed && !isMobile ? 'justify-center px-0' : 'gap-2.5'
          ]"
          :aria-expanded="showProfileMenu"
        >
          <!-- Avatar with Online Dot -->
          <div class="relative shrink-0">
            <div class="w-8 h-8 rounded-full bg-primary-500/15 border border-primary-500/30 flex items-center justify-center font-bold text-xs text-primary-600 dark:text-primary-400">
              {{ auth.user?.name?.[0]?.toUpperCase() || 'U' }}
            </div>
            <!-- Online Dot -->
            <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-[#0c0d12]" />
          </div>

          <!-- User Details (Expanded) -->
          <div v-if="!isCollapsed || isMobile" class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5">
              <p class="text-xs font-semibold text-warm-900 dark:text-white truncate leading-tight">
                {{ auth.user?.name }}
              </p>
              <span class="px-1.5 py-0.2 rounded-full text-[9px] font-semibold bg-primary-50 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400 border border-primary-200/60 dark:border-primary-800/60 truncate">
                {{ auth.user?.roles?.[0] || 'Staff' }}
              </span>
            </div>
            <p
              class="text-[10px] text-warm-500 dark:text-neutral-400 truncate leading-tight mt-0.5"
              :title="auth.user?.email"
            >
              {{ auth.user?.email }}
            </p>
          </div>
        </button>

        <!-- Notification Bell Trigger (Near profile or header) -->
        <div v-if="!isCollapsed || isMobile" class="relative" ref="notificationsRef">
          <button
            @click="showNotifications = !showNotifications"
            class="p-2 rounded-xl text-warm-400 hover:text-warm-800 dark:text-neutral-400 dark:hover:text-white hover:bg-warm-100 dark:hover:bg-neutral-900 transition-colors relative cursor-pointer"
            :title="$t('sidebar.notifications')"
            aria-label="View notifications"
          >
            <Bell :size="16" />
            <span
              v-if="liveCounts.complaintsOpen + liveCounts.lowStockItems > 0"
              class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-[#0c0d12]"
            />
          </button>

          <!-- Notifications popover -->
          <Transition
            enter-active-class="transition-all duration-150 ease-out origin-bottom-right"
            enter-from-class="opacity-0 scale-95 translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition-all duration-100 ease-in origin-bottom-right"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-1"
          >
            <div
              v-if="showNotifications"
              class="absolute bottom-full right-0 mb-2 w-72 bg-white dark:bg-[#12131a] rounded-2xl border border-warm-200/90 dark:border-white/10 shadow-2xl z-50 p-3 space-y-2"
            >
              <div class="flex items-center justify-between pb-1.5 border-b border-warm-100 dark:border-white/5">
                <span class="text-xs font-bold text-warm-900 dark:text-white">{{ $t('sidebar.notifications') }}</span>
                <span class="text-[10px] font-mono text-warm-400">{{ liveCounts.complaintsOpen + liveCounts.lowStockItems }} active</span>
              </div>

              <div class="space-y-1.5 max-h-48 overflow-y-auto">
                <div v-if="liveCounts.complaintsOpen > 0" class="flex items-start gap-2 p-2 rounded-xl bg-red-50 dark:bg-red-950/30 text-xs">
                  <AlertTriangle :size="14" class="text-red-500 shrink-0 mt-0.5" />
                  <div>
                    <p class="font-semibold text-red-900 dark:text-red-200">{{ liveCounts.complaintsOpen }} Open Complaints</p>
                    <p class="text-[10px] text-red-700 dark:text-red-300">Requires manager resolution</p>
                  </div>
                </div>

                <div v-if="liveCounts.lowStockItems > 0" class="flex items-start gap-2 p-2 rounded-xl bg-amber-50 dark:bg-amber-950/30 text-xs">
                  <Package :size="14" class="text-amber-500 shrink-0 mt-0.5" />
                  <div>
                    <p class="font-semibold text-amber-900 dark:text-amber-200">{{ liveCounts.lowStockItems }} Low Stock Items</p>
                    <p class="text-[10px] text-amber-700 dark:text-amber-300">Reorder recommended</p>
                  </div>
                </div>

                <div v-if="liveCounts.complaintsOpen === 0 && liveCounts.lowStockItems === 0" class="py-4 text-center text-xs text-warm-400 dark:text-neutral-500">
                  {{ $t('sidebar.noNotifications') }}
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </div>
  </aside>

  <!-- ═══════════════════════════════════════════════════════════════════════
       ACTIVE SHIFT SIGN-OUT CONFIRMATION MODAL
  ════════════════════════════════════════════════════════════════════════ -->
  <Teleport to="body">
    <div
      v-if="showShiftSignOutWarning"
      class="fixed inset-0 z-[150] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
      role="alertdialog"
      aria-modal="true"
    >
      <div class="w-full max-w-md bg-white dark:bg-[#12131a] rounded-2xl border border-warm-200 dark:border-white/10 shadow-2xl p-6 space-y-4">
        <div class="flex items-center gap-3 text-amber-600 dark:text-amber-400">
          <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-950/50 flex items-center justify-center">
            <AlertTriangle :size="20" />
          </div>
          <div>
            <h3 class="text-base font-bold text-warm-900 dark:text-white">
              {{ $t('sidebar.signOutConfirmTitle') }}
            </h3>
            <p class="text-xs text-warm-500 dark:text-neutral-400">
              Shift Reconcile Reminder
            </p>
          </div>
        </div>

        <p class="text-sm text-warm-700 dark:text-neutral-300">
          {{ $t('sidebar.signOutConfirmMessage') }}
        </p>

        <div class="flex items-center justify-end gap-3 pt-2">
          <button
            @click="showShiftSignOutWarning = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-warm-700 dark:text-neutral-300 hover:bg-warm-100 dark:hover:bg-neutral-800 transition-colors"
          >
            {{ $t('sidebar.stayLoggedIn') }}
          </button>
          <button
            @click="performSignOut"
            class="px-4 py-2 rounded-xl text-xs font-semibold bg-red-600 hover:bg-red-700 text-white transition-colors shadow-xs"
          >
            {{ $t('sidebar.signOutAnyway') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- ═══════════════════════════════════════════════════════════════════════
       GLOBAL ACCESSIBLE MODALS (Command Palette, Shortcuts, Lock Screen)
  ════════════════════════════════════════════════════════════════════════ -->
  <CommandPalette
    ref="commandPaletteRef"
    v-model="showCommandPalette"
    @open-shift="router.push('/staff/cashier')"
  />

  <KeyboardShortcutsModal
    ref="shortcutsModalRef"
    v-model="showShortcutsModal"
  />

  <LockScreenModal
    ref="lockScreenModalRef"
    v-model="showLockScreen"
  />
</template>

<style scoped>
/* Sleek minimal scrollbar that fades on idle */
.sidebar-scroll::-webkit-scrollbar {
  width: 4px;
}
.sidebar-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.sidebar-scroll::-webkit-scrollbar-thumb {
  background: rgba(156, 163, 175, 0.25);
  border-radius: 9999px;
}
.sidebar-scroll:hover::-webkit-scrollbar-thumb {
  background: rgba(156, 163, 175, 0.45);
}
</style>
