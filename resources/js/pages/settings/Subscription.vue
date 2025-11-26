<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import SubscriptionSuccessModal from '@/components/SubscriptionSuccessModal.vue';
import PlanChangeConfirmationModal from '@/components/PlanChangeConfirmationModal.vue';
import stripeService from '@/services/stripeService';
import { 
    Check, 
    Crown, 
    Zap, 
    Clock,
    Star,
    Loader2,
    AlertCircle,
    Settings
} from 'lucide-vue-next';

interface PendingPlanChange {
    priceId: string;
    currentPlan: {
        key: string;
        name: string;
        price: number;
    };
    targetPlan: {
        key: string;
        name: string;
        price: number;
    };
    priceDifference: number;
    currency: string;
    isUpgrade: boolean;
    isDowngrade: boolean;
}

// Reactive state
const loading = ref(true);
const error = ref<string | null>(null);
const subscriptionData = ref<any>(null);
const plansData = ref<any>(null);
const processingUpgrade = ref(false);
const preselectedPlanKey = ref<string | null>(null);
const autoPlanTriggerHandled = ref(false);
const showConfirmationModal = ref(false);
const pendingPlanChange = ref<PendingPlanChange | null>(null);
const confirmationLoading = ref(false);
let autoRefreshTimerId: ReturnType<typeof setTimeout> | null = null;

// Success modal state & auth user
const page = usePage();

// Determine the current plan key, preferring live subscription data when available,
// and falling back to the user's subscription_type from page props.
const userSubscriptionType = computed(() => {
    // If we have live subscription + plans data, derive the plan key from the Stripe price ID
    if (subscriptionData.value?.subscription && plansData.value?.plans) {
        const sub = subscriptionData.value.subscription;
        const plans = plansData.value.plans;
        const derivedKey = Object.keys(plans).find(
            (key) => plans[key].stripe_price_id === sub.stripe_price_id
        );
        if (derivedKey) return derivedKey as string;
    }

    // Fallback to the value persisted on the user model (from users.subscription_type)
    return (page.props.auth?.user?.subscription_type as string) || 'free';
});

const showSuccessModal = ref(false);
const successModalTitle = ref('Thank You for Subscribing!');
const successModalDescription = ref('Your subscription is now active! You have full access to all premium features.');
const successModalStatusLabel = ref('Active');
const successModalPlanName = ref<string | null>(null);

// Check for success parameter in URL
onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === 'true') {
        const sessionId = urlParams.get('session_id');
        handleSuccessfulCheckoutReturn(sessionId);
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    // Capture preselected plan from query param
    const planParam = urlParams.get('plan');
    if (planParam) {
        preselectedPlanKey.value = planParam;
    }
});

// Computed properties
const currentPlan = computed(() => {
    if (!subscriptionData.value || !subscriptionData.value.subscription) {
        return plansData.value?.free_plan || {
            name: 'Free',
            price: 0,
            period: 'forever',
            features: [],
            limits: {}
        };
    }

    const subscription = subscriptionData.value.subscription;
    const planKey = Object.keys(plansData.value?.plans || {}).find(key => 
        plansData.value.plans[key].stripe_price_id === subscription.stripe_price_id
    );

    if (planKey) {
        return {
            ...plansData.value.plans[planKey],
            status: subscription.status,
            isActive: subscription.is_active,
            onTrial: subscription.on_trial,
            currentPeriodEnd: subscription.current_period_end,
            cancelAtPeriodEnd: subscription.cancel_at_period_end
        };
    }

    return plansData.value?.free_plan || { name: 'Unknown', price: 0, period: 'month', features: [] };
});

const currentPlanNameFromUser = computed(() => {
    const plans = plansData.value?.plans || {};
    const key = userSubscriptionType.value;

    // If the key matches a configured plan, use its display name
    if (plans[key]?.name) {
        return plans[key].name as string;
    }

    // Fallback: simple capitalized key (e.g., 'free' -> 'Free')
    if (!key) return 'Free';
    return key.charAt(0).toUpperCase() + key.slice(1);
});

const scheduledPlanChange = computed(() => {
    return subscriptionData.value?.scheduled_plan_change 
        ?? subscriptionData.value?.subscription?.scheduled_plan_change 
        ?? null;
});

const hasScheduledPlanChange = computed(() => Boolean(scheduledPlanChange.value));

