<script setup lang="ts">
withDefaults(
  defineProps<{
    hasImage?: boolean
    lines?: number
    compact?: boolean
  }>(),
  {
    hasImage: true,
    lines: 2,
    compact: false,
  }
)
</script>

<template>
  <div
    class="card overflow-hidden border border-warm-100 dark:border-warm-800 animate-pulse select-none flex flex-col"
    role="status"
    aria-label="Loading card content"
  >
    <!-- Image placeholder -->
    <div
      v-if="hasImage"
      :class="[
        'w-full bg-warm-200/60 dark:bg-warm-800/60 relative overflow-hidden',
        compact ? 'aspect-[16/9]' : 'aspect-[4/3]'
      ]"
    >
      <div class="skeleton w-full h-full !rounded-none"></div>
      <div class="absolute top-3 left-3 skeleton h-5 w-16 !rounded-full opacity-70"></div>
    </div>

    <!-- Body placeholder -->
    <div class="p-4 space-y-3 flex-1 flex flex-col justify-between">
      <div class="space-y-2">
        <!-- Title -->
        <div class="skeleton h-5 w-3/4 rounded-lg"></div>
        <!-- Subtitle or meta -->
        <div class="skeleton h-3.5 w-1/2 rounded"></div>

        <!-- Description lines -->
        <div class="space-y-1.5 pt-1">
          <div v-for="i in lines" :key="i" class="skeleton h-3 rounded" :style="{ width: i === lines ? '80%' : '100%' }"></div>
        </div>
      </div>

      <!-- Footer placeholder -->
      <div class="pt-3 border-t border-warm-100 dark:border-warm-800/80 flex items-center justify-between">
        <div class="skeleton h-5 w-20 rounded-md"></div>
        <div class="skeleton h-8 w-24 rounded-xl"></div>
      </div>
    </div>
  </div>
</template>
