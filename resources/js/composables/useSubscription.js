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
            usage: {}
        }
    })

    const hasPremium = computed(() => subscription.value.has_premium)
    const isOnTrial = computed(() => subscription.value.on_trial)
    const isOnGracePeriod = computed(() => subscription.value.on_grace_period)
    const subscriptionStatus = computed(() => subscription.value.subscription_status)
    const usage = computed(() => subscription.value.usage || {})

    // Check if user has access to a feature
    const hasFeatureAccess = (feature) => {
        if (hasPremium.value) return true
        
        const featureUsage = usage.value[feature]
        if (!featureUsage) return true
        
        return featureUsage.remaining > 0
    }

    // Check if user is approaching usage limit
    const isApproachingLimit = (feature, threshold = 80) => {
        if (hasPremium.value) return false
        
        const featureUsage = usage.value[feature]
        if (!featureUsage) return false
        
        return featureUsage.percentage >= threshold
    }

    // Get usage percentage for a feature
    const getUsagePercentage = (feature) => {
        if (hasPremium.value) return 0
        
        const featureUsage = usage.value[feature]
        return featureUsage ? featureUsage.percentage : 0
    }

    // Get remaining usage for a feature
    const getRemainingUsage = (feature) => {
        if (hasPremium.value) return Infinity
        
        const featureUsage = usage.value[feature]
        return featureUsage ? featureUsage.remaining : 0
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

    return {
        subscription,
        hasPremium,
        isOnTrial,
        isOnGracePeriod,
        subscriptionStatus,
        usage,
        loading,
        error,
        hasFeatureAccess,
        isApproachingLimit,
        getUsagePercentage,
        getRemainingUsage,
        fetchSubscriptionStatus,
        refresh
    }
}