<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import InputError from '@/components/InputError.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import stripeService from '@/services/stripeService';
import { useSubscription } from '@/composables/useSubscription.js';
import { 
    X,
    User,
    Lock,
    CreditCard,
    Shield,
    Check,
    Crown,
    Star,
    Zap,
    MessageSquare,
    Users,
    FileText,
    Clock,
    ChevronRight,
    Trash2,
    Scale,
    Loader2,
    AlertCircle,
    ExternalLink,
    Settings,
    HelpCircle,
    Mail
} from 'lucide-vue-next';

interface Props {
    isOpen: boolean;
    mustVerifyEmail?: boolean;
    status?: string;
}

const props = withDefaults(defineProps<Props>(), {
    mustVerifyEmail: false
});

const emit = defineEmits<{
    close: [];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const activeTab = ref('profile');
const showUpgradeModal = ref(false);

// Subscription state
const subscriptionData = ref(null);
const subscriptionLoading = ref(false);
const subscriptionError = ref(null);
const availablePlans = ref([]);
const isUpgrading = ref(false);

// Profile form - initialize with reactive user data
const profileForm = useForm({
    name: '',
    email: ''
});

// Password form
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: ''
});

const tabs = [
    { id: 'profile', label: 'Profile', icon: User },
    { id: 'password', label: 'Password', icon: Lock },
    { id: 'subscription', label: 'Subscription', icon: CreditCard },
    { id: 'privacy', label: 'Privacy', icon: Shield },
    { id: 'support', label: 'Support', icon: HelpCircle }
];

// Computed properties for subscription data
const currentSubscription = computed(() => {
    return subscriptionData.value?.subscription || null;
});

const isSubscribed = computed(() => {
    return currentSubscription.value && currentSubscription.value.is_active;
});

const subscriptionStatus = computed(() => {
    if (!currentSubscription.value) return 'free';
    return currentSubscription.value.status;
});

const currentPlan = computed(() => {
    if (!isSubscribed.value) {
        return {
            name: 'Free',
            price: '$0',
            period: 'forever',
                features: [
                    '3 messages per day',
                    'Community support'
                ]
        };
    }

    const subscription = currentSubscription.value;
    const priceId = subscription.stripe_price_id;
    
    // Find the plan details from available plans
    const planDetails = availablePlans.value.find(plan => plan.stripe_price_id === priceId);
    const planName = planDetails?.name || 'Unknown';
    const price = planDetails?.price ? `$${planDetails.price}` : '$0';
    const interval = planDetails?.interval || 'month';
    const features = planDetails?.features || [];
    
    return {
        name: planName,
        price: price,
        period: interval,
        features: features,
        nextBillingDate: subscription.current_period_end,
        cancelAtPeriodEnd: subscription.cancel_at_period_end
    };
});

const statusBadgeVariant = computed(() => {
    switch (subscriptionStatus.value) {
        case 'active':
            return 'default';
        case 'trialing':
            return 'secondary';
        case 'past_due':
        case 'unpaid':
            return 'destructive';
        case 'canceled':
            return 'outline';
        default:
            return 'outline';
    }
});

const statusBadgeText = computed(() => {
    switch (subscriptionStatus.value) {
        case 'active':
            return 'Active';
        case 'trialing':
            return 'Trial';
        case 'past_due':
            return 'Past Due';
        case 'unpaid':
            return 'Unpaid';
        case 'canceled':
            return 'Canceled';
        case 'free':
            return 'Free';
        default:
            return 'Unknown';
    }
});

