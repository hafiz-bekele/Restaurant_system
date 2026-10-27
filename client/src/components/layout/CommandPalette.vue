<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  Search,
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
  PlusCircle,
  Sun,
  Moon,
  ArrowRight,
  CornerDownLeft,
  X
} from 'lucide-vue-next'
import { useAuthStore } from '../../stores/auth'
import { useUiStore } from '../../stores/ui'

const props = defineProps<{
  modelValue?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'close'): void
  (e: 'open-shift'): void
}>()

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()

const isOpen = ref(props.modelValue ?? false)
const query = ref('')
const selectedIndex = ref(0)
const inputRef = ref<HTMLInputElement | null>(null)

watch(() => props.modelValue, (val) => {
  if (val !== undefined) {
    isOpen.value = val
    if (val) {
      query.value = ''
      selectedIndex.value = 0
      nextTick(() => inputRef.value?.focus())
    }
  }
})

function close() {
  isOpen.value = false
  emit('update:modelValue', false)
  emit('close')
}

function open() {
  isOpen.value = true
  query.value = ''
  selectedIndex.value = 0
  emit('update:modelValue', true)
  nextTick(() => inputRef.value?.focus())
}

defineExpose({ open, close })

// Keyboard listener for Cmd+K / Ctrl+K
function onGlobalKeydown(e: KeyboardEvent) {
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault()
    if (isOpen.value) {
      close()
    } else {
      open()
    }
  } else if (e.key === 'Escape' && isOpen.value) {
    e.preventDefault()
    close()
  }
}

onMounted(() => {
  window.addEventListener('keydown', onGlobalKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onGlobalKeydown)
})

// Define all searchable items
interface PaletteItem {
  id: string
  title: string
  subtitle?: string
  group: 'actions' | 'pages'
  icon: any
  action?: () => void
  to?: string
  permission?: string
  superAdminOnly?: boolean
  adminOnly?: boolean
  keywords?: string[]
}

