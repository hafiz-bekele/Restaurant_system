<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Globe } from '@lucide/vue'

const { locale } = useI18n()

const isAmharic = computed(() => locale.value === 'am')

function toggleLanguage() {
  const next = isAmharic.value ? 'en' : 'am'
  locale.value = next
  localStorage.setItem('locale', next)
  document.documentElement.lang = next
}

function setLanguage(lang) {
  locale.value = lang
  localStorage.setItem('locale', lang)
  document.documentElement.lang = lang
}
</script>

<template>
  <button
    type="button"
    @click="toggleLanguage"
    class="group inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl text-xs font-semibold tracking-wide transition-all duration-200 border border-warm-200 dark:border-white/10 hover:border-primary-500/40 bg-warm-50 dark:bg-white/5 hover:bg-warm-100 dark:hover:bg-white/10 text-warm-700 dark:text-warm-200 shadow-xs"
    :title="isAmharic ? 'ወደ እንግሊዝኛ ቀይር (Switch to English)' : 'Switch to Amharic (ወደ አማርኛ ቀይር)'"
    aria-label="Toggle language between Amharic and English"
  >
    <Globe :size="14" class="text-primary-500 group-hover:rotate-45 transition-transform duration-300" />
    <span class="font-medium">
      <span :class="{ 'text-primary-600 dark:text-primary-400 font-bold': isAmharic }">አማ</span>
      <span class="text-warm-400 dark:text-warm-600 mx-0.5">/</span>
      <span :class="{ 'text-primary-600 dark:text-primary-400 font-bold': !isAmharic }">EN</span>
    </span>
  </button>
</template>
