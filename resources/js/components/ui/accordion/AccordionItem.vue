<script setup lang="ts">
import { cn } from '@/lib/utils'

interface AccordionItemProps {
  value: string
  class?: string
}

const props = defineProps<AccordionItemProps>()
</script>

<template>
  <div :class="cn('border-b', props.class)">
    <slot />
  </div>
</template>