const scheduledPlanChangeDescription = computed(() => {
    if (!scheduledPlanChange.value) return '';
    const targetName = scheduledPlanChange.value.plan_name || 'tu nuevo plan';
    const scheduledFor = scheduledPlanChange.value.scheduled_for
        ? formatDate(scheduledPlanChange.value.scheduled_for)
        : '';
    if (scheduledFor) {
        return `Tu plan cambiará automáticamente a ${targetName} el ${scheduledFor}. Mantendrás tu plan actual hasta esa fecha.`;
    }
    return `Tu plan cambiará automáticamente a ${targetName} al final de tu período de facturación.`;
});

const availablePlans = computed(() => {
    if (!plansData.value?.plans) return [];
    
    return Object.values(plansData.value.plans).filter(plan => {
        // Don't show current plan in upgrade options
        if (subscriptionData.value?.subscription) {
            return plan.stripe_price_id !== subscriptionData.value.subscription.stripe_price_id;
        }
        return true;
    });
});

// Compute dynamic price difference between Premium and Plus (for display only)
const priceDiffPremiumToPlus = computed<string | null>(() => {
    const plans = plansData.value?.plans;
    if (!plans?.premium?.price || !plans?.plus?.price) {
        return null;
    }
    const diff = Number(plans.plus.price) - Number(plans.premium.price);
    if (!isFinite(diff) || diff <= 0) {
        return null;
    }
    return diff.toFixed(2);
});

const hasActiveSubscription = computed(() => {
    return subscriptionData.value?.subscription?.is_active || false;
});

const subscriptionStatus = computed(() => {
    if (!subscriptionData.value?.subscription) return 'none';
    
    const subscription = subscriptionData.value.subscription;
    if (subscription.on_trial) return 'trial';
    if (subscription.cancel_at_period_end) return 'canceling';
    return subscription.status;
});

// Methods
const clearPlanQueryParam = () => {
    if (!preselectedPlanKey.value) {
        return;
    }

    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);
    params.delete('plan');
    const newQuery = params.toString();
    const newUrl = newQuery ? `${url.pathname}?${newQuery}` : url.pathname;
    window.history.replaceState({}, document.title, newUrl);
    preselectedPlanKey.value = params.get('plan');
};

const attemptAutoPlanChange = async () => {
    if (autoPlanTriggerHandled.value) {
        return;
    }

    const key = preselectedPlanKey.value;
    if (!key || !plansData.value?.plans?.[key]) {
        return;
    }

    if (!hasActiveSubscription.value) {
        return;
    }

    autoPlanTriggerHandled.value = true;
    clearPlanQueryParam();

    try {
        await handleUpgrade(plansData.value.plans[key]);
    } catch (err) {
        console.error('Automatic plan change failed:', err);
        autoPlanTriggerHandled.value = false;
    }
};

