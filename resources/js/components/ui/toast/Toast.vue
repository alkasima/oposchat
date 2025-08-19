<template>
  <Teleport to="body">
    <div
      v-if="visible"
      :class="[
        'fixed top-4 right-4 z-50 max-w-sm w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg transform transition-all duration-300 ease-in-out',
        visible ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0',
        variantClasses[variant]
      ]"
    >
      <div class="p-4">
        <div class="flex items-start">
          <div class="flex-shrink-0">
            <component :is="iconComponent" :class="iconClasses[variant]" class="w-5 h-5" />
          </div>
          <div class="ml-3 w-0 flex-1">
            <p v-if="title" :class="titleClasses[variant]" class="text-sm font-medium">
              {{ title }}
            </p>
            <p :class="[messageClasses[variant], 'text-sm', { 'mt-1': title }]">
              {{ message }}
            </p>
          </div>
          <div class="ml-4 flex-shrink-0 flex">
            <button
              @click="close"
              :class="closeButtonClasses[variant]"
              class="inline-flex rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-offset-2"
            >
              <span class="sr-only">Close</span>
              <X class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { CheckCircle, AlertCircle, AlertTriangle, Info, X } from 'lucide-vue-next'

export interface ToastProps {
  id?: string
  title?: string
  message: string
  variant?: 'success' | 'error' | 'warning' | 'info'
  duration?: number
  persistent?: boolean
}

const props = withDefaults(defineProps<ToastProps>(), {
  variant: 'info',
  duration: 5000,
  persistent: false
})

const emit = defineEmits<{
  close: [id?: string]
}>()

const visible = ref(false)

const iconComponent = computed(() => {
  switch (props.variant) {
    case 'success':
      return CheckCircle
    case 'error':
      return AlertCircle
    case 'warning':
      return AlertTriangle
    default:
      return Info
  }
})

const variantClasses = {
  success: 'border-green-200 dark:border-green-800',
  error: 'border-red-200 dark:border-red-800',
  warning: 'border-yellow-200 dark:border-yellow-800',
  info: 'border-blue-200 dark:border-blue-800'
}

const iconClasses = {
  success: 'text-green-500',
  error: 'text-red-500',
  warning: 'text-yellow-500',
  info: 'text-blue-500'
}

const titleClasses = {
  success: 'text-green-900 dark:text-green-100',
  error: 'text-red-900 dark:text-red-100',
  warning: 'text-yellow-900 dark:text-yellow-100',
  info: 'text-blue-900 dark:text-blue-100'
}

const messageClasses = {
  success: 'text-green-700 dark:text-green-300',
  error: 'text-red-700 dark:text-red-300',
  warning: 'text-yellow-700 dark:text-yellow-300',
  info: 'text-blue-700 dark:text-blue-300'
}

const closeButtonClasses = {
  success: 'text-green-500 hover:text-green-600 focus:ring-green-500',
  error: 'text-red-500 hover:text-red-600 focus:ring-red-500',
  warning: 'text-yellow-500 hover:text-yellow-600 focus:ring-yellow-500',
  info: 'text-blue-500 hover:text-blue-600 focus:ring-blue-500'
}

const close = () => {
  visible.value = false
  setTimeout(() => {
    emit('close', props.id)
  }, 300) // Wait for animation to complete
}

onMounted(() => {
  visible.value = true
  
  if (!props.persistent && props.duration > 0) {
    setTimeout(() => {
      close()
    }, props.duration)
  }
})
</script>