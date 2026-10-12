<script setup lang="ts">
interface Props {
  className?: string
  width?: string
  height?: string
  rounded?: string
  circle?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  className: '',
  width: '',
  height: '',
  rounded: 'rounded-xl',
  circle: false,
})
</script>

<template>
  <div
    :class="[
      'skeleton shrink-0',
      circle ? '!rounded-full' : rounded,
      className
    ]"
    :style="{
      width: width || undefined,
      height: height || undefined,
    }"
  ></div>
</template>