// Subscription methods (simplified for MVP)
const loadSubscriptionData = async () => {
    subscriptionLoading.value = true;
    subscriptionError.value = null;
    
    try {
        console.log('Loading subscription data...');
        
        // For MVP: Use subscription data from Inertia props
        const { subscription } = useSubscription();
        subscriptionData.value = subscription.value;
        
        // Set some basic plans for MVP
        availablePlans.value = [
            {
                key: 'premium',
                name: 'Premium',
                description: 'Perfect for individuals and small teams',
                price: 9.99,
                currency: 'EUR',
                interval: 'month',
                stripe_price_id: 'price_1RuE5gAVc1w1yLTUdkry1i2o', // Premium plan €9.99/month
                features: [
                    '200 messages per month',
                    'Upload files',
                    'Access to exams',
                    'Priority technical support'
                ]
            },
            {
                key: 'plus',
                name: 'Plus',
                description: 'For growing teams and businesses',
                price: 14.99,
                currency: 'EUR',
                interval: 'month',
                stripe_price_id: 'price_1RuE5gAVc1w1yLTUopmMCnBb', // Plus plan €14.99/month
                features: [
                    'Unlimited messages',
                    'Upload files',
                    'Access to exams',
                    'Priority technical support'
                ]
            },
            {
                key: 'academy',
                name: 'Academy',
                description: 'For teams and organizations',
                price: null,
                currency: 'EUR',
                interval: 'month',
                stripe_price_id: null,
                contact_sales: true,
                features: [
                    'Unlimited messages',
                    'Upload files',
                    'Access to exams',
                    'Priority technical support',
                    'Advanced analytics'
                ]
            }
        ];
        
        console.log('Subscription data loaded from props:', subscriptionData.value);
        console.log('Available plans:', availablePlans.value);
    } catch (error) {
        console.error('Failed to load subscription data:', error);
        subscriptionError.value = error.message || 'Failed to load subscription data';
        
        // Provide fallback data to prevent complete failure
        if (!subscriptionData.value) {
            subscriptionData.value = {
                subscription: null,
                status: 'none',
                plan: null
            };
        }
        
        if (availablePlans.value.length === 0) {
            // Provide fallback plans from config
            availablePlans.value = [
                {
                    key: 'pro',
                    name: 'Pro',
                    price: 19,
                    interval: 'month',
                    stripe_price_id: 'price_pro',
                    popular: true,
                    features: [
                        'Unlimited conversations',
                        'Advanced AI responses',
                        'Priority support',
                        'Mobile app access',
                        'Export conversations'
                    ]
                },
                {
                    key: 'team',
                    name: 'Team',
                    price: 49,
                    interval: 'month',
                    stripe_price_id: 'price_team',
                    popular: false,
                    features: [
                        'Everything in Pro',
                        'Team collaboration',
                        'Admin dashboard',
                        'API access',
                        'Dedicated support'
                    ]
                }
            ];
        }
    } finally {
        subscriptionLoading.value = false;
    }
};

const handleUpgrade = async (planKey: string) => {
    if (isUpgrading.value) return;
    
    const plan = availablePlans.value.find(p => p.key === planKey);
    if (!plan) {
        console.error('Plan not found:', planKey);
        return;
    }
    
    // Handle Academy plan (contact sales)
    if (plan.contact_sales || !plan.stripe_price_id) {
        // Redirect to academy contact page
        router.visit('/academy-contact');
        return;
    }
    
    isUpgrading.value = true;
    
    try {
        await stripeService.redirectToCheckout(plan.stripe_price_id);
    } catch (error) {
        console.error('Failed to start upgrade process:', error);
        subscriptionError.value = error.message;
        isUpgrading.value = false;
    }
};

const handleManageSubscription = async () => {
    if (!isSubscribed.value) return;
    
    try {
        await stripeService.redirectToPortal();
    } catch (error) {
        console.error('Failed to open customer portal:', error);
        subscriptionError.value = error.message;
    }
};

const handleCancelSubscription = async () => {
    if (!isSubscribed.value || !confirm('Are you sure you want to cancel your subscription?')) {
        return;
    }
    
    try {
        await stripeService.cancelSubscription();
        await loadSubscriptionData(); // Refresh data
    } catch (error) {
        console.error('Failed to cancel subscription:', error);
        subscriptionError.value = error.message;
    }
};

const formatDate = (dateString: string) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

const submitProfile = () => {
    profileForm.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Success message will show automatically via recentlySuccessful
            // Optionally refresh user data or emit an event
        },
        onError: (errors) => {
            console.error('Profile update failed:', errors);
        }
    });
};

const submitPassword = () => {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
        }
    });
};

const closeModal = () => {
    emit('close');
};

// Reset forms when modal opens and populate with current user data
watch(() => props.isOpen, (isOpen) => {
    if (isOpen) {
        // Populate profile form with current user data
        profileForm.name = user.value?.name || '';
        profileForm.email = user.value?.email || '';
        
        profileForm.clearErrors();
        passwordForm.clearErrors();
        passwordForm.reset();
        activeTab.value = 'profile';
        
        // Only load subscription data if we don't have it already or if there was an error
        if (!subscriptionData.value || subscriptionError.value) {
            loadSubscriptionData();
        }
    }
});

// Also watch for user changes to update form
watch(user, (newUser) => {
    if (newUser && props.isOpen) {
        profileForm.name = newUser.name || '';
        profileForm.email = newUser.email || '';
    }
}, { immediate: true });

