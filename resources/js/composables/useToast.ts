import { ref, reactive } from 'vue'
import type { ToastProps } from '@/components/ui/toast/Toast.vue'

interface Toast extends ToastProps {
  id: string
}

const toasts = ref<Toast[]>([])

let toastIdCounter = 0

export function useToast() {
  const addToast = (toast: Omit<Toast, 'id'>) => {
    const id = `toast-${++toastIdCounter}`
    const newToast: Toast = {
      id,
      ...toast
    }
    
    toasts.value.push(newToast)
    return id
  }

  const removeToast = (id: string) => {
    const index = toasts.value.findIndex(toast => toast.id === id)
    if (index > -1) {
      toasts.value.splice(index, 1)
    }
  }

  const clearToasts = () => {
    toasts.value = []
  }

  // Convenience methods for different toast types
  const success = (message: string, options?: Partial<Omit<Toast, 'id' | 'message' | 'variant'>>) => {
    return addToast({
      message,
      variant: 'success',
      ...options
    })
  }

  const error = (message: string, options?: Partial<Omit<Toast, 'id' | 'message' | 'variant'>>) => {
    return addToast({
      message,
      variant: 'error',
      persistent: true, // Error toasts should be persistent by default
      ...options
    })
  }

  const warning = (message: string, options?: Partial<Omit<Toast, 'id' | 'message' | 'variant'>>) => {
    return addToast({
      message,
      variant: 'warning',
      ...options
    })
  }

  const info = (message: string, options?: Partial<Omit<Toast, 'id' | 'message' | 'variant'>>) => {
    return addToast({
      message,
      variant: 'info',
      ...options
    })
  }

  // Payment-specific toast methods
  const paymentSuccess = (message?: string) => {
    return success(message || 'Payment processed successfully!', {
      title: 'Payment Successful',
      duration: 6000
    })
  }

  const paymentError = (message?: string) => {
    return error(message || 'Payment failed. Please try again.', {
      title: 'Payment Failed',
      persistent: true
    })
  }

  const subscriptionSuccess = (message?: string) => {
    return success(message || 'Subscription updated successfully!', {
      title: 'Subscription Updated',
      duration: 6000
    })
  }

  const subscriptionError = (message?: string) => {
    return error(message || 'Failed to update subscription. Please try again.', {
      title: 'Subscription Error',
      persistent: true
    })
  }

  const networkError = () => {
    return error('Network error. Please check your connection and try again.', {
      title: 'Connection Error',
      persistent: true
    })
  }

  return {
    toasts,
    addToast,
    removeToast,
    clearToasts,
    success,
    error,
    warning,
    info,
    paymentSuccess,
    paymentError,
    subscriptionSuccess,
    subscriptionError,
    networkError
  }
}