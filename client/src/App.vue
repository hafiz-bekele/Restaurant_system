<script setup lang="ts">
import { RouterView } from 'vue-router'
import { useUiStore } from './stores/ui'
import ToastContainer from './components/ui/ToastContainer.vue'

const ui = useUiStore()
</script>

<template>
  <div :class="{ dark: ui.darkMode }" class="min-h-screen bg-warm-50 dark:bg-gray-950 transition-colors duration-300">
    <RouterView />
    <ToastContainer />
  </div>
</template>