// Handle URL parameters for payment success/cancel
const handlePaymentCallback = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get('success');
    const canceled = urlParams.get('canceled');
    
    if (success === 'true') {
        // Payment was successful, refresh subscription data
        loadSubscriptionData();
        // Remove URL parameters
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (canceled === 'true') {
        // Payment was canceled
        subscriptionError.value = 'Payment was canceled. You can try again anytime.';
        // Remove URL parameters
        window.history.replaceState({}, document.title, window.location.pathname);
    }
};

// Periodic subscription status updates
let statusUpdateInterval: number | null = null;

const startStatusUpdates = () => {
    // Update subscription status every 30 seconds when modal is open
    statusUpdateInterval = window.setInterval(() => {
        if (props.isOpen && isSubscribed.value) {
            loadSubscriptionData();
        }
    }, 30000);
};

const stopStatusUpdates = () => {
    if (statusUpdateInterval) {
        window.clearInterval(statusUpdateInterval);
        statusUpdateInterval = null;
    }
};

// Load subscription data on component mount
onMounted(() => {
    if (props.isOpen) {
        handlePaymentCallback();
        startStatusUpdates();
        // Only load if we don't have data
        if (!subscriptionData.value) {
            loadSubscriptionData();
        }
    }
});

// Watch for modal open/close to manage status updates
watch(() => props.isOpen, (isOpen) => {
    if (isOpen) {
        startStatusUpdates();
        handlePaymentCallback();
    } else {
        stopStatusUpdates();
    }
});

// Watch for subscription tab activation to load data
watch(activeTab, (newTab) => {
    if (newTab === 'subscription' && props.isOpen && !subscriptionData.value && !subscriptionLoading.value) {
        loadSubscriptionData();
    }
});

// Cleanup on component unmount
onUnmounted(() => {
    stopStatusUpdates();
});
</script>