const allItems = computed<PaletteItem[]>(() => [
  // Quick Actions
  {
    id: 'act-new-order',
    title: t('sidebar.quickActions.newOrder'),
    subtitle: 'Open POS register or order taker',
    group: 'actions',
    icon: PlusCircle,
    to: '/staff/cashier',
    permission: 'cashier.view_pos',
    keywords: ['order', 'create', 'pos', 'take', 'ትዕዛዝ', 'አዲስ'],
  },
  {
    id: 'act-new-reservation',
    title: t('sidebar.quickActions.newReservation'),
    subtitle: 'Create a new table reservation',
    group: 'actions',
    icon: Calendar,
    to: '/admin/reservations',
    permission: 'reservations.manage',
    keywords: ['reserve', 'book', 'table', 'guest', 'ቦታ', 'ማስያዣ'],
  },
  {
    id: 'act-open-shift',
    title: t('sidebar.quickActions.openShift'),
    subtitle: 'Start cash drawer shift',
    group: 'actions',
    icon: CreditCard,
    action: () => {
      emit('open-shift')
      router.push('/staff/cashier')
    },
    permission: 'cashier.view_pos',
    keywords: ['shift', 'cash', 'drawer', 'float', 'open', 'ሺፍት', 'ክፈት'],
  },
  {
    id: 'act-new-dish',
    title: t('sidebar.quickActions.newDish'),
    subtitle: 'Add culinary item to the live menu',
    group: 'actions',
    icon: UtensilsCrossed,
    to: '/admin/menu',
    permission: 'menu.manage',
    keywords: ['dish', 'food', 'recipe', 'price', 'ምግብ'],
  },
  {
    id: 'act-book-room',
    title: t('sidebar.quickActions.bookRoom'),
    subtitle: 'Reserve hotel suite or check in',
    group: 'actions',
    icon: BedDouble,
    to: '/admin/rooms',
    permission: 'reception.manage_rooms',
    keywords: ['room', 'suite', 'hotel', 'check-in', 'ክፍል', 'ሆቴል'],
  },
  {
    id: 'act-log-complaint',
    title: t('sidebar.quickActions.logComplaint'),
    subtitle: 'Record guest issue or feedback',
    group: 'actions',
    icon: AlertTriangle,
    to: '/complaints/new',
    keywords: ['complaint', 'issue', 'ticket', 'guest', 'feedback', 'ቅሬታ'],
  },
  {
    id: 'act-toggle-theme',
    title: ui.darkMode ? t('adminLayout.lightMode') : t('adminLayout.darkMode'),
    subtitle: 'Switch workspace color appearance',
    group: 'actions',
    icon: ui.darkMode ? Sun : Moon,
    action: () => ui.toggleDarkMode(),
    keywords: ['theme', 'dark', 'light', 'mode', 'ቀለም'],
  },

  // Pages & Navigation
  {
    id: 'page-dashboard',
    title: t('sidebar.nav.dashboard'),
    subtitle: 'Overview KPIs and daily statistics',
    group: 'pages',
    icon: LayoutDashboard,
    to: '/admin',
    keywords: ['home', 'kpi', 'sales', 'analytics', 'ዳሽቦርድ'],
  },
  {
    id: 'page-analytics',
    title: t('sidebar.nav.analytics'),
    subtitle: 'Sales charts, revenue and performance',
    group: 'pages',
    icon: BarChart2,
    to: '/admin/reports',
    permission: 'reports.view',
    keywords: ['reports', 'charts', 'sales', 'revenue', 'tax', 'ሪፖርት'],
  },
  {
    id: 'page-orders',
    title: t('sidebar.nav.ordersLive'),
    subtitle: 'Live kitchen and dining orders',
    group: 'pages',
    icon: ShoppingBag,
    to: '/admin/orders',
    permission: 'orders.view',
    keywords: ['orders', 'live', 'tickets', 'dine-in', 'takeout', 'ትዕዛዞች'],
  },
  {
    id: 'page-cashier',
    title: t('sidebar.nav.cashierPos'),
    subtitle: 'Billing terminal and shift management',
    group: 'pages',
    icon: CreditCard,
    to: '/staff/cashier',
    permission: 'cashier.view_pos',
    keywords: ['pos', 'cashier', 'payment', 'receipt', 'settle', 'ካሼር'],
  },
  {
    id: 'page-kitchen',
    title: t('sidebar.nav.kitchenDisplay'),
    subtitle: 'Kitchen Display System (KDS)',
    group: 'pages',
    icon: ChefHat,
    to: '/staff/kitchen',
    permission: 'kitchen.view_kds',
    keywords: ['kds', 'kitchen', 'cooking', 'chef', 'prep', 'ወጥ ቤት'],
  },
  {
    id: 'page-reservations',
    title: t('sidebar.nav.reservations'),
    subtitle: 'Table bookings and guest schedule',
    group: 'pages',
    icon: Calendar,
    to: '/admin/reservations',
    permission: 'reservations.manage',
    keywords: ['reservations', 'bookings', 'calendar', 'ቦታ ማስያዣ'],
  },
  {
    id: 'page-tables',
    title: t('sidebar.nav.tables'),
    subtitle: 'Floor layout, zones and QR codes',
    group: 'pages',
    icon: Grid3x3,
    to: '/admin/tables',
    permission: 'reception.manage_tables',
    keywords: ['tables', 'floor', 'zones', 'qr', 'ጠረጴዛ'],
  },
  {
    id: 'page-rooms',
    title: t('sidebar.nav.rooms'),
    subtitle: 'Hotel suites, pricing and occupancy',
    group: 'pages',
    icon: BedDouble,
    to: '/admin/rooms',
    permission: 'reception.manage_rooms',
    keywords: ['rooms', 'hotel', 'guests', 'stay', 'ክፍሎች'],
  },
  {
    id: 'page-housekeeping',
    title: t('sidebar.nav.housekeeping'),
    subtitle: 'Room turnover, cleaning and inspection',
    group: 'pages',
    icon: Sparkles,
    to: '/admin/rooms?tab=housekeeping',
    permission: 'reception.manage_rooms',
    keywords: ['clean', 'turnover', 'maid', 'maintenance', 'ጽዳት'],
  },
  {
    id: 'page-menu',
    title: t('sidebar.nav.menu'),
    subtitle: 'Dishes, categories, modifiers and pricing',
    group: 'pages',
    icon: UtensilsCrossed,
    to: '/admin/menu',
    permission: 'menu.view',
    keywords: ['menu', 'dishes', 'food', 'categories', 'ምግብ'],
  },
  {
    id: 'page-inventory',
    title: t('sidebar.nav.inventoryStock'),
    subtitle: 'Stock counts, units and threshold alerts',
    group: 'pages',
    icon: Package,
    to: '/admin/inventory',
    permission: 'inventory.view',
    keywords: ['inventory', 'stock', 'supplies', 'ingredients', 'ዕቃ ክምችት'],
  },
  {
    id: 'page-coupons',
    title: t('sidebar.nav.coupons'),
    subtitle: 'Promotional discount codes & deals',
    group: 'pages',
    icon: Tag,
    to: '/admin/coupons',
    permission: 'menu.manage',
    keywords: ['coupons', 'discounts', 'promo', 'ኩፖን'],
  },
  {
    id: 'page-guests',
    title: t('sidebar.nav.guests'),
    subtitle: 'Customer profiles, history and CRM',
    group: 'pages',
    icon: Users,
    to: '/admin/users?role=customer',
    permission: 'users.view',
    keywords: ['guests', 'customers', 'crm', 'profiles', 'እንግዶች'],
  },
  {
    id: 'page-reviews',
    title: t('sidebar.nav.reviews'),
    subtitle: 'Customer dish ratings and testimonials',
    group: 'pages',
    icon: Star,
    to: '/admin/reviews',
    keywords: ['reviews', 'ratings', 'feedback', 'stars', 'ግምገማ'],
  },
  {
    id: 'page-complaints',
    title: t('sidebar.nav.complaints'),
    subtitle: 'Active dispute tickets and SLA',
    group: 'pages',
    icon: AlertTriangle,
    to: '/admin/complaints',
    keywords: ['complaints', 'tickets', 'disputes', 'ቅሬታ'],
  },
  {
    id: 'page-deliveries',
    title: t('sidebar.nav.deliveries'),
    subtitle: 'Delivery dispatch and courier zones',
    group: 'pages',
    icon: Truck,
    to: '/admin/delivery-zones',
    keywords: ['delivery', 'zones', 'courier', 'ማድረሻ'],
  },
  {
    id: 'page-suppliers',
    title: t('sidebar.nav.suppliers'),
    subtitle: 'Vendors, contacts and purchase orders',
    group: 'pages',
    icon: Building2,
    to: '/admin/inventory?tab=suppliers',
    permission: 'inventory.view',
    keywords: ['suppliers', 'vendors', 'procurement', 'አቅራቢዎች'],
  },
  {
    id: 'page-staff',
    title: t('sidebar.nav.staffRoles'),
    subtitle: 'Staff accounts, permissions and roster',
    group: 'pages',
    icon: Shield,
    to: '/admin/users',
    adminOnly: true,
    keywords: ['staff', 'roles', 'users', 'permissions', 'ሰራተኞች'],
  },
  {
    id: 'page-properties',
    title: t('sidebar.nav.hotels'),
    subtitle: 'Multi-branch properties and branches',
    group: 'pages',
    icon: Building2,
    to: '/admin/hotels',
    superAdminOnly: true,
    keywords: ['hotels', 'properties', 'branches', 'ሆቴሎች'],
  },
  {
    id: 'page-settings',
    title: t('sidebar.nav.settings'),
    subtitle: 'VAT, currency, receipts and system options',
    group: 'pages',
    icon: Settings,
    to: '/admin/settings',
    adminOnly: true,
    keywords: ['settings', 'config', 'vat', 'tax', 'currency', 'ቅንብሮች'],
  },
])

