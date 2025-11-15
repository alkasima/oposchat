<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
      <!-- Success Icon -->
      <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 bg-green-100 dark:bg-green-900 rounded-full">
        <CheckCircle class="w-8 h-8 text-green-600 dark:text-green-400" />
      </div>

      <!-- Title -->
      <h2 class="text-2xl font-bold text-center text-gray-900 dark:text-white mb-2">
        Thank You for Subscribing!
      </h2>

      <!-- Subscription Details -->
      <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Plan:</span>
          <div class="flex items-center">
            <Crown class="w-4 h-4 text-yellow-500 mr-1" />
            <span class="font-semibold text-gray-900 dark:text-white">{{ planName }}</span>
          </div>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Status:</span>
          <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full">
            Active
          </span>
        </div>
      </div>

      <!-- Description -->
      <p class="text-center text-gray-600 dark:text-gray-300 mb-6">
        Your subscription is now active! You have full access to all premium features.
      </p>

      <!-- Action Buttons -->
      <div class="flex flex-col space-y-3">
        <Button @click="startChatting" class="w-full bg-blue-600 hover:bg-blue-700 text-white">
          <MessageSquare class="w-4 h-4 mr-2" />
          Start Chatting
        </Button>
        <Button @click="viewSubscription" variant="outline" class="w-full">
          <Settings class="w-4 h-4 mr-2" />
          View Subscription Details
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { CheckCircle, Crown, MessageSquare, Settings } from 'lucide-vue-next'

interface Props {
  show: boolean
  subscription?: {
    plan?: string
    status?: string
  }
  planName?: string
}

const props = withDefaults(defineProps<Props>(), {
  show: false,
  subscription: () => ({})
})

const emit = defineEmits<{
  close: []
}>()

const planName = computed(() => {
  if (props.planName) {
    return props.planName;
  }

  // Fallback: try subscription.plan or a generic label
  const raw = (props.subscription as any)?.subscription?.plan_name || props.subscription?.plan || 'Free';
  if (typeof raw === 'string' && raw.length > 0) {
    return raw;
  }
  return 'Free';
})

const startChatting = () => {
  emit('close')
  router.visit('/dashboard')
}

const viewSubscription = () => {
  emit('close')
  router.visit('/settings/subscription')
}
</script>