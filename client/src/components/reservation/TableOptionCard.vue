<script setup lang="ts">
import { computed } from 'vue'
import { Users, Check, Sparkles, MapPin } from '@lucide/vue'
import type { TableOption } from '../../stores/reservation'

const props = defineProps<{
  table: TableOption
  isSelected: boolean
}>()

const emit = defineEmits<{
  (e: 'select', table: TableOption): void
}>()

const areaColors = computed(() => {
  const a = (props.table.area || '').toLowerCase()
  switch (a) {
    case 'window':
      return 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-300 border-sky-200 dark:border-sky-800'
    case 'outdoor':
      return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
    case 'vip':
      return 'bg-purple-100 text-purple-800 dark:bg-purple-950/50 dark:text-purple-300 border-purple-200 dark:border-purple-800'
    case 'quiet':
      return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800'
    default:
      return 'bg-warm-100 text-warm-800 dark:bg-warm-800 dark:text-warm-300 border-warm-200 dark:border-warm-700'
  }
})
</script>

<template>
  <div
    role="radio"
    :aria-checked="isSelected"
    tabindex="0"
    @click="emit('select', table)"
    @keydown.space.prevent="emit('select', table)"
    @keydown.enter.prevent="emit('select', table)"
    class="relative p-5 rounded-2xl border transition-all duration-200 cursor-pointer select-none focus:outline-hidden focus:ring-2 focus:ring-primary-500"
    :class="[
      isSelected
        ? 'border-primary-500 bg-primary-500/10 dark:bg-primary-500/15 backdrop-blur-sm shadow-md ring-2 ring-primary-500 dark:ring-primary-400'
        : 'border-warm-200/90 dark:border-white/10 bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm hover:border-primary-400 hover:bg-white dark:hover:bg-gray-900 shadow-2xs'
    ]"
  >
    <!-- Top-Right Selected Checkmark -->
    <div
      v-if="isSelected"
      class="absolute top-4 right-4 w-6 h-6 rounded-full bg-primary-500 text-white flex items-center justify-center shadow-sm"
    >
      <Check :size="14" class="stroke-[3]" />
    </div>

    <!-- Header: Table Name & Area Badge -->
    <div class="flex items-start justify-between pr-8 mb-2">
      <div>
        <h3 class="font-heading font-bold text-base text-warm-900 dark:text-white flex items-center gap-2">
          {{ table.name }}
        </h3>
        <div class="flex items-center gap-2 mt-1">
          <span
            class="inline-flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full border"
            :class="areaColors"
          >
            <MapPin :size="11" />
            {{ table.area }}
          </span>
          <span class="inline-flex items-center gap-1 text-xs text-warm-500 dark:text-warm-400 font-medium">
            <Users :size="13" /> Up to {{ table.capacity }} guests
          </span>
        </div>
      </div>
    </div>

    <!-- Feature Tags -->
    <div v-if="table.features && table.features.length" class="flex flex-wrap gap-1.5 mt-3 pt-3 border-t border-warm-100 dark:border-warm-800/60">
      <span
        v-for="feat in table.features"
        :key="feat"
        class="text-[11px] font-medium px-2 py-0.5 rounded-lg bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300"
      >
        {{ feat }}
      </span>
    </div>
  </div>
</template>