const loadData = async () => {
    try {
        loading.value = true;
        error.value = null;

        // First try to refresh subscription data from Stripe to fix any sync issues
        try {
            await refreshSubscriptionData();
        } catch (refreshError) {
            console.warn('Failed to refresh subscription data, continuing with cached data:', refreshError);
        }

        // Load live subscription status from API
        const subResp = await stripeService.getSubscriptionStatus();
        subscriptionData.value = subResp;

        // Load plans data
        const plans = await stripeService.getPlans();
        plansData.value = plans;

        // If plan preselected, attempt to auto-focus the plan card or start upgrade flow
        if (preselectedPlanKey.value && plansData.value?.plans?.[preselectedPlanKey.value]) {
            // If user already subscribed to another plan, just highlight and scroll; else, optionally auto-open checkout
            const selected = plansData.value.plans[preselectedPlanKey.value];
            // Smooth scroll to plans section
            setTimeout(() => {
                const el = document.querySelector('[data-plans-section]');
                el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 200);

            await attemptAutoPlanChange();
        }
    } catch (err) {
        console.error('Failed to load subscription data:', err);
        error.value = err instanceof Error ? err.message : 'Failed to load subscription data';
    } finally {
        loading.value = false;
    }
};

const getUpgradeButtonLabel = (plan: any): string => {
    // Handle contact-sales plans elsewhere; this function assumes a normal priced plan
    const name: string = (plan.name as string) || '';
    const normalized = name.toLowerCase();

    // If user already has an active subscription, this is a plan switch
    if (hasActiveSubscription.value) {
        // Special case: Premium -> Plus upgrade with explicit difference
        const currentKey = userSubscriptionType.value;
        const isPremiumToPlus =
            currentKey === 'premium' && normalized === 'plus';
        const isPlusToPremium =
            currentKey === 'plus' && normalized === 'premium';

        if (isPremiumToPlus && priceDiffPremiumToPlus.value) {
            return `Mejorar al Plus por €${priceDiffPremiumToPlus.value}`;
        }

        if (isPlusToPremium) {
            return 'Downgrade to Premium';
        }

        // Generic switch text in Spanish
        if (normalized === 'plus') {
            return 'Cambiar a Plus';
        }
        if (normalized === 'premium') {
            return 'Cambiar a Premium';
        }
        return `Cambiar a ${name}`;
    }

    // No active subscription yet: upgrading from Free or none
    if (normalized === 'plus') {
        return 'Mejorar al Plus';
    }
    if (normalized === 'premium') {
        return 'Mejorar al Premium';
    }

    return `Mejorar al ${name}`;
};

const processPlanChangeSuccess = async (res: any, planContext?: { name?: string }) => {
    if (!res) {
        throw new Error('Missing plan change response');
    }

    // Redirect to Stripe invoice page if available
    if (res.invoice_url) {
        window.location.href = res.invoice_url;
        return;
    }

    await loadData();

    if (res.status === 'scheduled' && res.scheduled_plan_change) {
        const targetName = res.scheduled_plan_change.plan_name || planContext?.name || 'tu nuevo plan';
        const scheduledFor = res.scheduled_plan_change.scheduled_for
            ? formatDate(res.scheduled_plan_change.scheduled_for)
            : '';
        const description = scheduledFor
            ? `Tu plan cambiará a ${targetName} el ${scheduledFor}. Mantendrás las ventajas actuales hasta esa fecha.`
            : `Tu plan cambiará a ${targetName} al final del período actual.`;

        setSuccessModalContent({
            title: 'Cambio programado',
            description,
            statusLabel: 'Pendiente',
            planName: targetName,
        });
    } else {
        const planName = res.plan_name || planContext?.name || currentPlanNameFromUser.value;
        setSuccessModalContent({
            title: '¡Suscripción actualizada!',
            description: 'Tu nuevo plan ya está activo. Actualizaremos la página automáticamente.',
            statusLabel: 'Activo',
            planName,
        });
    }

    triggerAutoRefresh();
};

const handleUpgrade = async (plan: any) => {
    try {
        processingUpgrade.value = true;
        
        // Handle Academy plan (contact sales)
        if (plan.contact_sales || !plan.stripe_price_id) {
            router.visit('/academy-contact');
            return;
        }
        
        if (hasActiveSubscription.value) {
            const res = await stripeService.upgrade(plan.stripe_price_id);

            if (res?.requires_confirmation) {
                const priceDiff = Number(res.price_difference) || 0;
                pendingPlanChange.value = {
                    priceId: plan.stripe_price_id,
                    currentPlan: res.current_plan,
                    targetPlan: res.target_plan,
                    priceDifference: priceDiff,
                    currency: res.currency || plan.currency || 'EUR',
                    isUpgrade: priceDiff > 0,
                    isDowngrade: priceDiff < 0,
                };
                showConfirmationModal.value = true;
                return;
            }

            await processPlanChangeSuccess(res, { name: plan.name });
            return;
        } else {
            await stripeService.redirectToCheckout(plan.stripe_price_id);
        }
    } catch (err) {
        console.error('Failed to start upgrade process:', err);
        error.value = err instanceof Error ? err.message : 'Failed to start upgrade process';
    } finally {
        processingUpgrade.value = false;
    }
};

const handleManageSubscription = async () => {
    try {
        processingUpgrade.value = true;
        await stripeService.redirectToPortal();
    } catch (err) {
        console.error('Failed to access subscription management:', err);
        error.value = err instanceof Error ? err.message : 'Failed to access subscription management';
    } finally {
        processingUpgrade.value = false;
    }
};

const handleConfirmPlanChange = async () => {
    if (!pendingPlanChange.value) return;
    const planMeta = pendingPlanChange.value;
    try {
        confirmationLoading.value = true;
        const res = await stripeService.upgrade(planMeta.priceId, true);
        showConfirmationModal.value = false;
        pendingPlanChange.value = null;
        await processPlanChangeSuccess(res, { name: planMeta.targetPlan.name });
    } catch (err) {
        console.error('Failed to confirm plan change:', err);
        error.value = err instanceof Error ? err.message : 'Failed to confirm plan change';
    } finally {
        confirmationLoading.value = false;
    }
};

const handleCancelPlanChange = () => {
    showConfirmationModal.value = false;
    pendingPlanChange.value = null;
};

const handleRefreshSubscription = async () => {
    try {
        await refreshSubscriptionData();
        await loadData();
        console.log('Subscription data refreshed successfully');
    } catch (err) {
        console.error('Failed to refresh subscription:', err);
        error.value = err instanceof Error ? err.message : 'Failed to refresh subscription data';
    }
};

const refreshSubscriptionData = async () => {
    try {
        const response = await fetch('/api/subscriptions/refresh', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin'
        });

        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                console.log('Subscription data refreshed successfully:', data.data);
                return data.data;
            }
        }
        throw new Error('Failed to refresh subscription data');
    } catch (err) {
        console.error('Error refreshing subscription data:', err);
        throw err;
    }
};

