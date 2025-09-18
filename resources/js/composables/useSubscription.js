import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useSubscription() {
    const page = usePage()
    const subscriptionData = ref(null)
    const loading = ref(false)
    const error = ref(null)

    // Get subscription status from page props (no API calls needed for MVP)
    const subscription = computed(() => {
        return page.props.subscription || {
            has_premium: false,
            subscription_status: 'none',
            on_trial: false,
            on_grace_period: false,
            current_plan_name: 'Free',
            usage: {}
        }
    })

    const hasPremium = computed(() => subscription.value.has_premium)
    const isOnTrial = computed(() => subscription.value.on_trial)
    const isOnGracePeriod = computed(() => subscription.value.on_grace_period)
    const subscriptionStatus = computed(() => subscription.value.subscription_status)
    const currentPlanName = computed(() => subscription.value.current_plan_name || 'Free')
    const usage = computed(() => subscription.value.usage || {})

    // Check if user has access to a feature
    const hasFeatureAccess = (feature) => {
        const featureUsage = usage.value[feature]
        if (!featureUsage) return true
        
        // If feature is unlimited, always allow
        if (featureUsage.unlimited) return true
        
        // If feature is not allowed, deny
        if (featureUsage.not_allowed) return false
        
        return featureUsage.remaining > 0
    }

    // Check if user is approaching usage limit
    const isApproachingLimit = (feature, threshold = 80) => {
        const featureUsage = usage.value[feature]
        if (!featureUsage || featureUsage.unlimited) return false
        
        return featureUsage.percentage >= threshold
    }

    // Get usage percentage for a feature
    const getUsagePercentage = (feature) => {
        const featureUsage = usage.value[feature]
        if (!featureUsage || featureUsage.unlimited) return 0
        
        return featureUsage.percentage || 0
    }

    // Get remaining usage for a feature
    const getRemainingUsage = (feature) => {
        const featureUsage = usage.value[feature]
        if (!featureUsage || featureUsage.unlimited) return Infinity
        
        return featureUsage.remaining || 0
    }

    // For MVP: Simple method that returns current subscription data
    const fetchSubscriptionStatus = async () => {
        // Just return the current subscription data from props
        return subscription.value
    }

    // Refresh subscription data (for MVP, just return current data)
    const refresh = () => {
        return Promise.resolve(subscription.value)
    }

    // Fetch fresh usage data from API
    const fetchUsageData = async () => {
        try {
            loading.value = true;
            
            const response = await fetch('/api/usage', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Failed to fetch usage data');
            }

            const data = await response.json();
            
            if (data.success && data.usage) {
                // Update the subscription data with fresh usage
                subscription.value = {
                    ...subscription.value,
                    usage: data.usage
                };
                return data.usage;
            }
            
            return null;
        } catch (error) {
            console.error('Error fetching usage data:', error);
            error.value = error.message;
            return null;
        } finally {
            loading.value = false;
        }
    }

    return {
        subscription,
        hasPremium,
        isOnTrial,
        isOnGracePeriod,
        subscriptionStatus,
        currentPlanName,
        usage,
        loading,
        error,
        hasFeatureAccess,
        isApproachingLimit,
        getUsagePercentage,
        getRemainingUsage,
        fetchSubscriptionStatus,
        refresh,
        fetchUsageData
    }
}