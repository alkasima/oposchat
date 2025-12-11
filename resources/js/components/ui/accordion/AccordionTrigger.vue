<script setup lang="ts">
import { inject, computed } from 'vue'
import { ChevronDown } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

interface AccordionTriggerProps {
  class?: string
}

const props = defineProps<AccordionTriggerProps>()

// Get parent item value
const itemValue = inject<string>('accordion-item-value', '')
const accordion = inject<any>('accordion')

const isOpen = computed(() => accordion.isOpen(itemValue))

const toggle = () => {
  accordion.toggleItem(itemValue)
}
</script>

<template>
  <button
    type="button"
    :class="cn(
      'flex flex-1 items-center justify-between py-4 font-medium transition-all hover:underline [&[data-state=open]>svg]:rotate-180',
      props.class
    )"
    @click="toggle"
    :data-state="isOpen ? 'open' : 'closed'"
  >
    <slot />
    <ChevronDown class="h-4 w-4 shrink-0 transition-transform duration-200" />
  </button>
</template>
