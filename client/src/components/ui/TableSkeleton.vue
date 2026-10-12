<script setup lang="ts">
interface Props {
  rows?: number
  cols?: number
  showHeader?: boolean
}

withDefaults(defineProps<Props>(), {
  rows: 5,
  cols: 5,
  showHeader: true,
})
</script>

<template>
  <div class="card overflow-hidden border border-warm-100 dark:border-warm-800">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead v-if="showHeader" class="bg-warm-50/70 dark:bg-warm-900/40 border-b border-warm-100 dark:border-warm-800">
          <tr>
            <th v-for="c in cols" :key="c" class="p-4">
              <div class="skeleton h-3.5 w-20 rounded"></div>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-warm-100 dark:divide-warm-800/60">
          <tr v-for="r in rows" :key="r" class="hover:bg-warm-50/30 dark:hover:bg-warm-900/20">
            <td v-for="c in cols" :key="c" class="p-4">
              <div
                class="skeleton h-4 rounded"
                :class="[
                  c === 1 ? 'w-24 font-medium' :
                  c === cols ? 'w-16 ml-auto' :
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
