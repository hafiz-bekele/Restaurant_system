<template>
  <article class="card overflow-hidden border border-warm-100 dark:border-warm-800 animate-pulse">
    <!-- Image placeholder -->
    <div class="relative overflow-hidden aspect-[4/3] bg-warm-100 dark:bg-warm-800">
      <div class="skeleton w-full h-full !rounded-none"></div>
      <!-- Badges placeholder -->
      <div class="absolute top-3 left-3 flex gap-1.5">
        <div class="skeleton h-5 w-12 !rounded-full opacity-80"></div>
      </div>
      <!-- Heart placeholder -->
      <div class="absolute top-3 right-3 skeleton w-8 h-8 !rounded-full opacity-80"></div>
    </div>

    <!-- Content placeholders -->
    <div class="p-4 space-y-3">
      <!-- Title -->
      <div class="skeleton h-5 w-3/4 rounded-lg"></div>

      <!-- Description lines -->
      <div class="space-y-1.5 pt-0.5">
        <div class="skeleton h-3.5 w-full rounded"></div>
        <div class="skeleton h-3.5 w-4/5 rounded"></div>
      </div>

      <!-- Rating & prep time -->
      <div class="flex items-center gap-3 pt-1">
        <div class="skeleton h-3.5 w-12 rounded"></div>
        <div class="skeleton h-3.5 w-16 rounded"></div>
      </div>

      <!-- Price & CTA -->
      <div class="flex items-center justify-between pt-2 border-t border-warm-100 dark:border-warm-800/60">
        <div class="skeleton h-6 w-16 rounded-lg"></div>
        <div class="skeleton h-8 w-18 rounded-xl"></div>
      </div>
    </div>
  </article>
</template>
