<script setup lang="ts">
import { provide, ref } from 'vue'

interface AccordionProps {
  type?: 'single' | 'multiple'
  collapsible?: boolean
  defaultValue?: string
}

const props = withDefaults(defineProps<AccordionProps>(), {
  type: 'single',
  collapsible: false
})

const openItems = ref<string[]>(props.defaultValue ? [props.defaultValue] : [])

const toggleItem = (value: string) => {
  if (props.type === 'single') {
    if (openItems.value.includes(value)) {
      if (props.collapsible) {
        openItems.value = []
      }
    } else {
      openItems.value = [value]
    }
  } else {
    const index = openItems.value.indexOf(value)
    if (index > -1) {
      openItems.value.splice(index, 1)
    } else {
      openItems.value.push(value)
    }
  }
}

const isOpen= (value: string) => {
  return openItems.value.includes(value)
}

provide('accordion', {
  openItems,
  toggleItem,
  isOpen
})
</script>

<template>
  <div>
    <slot />
  </div>
</template>