const triggerAutoRefresh = (attempt: number = 0) => {
    const maxAttempts = 8;
    const intervalMs = 3000;

    if (autoRefreshTimerId) {
        clearTimeout(autoRefreshTimerId);
        autoRefreshTimerId = null;
    }

    const run = async () => {
        try {
            await refreshSubscriptionData();

            const latestStatus = await stripeService.getSubscriptionStatus();
            subscriptionData.value = latestStatus;

            try {
                const response = await fetch('/api/subscriptions/refresh-plan', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    credentials: 'same-origin'
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.data?.plan_key && page.props.auth?.user) {
                        page.props.auth.user.subscription_type = data.data.plan_key;

                        // Ensure all Inertia-shared auth props (including subscription_type)
                        // are refreshed globally without a full page reload
                        router.reload({
                            only: ['auth'],
                            preserveScroll: true,
                        });
                    }
                }
            } catch (e) {
            }

            const isActive = latestStatus?.subscription?.is_active || latestStatus?.subscription?.status === 'active';
            if (isActive || attempt + 1 >= maxAttempts) {
                return;
            }
        } catch (e) {
            if (attempt + 1 >= maxAttempts) {
                return;
            }
        }

        autoRefreshTimerId = setTimeout(() => triggerAutoRefresh(attempt + 1), intervalMs);
    };

    run();
};

const formatPrice = (price: number, currency: string = 'usd') => {
    if (price === 0) return '$0';
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency.toUpperCase()
    }).format(price);
};

const formatDate = (dateString: string) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

const setSuccessModalContent = (options: { title: string; description: string; statusLabel: string; planName?: string | null }) => {
    successModalTitle.value = options.title;
    successModalDescription.value = options.description;
    successModalStatusLabel.value = options.statusLabel;
    successModalPlanName.value = options.planName ?? null;
    showSuccessModal.value = true;
};


const handleSuccessfulCheckoutReturn = async (sessionId: string | null) => {
    try {
        if (sessionId) {
            await stripeService.confirmCheckout(sessionId);
        } else {
            const status = await stripeService.pollSubscriptionUntilActive();
            if (status) {
                subscriptionData.value = status;
            }
        }
    } catch (error) {
        console.error('Failed to finalize checkout session:', error);
    } finally {
        await loadData();
        setSuccessModalContent({
            title: '¡Suscripción actualizada!',
            description: 'Tu suscripción está activa. Estamos actualizando la página...',
            statusLabel: 'Activo',
            planName: currentPlanNameFromUser.value,
        });
        triggerAutoRefresh();
    }
};

