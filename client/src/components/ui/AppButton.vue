<script setup lang="ts">
import { computed } from 'vue'
import CircleLoader from './CircleLoader.vue'

interface Props {
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger'
  size?: 'xs' | 'sm' | 'md' | 'lg'
  loading?: boolean
  loadingText?: string
  disabled?: boolean
  fullWidth?: boolean
  type?: 'button' | 'submit' | 'reset'
  icon?: any
  iconPosition?: 'left' | 'right'
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
  size: 'md',
  loading: false,
  loadingText: '',
  disabled: false,
  fullWidth: false,
  type: 'button',
  icon: null,
  iconPosition: 'left',
})

const emit = defineEmits<{
  (e: 'click', event: MouseEvent): void
}>()

const isDisabled = computed(() => props.disabled || props.loading)

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'primary':
      return 'bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 text-white active:scale-[0.98] shadow-md shadow-primary-500/25 hover:shadow-lg hover:shadow-primary-500/35 border-t border-white/20 disabled:shadow-none'
    case 'secondary':
      return 'bg-white/60 dark:bg-white/10 text-warm-800 dark:text-warm-200 hover:bg-white/85 dark:hover:bg-white/15 border border-white/60 dark:border-white/10 backdrop-blur-md shadow-2xs active:scale-[0.98]'
    case 'outline':
      return 'bg-white/40 dark:bg-white/5 backdrop-blur-md border border-primary-500/50 text-primary-600 hover:bg-primary-500/10 hover:border-primary-500 dark:text-primary-400 dark:hover:bg-primary-500/20 active:scale-[0.98] shadow-2xs'
    case 'ghost':
      return 'text-warm-700 hover:bg-white/60 dark:text-warm-300 dark:hover:bg-white/10 backdrop-blur-xs active:scale-[0.98]'
    case 'danger':
      return 'bg-red-500/90 text-white hover:bg-red-600 active:scale-[0.98] shadow-md shadow-red-500/25 border-t border-white/20'
    default:
      return 'bg-primary-500 text-white hover:bg-primary-600'
  }
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'xs':
      return 'px-2.5 py-1 text-xs rounded-lg gap-1.5'
    case 'sm':
      return 'px-3 py-1.5 text-xs font-medium rounded-xl gap-1.5'
    case 'md':
      return 'px-4 py-2.5 text-sm font-semibold rounded-xl gap-2 min-h-[42px]'
    case 'lg':
      return 'px-6 py-3.5 text-base font-bold rounded-2xl gap-2.5 min-h-[48px]'
    default:
      return 'px-4 py-2.5 text-sm font-semibold rounded-xl gap-2'
  }
})

const loaderColor = computed(() => {
  if (props.variant === 'primary' || props.variant === 'danger') return 'white'
  if (props.variant === 'outline') return 'primary'
  return 'warm'
})

const loaderSize = computed(() => {
  if (props.size === 'xs' || props.size === 'sm') return 'xs'
  return 'sm'
})

function handleClick(e: MouseEvent) {
  if (isDisabled.value) {
    e.preventDefault()
    e.stopPropagation()
    return
  }
  emit('click', e)
}
</script>

<template>
  <button
    :type="type"
    :disabled="isDisabled"
    :aria-busy="loading ? 'true' : 'false'"
    :class="[
      'inline-flex items-center justify-center font-heading transition-all duration-150 select-none relative overflow-hidden',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50',
      'disabled:opacity-60 disabled:cursor-not-allowed disabled:pointer-events-none',
      variantClasses,
      sizeClasses,
      fullWidth ? 'w-full' : '',
    ]"
    @click="handleClick"
  >
    <!-- Spinner when loading -->
    <CircleLoader
      v-if="loading"
      :size="loaderSize"
      :color="loaderColor"
      class="shrink-0 transition-opacity"
    />

    <!-- Left Icon (when not loading) -->
    <component
      :is="icon"
      v-else-if="icon && iconPosition === 'left'"
      :size="size === 'xs' ? 12 : size === 'sm' ? 14 : size === 'lg' ? 18 : 16"
      class="shrink-0"
    />

    <!-- Button Text / Slot Content -->
    <span class="inline-flex items-center gap-1.5 truncate">
      <template v-if="loading && loadingText">
        {{ loadingText }}
      </template>
      <template v-else>
        <slot />
      </template>
    </span>

    <!-- Right Icon (when not loading) -->
    <component
      :is="icon"
      v-if="!loading && icon && iconPosition === 'right'"
      :size="size === 'xs' ? 12 : size === 'sm' ? 14 : size === 'lg' ? 18 : 16"
      class="shrink-0"
    />
  </button>
</template>
