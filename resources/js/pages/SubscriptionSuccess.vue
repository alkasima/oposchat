<script setup lang="ts">
import { CheckCircle, Crown, MessageSquare, Settings } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

const planName = 'Premium'
const statusLabel = 'Activo'

const startChatting = async () => {
  try {
    // Ask backend to refresh the user's plan/subscription before loading dashboard
    await fetch('/api/subscriptions/refresh-plan', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document
          .querySelector('meta[name="csrf-token"]')
          ?.getAttribute('content') || ''
      },
      credentials: 'same-origin'
    })
  } catch (e) {
    console.error('Failed to refresh plan before redirecting:', e)
  } finally {
    // Always perform a full page load of the dashboard
    window.location.href = '/dashboard'
  }
}

const viewSubscription = () => {
  window.location.href = '/settings/subscription'
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900">
    <div
      class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full mx-4 p-8 border border-gray-100 dark:border-gray-700"
    >
      <!-- Success Icon -->
      <div class="flex items-center justify-center w-16 h-16 mx-auto mb-6 bg-green-100 rounded-full">
        <CheckCircle class="w-9 h-9 text-green-600" />
      </div>

      <!-- Title -->
      <h1 class="text-2xl font-bold text-center text-gray-900 mb-2">
        ¡Suscripción actualizada!
      </h1>

      <!-- Subscription Details Card -->
      <div class="bg-gray-50 rounded-xl p-4 mb-6">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm font-medium text-gray-600">Plan:</span>
          <div class="flex items-center">
            <Crown class="w-4 h-4 text-yellow-500 mr-1" />
            <span class="font-semibold text-gray-900">{{ planName }}</span>
          </div>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-gray-600">Status:</span>
          <span
            class="px-3 py-1 text-xs font-semibold bg-green-100 text-green-800 rounded-full"
          >
            {{ statusLabel }}
          </span>
        </div>
      </div>

      <!-- Description -->
      <p class="text-center text-gray-600 text-sm mb-8">
        Tu suscripción está activa. Estamos actualizando la página...
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
