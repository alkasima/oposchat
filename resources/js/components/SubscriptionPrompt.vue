<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ title }}
        </h3>
        <button
          @click="$emit('close')"
          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="mb-6">
        <p class="text-gray-600 dark:text-gray-300 mb-4">
          {{ message }}
        </p>

        <div v-if="usageInfo" class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ usageInfo.feature_name }}
            </span>
            <span class="text-sm text-gray-500 dark:text-gray-400">
              {{ usageInfo.usage }} / {{ usageInfo.limit }}
            </span>
          </div>
          <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2">
            <div
              class="bg-red-500 h-2 rounded-full"
              :style="{ width: `${usageInfo.percentage}%` }"
            ></div>
          </div>
        </div>

        <div class="space-y-2">
          <div class="flex items-center text-sm text-gray-600 dark:text-gray-300">
            <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            Unlimited access to all features
          </div>
          <div class="flex items-center text-sm text-gray-600 dark:text-gray-300">
            <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            Priority support
          </div>
          <div class="flex items-center text-sm text-gray-600 dark:text-gray-300">
            <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            Advanced analytics
          </div>
        </div>
      </div>

      <div class="flex space-x-3">
        <button
          @click="$emit('upgrade')"
          class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors"
        >
          Upgrade to Premium
        </button>
        <button
          @click="$emit('close')"
          class="px-4 py-2 text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white transition-colors"
        >
          Maybe Later
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  show: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: 'Premium Feature'
  },
  message: {
    type: String,
    default: 'This feature requires a premium subscription to access.'
  },
  usageInfo: {
    type: Object,
    default: null
  }
})

defineEmits(['close', 'upgrade'])
</script>