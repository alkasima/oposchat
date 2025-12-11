<script setup lang="ts">
import { inject, computed } from 'vue'
import { cn } from '@/lib/utils'

interface TabsTriggerProps {
  value: string
  class?: string
}

const props = defineProps<TabsTriggerProps>()

const tabs = inject<any>('tabs')

const isActive = computed(() => tabs.selectedTab.value === props.value)

const handleClick = () => {
  tabs.selectTab(props.value)
}
</script>

<template>
  <button
    type="button"
    :class="cn(
      'inline-flex items-center justify-center whitespace-nowrap rounded-sm px-3 py-1.5 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
      isActive
        ? 'bg-background text-foreground shadow-sm'
        : 'text-muted-foreground hover:bg-muted',
      props.class
    )"
    @click="handleClick"
  >
    <slot />
  </button>
</template>
