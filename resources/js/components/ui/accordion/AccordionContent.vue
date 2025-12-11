<script setup lang="ts">
import { inject, computed, provide } from 'vue'
import { cn } from '@/lib/utils'

interface AccordionContentProps {
  class?: string
}

const props = defineProps<AccordionContentProps>()

// Get parent item value
const itemValue = inject<string>('accordion-item-value', '')
const accordion = inject<any>('accordion')

// Provide value to trigger
provide('accordion-item-value', itemValue)

const isOpen = computed(() => accordion.isOpen(itemValue))
</script>

<template>
  <div
    v-show="isOpen"
    :class="cn(
      'overflow-hidden text-sm transition-all data-[state=closed]:animate-accordion-up data-[state=open]:animate-accordion-down',
      props.class
    )"
    :data-state="isOpen ? 'open' : 'closed'"
  >
    <div class="pb-4 pt-0">
      <slot />
    </div>
  </div>
</template>
