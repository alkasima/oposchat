<script setup lang="ts">
import { provide, ref } from 'vue'

interface TabsProps {
  modelValue?: string
  defaultValue?: string
}

const props = withDefaults(defineProps<TabsProps>(), {
  defaultValue: ''
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const selectedTab = ref(props.modelValue || props.defaultValue)

const selectTab = (value: string) => {
  selectedTab.value = value
  emit('update:modelValue', value)
}

provide('tabs', {
  selectedTab,
  selectTab
})
</script>

<template>
  <div>
    <slot />
  </div>
</template>