<template>
    <!-- Modal Overlay -->
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black bg-opacity-50" @click="closeModal"></div>
        
        <!-- Modal Content -->
        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] mx-4 overflow-hidden">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-4 md:p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg md:text-xl font-semibold text-gray-900 dark:text-white">Settings</h2>
                <Button @click="closeModal" variant="ghost" size="sm" class="p-2">
                    <X class="w-5 h-5" />
                </Button>
            </div>

            <div class="flex flex-col md:flex-row h-[500px] md:h-[600px]">
                <!-- Sidebar Navigation -->
                <div class="w-full md:w-64 bg-gray-50 dark:bg-gray-900 border-b md:border-b-0 md:border-r border-gray-200 dark:border-gray-700 overflow-y-auto">
                    <!-- Mobile Tab Navigation -->
                    <div class="md:hidden flex overflow-x-auto p-2 space-x-1">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            class="flex items-center space-x-2 px-3 py-2 rounded-lg text-sm whitespace-nowrap transition-colors"
                            :class="{
                                'bg-orange-100 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400': activeTab === tab.id,
                                'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800': activeTab !== tab.id
                            }"
                        >
                            <component :is="tab.icon" class="w-4 h-4" />
                            <span>{{ tab.label }}</span>
                        </button>
                    </div>
                    
                    <!-- Desktop Tab Navigation -->
                    <nav class="hidden md:block p-4 space-y-1">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-left transition-colors"
                            :class="{
                                'bg-orange-100 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400': activeTab === tab.id,
                                'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800': activeTab !== tab.id
                            }"
                        >
                            <component :is="tab.icon" class="w-4 h-4" />
                            <span class="font-medium">{{ tab.label }}</span>
                            <ChevronRight v-if="activeTab === tab.id" class="w-4 h-4 ml-auto" />
                        </button>
                    </nav>
                </div>

                <!-- Content Area -->
                <div class="flex-1 overflow-y-auto p-4 md:p-6">
                    <!-- Profile Tab -->
                    <div v-if="activeTab === 'profile'" class="space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Profile Information</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm">Update your personal information and account details.</p>
                        </div>

                        <form @submit.prevent="submitProfile" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="modal-name" class="text-sm font-medium">Full Name</Label>
                                <Input
                                    id="modal-name"
                                    v-model="profileForm.name"
                                    class="h-10 border-gray-300 dark:border-gray-600 focus:ring-orange-500 focus:border-orange-500 text-black dark:text-white bg-white dark:bg-gray-700"
                                    placeholder="Enter your full name"
                                    required
                                />
                                <InputError :message="profileForm.errors.name" class="text-xs" />
                            </div>

                            <div class="space-y-2">
                                <Label for="modal-email" class="text-sm font-medium">Email Address</Label>
                                <Input
                                    id="modal-email"
                                    v-model="profileForm.email"
                                    type="email"
                                    class="h-10 border-gray-300 dark:border-gray-600 focus:ring-orange-500 focus:border-orange-500 text-black dark:text-white bg-white dark:bg-gray-700"
                                    placeholder="Enter your email address"
                                    required
                                />
                                <InputError :message="profileForm.errors.email" class="text-xs" />
                            </div>

                            <div v-if="mustVerifyEmail && !user?.email_verified_at" class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3">
                                <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                    Your email address is unverified.
                                    <Link :href="route('verification.send')" method="post" as="button" class="font-medium underline hover:no-underline">
                                        Click here to resend verification email.
                                    </Link>
                                </p>
                            </div>

                            <div class="flex items-center gap-3 pt-4">
                                <Button 
                                    type="submit"
                                    :disabled="profileForm.processing"
                                    class="bg-orange-500 hover:bg-orange-600 text-white"
                                >
                                    {{ profileForm.processing ? 'Saving...' : 'Save Changes' }}
                                </Button>
                                
                                <Transition
                                    enter-active-class="transition ease-in-out duration-300"
                                    enter-from-class="opacity-0 transform translate-y-1"
                                    enter-to-class="opacity-100 transform translate-y-0"
                                    leave-active-class="transition ease-in-out duration-300"
                                    leave-from-class="opacity-100 transform translate-y-0"
                                    leave-to-class="opacity-0 transform translate-y-1"
                                >
                                    <p v-if="profileForm.recentlySuccessful" class="text-sm text-green-600 dark:text-green-400 font-medium">
                                        ✓ Profile updated successfully!
                                    </p>
                                </Transition>
                            </div>
                        </form>
                    </div>

                    <!-- Password Tab -->
                    <div v-if="activeTab === 'password'" class="space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Change Password</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm">Ensure your account is using a strong password to stay secure.</p>
                        </div>

                        <form @submit.prevent="submitPassword" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="modal-current-password" class="text-sm font-medium">Current Password</Label>
                                <Input
                                    id="modal-current-password"
                                    v-model="passwordForm.current_password"
                                    type="password"
                                    class="h-10 border-gray-300 dark:border-gray-600 focus:ring-orange-500 focus:border-orange-500 text-black dark:text-white bg-white dark:bg-gray-700"
                                    placeholder="Enter your current password"
                                />
                                <InputError :message="passwordForm.errors.current_password" class="text-xs" />
                            </div>

                            <div class="space-y-2">
                                <Label for="modal-new-password" class="text-sm font-medium">New Password</Label>
                                <Input
                                    id="modal-new-password"
                                    v-model="passwordForm.password"
                                    type="password"
                                    class="h-10 border-gray-300 dark:border-gray-600 focus:ring-orange-500 focus:border-orange-500 text-black dark:text-white bg-white dark:bg-gray-700"
                                    placeholder="Enter your new password"
                                />
                                <InputError :message="passwordForm.errors.password" class="text-xs" />
                            </div>

                            <div class="space-y-2">
                                <Label for="modal-confirm-password" class="text-sm font-medium">Confirm New Password</Label>
                                <Input
                                    id="modal-confirm-password"
                                    v-model="passwordForm.password_confirmation"
                                    type="password"
                                    class="h-10 border-gray-300 dark:border-gray-600 focus:ring-orange-500 focus:border-orange-500 text-black dark:text-white bg-white dark:bg-gray-700"
                                    placeholder="Confirm your new password"
                                />
                                <InputError :message="passwordForm.errors.password_confirmation" class="text-xs" />
                            </div>

                            <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <Button 
                                    type="submit"
                                    :disabled="passwordForm.processing || !passwordForm.current_password || !passwordForm.password || !passwordForm.password_confirmation"
                                    class="bg-orange-500 hover:bg-orange-600 text-white disabled:opacity-50"
                                >
                                    {{ passwordForm.processing ? 'Updating...' : 'Update Password' }}
                                </Button>
                                
                                <Button 
                                    type="button"
                                    variant="outline"
                                    @click="passwordForm.reset()"
                                    :disabled="passwordForm.processing"
                                    class="text-gray-600 dark:text-gray-400"
                                >
                                    Clear
                                </Button>
                                
                                <div class="flex-1">
                                    <Transition
                                        enter-active-class="transition ease-in-out duration-300"
                                        enter-from-class="opacity-0 transform translate-y-1"
                                        enter-to-class="opacity-100 transform translate-y-0"
                                        leave-active-class="transition ease-in-out duration-300"
                                        leave-from-class="opacity-100 transform translate-y-0"
                                        leave-to-class="opacity-0 transform translate-y-1"
                                    >
                                        <p v-if="passwordForm.recentlySuccessful" class="text-sm text-green-600 dark:text-green-400 font-medium">
                                            ✓ Password updated successfully!
                                        </p>
                                    </Transition>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Subscription Tab -->
                    <div v-if="activeTab === 'subscription'" class="space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">My Plan</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm">Manage your subscription and access.</p>
                        </div>

                        <!-- Loading State -->
                        <div v-if="subscriptionLoading" class="flex flex-col items-center justify-center py-8">
                            <Loader2 class="w-6 h-6 animate-spin text-orange-500" />
                            <span class="ml-2 text-gray-600 dark:text-gray-400 mb-4">Loading subscription data...</span>
                            <Button 
                                @click="() => { subscriptionLoading = false; subscriptionError = 'Loading cancelled. Click Try Again to retry.'; }"
                                variant="outline" 
                                size="sm"
                                class="text-gray-600 dark:text-gray-400"
                            >
                                Cancel Loading
                            </Button>
                        </div>

                        <!-- Error State -->
                        <div v-else-if="subscriptionError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                            <div class="flex items-center space-x-2">
                                <AlertCircle class="w-5 h-5 text-red-500" />
                                <p class="text-sm text-red-800 dark:text-red-200">{{ subscriptionError }}</p>
                            </div>
                            <Button 
                                @click="loadSubscriptionData" 
                                variant="outline" 
                                size="sm" 
                                class="mt-3 text-red-600 border-red-300 hover:bg-red-50"
                            >
                                Try Again
                            </Button>
                        </div>

                        <!-- Current Plan Card -->
                        <div v-else class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                         :class="{
                                             'bg-gray-100 dark:bg-gray-700': !isSubscribed,
                                             'bg-orange-100 dark:bg-orange-900': isSubscribed && currentPlan.name === 'Pro',
                                             'bg-purple-100 dark:bg-purple-900': isSubscribed && currentPlan.name === 'Team'
                                         }">
                                        <User v-if="!isSubscribed" class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                                        <Zap v-else-if="currentPlan.name === 'Pro'" class="w-5 h-5 text-orange-600 dark:text-orange-400" />
                                        <Users v-else-if="currentPlan.name === 'Team'" class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                                        <Crown v-else class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-900 dark:text-white">{{ currentPlan.name }} Plan</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ isSubscribed ? 'Your current subscription' : 'Your current plan' }}
                                        </p>
                                    </div>
                                </div>
                                <Badge :variant="statusBadgeVariant">
                                    {{ statusBadgeText }}
                                </Badge>
                            </div>

                            <!-- Subscription Details -->
                            <div v-if="isSubscribed" class="space-y-3 mb-6">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Next billing date</span>
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        {{ formatDate(currentPlan.nextBillingDate) }}
                                    </span>
                                </div>
                                <div v-if="currentPlan.cancelAtPeriodEnd" class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Cancellation</span>
                                    <span class="font-medium text-orange-600 dark:text-orange-400">
                                        Ends {{ formatDate(currentPlan.nextBillingDate) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Free Plan Details -->
                            <div v-else class="space-y-3 mb-6">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Daily message limit</span>
                                    <span class="font-medium text-gray-900 dark:text-white">10 messages</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Response speed</span>
                                    <span class="font-medium text-gray-900 dark:text-white">Standard</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Model access</span>
                                    <span class="font-medium text-gray-900 dark:text-white">Basic AI</span>
                                </div>
                            </div>

                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <div class="flex items-center justify-between mb-4">
                                    <span class="font-medium text-gray-900 dark:text-white">Monthly cost</span>
                                    <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ currentPlan.price }}</span>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div v-if="isSubscribed" class="flex space-x-3">
                                    <Button 
                                        @click="handleManageSubscription"
                                        class="flex-1 bg-orange-500 hover:bg-orange-600 text-white"
                                    >
                                        <Settings class="w-4 h-4 mr-2" />
                                        Manage Subscription
                                    </Button>
                                    <Button 
                                        @click="handleManageSubscription"
                                        variant="outline"
                                        class="text-gray-600 dark:text-gray-400"
                                    >
                                        <ExternalLink class="w-4 h-4 mr-2" />
                                        Billing Portal
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Upgrade Options -->
                        <div v-if="!subscriptionLoading && !subscriptionError && !isSubscribed" class="space-y-4">
                            <h4 class="font-semibold text-gray-900 dark:text-white">Upgrade for more access</h4>
                            
                            <!-- Dynamic Plan Cards -->
                            <div 
                                v-for="plan in availablePlans" 
                                :key="plan.key"
                                class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6 transition-colors"
                                :class="{
                                    'hover:border-orange-300 dark:hover:border-orange-600': plan.key === 'pro',
                                    'hover:border-purple-300 dark:hover:border-purple-600': plan.key === 'team'
                                }"
                            >
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                             :class="{
                                                 'bg-orange-100 dark:bg-orange-900': plan.key === 'pro',
                                                 'bg-purple-100 dark:bg-purple-900': plan.key === 'team'
                                             }">
                                            <Zap v-if="plan.key === 'pro'" class="w-5 h-5 text-orange-600 dark:text-orange-400" />
                                            <Users v-else-if="plan.key === 'team'" class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                                            <Crown v-else class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-900 dark:text-white">{{ plan.name }}</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ plan.key === 'pro' ? 'Our most popular plan' : 'For teams and organizations' }}
                                            </p>
                                        </div>
                                    </div>
                                    <Badge v-if="plan.popular" 
                                           :class="{
                                               'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200': plan.key === 'pro',
                                               'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200': plan.key === 'team'
                                           }">
                                        <Star v-if="plan.popular" class="w-3 h-3 mr-1" />
                                        {{ plan.popular ? 'Popular' : plan.name }}
                                    </Badge>
                                </div>

                                <!-- Plan Features -->
                                <div class="space-y-3 mb-6">
                                    <div v-for="feature in plan.features.slice(0, 3)" :key="feature" class="flex items-center space-x-2">
                                        <Check class="w-4 h-4 text-green-500" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ feature }}</span>
                                    </div>
                                    
                                    <!-- Show additional features if available -->
                                    <div v-if="plan.features.length > 3" class="space-y-2 mt-4">
                                        <div v-for="feature in plan.features.slice(3)" :key="feature" class="flex items-center space-x-2">
                                            <Check class="w-4 h-4 text-green-500" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ feature }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-baseline space-x-1">
                                        <span v-if="plan.contact_sales || plan.price === null" class="text-lg font-semibold text-gray-900 dark:text-white">
                                            Precio personalizado
                                        </span>
                                        <template v-else>
                                            <span class="text-2xl font-bold text-gray-900 dark:text-white">${{ plan.price }}</span>
                                            <span class="text-gray-600 dark:text-gray-400">USD / {{ plan.interval }}</span>
                                        </template>
                                    </div>
                                </div>

                                <Button 
                                    @click="handleUpgrade(plan.key)"
                                    :disabled="isUpgrading"
                                    class="w-full font-medium"
                                    :class="{
                                        'bg-orange-500 hover:bg-orange-600 text-white': plan.key === 'pro',
                                        'border-purple-300 text-purple-600 hover:bg-purple-50 dark:border-purple-600 dark:text-purple-400 dark:hover:bg-purple-900/20': plan.key === 'team'
                                    }"
                                    :variant="plan.key === 'pro' ? 'default' : 'outline'"
                                >
                                    <Loader2 v-if="isUpgrading" class="w-4 h-4 mr-2 animate-spin" />
                                    {{ isUpgrading ? 'Processing...' : (plan.contact_sales || !plan.stripe_price_id ? 'Consultar precio' : `Upgrade to ${plan.name}`) }}
                                </Button>
                            </div>
                        </div>

                        <!-- Current Subscription Management -->
                        <div v-else-if="isSubscribed && !subscriptionLoading" class="space-y-4">
                            <h4 class="font-semibold text-gray-900 dark:text-white">Subscription Management</h4>
                            
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                <div class="flex items-start space-x-3">
                                    <CreditCard class="w-5 h-5 text-blue-500 mt-0.5" />
                                    <div>
                                        <h5 class="font-medium text-blue-900 dark:text-blue-100">Manage Your Subscription</h5>
                                        <p class="text-sm text-blue-700 dark:text-blue-200 mt-1">
                                            Use the Stripe Customer Portal to update your payment method, download invoices, 
                                            or make changes to your subscription.
                                        </p>
                                        <Button 
                                            @click="handleManageSubscription"
                                            variant="outline"
                                            size="sm"
                                            class="mt-3 text-blue-600 border-blue-300 hover:bg-blue-50"
                                        >
                                            <ExternalLink class="w-4 h-4 mr-2" />
                                            Open Customer Portal
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <!-- Upgrade Options for Current Subscribers -->
                            <div v-if="availablePlans.length > 0" class="space-y-3">
                                <h5 class="font-medium text-gray-900 dark:text-white">Upgrade Your Plan</h5>
                                <div 
                                    v-for="plan in availablePlans.filter(p => p.price > (currentSubscription?.price || 0))" 
                                    :key="plan.key"
                                    class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h6 class="font-medium text-gray-900 dark:text-white">{{ plan.name }}</h6>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">${{ plan.price }}/{{ plan.interval }}</p>
                                        </div>
                                        <Button 
                                            @click="handleUpgrade(plan.key)"
                                            :disabled="isUpgrading"
                                            size="sm"
                                            class="bg-orange-500 hover:bg-orange-600 text-white"
                                        >
                                            <Loader2 v-if="isUpgrading" class="w-4 h-4 mr-2 animate-spin" />
                                            {{ isUpgrading ? 'Processing...' : 'Upgrade' }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method Management -->
                        <div v-if="isSubscribed && !subscriptionLoading" class="space-y-4">
                            <h4 class="font-semibold text-gray-900 dark:text-white">Payment Method</h4>
                            
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                            <CreditCard class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                                        </div>
                                        <div>
                                            <h5 class="font-medium text-gray-900 dark:text-white">Payment Method</h5>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                Manage your payment methods and billing information
                                            </p>
                                        </div>
                                    </div>
                                    <Button 
                                        @click="handleManageSubscription"
                                        variant="outline"
                                        size="sm"
                                        class="text-gray-600 dark:text-gray-400"
                                    >
                                        <Settings class="w-4 h-4 mr-2" />
                                        Manage
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Subscription Status Indicators -->
                        <div v-if="isSubscribed && !subscriptionLoading" class="space-y-4">
                            <h4 class="font-semibold text-gray-900 dark:text-white">Subscription Status</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Status Card -->
                                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Status</span>
                                        <Badge :variant="statusBadgeVariant">{{ statusBadgeText }}</Badge>
                                    </div>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ currentPlan.name }}</p>
                                </div>

                                <!-- Next Billing Card -->
                                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                            {{ currentPlan.cancelAtPeriodEnd ? 'Ends' : 'Next Billing' }}
                                        </span>
                                        <Clock class="w-4 h-4 text-gray-400" />
                                    </div>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ formatDate(currentPlan.nextBillingDate) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Cancellation Warning -->
                            <div v-if="currentPlan.cancelAtPeriodEnd" class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                                <div class="flex items-start space-x-3">
                                    <AlertCircle class="w-5 h-5 text-yellow-500 mt-0.5" />
                                    <div>
                                        <h5 class="font-medium text-yellow-900 dark:text-yellow-100">Subscription Ending</h5>
                                        <p class="text-sm text-yellow-700 dark:text-yellow-200 mt-1">
                                            Your subscription will end on {{ formatDate(currentPlan.nextBillingDate) }}. 
                                            You can reactivate it anytime before then.
                                        </p>
                                        <Button 
                                            @click="handleManageSubscription"
                                            variant="outline"
                                            size="sm"
                                            class="mt-3 text-yellow-600 border-yellow-300 hover:bg-yellow-50"
                                        >
                                            Reactivate Subscription
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- FAQ Section -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Frequently asked questions</h4>
                            <div class="space-y-3 text-sm">
                                <details class="group">
                                    <summary class="flex items-center justify-between cursor-pointer text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                                        <span>Can I cancel my subscription anytime?</span>
                                        <ChevronRight class="w-4 h-4 transition-transform group-open:rotate-90" />
                                    </summary>
                                    <p class="mt-2 text-gray-600 dark:text-gray-400 pl-0">
                                        Yes, you can cancel your subscription at any time. You'll continue to have access until the end of your billing period.
                                    </p>
                                </details>
                                <details class="group">
                                    <summary class="flex items-center justify-between cursor-pointer text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                                        <span>What happens to my data if I cancel?</span>
                                        <ChevronRight class="w-4 h-4 transition-transform group-open:rotate-90" />
                                    </summary>
                                    <p class="mt-2 text-gray-600 dark:text-gray-400 pl-0">
                                        Your conversation history will be preserved and you can export it before canceling. After cancellation, you'll revert to the free plan limits.
                                    </p>
                                </details>
                            </div>
                        </div>
                    </div>

                    <!-- Privacy Tab -->
                    <div v-if="activeTab === 'privacy'" class="space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Legal Notice and Privacy Policy</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm">Information about how we handle your data and privacy.</p>
                        </div>

                        <div class="space-y-6 text-sm text-gray-700 dark:text-gray-300">
                            <!-- Data Security -->
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                                    <Shield class="w-4 h-4 mr-2 text-blue-600" />
                                    1. Data Security
                                </h4>
                                <p class="leading-relaxed">
                                    Appropriate security measures are used to protect your data against manipulation, loss, destruction, and unauthorized access by third parties. Security is constantly being updated.
                                </p>
                            </div>

                            <!-- Data Collection -->
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                                    <FileText class="w-4 h-4 mr-2 text-green-600" />
                                    2. Data Collection and Storage
                                </h4>
                                <p class="leading-relaxed mb-3">
                                    All data and chats will be stored in your chat history in order to access previous conversations. The stored data is also used to improve the quality of the website and improve usage by users:
                                </p>
                                <div class="ml-4 space-y-1">
                                    <p><strong>a.</strong> Account data: username, email address, and IP.</p>
                                    <p><strong>b.</strong> Chat history.</p>
                                </div>
                            </div>

                            <!-- Data Deletion -->
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                                    <Trash2 class="w-4 h-4 mr-2 text-red-600" />
                                    3. Data Deletion
                                </h4>
                                <p class="leading-relaxed">
                                    You have the right to have your data and account completely deleted immediately upon your request.
                                </p>
                            </div>

                            <!-- Third Party Disclosure -->
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                                    <Users class="w-4 h-4 mr-2 text-orange-600" />
                                    4. Third Party Disclosure
                                </h4>
                                <p class="leading-relaxed mb-2">
                                    The website sends information to an AI chat for the elaboration of responses.
                                </p>
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded p-3">
                                    <p class="text-yellow-800 dark:text-yellow-200 font-medium">
                                        ⚠️ Do not disclose sensitive data such as passwords, credit cards, and private information.
                                    </p>
                                </div>
                            </div>

                            <!-- Rights -->
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                                    <Scale class="w-4 h-4 mr-2 text-purple-600" />
                                    5. Rights
                                </h4>
                                <p class="leading-relaxed text-gray-600 dark:text-gray-400">
                                    We will continue drafting this section and will be updating it accordingly.
                                </p>
                            </div>
                        </div>

                        <!-- Contact Section -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Questions or Concerns?</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                If you have any questions about our privacy policy or data handling practices, please contact us.
                            </p>
                            <Button variant="outline" class="text-sm">
                                Contact Support
                            </Button>
                        </div>
                    </div>

                    <!-- Support Tab -->
                    <div v-if="activeTab === 'support'" class="space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Support</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm">Get help and support for your account.</p>
                        </div>

                        <div class="text-center">
                            <div class="w-16 h-16 bg-orange-100 dark:bg-orange-900/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                <Mail class="w-8 h-8 text-orange-600 dark:text-orange-400" />
                            </div>
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Need Help?</h4>
                            <p class="text-gray-600 dark:text-gray-400 text-sm mb-6">
                                We're here to help! Contact our support team for any questions or assistance.
                            </p>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-orange-100 dark:bg-orange-900/20 rounded-full flex items-center justify-center">
                                    <Mail class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                                </div>
                                <div class="flex-1">
                                    <h5 class="font-medium text-gray-900 dark:text-white">Email Support</h5>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                        Send us an email and we'll get back to you as soon as possible.
                                    </p>
                                    <a href="mailto:info@oposchat.com" 
                                       class="text-orange-600 dark:text-orange-400 hover:underline font-medium">
                                        info@oposchat.com
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <div class="flex items-start space-x-3">
                                <Clock class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" />
                                <div>
                                    <h5 class="font-medium text-blue-900 dark:text-blue-100">Response Time</h5>
                                    <p class="text-sm text-blue-800 dark:text-blue-200">
                                        We typically respond within 24 hours during business days.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-center">
                            <Button @click="window.open('mailto:info@oposchat.com', '_blank')" 
                                    class="bg-orange-500 hover:bg-orange-600 text-white">
                                <Mail class="w-4 h-4 mr-2" />
                                Send Email
                            </Button>
                        </div>
                    </div>


                </div>
            </div>
        </div>

        <!-- Upgrade Modal -->
        <div v-if="showUpgradeModal" class="absolute inset-0 flex items-center justify-center z-10">
            <div class="absolute inset-0 bg-black bg-opacity-50" @click="showUpgradeModal = false"></div>
            <Card class="relative max-w-sm mx-4 p-6 text-center">
                <div class="w-16 h-16 bg-orange-100 dark:bg-orange-900 rounded-full flex items-center justify-center mx-auto mb-4">
                    <Crown class="w-8 h-8 text-orange-600 dark:text-orange-400" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Coming Soon!</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4 text-sm">
                    We're working hard to bring you premium features. Stay tuned for updates!
                </p>
                <Button @click="showUpgradeModal = false" class="w-full">
                    Got it
                </Button>
            </Card>
        </div>
    </div>
</template>