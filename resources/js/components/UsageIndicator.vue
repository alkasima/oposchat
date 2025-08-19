<template>
  <div v-if="!hasPremium && usage" class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 mb-4">
    <div class="flex items-center justify-between mb-2">
      <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
        {{ featureName }} Usage
      </span>
      <span class="text-sm text-gray-500 dark:text-gray-400">
        {{ usage.usage }} / {{ usage.limit }}
      </span>
    </div>
    
    <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2 mb-2">
      <div
        class="h-2 rounded-full transition-all duration-300"
        :class="getProgressBarClass()"
        :style="{ width: `${Math.min(usage.percentage, 100)}%` }"
      ></div>
    </div>
    
    <div class="flex items-center justify-between text-xs">
      <span class="text-gray-500 dark:text-gray-400">
        {{ usage.remaining }} remaining
      </span>
      <span v-if="usage.percentage >= 80" class="text-orange-600 dark:text-orange-400 font-medium">
        {{ usage.percentage >= 100 ? 'Limit reached' : 'Approaching limit' }}
      </span>
    </div>
    
    <div v-if="usage.percentage >= 90" class="mt-2 text-xs text-center">
      <button
        @click="$emit('upgrade')"
        class="text-blue-600 dark:text-blue-400 hover:underline font-medium"
      >
        Upgrade for unlimited access
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  feature: {
    type: String,
    required: true
  },
  featureName: {
    type: String,
    required: true
  },
  usage: {
    type: Object,
    default: null
  },
  hasPremium: {
    type: Boolean,
    default: false
  }
})

defineEmits(['upgrade'])

const getProgressBarClass = () => {
  if (!props.usage) return 'bg-gray-300'
  
  const percentage = props.usage.percentage
  
  if (percentage >= 100) return 'bg-red-500'
  if (percentage >= 90) return 'bg-orange-500'
  if (percentage >= 70) return 'bg-yellow-500'
  return 'bg-green-500'
}
</script>