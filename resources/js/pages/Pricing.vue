<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import SiteHeader from '@/components/SiteHeader.vue';
import SiteFooter from '@/components/SiteFooter.vue';
import stripeService from '@/services/stripeService';
import PlanChangeConfirmationModal from '@/components/PlanChangeConfirmationModal.vue';
import SubscriptionSuccessModal from '@/components/SubscriptionSuccessModal.vue';
import { useSubscription } from '@/composables/useSubscription.js';

const page = usePage();

const activePlan = computed<string | null>(() => {
    const type = page.props.auth?.user?.subscription_type as string | undefined;
    return type ?? null;
});

const hasPremiumAccess = computed<boolean>(() => {
    return !!page.props.subscription?.has_premium;
});

const currentPlanName = computed<string | null>(() => {
    return (page.props.subscription?.current_plan_name as string | undefined) ?? null;
});

const pricingPlans = ref<any | null>(null);
const upgradingPlus = ref(false);

const priceDiffPremiumToPlus = computed<string | null>(() => {
    const plans = pricingPlans.value?.plans;
    if (!plans?.premium?.price || !plans?.plus?.price) {
        return null;
    }
    const diff = Number(plans.plus.price) - Number(plans.premium.price);
    if (!isFinite(diff) || diff <= 0) {
        return null;
    }
    return diff.toFixed(2);
});

const plusUpgradeLabel = computed<string>(() => {
    // Already on Plus -> manage current plan
    if (activePlan.value === 'plus' || currentPlanName.value === 'Plus') {
        return 'Manage plan';
    }

    // User has Premium subscription -> show dynamic upgrade difference
    if (hasPremiumAccess.value || activePlan.value === 'premium' || currentPlanName.value === 'Premium') {
        if (priceDiffPremiumToPlus.value) {
            return `Mejorar al Plus por €${priceDiffPremiumToPlus.value}`;
        }
        return 'Mejorar al Plus';
    }

    // Non-premium users
    return 'Mejorar al Plus';
});

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

const showPlanChangeModal = ref(false);
const pendingPlanChange = ref<PendingPlanChange | null>(null);
const planChangeLoading = ref(false);
const planChangeError = ref<string | null>(null);
const showPlanSuccessModal = ref(false);
const planSuccessModalData = ref({
    title: '¡Suscripción actualizada!',
    description: 'Actualizando tu cuenta...',
    statusLabel: 'Activo',
    planName: null as string | null,
    amount: null as number | null,
    currency: null as string | null,
    interval: null as string | null,
    nextBillingDate: null as string | null,
    receiptUrl: null as string | null,
});

const { refreshSubscriptionData } = useSubscription();

const ensurePricingPlansLoaded = async () => {
    if (!pricingPlans.value) {
        pricingPlans.value = await stripeService.getPlans();
    }
    return pricingPlans.value;
};

const startPlanChangeFromPricing = async (targetPlanKey: 'premium' | 'plus') => {
    if (!page.props.auth?.user) {
        router.visit(route('login'));
        return;
    }

    try {
        planChangeError.value = null;
        const plans = await ensurePricingPlansLoaded();
        const planConfig = plans?.plans ?? {};
        const targetPlan = planConfig[targetPlanKey];

        if (!targetPlan?.stripe_price_id) {
            throw new Error('El plan seleccionado no está disponible.');
        }

        const currentKey = (activePlan.value || page.props.auth?.user?.subscription_type || 'free') as string;
        const currentPlanConfig = planConfig[currentKey] ?? plans?.free_plan ?? { name: 'Free', price: 0, currency: targetPlan.currency ?? 'EUR' };
        const currentPrice = Number(currentPlanConfig.price ?? 0);
        const targetPrice = Number(targetPlan.price ?? 0);
        const priceDifference = targetPrice - currentPrice;

        pendingPlanChange.value = {
            priceId: targetPlan.stripe_price_id,
            currentPlan: {
                key: currentKey,
                name: currentPlanConfig.name ?? currentKey,
                price: currentPrice,
            },
            targetPlan: {
                key: targetPlanKey,
                name: targetPlan.name ?? targetPlanKey,
                price: targetPrice,
            },
            priceDifference,
            currency: (targetPlan.currency ?? 'EUR') as string,
            isUpgrade: priceDifference > 0,
            isDowngrade: priceDifference < 0,
        };

        showPlanChangeModal.value = true;
    } catch (error: any) {
        console.error('Failed to prepare plan change from pricing page:', error);
        planChangeError.value = error?.message ?? 'No se pudo preparar el cambio de plan.';
    }
};