// Filter items according to permissions & search query
const filteredItems = computed(() => {
  const q = query.value.trim().toLowerCase()
  const isSuperAdmin = auth.hasRole('super-admin')
  const isManager = auth.hasRole('manager') && !auth.hasRole('admin') && !isSuperAdmin

  return allItems.value.filter(item => {
    // Permission check
    if (item.superAdminOnly && !isSuperAdmin) return false
    if (item.adminOnly && isManager) return false
    if (item.permission && !auth.hasPermission(item.permission)) return false

    // Search query check
    if (!q) return true
    const inTitle = item.title.toLowerCase().includes(q)
    const inSubtitle = item.subtitle?.toLowerCase().includes(q) ?? false
    const inKeywords = item.keywords?.some(k => k.toLowerCase().includes(q)) ?? false
    return inTitle || inSubtitle || inKeywords
  })
})

const actionItems = computed(() => filteredItems.value.filter(i => i.group === 'actions'))
const pageItems = computed(() => filteredItems.value.filter(i => i.group === 'pages'))

// Flat list for arrow keyboard navigation
const flatList = computed(() => [...actionItems.value, ...pageItems.value])

watch(flatList, () => {
  if (selectedIndex.value >= flatList.value.length) {
    selectedIndex.value = Math.max(0, flatList.value.length - 1)
  }
})

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    if (flatList.value.length > 0) {
      selectedIndex.value = (selectedIndex.value + 1) % flatList.value.length
    }
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    if (flatList.value.length > 0) {
      selectedIndex.value = (selectedIndex.value - 1 + flatList.value.length) % flatList.value.length
    }
  } else if (e.key === 'Enter') {
    e.preventDefault()
    if (flatList.value[selectedIndex.value]) {
      executeItem(flatList.value[selectedIndex.value])
    }
  }
}

