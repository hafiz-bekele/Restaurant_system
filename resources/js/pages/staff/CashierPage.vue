<script setup>
import { UtensilsCrossed } from '@lucide/vue'
</script>
<template>
  <div>
    <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white mb-6">Cashier / POS</h1>
    <div class="card p-8 text-center text-warm-400">
      <UtensilsCrossed :size="48" class="mx-auto mb-4 opacity-40" />
      <p>POS terminal — use the Orders admin panel to manage payments.</p>
    </div>
  </div>
</template>
