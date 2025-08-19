import axios from 'axios';

// Configure axios defaults
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.withCredentials = true; // Send cookies with requests

// Add CSRF token to requests
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
    console.warn('CSRF token not found in meta tags');
}

/**
 * Frontend service for Stripe subscription management
 * Handles API calls to the Laravel backend for subscription operations
 */
class StripeService {
    constructor() {
        this.baseUrl = '/api/subscriptions';
        this.loading = false;
        this.error = null;
    }

    /**
     * Get current user's subscription status and details
     * @returns {Promise<Object>} Subscription data
     */
    async getSubscriptionStatus() {
        this.setLoading(true);
        this.clearError();

        try {
            const response = await axios.get(this.baseUrl);
            
            if (!response.data.success) {
                throw new Error(response.data.message || 'Failed to fetch subscription status');
            }

            return response.data.data;
        } catch (error) {
            const errorMessage = this.extractErrorMessage(error);
            this.setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            this.setLoading(false);
        }
    }

    /**
     * Create a Stripe checkout session for subscription
     * @param {string} priceId - Stripe price ID for the subscription plan
     * @param {string} successUrl - URL to redirect to after successful payment
     * @param {string} cancelUrl - URL to redirect to if payment is canceled
     * @returns {Promise<Object>} Checkout session data with URL
     */
    async createCheckoutSession(priceId, successUrl, cancelUrl) {
        this.setLoading(true);
        this.clearError();

        try {
            const response = await axios.post(`${this.baseUrl}/checkout`, {
                price_id: priceId,
                success_url: successUrl,
                cancel_url: cancelUrl
            });

            if (!response.data.success) {
                throw new Error(response.data.message || 'Failed to create checkout session');
            }

            return response.data.data;
        } catch (error) {
            const errorMessage = this.extractErrorMessage(error);
            this.setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            this.setLoading(false);
        }
    }

    /**
     * Create a customer portal session for subscription management
     * @param {string} returnUrl - URL to return to after managing subscription
     * @returns {Promise<Object>} Portal session data with URL
     */
    async createPortalSession(returnUrl) {
        this.setLoading(true);
        this.clearError();

        try {
            const response = await axios.post(`${this.baseUrl}/manage`, {
                return_url: returnUrl
            });

            if (!response.data.success) {
                throw new Error(response.data.message || 'Failed to create portal session');
            }

            return response.data.data;
        } catch (error) {
            const errorMessage = this.extractErrorMessage(error);
            this.setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            this.setLoading(false);
        }
    }

    /**
     * Cancel the current user's subscription
     * @returns {Promise<Object>} Updated subscription data
     */
    async cancelSubscription() {
        this.setLoading(true);
        this.clearError();

        try {
            const response = await axios.delete(`${this.baseUrl}/cancel`);

            if (!response.data.success) {
                throw new Error(response.data.message || 'Failed to cancel subscription');
            }

            return response.data.data;
        } catch (error) {
            const errorMessage = this.extractErrorMessage(error);
            this.setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            this.setLoading(false);
        }
    }

    /**
     * Redirect to Stripe Checkout
     * @param {string} priceId - Stripe price ID for the subscription plan
     * @param {string} successUrl - URL to redirect to after successful payment (optional)
     * @param {string} cancelUrl - URL to redirect to if payment is canceled (optional)
     */
    async redirectToCheckout(priceId, successUrl = null, cancelUrl = null) {
        // Use current page as default URLs if not provided
        const defaultSuccessUrl = successUrl || `${window.location.origin}/dashboard?success=true`;
        const defaultCancelUrl = cancelUrl || `${window.location.origin}/settings/subscription?canceled=true`;

        try {
            const checkoutData = await this.createCheckoutSession(
                priceId, 
                defaultSuccessUrl, 
                defaultCancelUrl
            );

            // Redirect to Stripe Checkout
            window.location.href = checkoutData.checkout_url;
        } catch (error) {
            console.error('Failed to redirect to checkout:', error);
            throw error;
        }
    }

    /**
     * Redirect to Stripe Customer Portal
     * @param {string} returnUrl - URL to return to after managing subscription (optional)
     */
    async redirectToPortal(returnUrl = null) {
        const defaultReturnUrl = returnUrl || `${window.location.origin}/settings/subscription`;

        try {
            const portalData = await this.createPortalSession(defaultReturnUrl);

            // Redirect to Stripe Customer Portal
            window.location.href = portalData.portal_url;
        } catch (error) {
            console.error('Failed to redirect to portal:', error);
            throw error;
        }
    }

    /**
     * Set loading state
     * @param {boolean} loading - Loading state
     */
    setLoading(loading) {
        this.loading = loading;
    }

    /**
     * Get current loading state
     * @returns {boolean} Current loading state
     */
    isLoading() {
        return this.loading;
    }

    /**
     * Set error state
     * @param {string} error - Error message
     */
    setError(error) {
        this.error = error;
    }

    /**
     * Clear error state
     */
    clearError() {
        this.error = null;
    }

    /**
     * Get current error
     * @returns {string|null} Current error message
     */
    getError() {
        return this.error;
    }

    /**
     * Extract user-friendly error message from axios error
     * @param {Error} error - Axios error object
     * @returns {string} User-friendly error message
     */
    extractErrorMessage(error) {
        if (error.response) {
            // Server responded with error status
            const data = error.response.data;
            
            if (data.message) {
                return data.message;
            }
            
            if (data.error) {
                return data.error;
            }

            // Handle validation errors
            if (data.errors) {
                const firstError = Object.values(data.errors)[0];
                return Array.isArray(firstError) ? firstError[0] : firstError;
            }

            return `Server error: ${error.response.status}`;
        } else if (error.request) {
            // Network error
            return 'Network error. Please check your connection and try again.';
        } else {
            // Other error
            return error.message || 'An unexpected error occurred';
        }
    }

    /**
     * Check if user has an active subscription
     * @returns {Promise<boolean>} Whether user has active subscription
     */
    async hasActiveSubscription() {
        try {
            const subscriptionData = await this.getSubscriptionStatus();
            return !!(subscriptionData.subscription && subscriptionData.subscription.is_active);
        } catch (error) {
            console.error('Failed to check subscription status:', error);
            return false;
        }
    }

    /**
     * Get subscription plan information
     * @returns {Promise<Object|null>} Subscription plan data or null
     */
    async getSubscriptionPlan() {
        try {
            const subscriptionData = await this.getSubscriptionStatus();
            return subscriptionData.subscription || null;
        } catch (error) {
            console.error('Failed to get subscription plan:', error);
            return null;
        }
    }

    /**
     * Get available subscription plans and configuration
     * @returns {Promise<Object>} Plans configuration data
     */
    async getPlans() {
        this.setLoading(true);
        this.clearError();

        try {
            const response = await axios.get(`${this.baseUrl}/plans`);
            
            if (!response.data.success) {
                throw new Error(response.data.message || 'Failed to fetch subscription plans');
            }

            return response.data.data;
        } catch (error) {
            const errorMessage = this.extractErrorMessage(error);
            this.setError(errorMessage);
            throw new Error(errorMessage);
        } finally {
            this.setLoading(false);
        }
    }


}

export default new StripeService();