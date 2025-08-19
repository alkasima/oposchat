import { useToast } from '@/composables/useToast'

export interface ApiError {
  message: string
  code?: string
  details?: any
  status?: number
}

export interface StripeError extends ApiError {
  type?: string
  decline_code?: string
  payment_intent?: any
}

export class ErrorHandler {
  private toast = useToast()

  /**
   * Handle Stripe-specific errors with user-friendly messages
   */
  handleStripeError(error: any): string {
    const stripeErrorMessages: Record<string, string> = {
      // Card errors
      'card_declined': 'Your card was declined. Please try a different payment method.',
      'insufficient_funds': 'Your card has insufficient funds. Please try a different payment method.',
      'expired_card': 'Your card has expired. Please update your payment method.',
      'incorrect_cvc': 'Your card\'s security code is incorrect. Please check and try again.',
      'processing_error': 'An error occurred while processing your card. Please try again.',
      'incorrect_number': 'Your card number is incorrect. Please check and try again.',
      
      // Rate limiting
      'rate_limit': 'Too many requests. Please wait a moment and try again.',
      
      // API errors
      'api_key_expired': 'Payment system error. Please contact support.',
      'api_connection_error': 'Unable to connect to payment processor. Please try again.',
      'authentication_required': 'Payment authentication required. Please try again.',
      
      // Subscription errors
      'subscription_not_found': 'Subscription not found. Please contact support.',
      'invoice_not_found': 'Invoice not found. Please contact support.',
      'customer_not_found': 'Customer account not found. Please contact support.',
      
      // Generic
      'generic_decline': 'Your payment was declined. Please try a different payment method.',
      'invalid_request_error': 'Invalid payment request. Please try again.',
      'idempotency_error': 'Duplicate request detected. Please refresh and try again.'
    }

    // Extract error details from Stripe error
    let errorCode = error.code || error.decline_code || error.type
    let userMessage = error.message || 'An unexpected error occurred'

    // Use friendly message if available
    if (errorCode && stripeErrorMessages[errorCode]) {
      userMessage = stripeErrorMessages[errorCode]
    } else if (error.message) {
      // Try to make generic Stripe messages more user-friendly
      userMessage = this.makeStripeMessageFriendly(error.message)
    }

    return userMessage
  }

  /**
   * Make generic Stripe error messages more user-friendly
   */
  private makeStripeMessageFriendly(message: string): string {
    const friendlyReplacements: Record<string, string> = {
      'No such customer': 'Customer account not found. Please contact support.',
      'No such subscription': 'Subscription not found. Please contact support.',
      'No such price': 'Selected plan is no longer available. Please refresh and try again.',
      'Invalid API Key': 'Payment system error. Please contact support.',
      'The payment method is not available': 'This payment method is not supported. Please try a different one.',
      'Your card does not support this type of purchase': 'Your card does not support subscription payments. Please try a different card.',
      'Authentication Required': 'Additional authentication required. Please complete the payment verification.'
    }

    for (const [pattern, replacement] of Object.entries(friendlyReplacements)) {
      if (message.includes(pattern)) {
        return replacement
      }
    }

    return message
  }

  /**
   * Handle network errors with retry logic
   */
  handleNetworkError(error: any, retryCallback?: () => Promise<any>): string {
    const isNetworkError = !error.response && error.request
    const isTimeoutError = error.code === 'ECONNABORTED' || error.message?.includes('timeout')
    
    if (isNetworkError || isTimeoutError) {
      const message = 'Network error. Please check your connection and try again.'
      
      if (retryCallback) {
        this.toast.error(message, {
          title: 'Connection Error',
          persistent: true
        })
      }
      
      return message
    }

    return this.handleApiError(error)
  }

  /**
   * Handle general API errors
   */
  handleApiError(error: any): string {
    if (error.response) {
      const status = error.response.status
      const data = error.response.data

      // Handle specific HTTP status codes
      switch (status) {
        case 400:
          return data.message || 'Invalid request. Please check your input and try again.'
        case 401:
          return 'Authentication required. Please log in and try again.'
        case 403:
          return 'You don\'t have permission to perform this action.'
        case 404:
          return 'The requested resource was not found.'
        case 422:
          // Validation errors
          if (data.errors) {
            const firstError = Object.values(data.errors)[0]
            return Array.isArray(firstError) ? firstError[0] : firstError
          }
          return data.message || 'Validation error. Please check your input.'
        case 429:
          return 'Too many requests. Please wait a moment and try again.'
        case 500:
          return 'Server error. Please try again later or contact support.'
        case 502:
        case 503:
        case 504:
          return 'Service temporarily unavailable. Please try again later.'
        default:
          return data.message || `Server error (${status}). Please try again later.`
      }
    }

    return error.message || 'An unexpected error occurred. Please try again.'
  }

  /**
   * Show appropriate toast notification for error
   */
  showErrorToast(error: any, context: 'payment' | 'subscription' | 'general' = 'general') {
    let message: string
    let title: string

    if (this.isStripeError(error)) {
      message = this.handleStripeError(error)
      title = context === 'payment' ? 'Payment Error' : 'Subscription Error'
    } else if (this.isNetworkError(error)) {
      message = this.handleNetworkError(error)
      title = 'Connection Error'
    } else {
      message = this.handleApiError(error)
      title = context === 'payment' ? 'Payment Error' : 
              context === 'subscription' ? 'Subscription Error' : 'Error'
    }

    this.toast.error(message, {
      title,
      persistent: true
    })

    return message
  }

  /**
   * Show success toast for various contexts
   */
  showSuccessToast(message: string, context: 'payment' | 'subscription' | 'general' = 'general') {
    const titles = {
      payment: 'Payment Successful',
      subscription: 'Subscription Updated',
      general: 'Success'
    }

    this.toast.success(message, {
      title: titles[context],
      duration: 6000
    })
  }

  /**
   * Check if error is a Stripe error
   */
  private isStripeError(error: any): boolean {
    return error.type === 'StripeError' || 
           error.code?.startsWith('card_') || 
           error.decline_code ||
           (error.response?.data?.error_code && error.response.data.error_code.includes('stripe'))
  }

  /**
   * Check if error is a network error
   */
  private isNetworkError(error: any): boolean {
    return !error.response && error.request
  }

  /**
   * Extract error details for logging
   */
  extractErrorDetails(error: any): ApiError {
    return {
      message: error.message || 'Unknown error',
      code: error.code || error.decline_code || error.type,
      status: error.response?.status,
      details: {
        url: error.config?.url,
        method: error.config?.method,
        data: error.response?.data,
        stack: error.stack
      }
    }
  }

  /**
   * Log error for debugging (in development) or monitoring (in production)
   */
  logError(error: any, context?: string) {
    const errorDetails = this.extractErrorDetails(error)
    
    if (import.meta.env.DEV) {
      console.error(`[${context || 'Error'}]`, errorDetails)
    }
    
    // In production, you might want to send to error monitoring service
    // Example: Sentry.captureException(error, { extra: errorDetails })
  }
}

export const errorHandler = new ErrorHandler()