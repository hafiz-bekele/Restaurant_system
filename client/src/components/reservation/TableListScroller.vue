<script setup lang="ts">
import { ref, computed } from 'vue'
import { Utensils, Filter, ArrowLeft, ArrowRight, AlertTriangle, RefreshCw } from '@lucide/vue'
import TableOptionCard from './TableOptionCard.vue'
import { useReservationStore, type TableOption } from '../../stores/reservation'

const store = useReservationStore()

const emit = defineEmits<{
  (e: 'back'): void
  (e: 'continue'): void
}>()

// Filter chips
const selectedArea = ref<string>('all')
const selectedCap  = ref<string>('all')

const areaFilterOptions = [
  { value: 'all',     label: 'All Areas' },
  { value: 'window',  label: 'Window View' },
  { value: 'indoor',  label: 'Indoor Bistro' },
  { value: 'outdoor', label: 'Outdoor Terrace' },
  { value: 'vip',     label: 'VIP Lounge' },
  { value: 'quiet',   label: 'Quiet Corner' },
]

const capacityFilterOptions = [
  { value: 'all', label: 'All Capacities' },
  { value: '2',   label: '2 Guests' },
  { value: '4',   label: '4 Guests' },
  { value: '6',   label: '6+ Guests' },
  { value: '10',  label: '10+ VIP' },
]

const filteredTables = computed(() => {
  return store.availableTables.filter((table) => {
    // Area filter
    if (selectedArea.value !== 'all' && (table.area || '').toLowerCase() !== selectedArea.value.toLowerCase()) {
      return false
    }

    // Capacity filter
    if (selectedCap.value !== 'all') {
      const cap = Number(table.capacity)
      if (selectedCap.value === '2' && cap !== 2) return false
      if (selectedCap.value === '4' && cap !== 4) return false
      if (selectedCap.value === '6' && cap < 6) return false
      if (selectedCap.value === '10' && cap < 10) return false
    }

    return true
  })
})

