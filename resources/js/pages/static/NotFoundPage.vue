<script setup>
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import { RouterLink } from 'vue-router'
</script>
<template>
  <DefaultLayout>
    <div class="page-container py-32 text-center">
      <p class="text-8xl font-heading font-bold text-warm-200 dark:text-warm-800">404</p>
      <h1 class="font-heading text-3xl font-bold text-warm-900 dark:text-white mt-4 mb-2">Page Not Found</h1>
      <p class="text-warm-500 dark:text-warm-400 mb-8">The page you are looking for does not exist.</p>
      <RouterLink to="/" class="btn-primary">Go Home</RouterLink>
    </div>
  </DefaultLayout>
</template>
