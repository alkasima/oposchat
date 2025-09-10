<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import SubscriptionSuccessModal from '@/components/SubscriptionSuccessModal.vue';
import stripeService from '@/services/stripeService';
import { 
    Check, 
    Crown, 
    Zap, 
    MessageSquare, 
    Users, 
    FileText,
    Clock,
    Shield,
    Star,
    Loader2,
    AlertCircle,
    Settings
} from 'lucide-vue-next';

// Reactive state
const loading = ref(true);
const error = ref<string | null>(null);
const subscriptionData = ref<any>(null);
const plansData = ref<any>(null);
const processingUpgrade = ref(false);
const preselectedPlanKey = ref<string | null>(null);

// Success modal state
const page = usePage();
const showSuccessModal = ref(false);

// Check for success parameter in URL
onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === 'true') {
        showSuccessModal.value = true;
        // If we have a session_id, confirm immediately to persist DB without webhook
        const sessionId = urlParams.get('session_id');
        if (sessionId) {
            stripeService.confirmCheckout(sessionId).then(() => {
                loadData();
            }).catch(() => {
                // Fallback to polling if confirm fails
                stripeService.pollSubscriptionUntilActive().then((status) => {
                    if (status) subscriptionData.value = status;
                });
            });
        } else {
            // No session id, try polling
            stripeService.pollSubscriptionUntilActive().then((status) => {
                if (status) subscriptionData.value = status;
            });
        }
        // Clean up URL without reloading
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
const loadData = async () => {
    try {
        loading.value = true;
        error.value = null;

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
        }
    } catch (err) {
        console.error('Failed to load subscription data:', err);
        error.value = err instanceof Error ? err.message : 'Failed to load subscription data';
    } finally {
        loading.value = false;
    }
};

const handleUpgrade = async (plan: any) => {
    try {
        processingUpgrade.value = true;
        await stripeService.redirectToCheckout(plan.stripe_price_id);
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
                Try Again
            </Button>
        </div>

        <div v-else class="space-y-8">
            <!-- Current Plan -->
            <Card class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Current Plan</h2>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">
                            You're currently on the {{ currentPlan.name }} plan
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
                        <div v-if="hasActiveSubscription" class="mt-6">
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
                                <span class="text-3xl font-bold text-gray-900 dark:text-white">
                                    {{ formatPrice(plan.price, plan.currency) }}
                                </span>
                                <span class="text-gray-600 dark:text-gray-400">/ {{ plan.interval }}</span>
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
                            {{ hasActiveSubscription ? `Switch to ${plan.name}` : `Upgrade to ${plan.name}` }}
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
                                <th class="text-center py-3 px-4 font-medium text-gray-900 dark:text-white">Pro</th>
                                <th class="text-center py-3 px-4 font-medium text-gray-900 dark:text-white">Team</th>
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
                                <td class="text-center py-3 px-4" :class="feature.pro === '✗' ? 'text-gray-400' : 'text-green-600'">
                                    {{ feature.pro }}
                                </td>
                                <td class="text-center py-3 px-4" :class="feature.team === '✗' ? 'text-gray-400' : 'text-green-600'">
                                    {{ feature.team }}
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
            @close="showSuccessModal = false"
        />
    </SettingsLayout>
</template>