<script setup lang="ts">
import { Sun, Moon } from '@lucide/vue'
import { useUiStore } from '../../stores/ui'

interface Props {
  variant?: 'icon' | 'button' | 'switch' | 'segmented'
  size?: 'sm' | 'md' | 'lg'
  showLabel?: boolean
}

withDefaults(defineProps<Props>(), {
  variant: 'icon',
  size: 'md',
  showLabel: true,
})

const ui = useUiStore()
</script>

<template>
  <!-- Segmented Control Variant -->
  <div
    v-if="variant === 'segmented'"
    class="inline-flex items-center p-1 rounded-xl bg-warm-100 dark:bg-warm-800/80 border border-warm-200/80 dark:border-warm-700/80 text-xs font-semibold select-none shadow-xs"
    role="radiogroup"
    aria-label="Theme selection"
  >
    <button
      type="button"
      @click="ui.setDarkMode(false)"
      :class="[
        'flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition-all duration-200',
        !ui.darkMode
          ? 'bg-white text-warm-900 shadow-xs'
          : 'text-warm-500 hover:text-warm-800 dark:text-warm-400 dark:hover:text-warm-200'
      ]"
      :aria-checked="!ui.darkMode"
      role="radio"
    >
      <Sun :size="14" class="text-amber-500" />
      <span>Light</span>
    </button>
    <button
      type="button"
      @click="ui.setDarkMode(true)"
      :class="[
        'flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition-all duration-200',
        ui.darkMode
          ? 'bg-gray-900 text-white shadow-xs'
          : 'text-warm-500 hover:text-warm-800 dark:text-warm-400 dark:hover:text-warm-200'
      ]"
      :aria-checked="ui.darkMode"
      role="radio"
    >
      <Moon :size="14" class="text-primary-400" />
      <span>Dark</span>
    </button>
  </div>

  <!-- Switch Variant (Toggle with track) -->
  <button
    v-else-if="variant === 'switch'"
    type="button"
    @click="ui.toggleDarkMode"
    class="group flex items-center justify-between w-full py-2 px-3 rounded-xl hover:bg-warm-100 dark:hover:bg-warm-800 transition-colors"
    :aria-label="ui.darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
  >
    <div class="flex items-center gap-2.5">
      <div class="p-1.5 rounded-lg bg-warm-200/60 dark:bg-warm-700/60 text-warm-700 dark:text-warm-200 transition-colors">
        <Sun v-if="!ui.darkMode" :size="16" class="text-amber-500" />
        <Moon v-else :size="16" class="text-primary-400" />
      </div>
      <span v-if="showLabel" class="text-sm font-medium text-warm-800 dark:text-warm-200">
        {{ ui.darkMode ? 'Dark Theme' : 'Light Theme' }}
      </span>
    </div>

    <!-- Switch Track & Thumb -->
    <div
      :class="[
        'w-11 h-6 flex items-center rounded-full p-1 transition-colors duration-300 ease-in-out',
        ui.darkMode ? 'bg-primary-600 justify-end' : 'bg-warm-300 dark:bg-warm-700 justify-start'
      ]"
    >
      <div
        class="w-4 h-4 rounded-full bg-white shadow-md transform transition-transform duration-300 ease-in-out"
      ></div>
    </div>
  </button>

  <!-- Button with text Variant -->
  <button
    v-else-if="variant === 'button'"
    type="button"
    @click="ui.toggleDarkMode"
    :class="[
      'inline-flex items-center gap-2 rounded-xl text-xs font-semibold px-3 py-2 border transition-all duration-200',
      'border-warm-200 dark:border-warm-700 bg-white/60 dark:bg-gray-800/60 hover:bg-warm-100 dark:hover:bg-warm-800 text-warm-700 dark:text-warm-200 shadow-xs'
    ]"
    :title="ui.darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
  >
    <Sun v-if="ui.darkMode" :size="15" class="text-amber-400 shrink-0" />
    <Moon v-else :size="15" class="text-primary-500 shrink-0" />
    <span v-if="showLabel">{{ ui.darkMode ? 'Light Mode' : 'Dark Mode' }}</span>
  </button>

  <!-- Icon Only Variant (Default) -->
  <button
    v-else
    type="button"
    @click="ui.toggleDarkMode"
    :class="[
      'btn-ghost rounded-xl transition-all duration-200 relative flex items-center justify-center',
      size === 'sm' ? 'p-1.5' : (size === 'lg' ? 'p-2.5' : 'p-2')
    ]"
    :aria-label="ui.darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
    :title="ui.darkMode ? 'Switch to light mode' : 'Switch to dark mode'"
  >
    <Moon
      v-if="!ui.darkMode"
      :size="size === 'sm' ? 16 : (size === 'lg' ? 22 : 19)"
      class="text-warm-700 dark:text-warm-200 hover:text-primary-600 transition-transform active:rotate-45 duration-200"
    />
    <Sun
      v-else
      :size="size === 'sm' ? 16 : (size === 'lg' ? 22 : 19)"
      class="text-amber-400 hover:text-amber-300 transition-transform active:rotate-90 duration-200"
    />
  </button>
</template>