function handleSelect(table: TableOption) {
  store.selectTable(table)
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header with Summary Pill & Slot Window -->
    <div class="card p-5 border border-warm-100 dark:border-warm-800 flex flex-wrap items-center justify-between gap-3 bg-gradient-to-r from-warm-50 to-primary-50/20 dark:from-warm-900 dark:to-warm-900/60">
      <div>
        <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white flex items-center gap-2">
          <Utensils :size="20" class="text-primary-500" />
          Choose Your Table
        </h2>
        <p class="text-xs text-warm-500 mt-0.5">
          Real-time availability for <strong>{{ store.form.party_size }} guests</strong> on <strong>{{ store.form.date }}</strong> ({{ store.form.start_time }} - {{ store.formattedEndTime }})
        </p>
      </div>

      <button
        type="button"
        @click="store.fetchAvailableTables"
        :disabled="store.isLoadingTables"
        class="btn-ghost text-xs gap-1.5 text-warm-600 dark:text-warm-300"
      >
        <RefreshCw :size="13" :class="store.isLoadingTables ? 'animate-spin text-primary-500' : ''" />
        Refresh
      </button>
    </div>

    <!-- Filter Chips (Area & Capacity) -->
    <div class="space-y-3">
      <!-- Area Filters -->
      <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none">
        <span class="text-xs font-semibold text-warm-400 mr-1 flex items-center gap-1 shrink-0">
          <Filter :size="12" /> Area:
        </span>
        <button
          v-for="opt in areaFilterOptions"
          :key="opt.value"
          type="button"
          @click="selectedArea = opt.value"
          class="px-3 py-1.5 rounded-full text-xs font-semibold shrink-0 transition-all"
          :class="[
            selectedArea === opt.value
              ? 'bg-primary-500 text-white shadow-xs'
              : 'bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-warm-200 dark:hover:bg-warm-700'
          ]"
        >
          {{ opt.label }}
        </button>
      </div>

      <!-- Capacity Filters -->
      <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none">
        <span class="text-xs font-semibold text-warm-400 mr-1 shrink-0">Size:</span>
        <button
          v-for="opt in capacityFilterOptions"
          :key="opt.value"
          type="button"
          @click="selectedCap = opt.value"
          class="px-3 py-1 rounded-full text-xs font-medium shrink-0 transition-all"
          :class="[
            selectedCap === opt.value
              ? 'bg-warm-900 text-white dark:bg-white dark:text-warm-900 font-bold'
              : 'bg-warm-100 dark:bg-warm-800 text-warm-600 dark:text-warm-400 hover:bg-warm-200 dark:hover:bg-warm-700'
          ]"
        >
          {{ opt.label }}
        </button>
      </div>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="store.isLoadingTables" class="space-y-3">
      <div
        v-for="i in 3"
        :key="i"
        class="card p-5 border border-warm-100 dark:border-warm-800 animate-pulse space-y-3"
      >
        <div class="flex justify-between">
          <div class="h-5 w-44 bg-warm-200 dark:bg-warm-800 rounded-md"></div>
          <div class="h-5 w-20 bg-warm-200 dark:bg-warm-800 rounded-full"></div>
        </div>
        <div class="h-3 w-32 bg-warm-200 dark:bg-warm-800 rounded-md"></div>
        <div class="flex gap-2 pt-2 border-t border-warm-100 dark:border-warm-800">
          <div class="h-4 w-20 bg-warm-200 dark:bg-warm-800 rounded-md"></div>
          <div class="h-4 w-24 bg-warm-200 dark:bg-warm-800 rounded-md"></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="!filteredTables.length"
      class="card p-12 border border-warm-200 dark:border-warm-800 text-center space-y-4 bg-warm-50/50 dark:bg-warm-900/30"
    >
      <div class="w-16 h-16 rounded-full bg-amber-50 dark:bg-amber-950/40 text-amber-500 mx-auto flex items-center justify-center">
        <AlertTriangle :size="30" />
      </div>
      <div>
        <h3 class="font-heading font-bold text-lg text-warm-900 dark:text-white">
          No tables available for this time
        </h3>
        <p class="text-sm text-warm-500 max-w-md mx-auto mt-1">
          All suitable tables are currently booked for the requested party size and hours. Try adjusting your start time or picking another date.
        </p>
      </div>
      <div class="pt-2 flex justify-center gap-3">
        <button
          type="button"
          @click="emit('back')"
          class="btn-outline gap-2 text-sm"
        >
          <ArrowLeft :size="16" />
          <span>Change Date & Time</span>
        </button>
      </div>
    </div>

    <!-- Scrollable Option List (Smooth scroll, max-height, custom scrollbar) -->
    <div
      v-else
      role="radiogroup"
      aria-label="Available restaurant tables"
      class="max-h-[520px] overflow-y-auto space-y-3 pr-2.5 scrollbar-thin scrollbar-thumb-warm-300 dark:scrollbar-thumb-warm-700 scrollbar-track-transparent focus:outline-hidden"
    >
      <TableOptionCard
        v-for="table in filteredTables"
        :key="table.id"
        :table="table"
        :is-selected="store.form.table_id === table.id"
        @select="handleSelect"
      />
    </div>

    <!-- Actions Footer -->
    <div class="flex items-center justify-between pt-4 border-t border-warm-200 dark:border-warm-800">
      <button
        type="button"
        @click="emit('back')"
        class="btn-outline gap-2 text-sm"
      >
        <ArrowLeft :size="16" />
        <span>Back to Details</span>
      </button>

      <button
        type="button"
        :disabled="!store.form.table_id"
        @click="emit('continue')"
        class="btn-primary gap-2 text-sm px-6 py-3 shadow-md shadow-primary-500/25"
      >
        <span>Continue to Review & Confirm</span>
        <ArrowRight :size="16" />
      </button>
    </div>
  </div>
</template>