const handlePlanChangeSuccessFromPricing = async (res: any, context: PendingPlanChange) => {
    if (res?.redirect_url) {
        window.location.href = res.redirect_url;
        return;
    }

    if (res?.status === 'scheduled' && res?.scheduled_plan_change) {
        planSuccessModalData.value = {
            title: 'Cambio programado',
            description: `Tu plan cambiará a ${context.targetPlan.name} al final de tu período de facturación actual.`,
            statusLabel: 'Pendiente',
            planName: context.targetPlan.name,
            amount: context.targetPlan.price,
            currency: context.currency,
            interval: 'mes',
            nextBillingDate: null,
            receiptUrl: res?.invoice_url ?? null,
        };
    } else {
        let nextBilling: string | null = null;
        try {
            const status = await stripeService.getSubscriptionStatus();
            nextBilling = status?.subscription?.current_period_end || null;
        } catch (error) {
            console.error('Failed to fetch latest subscription status for modal:', error);
        }

        planSuccessModalData.value = {
            title: '¡Suscripción actualizada!',
            description: 'Tu nuevo plan ya está activo. Puedes descargar el recibo o revisar los detalles de tu suscripción.',
            statusLabel: 'Activo',
            planName: context.targetPlan.name,
            amount: context.targetPlan.price,
            currency: context.currency,
            interval: 'mes',
            nextBillingDate: nextBilling,
            receiptUrl: res?.invoice_url ?? null,
        };
    }

    // Refresh global subscription/auth state so header and app use the new plan immediately
    try {
        await refreshSubscriptionData();
    } catch (error) {
        console.error('Failed to refresh subscription data after plan change:', error);
    }

    showPlanSuccessModal.value = true;
};

const confirmPlanChangeFromPricing = async () => {
    if (!pendingPlanChange.value) {
        return;
    }

    planChangeLoading.value = true;
    planChangeError.value = null;

    try {
        const res = await stripeService.upgrade(
            pendingPlanChange.value.priceId,
            true // Always confirm when user clicks the confirm button
        );

        showPlanChangeModal.value = false;
        await handlePlanChangeSuccessFromPricing(res, pendingPlanChange.value);
        pendingPlanChange.value = null;
    } catch (error: any) {
        console.error('Failed to change plan from pricing page:', error);
        planChangeError.value = error?.message ?? 'No se pudo completar el cambio de plan.';
    } finally {
        planChangeLoading.value = false;
    }
};

const cancelPlanChangeFromPricing = () => {
    showPlanChangeModal.value = false;
    pendingPlanChange.value = null;
    planChangeError.value = null;
};

const closePlanSuccessModal = () => {
    showPlanSuccessModal.value = false;
};

const managePlanFromPricing = async () => {
    if (!page.props.auth?.user) {
        router.visit(route('login'));
        return;
    }

    try {
        await stripeService.redirectToPortal();
    } catch (error) {
        console.error('Failed to open Stripe customer portal from pricing page:', error);
    }
};

const upgradeToPremiumFromPricing = async () => {
    if (!page.props.auth?.user) {
        router.visit(route('register'));
        return;
    }

    try {
        const plans = await stripeService.getPlans();
        const premiumPlan = plans.plans?.premium;
        const priceId = premiumPlan?.stripe_price_id;
        if (!priceId) {
            console.error('Premium price ID not found in plans config');
            return;
        }
        await stripeService.redirectToCheckout(priceId);
    } catch (error) {
        console.error('Failed to start Premium checkout from pricing page:', error);
    }
};

const upgradeToPlusFromPricing = async () => {
    if (!page.props.auth?.user) {
        router.visit(route('register'));
        return;
    }

    try {
        upgradingPlus.value = true;
        const plans = await stripeService.getPlans();
        const plusPlan = plans.plans?.plus;
        const priceId = plusPlan?.stripe_price_id;
        if (!priceId) {
            console.error('Plus price ID not found in plans config');
            return;
        }
        await stripeService.redirectToCheckout(priceId);
    } catch (error) {
        console.error('Failed to start Plus checkout from pricing page:', error);
    } finally {
        upgradingPlus.value = false;
    }
};

const handlePremiumCtaClick = async () => {
    if (!page.props.auth?.user) {
        router.visit(route('register'));
        return;
    }

    const currentKey = activePlan.value ?? (currentPlanName.value?.toLowerCase() === 'premium' ? 'premium' : 'free');

    if (currentKey === 'premium') {
        await managePlanFromPricing();
        return;
    }

    if (currentKey === 'plus') {
        await startPlanChangeFromPricing('premium');
        return;
    }

    await upgradeToPremiumFromPricing();
};

const handlePlusCtaClick = async () => {
    if (!page.props.auth?.user) {
        router.visit(route('register'));
        return;
    }

    const normalizedCurrent = currentPlanName.value?.toLowerCase();
    const currentKey = activePlan.value
        ?? (normalizedCurrent === 'plus' ? 'plus' : normalizedCurrent === 'premium' ? 'premium' : 'free');

    if (currentKey === 'plus') {
        await managePlanFromPricing();
        return;
    }

    if (currentKey === 'premium') {
        await startPlanChangeFromPricing('plus');
        return;
    }

    await upgradeToPlusFromPricing();
};