const getStatusBadge = () => {
    switch (subscriptionStatus.value) {
        case 'trial':
            return { variant: 'secondary', text: 'Trial', class: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' };
        case 'canceling':
            return { variant: 'secondary', text: 'Canceling', class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' };
        case 'active':
            return { variant: 'secondary', text: 'Active', class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' };
        default:
            return { variant: 'secondary', text: 'Free', class: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200' };
    }
};

// Lifecycle
onMounted(() => {
    loadData();
});

onUnmounted(() => {
    if (autoRefreshTimerId) {
        clearTimeout(autoRefreshTimerId);
    }
});
</script>

<template>
    <SettingsLayout title="Subscription" description="Manage your subscription and billing">
        <Head title="Subscription Settings" />

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <Loader2 class="w-8 h-8 animate-spin text-gray-500" />
            <span class="ml-2 text-gray-600 dark:text-gray-400">Loading subscription data...</span>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <AlertCircle class="w-5 h-5 text-red-500 mr-2" />
                <span class="text-red-700 dark:text-red-300">{{ error }}</span>
            </div>
            <Button @click="loadData" variant="outline" size="sm" class="mt-3">
                Vuelve a intentarlo
            </Button>
        </div>

        <div v-else class="space-y-8">
            <!-- Current Plan -->
            <Card class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Current Plan</h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">
                            You're currently on the {{ currentPlanNameFromUser }} plan
                            <span v-if="hasActiveSubscription && currentPlan.currentPeriodEnd">
                                (renews {{ formatDate(currentPlan.currentPeriodEnd) }})
                            </span>
                            <span v-if="currentPlan.cancelAtPeriodEnd">
                                (cancels {{ formatDate(currentPlan.currentPeriodEnd) }})
                            </span>
                        </p>
                    </div>
                    <Badge :variant="getStatusBadge().variant" :class="getStatusBadge().class">
                        {{ getStatusBadge().text }}
                    </Badge>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <div class="flex items-baseline space-x-2 mb-4">
                            <span class="text-3xl font-bold text-gray-900 dark:text-white">
                                {{ formatPrice(currentPlan.price, currentPlan.currency) }}
                            </span>
                            <span class="text-gray-600 dark:text-gray-400">/ {{ currentPlan.interval || currentPlan.period }}</span>
                        </div>
                        
                        <div class="space-y-3">
                            <div v-for="feature in currentPlan.features" :key="feature" class="flex items-center space-x-2">
                                <Check class="w-4 h-4 text-green-500" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ feature }}</span>
                            </div>
                        </div>

                        <!-- Manage Subscription Button for Active Subscribers -->
                        <div v-if="hasActiveSubscription" class="mt-6 space-y-3">
                            <Button 
                                @click="handleManageSubscription" 
                                variant="outline"
                                :disabled="processingUpgrade"
                                class="w-full sm:w-auto"
                            >
                                <Settings class="w-4 h-4 mr-2" />
                                <Loader2 v-if="processingUpgrade" class="w-4 h-4 mr-2 animate-spin" />
                                Manage Subscription
                            </Button>
                            <Button 
                                @click="handleRefreshSubscription" 
                                variant="outline"
                                :disabled="loading"
                                class="w-full sm:w-auto ml-0 sm:ml-3"
                            >
                                <Loader2 v-if="loading" class="w-4 h-4 mr-2 animate-spin" />
                                Refresh Subscription Data
                            </Button>
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4" v-if="currentPlan.limits">
                        <h3 class="font-medium text-gray-900 dark:text-white mb-3">Usage Limits</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Conversations</span>
                                <span class="text-gray-900 dark:text-white">{{ currentPlan.limits.conversations }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Response Time</span>
                                <span class="text-gray-900 dark:text-white">{{ currentPlan.limits.responseTime }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Support</span>
                                <span class="text-gray-900 dark:text-white">{{ currentPlan.limits.support }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>

            <div 
                v-if="hasScheduledPlanChange" 
                class="border border-blue-200 dark:border-blue-900/40 bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 flex items-start space-x-3"
            >
                <Clock class="w-5 h-5 text-blue-500 mt-1" />
                <div>
                    <p class="font-semibold text-blue-900 dark:text-blue-100">Cambio de plan programado</p>
                    <p class="text-sm text-blue-800 dark:text-blue-200 mt-1">
                        {{ scheduledPlanChangeDescription }}
                    </p>
                </div>
            </div>

            <!-- Available Plans -->
            <div v-if="availablePlans.length > 0">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">
                    {{ hasActiveSubscription ? 'Change Your Plan' : 'Upgrade Your Plan' }}
                </h2>
                
                <div class="grid md:grid-cols-2 gap-6" data-plans-section>
                    <Card 
                        v-for="plan in availablePlans" 
                        :key="plan.name"
                        class="relative p-6 hover:shadow-lg transition-shadow"
                        :class="{ 'ring-2 ring-orange-500': plan.popular || plan.name?.toLowerCase() === preselectedPlanKey }"
                    >
                        <div v-if="plan.popular" class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                            <Badge class="bg-orange-500 text-white px-3 py-1">
                                <Star class="w-3 h-3 mr-1" />
                                Most Popular
                            </Badge>
                        </div>

                        <div class="text-center mb-6">
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">{{ plan.name }}</h3>
                            <div class="flex items-baseline justify-center space-x-2">
                                <span v-if="plan.contact_sales || plan.price === null" class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Precio personalizado
                                </span>
                                <template v-else>
                                    <span class="text-3xl font-bold text-gray-900 dark:text-white">
                                        {{ formatPrice(plan.price, plan.currency) }}
                                    </span>
                                    <span class="text-gray-600 dark:text-gray-400">/ {{ plan.interval }}</span>
                                </template>
                            </div>
                        </div>

                        <div class="space-y-3 mb-6">
                            <div v-for="feature in plan.features" :key="feature" class="flex items-center space-x-2">
                                <Check class="w-4 h-4 text-green-500" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ feature }}</span>
                            </div>
                        </div>

                        <Button 
                            @click="handleUpgrade(plan)"
                            class="w-full"
                            :variant="plan.popular ? 'default' : 'outline'"
                            :disabled="processingUpgrade"
                            :class="{
                                'bg-orange-500 hover:bg-orange-600 text-white': plan.popular,
                                'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800': !plan.popular
                            }"
                        >
                            <Loader2 v-if="processingUpgrade" class="w-4 h-4 mr-2 animate-spin" />
                            <Crown v-else-if="plan.popular" class="w-4 h-4 mr-2" />
                            <Zap v-else class="w-4 h-4 mr-2" />
                            {{ plan.contact_sales || !plan.stripe_price_id
                                ? 'Consultar precio'
                                : getUpgradeButtonLabel(plan)
                            }}
                        </Button>
                    </Card>
                </div>
            </div>

            <!-- Features Comparison -->
            <Card class="p-6" v-if="plansData?.feature_comparison">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">Feature Comparison</h2>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-3 px-4 font-medium text-gray-900 dark:text-white">Feature</th>
                                <th class="text-center py-3 px-4 font-medium text-gray-900 dark:text-white">Free</th>
                                <th class="text-center py-3 px-4 font-medium text-gray-900 dark:text-white">Premium</th>
                                <th class="text-center py-3 px-4 font-medium text-gray-900 dark:text-white">Plus</th>
                                <th class="text-center py-3 px-4 font-medium text-gray-900 dark:text-white">Academy</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="feature in plansData.feature_comparison" :key="feature.name">
                                <td class="py-3 px-4 flex items-center space-x-2">
                                    <component :is="feature.icon" class="w-4 h-4 text-gray-500" />
                                    <span class="text-gray-700 dark:text-gray-300">{{ feature.name }}</span>
                                </td>
                                <td class="text-center py-3 px-4" :class="feature.free === '✗' ? 'text-gray-400' : 'text-gray-600 dark:text-gray-400'">
                                    {{ feature.free }}
                                </td>
                                <td class="text-center py-3 px-4" :class="feature.premium === '✗' ? 'text-gray-400' : 'text-green-600'">
                                    {{ feature.premium }}
                                </td>
                                <td class="text-center py-3 px-4" :class="feature.plus === '✗' ? 'text-gray-400' : 'text-green-600'">
                                    {{ feature.plus }}
                                </td>
                                <td class="text-center py-3 px-4" :class="feature.academy === '✗' ? 'text-gray-400' : 'text-green-600'">
                                    {{ feature.academy }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>

        <!-- Success Modal -->
        <SubscriptionSuccessModal 
            :show="showSuccessModal"
            :subscription="subscriptionData"
            :plan-name="successModalPlanName || currentPlanNameFromUser"
            :title="successModalTitle"
            :description="successModalDescription"
            :status-label="successModalStatusLabel"
            @close="showSuccessModal = false"
        />

        <PlanChangeConfirmationModal
            v-if="pendingPlanChange"
            :show="showConfirmationModal"
            :current-plan="pendingPlanChange.currentPlan"
            :target-plan="pendingPlanChange.targetPlan"
            :price-difference="pendingPlanChange.priceDifference"
            :currency="pendingPlanChange.currency"
            :is-upgrade="pendingPlanChange.isUpgrade"
            :is-downgrade="pendingPlanChange.isDowngrade"
            :loading="confirmationLoading"
            @confirm="handleConfirmPlanChange"
            @cancel="handleCancelPlanChange"
        />
    </SettingsLayout>
</template>
