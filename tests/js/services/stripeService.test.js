import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import axios from 'axios'
import stripeService from '@/services/stripeService'

// Mock axios
vi.mock('axios')

describe('StripeService', () => {
  beforeEach(() => {
    // Reset mocks before each test
    vi.clearAllMocks()
    stripeService.clearError()
    stripeService.setLoading(false)
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  describe('getSubscriptionStatus', () => {
    it('should fetch subscription status successfully', async () => {
      const mockResponse = {
        data: {
          success: true,
          data: {
            subscription: {
              id: 1,
              status: 'active',
              is_active: true,
              stripe_price_id: 'price_123'
            },
            status: 'active',
            plan: 'price_123'
          }
        }
      }

      axios.get.mockResolvedValue(mockResponse)

      const result = await stripeService.getSubscriptionStatus()

      expect(axios.get).toHaveBeenCalledWith('/api/subscriptions')
      expect(result).toEqual(mockResponse.data.data)
      expect(stripeService.isLoading()).toBe(false)
      expect(stripeService.getError()).toBeNull()
    })

    it('should handle API error response', async () => {
      const mockError = {
        response: {
          data: {
            success: false,
            message: 'Subscription not found'
          }
        }
      }

      axios.get.mockRejectedValue(mockError)

      await expect(stripeService.getSubscriptionStatus()).rejects.toThrow('Subscription not found')
      expect(stripeService.getError()).toBe('Subscription not found')
      expect(stripeService.isLoading()).toBe(false)
    })

    it('should handle network error', async () => {
      const mockError = {
        request: {}
      }

      axios.get.mockRejectedValue(mockError)

      await expect(stripeService.getSubscriptionStatus()).rejects.toThrow('Network error. Please check your connection and try again.')
      expect(stripeService.getError()).toBe('Network error. Please check your connection and try again.')
    })

    it('should set loading state during request', async () => {
      const mockResponse = {
        data: {
          success: true,
          data: { subscription: null }
        }
      }

      // Create a promise that we can control
      let resolvePromise
      const promise = new Promise((resolve) => {
        resolvePromise = resolve
      })

      axios.get.mockReturnValue(promise)

      // Start the request
      const requestPromise = stripeService.getSubscriptionStatus()

      // Check loading state is true
      expect(stripeService.isLoading()).toBe(true)

      // Resolve the promise
      resolvePromise(mockResponse)
      await requestPromise

      // Check loading state is false
      expect(stripeService.isLoading()).toBe(false)
    })
  })

  describe('createCheckoutSession', () => {
    it('should create checkout session successfully', async () => {
      const mockResponse = {
        data: {
          success: true,
          data: {
            checkout_url: 'https://checkout.stripe.com/session_123',
            session_id: 'cs_123'
          }
        }
      }

      axios.post.mockResolvedValue(mockResponse)

      const result = await stripeService.createCheckoutSession(
        'price_123',
        'http://localhost/success',
        'http://localhost/cancel'
      )

      expect(axios.post).toHaveBeenCalledWith('/api/subscriptions/checkout', {
        price_id: 'price_123',
        success_url: 'http://localhost/success',
        cancel_url: 'http://localhost/cancel'
      })
      expect(result).toEqual(mockResponse.data.data)
    })

    it('should handle checkout creation error', async () => {
      const mockError = {
        response: {
          data: {
            success: false,
            message: 'User already has an active subscription'
          }
        }
      }

      axios.post.mockRejectedValue(mockError)

      await expect(stripeService.createCheckoutSession('price_123', 'success', 'cancel'))
        .rejects.toThrow('User already has an active subscription')
    })
  })

  describe('createPortalSession', () => {
    it('should create portal session successfully', async () => {
      const mockResponse = {
        data: {
          success: true,
          data: {
            portal_url: 'https://billing.stripe.com/session_123'
          }
        }
      }

      axios.post.mockResolvedValue(mockResponse)

      const result = await stripeService.createPortalSession('http://localhost/return')

      expect(axios.post).toHaveBeenCalledWith('/api/subscriptions/manage', {
        return_url: 'http://localhost/return'
      })
      expect(result).toEqual(mockResponse.data.data)
    })

    it('should handle portal creation error', async () => {
      const mockError = {
        response: {
          data: {
            success: false,
            message: 'No Stripe customer found for user'
          }
        }
      }

      axios.post.mockRejectedValue(mockError)

      await expect(stripeService.createPortalSession('http://localhost/return'))
        .rejects.toThrow('No Stripe customer found for user')
    })
  })

  describe('cancelSubscription', () => {
    it('should cancel subscription successfully', async () => {
      const mockResponse = {
        data: {
          success: true,
          data: {
            subscription: {
              id: 1,
              status: 'active',
              cancel_at_period_end: true
            }
          }
        }
      }

      axios.delete.mockResolvedValue(mockResponse)

      const result = await stripeService.cancelSubscription()

      expect(axios.delete).toHaveBeenCalledWith('/api/subscriptions/cancel')
      expect(result).toEqual(mockResponse.data.data)
    })

    it('should handle cancellation error', async () => {
      const mockError = {
        response: {
          data: {
            success: false,
            message: 'No active subscription found'
          }
        }
      }

      axios.delete.mockRejectedValue(mockError)

      await expect(stripeService.cancelSubscription())
        .rejects.toThrow('No active subscription found')
    })
  })

  describe('redirectToCheckout', () => {
    it('should redirect to checkout with custom URLs', async () => {
      const mockResponse = {
        data: {
          success: true,
          data: {
            checkout_url: 'https://checkout.stripe.com/session_123'
          }
        }
      }

      axios.post.mockResolvedValue(mockResponse)

      // Mock window.location.href setter
      const originalLocation = window.location
      delete window.location
      window.location = { ...originalLocation, href: '' }

      await stripeService.redirectToCheckout(
        'price_123',
        'http://localhost/success',
        'http://localhost/cancel'
      )

      expect(axios.post).toHaveBeenCalledWith('/api/subscriptions/checkout', {
        price_id: 'price_123',
        success_url: 'http://localhost/success',
        cancel_url: 'http://localhost/cancel'
      })
      expect(window.location.href).toBe('https://checkout.stripe.com/session_123')

      // Restore window.location
      window.location = originalLocation
    })

    it('should use default URLs when not provided', async () => {
      const mockResponse = {
        data: {
          success: true,
          data: {
            checkout_url: 'https://checkout.stripe.com/session_123'
          }
        }
      }

      axios.post.mockResolvedValue(mockResponse)

      await stripeService.redirectToCheckout('price_123')

      expect(axios.post).toHaveBeenCalledWith('/api/subscriptions/checkout', {
        price_id: 'price_123',
        success_url: 'http://localhost/settings/subscription?success=true',
        cancel_url: 'http://localhost/settings/subscription?canceled=true'
      })
    })
  })

  describe('redirectToPortal', () => {
    it('should redirect to portal with custom return URL', async () => {
      const mockResponse = {
        data: {
          success: true,
          data: {
            portal_url: 'https://billing.stripe.com/session_123'
          }
        }
      }

      axios.post.mockResolvedValue(mockResponse)

      // Mock window.location.href setter
      const originalLocation = window.location
      delete window.location
      window.location = { ...originalLocation, href: '' }

      await stripeService.redirectToPortal('http://localhost/custom-return')

      expect(axios.post).toHaveBeenCalledWith('/api/subscriptions/manage', {
        return_url: 'http://localhost/custom-return'
      })
      expect(window.location.href).toBe('https://billing.stripe.com/session_123')

      // Restore window.location
      window.location = originalLocation
    })

    it('should use default return URL when not provided', async () => {
      const mockResponse = {
        data: {
          success: true,
          data: {
            portal_url: 'https://billing.stripe.com/session_123'
          }
        }
      }

      axios.post.mockResolvedValue(mockResponse)

      await stripeService.redirectToPortal()

      expect(axios.post).toHaveBeenCalledWith('/api/subscriptions/manage', {
        return_url: 'http://localhost/settings/subscription'
      })
    })
  })

  describe('hasActiveSubscription', () => {
    it('should return true for active subscription', async () => {
      const mockResponse = {
        data: {
          success: true,
          data: {
            subscription: {
              is_active: true
            }
          }
        }
      }

      axios.get.mockResolvedValue(mockResponse)

      const result = await stripeService.hasActiveSubscription()

      expect(result).toBe(true)
    })

    it('should return false for inactive subscription', async () => {
      const mockResponse = {
        data: {
          success: true,
          data: {
            subscription: {
              is_active: false
            }
          }
        }
      }

      axios.get.mockResolvedValue(mockResponse)

      const result = await stripeService.hasActiveSubscription()

      expect(result).toBe(false)
    })

    it('should return false when no subscription exists', async () => {
      const mockResponse = {
        data: {
          success: true,
          data: {
            subscription: null
          }
        }
      }

      axios.get.mockResolvedValue(mockResponse)

      const result = await stripeService.hasActiveSubscription()

      expect(result).toBe(false)
    })

    it('should return false on error', async () => {
      axios.get.mockRejectedValue(new Error('Network error'))

      const result = await stripeService.hasActiveSubscription()

      expect(result).toBe(false)
    })
  })

  describe('getSubscriptionPlan', () => {
    it('should return subscription plan data', async () => {
      const mockSubscription = {
        id: 1,
        status: 'active',
        stripe_price_id: 'price_123'
      }

      const mockResponse = {
        data: {
          success: true,
          data: {
            subscription: mockSubscription
          }
        }
      }

      axios.get.mockResolvedValue(mockResponse)

      const result = await stripeService.getSubscriptionPlan()

      expect(result).toEqual(mockSubscription)
    })

    it('should return null when no subscription exists', async () => {
      const mockResponse = {
        data: {
          success: true,
          data: {
            subscription: null
          }
        }
      }

      axios.get.mockResolvedValue(mockResponse)

      const result = await stripeService.getSubscriptionPlan()

      expect(result).toBeNull()
    })

    it('should return null on error', async () => {
      axios.get.mockRejectedValue(new Error('Network error'))

      const result = await stripeService.getSubscriptionPlan()

      expect(result).toBeNull()
    })
  })

  describe('error handling', () => {
    it('should extract error message from response data', () => {
      const error = {
        response: {
          data: {
            message: 'Custom error message'
          }
        }
      }

      const message = stripeService.extractErrorMessage(error)
      expect(message).toBe('Custom error message')
    })

    it('should extract error from validation errors', () => {
      const error = {
        response: {
          data: {
            errors: {
              price_id: ['The price id field is required.']
            }
          }
        }
      }

      const message = stripeService.extractErrorMessage(error)
      expect(message).toBe('The price id field is required.')
    })

    it('should handle server error status', () => {
      const error = {
        response: {
          status: 500,
          data: {}
        }
      }

      const message = stripeService.extractErrorMessage(error)
      expect(message).toBe('Server error: 500')
    })

    it('should handle network error', () => {
      const error = {
        request: {}
      }

      const message = stripeService.extractErrorMessage(error)
      expect(message).toBe('Network error. Please check your connection and try again.')
    })

    it('should handle generic error', () => {
      const error = {
        message: 'Generic error'
      }

      const message = stripeService.extractErrorMessage(error)
      expect(message).toBe('Generic error')
    })
  })

  describe('state management', () => {
    it('should manage loading state', () => {
      expect(stripeService.isLoading()).toBe(false)

      stripeService.setLoading(true)
      expect(stripeService.isLoading()).toBe(true)

      stripeService.setLoading(false)
      expect(stripeService.isLoading()).toBe(false)
    })

    it('should manage error state', () => {
      expect(stripeService.getError()).toBeNull()

      stripeService.setError('Test error')
      expect(stripeService.getError()).toBe('Test error')

      stripeService.clearError()
      expect(stripeService.getError()).toBeNull()
    })
  })

  describe('getPlans', () => {
    it('should fetch subscription plans successfully', async () => {
      const mockResponse = {
        data: {
          success: true,
          data: {
            plans: {
              pro: {
                name: 'Pro',
                price: 19,
                currency: 'usd',
                interval: 'month',
                stripe_price_id: 'price_pro123',
                features: ['Unlimited conversations', 'Priority support'],
                popular: true
              }
            },
            free_plan: {
              name: 'Free',
              price: 0,
              currency: 'usd',
              interval: 'forever'
            },
            feature_comparison: []
          }
        }
      }

      axios.get.mockResolvedValue(mockResponse)

      const result = await stripeService.getPlans()

      expect(axios.get).toHaveBeenCalledWith('/api/subscriptions/plans')
      expect(result).toEqual(mockResponse.data.data)
      expect(stripeService.isLoading()).toBe(false)
      expect(stripeService.getError()).toBeNull()
    })

    it('should handle API error response', async () => {
      const mockError = {
        response: {
          data: {
            success: false,
            message: 'Failed to fetch plans'
          }
        }
      }

      axios.get.mockRejectedValue(mockError)

      await expect(stripeService.getPlans())
        .rejects.toThrow('Failed to fetch plans')

      expect(stripeService.getError()).toBe('Failed to fetch plans')
    })
  })
})