onMounted(async () => {
    try {
        pricingPlans.value = await stripeService.getPlans();
    } catch (error) {
        console.error('Failed to load plans for pricing page:', error);
    }
});

// No auto-hide timers for the success modal; it stays until user closes it.
</script>

<template>
    <Head title="Pricing - OposChat" />
    
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 dark:from-slate-900 dark:to-slate-800">
        <SiteHeader />
        <!-- Pricing Section mirrored from homepage -->
        <section id="pricing" class="py-20 bg-gradient-to-br from-gray-50 to-blue-50 dark:from-slate-900 dark:to-slate-800">
            <div class="container mx-auto px-4">
                <div class="text-center mb-16">
                    <h2 class="text-4xl lg:text-5xl font-bold text-gray-900 dark:text-white mb-6">
                        Escoge tu <span class="text-blue-600">Plan</span>
                    </h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                        Prueba sin coste y desbloquea más funciones cuando quieras. Tú decides cómo avanzar hacia tu plaza
                    </p>
                </div>
                
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8 max-w-7xl mx-auto">
                    <!-- Free -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-gray-200 hover:border-blue-300 transition-all duration-300 transform hover:-translate-y-2 flex flex-col">
                        <div class="p-8 flex flex-col flex-grow">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Free</h3>
                                <p class="text-gray-600 mb-4">Get started with basic features</p>
                                <div class="text-4xl font-bold text-gray-900 mb-2">
                                    €0
                                    <span class="text-lg font-normal text-gray-600">/mes</span>
                                </div>
                            </div>
                            
                            <ul class="space-y-4 mb-8">
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">3 mensajes al día</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Soporte comunitario</span>
                                </li>
                            </ul>
                            
                            <div class="mt-auto">
                                <Link 
                                    v-if="!$page.props.auth.user"
                                    :href="route('register')"
                                    class="w-full bg-gray-200 text-gray-800 py-3 px-6 rounded-xl font-semibold text-center hover:bg-gray-300 transition-colors duration-300 block h-12 flex items-center justify-center whitespace-nowrap"
                                >
                                    Empieza gratis
                                </Link>
                                <Link 
                                    v-else
                                    :href="route('dashboard')"
                                    class="w-full bg-gray-200 text-gray-800 py-3 px-6 rounded-xl font-semibold text-center hover:bg-gray-300 transition-colors duration-300 block h-12 flex items-center justify-center whitespace-nowrap"
                                >
                                    Empieza con el estudio
                                </Link>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Premium  -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-blue-500 relative transform hover:-translate-y-2 transition-all duration-300 flex flex-col">
                        <div class="absolute top-0 left-0 right-0 bg-gradient-to-r from-blue-500 to-purple-600 text-white text-center py-2 text-sm font-semibold">
                            POPULAR
                        </div>
                        <div class="p-8 pt-12 flex flex-col flex-grow">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Premium</h3>
                                <p class="text-gray-600 mb-4">Perfecto para opositores que quieren una ayuda constante y acceso a materiales de práctica</p>
                                <div class="text-4xl font-bold text-blue-600 mb-2">
                                    €9.99
                                    <span class="text-lg font-normal text-gray-600">/mes</span>
                                </div>
                            </div>
                            
                            <ul class="space-y-4 mb-8">
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">200 mensajes al mes</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Generador de tablas y resúmenes</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Guías de estudio personalizadas</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Soporte técnico prioritario</span>
                                </li>
                            </ul>
                            
                            <div class="mt-auto">
                                <Link 
                                    v-if="!$page.props.auth.user"
                                    :href="route('register')"
                                    class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-blue-600 hover:to-purple-700 transition-all duration-300 block h-12 flex items-center justify-center whitespace-nowrap"
                                >
                                    Mejorar al Premium
                                </Link>
                                <button
                                    v-else
                                    type="button"
                                    @click="handlePremiumCtaClick"
                                    class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-blue-600 hover:to-purple-700 transition-all duration-300 block h-12 flex items-center justify-center whitespace-nowrap"
                                >
                                    {{
                                        activePlan === 'premium'
                                            ? 'Manage plan'
                                            : activePlan === 'plus'
                                                ? 'Downgrade to Premium'
                                                : 'Mejorar al Premium'
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Plan Plus -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-gray-200 hover:border-purple-300 transition-all duration-300 transform hover:-translate-y-2 flex flex-col">
                        <div class="p-8 flex flex-col flex-grow">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Plus</h3>
                                <p class="text-gray-600 mb-4">La mejor opción para opositores que buscan entrenar a fondo y sin límites</p>
                                <div class="text-4xl font-bold text-purple-600 mb-2">
                                    €14.99
                                    <span class="text-lg font-normal text-gray-600">/mes</span>
                                </div>
                            </div>
                            
                            <ul class="space-y-4 mb-8">
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Mensajes ilimitados</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Generador de tablas y resúmenes</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Acceso completo a tests actualizados y simulacros</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Soporte técnico prioritario</span>
                                </li>
                            </ul>
                            
                            <div class="mt-auto">
                                <Link 
                                    v-if="!$page.props.auth.user"
                                    :href="route('register')"
                                    class="w-full bg-gradient-to-r from-purple-500 to-pink-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-purple-600 hover:to-pink-700 transition-all duration-300 block h-12 flex items-center justify-center whitespace-nowrap"
                                >
                                    Mejorar al Plus
                                </Link>
                                <button
                                    v-else
                                    type="button"
                                    @click="handlePlusCtaClick"
                                    :disabled="upgradingPlus"
                                    class="w-full bg-gradient-to-r from-purple-500 to-pink-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-purple-600 hover:to-pink-700 transition-all duration-300 block h-12 flex items-center justify-center whitespace-nowrap"
                                >
                                    {{ upgradingPlus ? 'Procesando...' : plusUpgradeLabel }}
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Academias -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-gray-200 hover:border-orange-300 transition-all duration-300 transform hover:-translate-y-2 flex flex-col">
                        <div class="p-8 flex flex-col flex-grow">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Academias</h3>
                                <p class="text-gray-600 mb-4">Pensado para academias y grupos de preparación de opositores</p>
                                <div class="text-2xl font-bold text-orange-600 mb-2">
                                    Precio personalizado
                                </div>
                                <p class="text-sm text-gray-500">Consulta el precio que se adapta a tus características</p>
                            </div>
                            
                            <ul class="space-y-4 mb-8">
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Soporte técnico avanzado</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Adaptamos el chat a tu temario</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Asistencia didáctica de tus clientes 24 h</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Access to exams</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Priority technical support</span>
                                </li>
                            </ul>
                            
                            <div class="mt-auto">
                                <Link 
                                    v-if="!$page.props.auth.user"
                                    :href="route('academy.contact')"
                                    class="w-full bg-gradient-to-r from-orange-500 to-red-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-orange-600 hover:to-red-700 transition-all duration-300 block h-12 flex items-center justify-center whitespace-nowrap"
                                >
                                    Consultar precio
                                </Link>
                                <button
                                    v-else-if="activePlan === 'academias'"
                                    type="button"
                                    @click="managePlanFromPricing()"
                                    class="w-full bg-gradient-to-r from-orange-500 to-red-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-orange-600 hover:to-red-700 transition-all duration-300 block h-12 flex items-center justify-center whitespace-nowrap"
                                >
                                    Manage plan
                                </button>
                                <Link 
                                    v-else
                                    :href="route('academy.contact')"
                                    class="w-full bg-gradient-to-r from-orange-500 to-red-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-orange-600 hover:to-red-700 transition-all duration-300 block h-12 flex items-center justify-center whitespace-nowrap"
                                >
                                    Consultar precio
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-12">
                    <p class="text-gray-600 dark:text-gray-300 mb-4">All plans include a 14-day free trial. No credit card required.</p>
                    <Link 
                        :href="route('pricing')"
                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-semibold underline"
                    >
                        Ver comparación detallada de los precios →
                    </Link>
                </div>
            </div>
        </section>
        <SiteFooter />

        <PlanChangeConfirmationModal
            v-if="pendingPlanChange"
            :show="showPlanChangeModal"
            :current-plan="pendingPlanChange.currentPlan"
            :target-plan="pendingPlanChange.targetPlan"
            :price-difference="pendingPlanChange.priceDifference"
            :currency="pendingPlanChange.currency"
            :is-upgrade="pendingPlanChange.isUpgrade"
            :is-downgrade="pendingPlanChange.isDowngrade"
            :loading="planChangeLoading"
            :error-message="planChangeError"
            @confirm="confirmPlanChangeFromPricing"
            @cancel="cancelPlanChangeFromPricing"
        />

        <SubscriptionSuccessModal
            :show="showPlanSuccessModal"
            :plan-name="planSuccessModalData.planName ?? undefined"
            :title="planSuccessModalData.title"
            :description="planSuccessModalData.description"
            :status-label="planSuccessModalData.statusLabel"
            :price-amount="planSuccessModalData.amount ?? undefined"
            :price-currency="planSuccessModalData.currency ?? undefined"
            :interval="planSuccessModalData.interval ?? undefined"
            :next-billing-date="planSuccessModalData.nextBillingDate ?? undefined"
            :receipt-url="planSuccessModalData.receiptUrl ?? undefined"
            @close="closePlanSuccessModal"
        />
    </div>
</template>
