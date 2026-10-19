<script setup>
import DefaultLayout from '../../layouts/DefaultLayout.vue'
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-16 max-w-3xl">
      <h1 class="font-heading text-4xl font-bold text-warm-900 dark:text-white mb-6">{{ $t('aboutPage.title') }}</h1>
      <p class="text-lg text-warm-600 dark:text-warm-300 leading-relaxed mb-6">
        {{ $t('aboutPage.p1') }}
      </p>
      <p class="text-warm-600 dark:text-warm-300 leading-relaxed">
        {{ $t('aboutPage.p2') }}
      </p>
    </div>
  </DefaultLayout>
</template>