function executeItem(item: PaletteItem) {
  close()
  if (item.action) {
    item.action()
  } else if (item.to) {
    router.push(item.to)
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-150 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-100 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-[100] flex items-start justify-center pt-16 sm:pt-24 px-4 bg-black/60 backdrop-blur-sm"
        @click.self="close"
        role="dialog"
        aria-modal="true"
        aria-label="Command Palette"
      >
        <div
          class="relative w-full max-w-2xl bg-white dark:bg-[#0f1117] rounded-2xl border border-warm-200/90 dark:border-white/10 shadow-2xl overflow-hidden flex flex-col max-h-[80vh] transition-all"
        >
          <!-- Search Header Input -->
          <div class="flex items-center px-4 py-3.5 border-b border-warm-200/80 dark:border-white/10 gap-3 shrink-0">
            <Search :size="20" class="text-warm-400 dark:text-neutral-500 shrink-0" />
            <input
              ref="inputRef"
              v-model="query"
              type="text"
              class="flex-1 bg-transparent border-0 text-warm-900 dark:text-white placeholder:text-warm-400 dark:placeholder:text-neutral-500 text-base focus:outline-none focus:ring-0"
              :placeholder="$t('sidebar.commandPalette.placeholder')"
              @keydown="onKeydown"
            />
            <span
              v-if="query"
              @click="query = ''"
              class="p-1 rounded-md text-warm-400 hover:text-warm-700 dark:text-neutral-500 dark:hover:text-neutral-200 cursor-pointer"
            >
              <X :size="16" />
            </span>
            <kbd class="hidden sm:inline-flex items-center px-2 py-0.5 text-[11px] font-mono font-medium text-warm-500 dark:text-neutral-400 bg-warm-100 dark:bg-neutral-800/80 border border-warm-200 dark:border-white/10 rounded-md shadow-xs">
              ESC
            </kbd>
          </div>

          <!-- Results Scroll Area -->
          <div class="flex-1 overflow-y-auto p-2 space-y-4">
            <!-- Empty state -->
            <div
              v-if="flatList.length === 0"
              class="py-12 text-center text-warm-500 dark:text-neutral-400"
            >
              <p class="text-sm font-medium">{{ $t('sidebar.commandPalette.noResults') }}</p>
              <p class="text-xs text-warm-400 dark:text-neutral-500 mt-1">Try searching "Orders", "POS", "Shift", "Tables" or "Menu"</p>
            </div>

            <!-- Quick Actions Group -->
            <div v-if="actionItems.length > 0">
              <p class="px-3 pt-1 pb-1 text-[11px] font-bold uppercase tracking-wider text-warm-500 dark:text-neutral-400">
                {{ $t('sidebar.commandPalette.actionsSection') }}
              </p>
              <div class="space-y-0.5">
                <button
                  v-for="(item, idx) in actionItems"
                  :key="item.id"
                  @click="executeItem(item)"
                  @mouseenter="selectedIndex = idx"
                  :class="[
                    'w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl text-left transition-colors duration-100 group cursor-pointer',
                    selectedIndex === idx
                      ? 'bg-primary-500 text-white shadow-sm'
                      : 'text-warm-700 dark:text-neutral-200 hover:bg-warm-100 dark:hover:bg-neutral-900'
                  ]"
                >
                  <div class="flex items-center gap-3 min-w-0">
                    <div
                      :class="[
                        'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors',
                        selectedIndex === idx
                          ? 'bg-white/20 text-white'
                          : 'bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400'
                      ]"
                    >
                      <component :is="item.icon" :size="18" />
                    </div>
                    <div class="min-w-0">
                      <p :class="['text-sm font-medium leading-tight truncate', selectedIndex === idx ? 'text-white font-semibold' : 'text-warm-900 dark:text-white']">
                        {{ item.title }}
                      </p>
                      <p v-if="item.subtitle" :class="['text-xs leading-tight truncate mt-0.5', selectedIndex === idx ? 'text-white/80' : 'text-warm-500 dark:text-neutral-400']">
                        {{ item.subtitle }}
                      </p>
                    </div>
                  </div>

                  <CornerDownLeft
                    :size="14"
                    :class="[selectedIndex === idx ? 'text-white' : 'text-transparent group-hover:text-warm-400 dark:group-hover:text-neutral-500']"
                  />
                </button>
              </div>
            </div>

            <!-- Pages & Navigation Group -->
            <div v-if="pageItems.length > 0">
              <p class="px-3 pt-2 pb-1 text-[11px] font-bold uppercase tracking-wider text-warm-500 dark:text-neutral-400">
                {{ $t('sidebar.commandPalette.pagesSection') }}
              </p>
              <div class="space-y-0.5">
                <button
                  v-for="(item, idx) in pageItems"
                  :key="item.id"
                  @click="executeItem(item)"
                  @mouseenter="selectedIndex = actionItems.length + idx"
                  :class="[
                    'w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl text-left transition-colors duration-100 group cursor-pointer',
                    selectedIndex === actionItems.length + idx
                      ? 'bg-primary-500 text-white shadow-sm'
                      : 'text-warm-700 dark:text-neutral-200 hover:bg-warm-100 dark:hover:bg-neutral-900'
                  ]"
                >
                  <div class="flex items-center gap-3 min-w-0">
                    <div
                      :class="[
                        'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors',
                        selectedIndex === actionItems.length + idx
                          ? 'bg-white/20 text-white'
                          : 'bg-warm-100 dark:bg-neutral-800 text-warm-500 dark:text-neutral-400'
                      ]"
                    >
                      <component :is="item.icon" :size="18" />
                    </div>
                    <div class="min-w-0">
                      <p :class="['text-sm font-medium leading-tight truncate', selectedIndex === actionItems.length + idx ? 'text-white font-semibold' : 'text-warm-900 dark:text-white']">
                        {{ item.title }}
                      </p>
                      <p v-if="item.subtitle" :class="['text-xs leading-tight truncate mt-0.5', selectedIndex === actionItems.length + idx ? 'text-white/80' : 'text-warm-500 dark:text-neutral-400']">
                        {{ item.subtitle }}
                      </p>
                    </div>
                  </div>

                  <div class="flex items-center gap-2">
                    <span v-if="item.to" :class="['text-[11px] font-mono px-1.5 py-0.5 rounded', selectedIndex === actionItems.length + idx ? 'bg-white/20 text-white' : 'text-warm-400 dark:text-neutral-500']">
                      {{ item.to }}
                    </span>
                    <ArrowRight
                      :size="14"
                      :class="[selectedIndex === actionItems.length + idx ? 'text-white' : 'text-transparent group-hover:text-warm-400 dark:group-hover:text-neutral-500']"
                    />
                  </div>
                </button>
              </div>
            </div>
          </div>

          <!-- Footer Navigation Hints -->
          <div class="px-4 py-2.5 border-t border-warm-200/80 dark:border-white/10 bg-warm-50/50 dark:bg-neutral-900/40 flex items-center justify-between text-xs text-warm-500 dark:text-neutral-400 shrink-0">
            <span class="flex items-center gap-1.5">
              <kbd class="px-1.5 py-0.5 font-mono text-[10px] bg-white dark:bg-neutral-800 border border-warm-200 dark:border-white/10 rounded">↑</kbd>
              <kbd class="px-1.5 py-0.5 font-mono text-[10px] bg-white dark:bg-neutral-800 border border-warm-200 dark:border-white/10 rounded">↓</kbd>
              <span>{{ $t('sidebar.commandPalette.navigateHint') }}</span>
            </span>
            <span class="flex items-center gap-1.5">
              <kbd class="px-1.5 py-0.5 font-mono text-[10px] bg-white dark:bg-neutral-800 border border-warm-200 dark:border-white/10 rounded">Esc</kbd>
              <span>{{ $t('sidebar.commandPalette.pressEsc') }}</span>
            </span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
