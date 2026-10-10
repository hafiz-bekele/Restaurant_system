<script setup>
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import { ref } from 'vue'
const form = ref({ name: '', email: '', message: '' })
const sent = ref(false)
function submit() { sent.value = true }
</script>
<template>
  <DefaultLayout>
    <div class="page-container py-16 max-w-2xl">
      <h1 class="font-heading text-4xl font-bold text-warm-900 dark:text-white mb-8">Contact Us</h1>
      <div v-if="sent" class="card p-8 text-center">
        <p class="text-green-600 dark:text-green-400 font-semibold text-lg">Message sent! We will get back to you soon.</p>
      </div>
      <form v-else @submit.prevent="submit" class="card p-8 space-y-5">
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Name</label>
          <input v-model="form.name" type="text" required class="input" placeholder="Your name" />
        </div>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Email</label>
          <input v-model="form.email" type="email" required class="input" placeholder="you@example.com" />
        </div>
        <div>
          <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Message</label>
          <textarea v-model="form.message" required rows="5" class="input resize-none" placeholder="How can we help you?"></textarea>
        </div>
        <button type="submit" class="btn-primary w-full justify-center py-3">Send Message</button>
      </form>
    </div>
  </DefaultLayout>
</template>
