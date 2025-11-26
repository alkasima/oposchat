<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
      <!-- Success Icon -->
      <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 bg-green-100 dark:bg-green-900 rounded-full">
        <CheckCircle class="w-8 h-8 text-green-600 dark:text-green-400" />
      </div>

      <!-- Title -->
      <h2 class="text-2xl font-bold text-center text-gray-900 dark:text-white mb-2">
        {{ modalTitle }}
      </h2>

      <!-- Subscription Details -->
      <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6 space-y-2">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Plan:</span>
          <div class="flex items-center">
            <Crown class="w-4 h-4 text-yellow-500 mr-1" />
            <span class="font-semibold text-gray-900 dark:text-white">{{ planName }}</span>
          </div>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Status:</span>
          <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full">
            {{ statusLabelText }}
          </span>
        </div>
        <div v-if="formattedPrice" class="flex items-center justify-between">
          <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Price:</span>
          <span class="text-sm font-semibold text-gray-900 dark:text-white">
            {{ formattedPrice }}
            <span v-if="interval" class="text-xs font-normal text-gray-500 dark:text-gray-300">/ {{ interval }}</span>
          </span>
        </div>
        <div v-if="formattedNextBillingDate" class="flex items-center justify-between">
          <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Next billing date:</span>
          <span class="text-sm text-gray-900 dark:text-white">
            {{ formattedNextBillingDate }}
          </span>
        </div>
      </div>

      <!-- Description -->
      <p class="text-center text-gray-600 dark:text-gray-300 mb-6">
        {{ modalDescription }}
      </p>

      <!-- Action Buttons -->
      <div class="flex flex-col space-y-3">
        <Button 
          v-if="hasReceipt"
          as="a"
          :href="props.receiptUrl!"
          target="_blank"
          rel="noopener"
          variant="outline"
          class="w-full"
        >
          <Download class="w-4 h-4 mr-2" />
          Download Receipt
        </Button>
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
import { CheckCircle, Crown, Download, MessageSquare, Settings } from 'lucide-vue-next'

interface Props {
  show: boolean
  subscription?: {
    plan?: string
    status?: string
  }
  planName?: string
  title?: string
  description?: string
  statusLabel?: string
  priceAmount?: number | null
  priceCurrency?: string | null
  interval?: string | null
  nextBillingDate?: string | null
  receiptUrl?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  show: false,
  subscription: () => ({}),
  title: 'Thank You for Subscribing!',
  description: 'Your subscription is now active! You have full access to all premium features.',
  statusLabel: 'Active',
  priceAmount: null,
  priceCurrency: 'EUR',
  interval: null,
  nextBillingDate: null,
  receiptUrl: null,
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

const modalTitle = computed(() => props.title || 'Thank You for Subscribing!');
const modalDescription = computed(() => props.description || 'Your subscription is now active! You have full access to all premium features.');
const statusLabelText = computed(() => props.statusLabel || 'Active');

const formattedPrice = computed(() => {
  if (props.priceAmount === null || props.priceAmount === undefined) return null;
  const currency = (props.priceCurrency || 'EUR').toUpperCase();
  try {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency,
    }).format(props.priceAmount);
  } catch {
    return `${props.priceAmount} ${currency}`;
  }
});

const formattedNextBillingDate = computed(() => {
  if (!props.nextBillingDate) return null;
  const date = new Date(props.nextBillingDate);
  if (isNaN(date.getTime())) return null;
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
});

const hasReceipt = computed(() => !!props.receiptUrl);

const startChatting = () => {
  emit('close')
  router.visit('/dashboard')
}

const viewSubscription = () => {
  emit('close')
  router.visit('/settings/subscription')
}
</script>