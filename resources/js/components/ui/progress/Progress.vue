<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '@/lib/utils'

interface ProgressProps {
  modelValue?: number
  class?: string
}

const props = withDefaults(defineProps<ProgressProps>(), {
  modelValue: 0
})

const percentage = computed(() => {
  return Math.min(100, Math.max(0, props.modelValue))
})
</script>

<template>
  <div
    :class="cn(
      'relative h-4 w-full overflow-hidden rounded-full bg-secondary',
      props.class
    )"
  >
    <div
      class="h-full w-full flex-1 bg-primary transition-all"
      :style="{ transform: `translateX(-${100 - percentage}%)` }"
    />
  </div>
</template>
