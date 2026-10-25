<script setup lang="ts">
import { computed } from 'vue'
import CircleLoader from './CircleLoader.vue'

interface Props {
  rows?: number
  cols?: number
  columns?: number
  showHeader?: boolean
  label?: string
}

const props = withDefaults(defineProps<Props>(), {
  rows: 5,
  cols: 5,
  showHeader: true,
  label: 'Loading records...',
})

const columnCount = computed(() => props.columns ?? props.cols ?? 5)
</script>

<template>
  <div class="card relative overflow-hidden border border-warm-100 dark:border-warm-800">
    <!-- Center CircleLoader Floating Badge -->
    <div
      class="absolute inset-0 bg-white/50 dark:bg-warm-950/50 backdrop-blur-[2px] flex items-center justify-center z-10 pointer-events-none"
      aria-hidden="true"
    >
      <div
        class="px-4 py-2.5 rounded-2xl bg-white/95 dark:bg-warm-900/95 border border-warm-200/90 dark:border-warm-700/90 shadow-xl flex items-center gap-3"
      >
        <CircleLoader size="md" color="blue" />
        <span class="text-xs font-semibold text-warm-700 dark:text-warm-200 tracking-tight">
          {{ label }}
        </span>
      </div>
    </div>

    <!-- Skeleton Table Rows Behind -->
    <div class="overflow-x-auto opacity-50 select-none">
      <table class="w-full text-left text-sm">
        <thead v-if="showHeader" class="bg-warm-50/70 dark:bg-warm-900/40 border-b border-warm-100 dark:border-warm-800">
          <tr>
            <th v-for="c in columnCount" :key="c" class="p-4">
              <div class="skeleton h-3.5 w-20 rounded"></div>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-warm-100 dark:divide-warm-800/60">
          <tr v-for="r in rows" :key="r" class="hover:bg-warm-50/30 dark:hover:bg-warm-900/20">
            <td v-for="c in columnCount" :key="c" class="p-4">
              <div
                class="skeleton h-4 rounded"
                :class="[
                  c === 1 ? 'w-24 font-medium' :
                  c === columnCount ? 'w-16 ml-auto' :
                  (c % 2 === 0 ? 'w-32' : 'w-20')
                ]"
              ></div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

