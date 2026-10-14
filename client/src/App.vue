<script setup lang="ts">
import { RouterView } from 'vue-router'
import { useUiStore } from './stores/ui'
import ToastContainer from './components/ui/ToastContainer.vue'

// ui store manages the `dark` class on <html> via syncDocumentTheme()
// We just reference it here to ensure the store is initialized early
useUiStore()
</script>

<template>
  <RouterView v-slot="{ Component, route }">
    <Transition name="page" mode="out-in">
      <component :is="Component" :key="route.path" />
    </Transition>
  </RouterView>
  <ToastContainer />
</template>
