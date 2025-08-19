<template>
  <div class="fixed top-4 right-4 z-50 space-y-2">
    <Toast
      v-for="toast in toasts"
      :key="toast.id"
      v-bind="toast"
      @close="removeToast"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import Toast, { type ToastProps } from './Toast.vue'
import { useToast } from '@/composables/useToast'

const { toasts, removeToast } = useToast()
</script>