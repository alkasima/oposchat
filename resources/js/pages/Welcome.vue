<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import SiteHeader from '@/components/SiteHeader.vue';
import SiteFooter from '@/components/SiteFooter.vue';
import stripeService from '@/services/stripeService';
import PlanChangeConfirmationModal from '@/components/PlanChangeConfirmationModal.vue';
import SubscriptionSuccessModal from '@/components/SubscriptionSuccessModal.vue';

const isMenuOpen = ref(false);
const openFaq = ref(null);

const toggleFaq = (index) => {
    openFaq.value = openFaq.value === index ? null : index;
};

// Courses loaded from admin via API
const courses = ref<any[]>([]);
const loadingCourses = ref<boolean>(false);
const coursesError = ref<string | null>(null);

onMounted(async () => {
    try {
        loadingCourses.value = true;
        coursesError.value = null;
        const res = await fetch('/public/courses', { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error('Failed to load courses');
        const data = await res.json();
        // Expecting: id, name, slug, description, namespace, icon, color
        courses.value = Array.isArray(data) ? data : [];
    } catch (e: any) {
        coursesError.value = e?.message || 'Could not load courses';
        courses.value = [];
    } finally {
        loadingCourses.value = false;
    }
});

// Handle exam selection for logged-in users
const selectCourse = async (course: any) => {
    // This will be called only for authenticated users due to the template condition

    try {
        const courseId = course.id;

        // Create or get exam-specific chat
        const response = await fetch('/api/chats', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                exam_type: course.slug || course.namespace || course.name,
                title: `${course.name} Chat`,
                course_id: courseId
            })
        });

        if (response.ok) {
            const data = await response.json();
            // Redirect to the chat with the exam context
            router.visit(route('chat', { chat: data.chat.id }));
        } else {
            console.error('No se pudo crear el chat de la oposición');
            // Fallback to general dashboard
            router.visit(route('dashboard'));
        }
    } catch (error) {
        console.error('Error selecting course:', error);
        // Fallback to general dashboard
        router.visit(route('dashboard'));
    }
};

const openMoreInfo = ref<string | null>(null);

const toggleMoreInfo = (id: string) => {
    openMoreInfo.value = openMoreInfo.value === id ? null : id;
};

// Subscription helpers for homepage pricing section
const page = usePage();

const homepageActivePlan = computed<string | null>(() => {
    const type = page.props.auth?.user?.subscription_type as string | undefined;
    return type ?? null;
});

const homepageHasPremiumAccess = computed<boolean>(() => {
    return !!page.props.subscription?.has_premium;
});

const homepageCurrentPlanName = computed<string | null>(() => {
    return (page.props.subscription?.current_plan_name as string | undefined) ?? null;
});

const homepagePlans = ref<any | null>(null);

const homepagePriceDiffPremiumToPlus = computed<string | null>(() => {
    const plans = homepagePlans.value?.plans;
    if (!plans?.premium?.price || !plans?.plus?.price) {
        return null;
    }
    const diff = Number(plans.plus.price) - Number(plans.premium.price);
    if (!isFinite(diff) || diff <= 0) {
        return null;
    }
    return diff.toFixed(2);
});

const homepagePlusUpgradeLabel = computed<string>(() => {
    // Already on Plus -> manage current plan
    if (homepageActivePlan.value === 'plus' || homepageCurrentPlanName.value === 'Plus') {
        return 'Manage plan';
    }

    // User has Premium subscription -> show dynamic upgrade difference
    if (homepageHasPremiumAccess.value || homepageActivePlan.value === 'premium' || homepageCurrentPlanName.value === 'Premium') {
        if (homepagePriceDiffPremiumToPlus.value) {
            return `Mejorar al Plus por €${homepagePriceDiffPremiumToPlus.value}`;
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
});
let planSuccessTimer: ReturnType<typeof setTimeout> | null = null;

const clearPlanSuccessTimer = () => {
    if (planSuccessTimer) {
        clearTimeout(planSuccessTimer);
        planSuccessTimer = null;
    }
};

const ensureHomepagePlansLoaded = async () => {
    if (!homepagePlans.value) {
        homepagePlans.value = await stripeService.getPlans();
    }
    return homepagePlans.value;
};

const startPlanChangeFromHome = async (targetPlanKey: 'premium' | 'plus') => {
    if (!page.props.auth?.user) {
        router.visit(route('login'));
        return;
    }

    try {
        planChangeError.value = null;
        const plans = await ensureHomepagePlansLoaded();
        const planConfig = plans?.plans ?? {};
        const targetPlan = planConfig[targetPlanKey];

        if (!targetPlan?.stripe_price_id) {
            throw new Error('El plan seleccionado no está disponible.');
        }

        const currentKey = (homepageActivePlan.value || page.props.auth?.user?.subscription_type || 'free') as string;
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
        console.error('Failed to prepare plan change from homepage:', error);
        planChangeError.value = error?.message ?? 'No se pudo preparar el cambio de plan.';
    }
};

const handlePlanChangeSuccessFromHome = (res: any, context: PendingPlanChange) => {
    if (res?.redirect_url) {
        window.location.href = res.redirect_url;
        return;
    }

    if (res?.status === 'scheduled' && res?.scheduled_plan_change) {
        planSuccessModalData.value = {
            title: 'Cambio programado',
            description: `Tu plan cambiará a ${context.targetPlan.name} al final de tu período de facturación actual. Te avisaremos cuando se complete.`,
            statusLabel: 'Pendiente',
            planName: context.targetPlan.name,
        };
    } else {
        planSuccessModalData.value = {
            title: '¡Suscripción actualizada!',
            description: 'Estamos procesando tu cambio de plan. Actualizaremos esta página automáticamente.',
            statusLabel: 'Activo',
            planName: context.targetPlan.name,
        };
    }

    showPlanSuccessModal.value = true;
    clearPlanSuccessTimer();
    planSuccessTimer = window.setTimeout(() => {
        window.location.reload();
    }, 2000);
};

const confirmPlanChangeFromHome = async () => {
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
        handlePlanChangeSuccessFromHome(res, pendingPlanChange.value);
        pendingPlanChange.value = null;
    } catch (error: any) {
        console.error('Failed to change plan from homepage:', error);
        planChangeError.value = error?.message ?? 'No se pudo completar el cambio de plan.';
    } finally {
        planChangeLoading.value = false;
    }
};

const cancelPlanChangeFromHome = () => {
    showPlanChangeModal.value = false;
    pendingPlanChange.value = null;
    planChangeError.value = null;
};

const closePlanSuccessModal = () => {
    showPlanSuccessModal.value = false;
    clearPlanSuccessTimer();
};

const managePlanFromHome = async () => {
    // Only for authenticated users
    if (!page.props.auth?.user) {
        router.visit(route('login'));
        return;
    }

    try {
        await stripeService.redirectToPortal();
    } catch (error) {
        console.error('Failed to open Stripe customer portal from homepage:', error);
    }
};

const upgradeToPremiumFromHome = async () => {
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
        console.error('Failed to start Premium checkout from homepage:', error);
    }
};

const upgradeToPlusFromHome = async () => {
    if (!page.props.auth?.user) {
        router.visit(route('register'));
        return;
    }

    try {
        const plans = await stripeService.getPlans();
        const plusPlan = plans.plans?.plus;
        const priceId = plusPlan?.stripe_price_id;
        if (!priceId) {
            console.error('Plus price ID not found in plans config');
            return;
        }

        await stripeService.redirectToCheckout(priceId);
    } catch (error) {
        console.error('Failed to start Plus checkout from homepage:', error);
    }
};

const handlePremiumCtaClick = async () => {
    if (!page.props.auth?.user) {
        router.visit(route('register'));
        return;
    }

    const currentKey = homepageActivePlan.value ?? (homepageCurrentPlanName.value?.toLowerCase() === 'premium' ? 'premium' : 'free');

    if (currentKey === 'premium') {
        await managePlanFromHome();
        return;
    }

    if (currentKey === 'plus') {
        await startPlanChangeFromHome('premium');
        return;
    }

    await upgradeToPremiumFromHome();
};

const handlePlusCtaClick = async () => {
    if (!page.props.auth?.user) {
        router.visit(route('register'));
        return;
    }

    const currentKey = homepageActivePlan.value ?? (homepageCurrentPlanName.value?.toLowerCase() === 'plus' ? 'plus' : homepageCurrentPlanName.value?.toLowerCase() === 'premium' ? 'premium' : 'free');

    if (currentKey === 'plus') {
        await managePlanFromHome();
        return;
    }

    if (currentKey === 'premium') {
        await startPlanChangeFromHome('plus');
        return;
    }

    await upgradeToPlusFromHome();
};

// Load plans once for homepage pricing labels
onMounted(async () => {
    try {
        homepagePlans.value = await stripeService.getPlans();
    } catch (error) {
        console.error('Failed to load plans for homepage pricing section:', error);
    }
});

onUnmounted(() => {
    clearPlanSuccessTimer();
});

const faqData = [
    { 
        question: '¿Qué es OposChat y en qué me puede ayudar?', 
        answer: 'OposChat es un asistente de estudio basado en inteligencia artificial diseñado específicamente para preparar oposiciones. Utiliza los temarios oficiales y materiales de estudio actualizados para responder tus dudas, ayudarte a repasar y organizar tu preparación de forma más eficaz.' 
    },
    { 
        question: '¿En qué se diferencia de ChatGPT u otras inteligencias artificiales?', 
        answer: 'A diferencia de un chat genérico, OposChat está entrenado con contenidos específicos de las oposiciones. Esto significa que no inventa respuestas, sino que utiliza únicamente la información contenida en los temarios para darte explicaciones dinámicas, fiables y adaptadas a tu examen.' 
    },
    { 
        question: '¿Necesito subir mis apuntes o documentos?', 
        answer: 'No. OposChat ya cuenta con los temarios y materiales necesarios para cada oposición. Tú solo tienes que empezar a preguntar y estudiar con nuestro asistente virtual.' 
    },
    { 
        question: '¿Qué oposiciones cubre OposChat actualmente?', 
        answer: 'Actualmente OposChat está enfocado en las oposiciones que puedes encontrar en la página principal, y seguimos trabajando para incorporar más oposiciones en el futuro.' 
    },
    { 
        question: '¿Hasta qué punto son fiables las respuestas de OposChat?', 
        answer: 'OposChat utiliza un sistema avanzado de inteligencia artificial (RAG) que se apoya exclusivamente en los temarios oficiales de cada oposición. De esta manera, las respuestas no son inventadas ni genéricas, sino explicaciones claras y fundamentadas en el material real de estudio' 
    }
];
</script>

<template>
    <Head title="OposChat - AI-Powered Exam Preparation">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>
    
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 dark:from-slate-900 dark:to-slate-950 font-inter">
        <SiteHeader />

        <!-- Hero Section -->
        <section id="home" class="relative py-20 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50"></div>
            <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-900 to-black opacity-0 dark:opacity-100"></div>
            <div class="absolute inset-0 opacity-40" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%239C92AC&quot; fill-opacity=&quot;0.05&quot;%3E%3Ccircle cx=&quot;30&quot; cy=&quot;30&quot; r=&quot;4&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')"></div>
            
            <div class="container mx-auto px-4 relative z-10">
                <div class="flex flex-col lg:flex-row items-center justify-between">
                    <div class="lg:w-3/5 mb-12 lg:mb-0">
                        <div class="inline-flex items-center bg-white/80 dark:bg-white/10 backdrop-blur-sm rounded-full px-4 py-2 mb-6 shadow-lg">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Estudio avanzado con IA</span>
                        </div>
                        
                        <h1 class="text-5xl lg:text-7xl font-bold text-gray-900 dark:text-white mb-6 leading-tight">
                            <span v-if="$page.props.auth.user">
                                Bienvenido,
                                <span class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent">
                                    {{ $page.props.auth.user.name }}
                                </span>
                                <br />Tu compañero de estudio para sacarte tu plaza
                            </span>
                            <span v-else>
                                Domina tus 
                                <span class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent">
                                    Oposiciones
                                </span>
                                <br />con IA
                            </span>
                        </h1>
                        
                        <p class="text-xl text-gray-600 dark:text-gray-300 leading-relaxed mb-8 max-w-2xl">
                            <span v-if="$page.props.auth.user">
                                Nuestra plataforma ofrece un chat con inteligencia artificial diseñado específicamente para opositores. Con él podrás resolver dudas al instante, generar test y simulacros personalizados, resumir temarios y organizar tu estudio de forma más eficiente. Nuestro objetivo es acompañarte en cada paso de la preparación para que avances con confianza y aumentes tus posibilidades de conseguir la plaza.

                            </span>
                            <span v-else>
                                Desde <span class="font-semibold text-blue-600">OposChat</span>, hemos integrado la  
                                <span class="font-semibold text-purple-600">Inteligencia Artificial</span> con
                                <span class="font-semibold text-cyan-600">temario actualizado</span> y 
                                <span class="font-semibold text-green-600">oficial</span> 
                                para  
                                <span class="font-semibold text-red-600">ayudarte</span> a
                                <span class="font-semibold text-blue-600">aprobar</span> tus oposiciones.
                            </span>
                        </p>

                        <div class="flex flex-col sm:flex-row gap-4">
                            <a 
                                v-if="$page.props.auth.user"
                                href="#courses"
                                class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-4 rounded-2xl font-semibold text-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-300 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 inline-block text-center"
                            >
                                Escoge tu oposición
                            </a>
                            <Link 
                                v-else
                                :href="route('exams.wiki')"
                                class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-4 rounded-2xl font-semibold text-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-300 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 inline-block text-center"
                            >
                                Empieza a estudiar gratis
                            </Link>
                            <Link 
                                v-if="$page.props.auth.user"
                                :href="route('dashboard')"
                                class="border-2 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 px-8 py-4 rounded-2xl font-semibold text-lg hover:border-blue-500 dark:hover:border-blue-400 hover:text-blue-600 dark:hover:text-blue-300 transition-all duration-300 bg-white/80 dark:bg-white/10 backdrop-blur-sm inline-block text-center"
                            >
                                Entrar al chat
                            </Link>
                            <button 
                                v-else
                                class="border-2 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 px-8 py-4 rounded-2xl font-semibold text-lg hover:border-blue-500 dark:hover:border-blue-400 hover:text-blue-600 dark:hover:text-blue-300 transition-all duration-300 bg-white/80 dark:bg-white/10 backdrop-blur-sm"
                            >
                                Watch Demo
                            </button>
                        </div>
                    </div>
                    
                    <div class="lg:w-2/5 flex justify-center">
                        <div class="relative">
                            <div class="w-80 h-80 bg-gradient-to-br from-blue-400 via-purple-500 to-indigo-600 rounded-3xl flex items-center justify-center shadow-2xl transform hover:scale-105 transition-transform duration-500 hover:rotate-2">
                                <div class="text-center text-white">
                                    <div class="text-8xl mb-4 animate-bounce">🧠</div>
                                    <p class="text-2xl font-bold mb-2">Chat de IA para opositores</p>
                                    <p class="text-blue-100">Tu compañero de estudio las 24h</p>
                                </div>
                            </div>
                            <div class="absolute -top-4 -right-4 w-24 h-24 bg-yellow-400 rounded-full flex items-center justify-center shadow-lg animate-pulse">
                                <span class="text-2xl">⚡</span>
                            </div>
                            <div class="absolute -bottom-4 -left-4 w-20 h-20 bg-green-400 rounded-full flex items-center justify-center shadow-lg animate-bounce">
                                <span class="text-xl">📚</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Courses Section -->
        <section id="courses" class="py-20 relative">
            <div class="absolute inset-0 bg-gradient-to-r from-gray-900 via-blue-900 to-purple-900"></div>
            <div class="absolute inset-0 opacity-20" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;100&quot; height=&quot;100&quot; viewBox=&quot;0 0 100 100&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z&quot; fill=&quot;%23ffffff&quot; fill-opacity=&quot;0.05&quot; fill-rule=&quot;evenodd&quot;/%3E%3C/svg%3E')"></div>
            
            <div class="container mx-auto px-4 relative z-10">
                <div class="text-center mb-16">
                    <h2 class="text-4xl lg:text-5xl font-bold text-white mb-6">
                        <span v-if="$page.props.auth.user">Selecciona tu <span class="text-yellow-400">Oposición</span></span>
                        <span v-else>Cursos <span class="text-yellow-400">disponibles</span></span>
                    </h2>
                    <p class="text-xl text-gray-300 max-w-3xl mx-auto">
                        <span v-if="$page.props.auth.user">Selecciona la oposición para la que quieres prepararte y comienza tu chat personalizado.</span>
                        <span v-else>Elige entre nuestra amplia selección de oposiciones</span>
                    </p>
                    <div class="mt-4">
                        <Link :href="route('exams.wiki')" class="text-yellow-300 hover:text-yellow-200 font-semibold underline">
                            Información general sobre las oposiciones →
                        </Link>
                    </div>
                </div>
                
                <div class="grid md:grid-cols-2 gap-8 max-w-6xl mx-auto mb-12">
                    <div 
                        v-if="!loadingCourses && !coursesError"
                        v-for="course in courses" 
                        :key="course.id"
                        @click="$page.props.auth.user ? selectCourse(course) : null"
                        class="group bg-white/10 backdrop-blur-sm border border-white/20 rounded-2xl p-8 hover:bg-white/20 transition-all duration-300 transform hover:-translate-y-2 hover:shadow-2xl"
                        :class="{ 'cursor-pointer': $page.props.auth.user, 'cursor-default': !$page.props.auth.user }"
                    >
                        <div class="flex items-center mb-4">
                            <div :class="`w-12 h-12 bg-gradient-to-br ${course.color || 'from-blue-400 to-blue-600'} rounded-xl flex items-center justify-center mr-4`">
                                <span class="text-white text-xl">{{ course.icon || '📘' }}</span>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white group-hover:text-yellow-300 transition-colors">
                                    {{ course.name }}
                                </h3>
                                <p class="text-gray-300 text-sm line-clamp-2">{{ course.description || 'Course description' }}</p>
                            </div>
                        </div>
                        <p class="text-gray-300 mb-4" v-if="course.full_description">{{ course.full_description }}</p>
                        <div class="flex items-center justify-between">
                            <span class="inline-block bg-blue-600 text-white px-3 py-1 rounded-full text-sm font-medium">
                                Más información
                            </span>
                            <span class="text-yellow-400 font-semibold">&nbsp;</span>
                        </div>
                        <div class="mt-4">
                            <button type="button" @click.stop="toggleMoreInfo(course.id)" class="inline-flex items-center px-3 py-1 text-sm rounded-full bg-blue-500 text-white hover:bg-blue-600">
                                + MÁS INFORMACIÓN
                            </button>
                        </div>
                        <div v-if="openMoreInfo === course.id" class="mt-4 p-4 rounded-xl bg-white/5 border border-white/10 text-gray-2 00">
                            <p class="mb-3">{{ course.full_description || course.description || 'Learn more about this exam in our Wiki.' }}</p>
                            <Link @click.stop :href="`${route('exams.wiki')}#${course.slug || course.namespace || ''}`" class="text-yellow-300 hover:text-yellow-200 font-semibold underline">Read detailed info in the Wiki →</Link>
                        </div>
                <div v-if="loadingCourses" class="text-center text-gray-300 mb-8">Loading courses...</div>
                <div v-if="coursesError" class="text-center text-red-300 mb-8">{{ coursesError }}</div>
                        
                        <!-- Action indicator for logged-in users -->
                        <div v-if="$page.props.auth.user" class="mt-4 pt-4 border-t border-white/20">
                            <div class="flex items-center justify-center text-yellow-300 font-medium text-sm">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                                Comienza tu aprendizaje
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <Link 
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="bg-gradient-to-r from-yellow-400 to-orange-500 text-gray-900 px-10 py-4 rounded-2xl font-bold text-lg hover:from-yellow-500 hover:to-orange-600 transition-all duration-300 shadow-2xl hover:shadow-3xl transform hover:-translate-y-1 inline-block"
                    >
                        IR A MI CHAT
                    </Link>
                    <Link 
                        v-else
                        :href="route('register')"
                        class="bg-gradient-to-r from-yellow-400 to-orange-500 text-gray-900 px-10 py-4 rounded-2xl font-bold text-lg hover:from-yellow-500 hover:to-orange-600 transition-all duration-300 shadow-2xl hover:shadow-3xl transform hover:-translate-y-1 inline-block"
                    >
                        ¡COMIENZA YA!
                    </Link>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="py-20 bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-slate-900 dark:to-slate-800">
            <div class="container mx-auto px-4">
                <div class="text-center mb-16">
                    <h2 class="text-4xl lg:text-5xl font-bold text-gray-900 dark:text-white mb-6">
                        ¿Qué <span class="text-blue-600">ofrecemos?</span>
                    </h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                        Comienza a estudiar ayudándote de la tecnología más novedosa
                    </p>
                </div>
                
                <div class="grid md:grid-cols-3 gap-8 max-w-7xl mx-auto">
                    <div class="group bg-white dark:bg-slate-800 rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2">
                        <div class="bg-gradient-to-r from-blue-600 to-purple-600 p-8 text-center relative overflow-hidden">
                            <div class="absolute inset-0 opacity-30" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; viewBox=&quot;0 0 40 40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;0.1&quot;%3E%3Cpath d=&quot;M20 20c0 11.046-8.954 20-20 20v20h40V20H20z&quot;/%3E%3C/g%3E%3C/svg%3E')"></div>
                            <div class="relative z-10">
                                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                                    <span class="text-3xl">🧠</span>
                                </div>
                                <h3 class="text-xl font-bold text-white mb-2">Aprendizaje impulsado por IA</h3>
                                <p class="text-blue-100 text-sm">Estudia a tu ritmo, la IA se adapta a tu plan de estudio</p>
                            </div>
                        </div>
                        <div class="p-8 bg-gradient-to-br from-blue-50 to-purple-50 dark:from-slate-800 dark:to-slate-800">
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                Planes de estudio personalizados según tu nivel y ritmo de aprendizaje. La IA detecta tus puntos fuertes y débiles, y genera test y ejercicios adaptados para que avances de forma más eficiente.
                            </p>
                        </div>
                    </div>
                    
                    <div class="group bg-white dark:bg-slate-800 rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2">
                        <div class="bg-gradient-to-r from-green-600 to-teal-600 p-8 text-center relative overflow-hidden">
                            <div class="absolute inset-0 opacity-30" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; viewBox=&quot;0 0 40 40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;0.1&quot;%3E%3Cpath d=&quot;M20 20c0 11.046-8.954 20-20 20v20h40V20H20z&quot;/%3E%3C/g%3E%3C/svg%3E')"></div>
                            <div class="relative z-10">
                                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                                    <span class="text-3xl">📚</span>
                                </div>
                                <h3 class="text-xl font-bold text-white mb-2">Contenido siempre actualizado</h3>
                                <p class="text-green-100 text-sm">Material de estudio basado en los últimos temarios oficiales </p>
                            </div>
                        </div>
                        <div class="p-8 bg-gradient-to-br from-green-50 to-teal-50 dark:from-slate-800 dark:to-slate-800">
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                Realiza junto al chat preguntas tipo test, simulacros y resúmenes basados en los últimos temarios y patrones de examen oficiales. Estudia con materiales confiables y al día.
                            </p>
                        </div>
                    </div>
                    
                    <div class="group bg-white dark:bg-slate-800 rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2">
                        <div class="bg-gradient-to-r from-orange-600 to-red-600 p-8 text-center relative overflow-hidden">
                            <div class="absolute inset-0 opacity-30" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; viewBox=&quot;0 0 40 40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;0.1&quot;%3E%3Cpath d=&quot;M20 20c0 11.046-8.954 20-20 20v20h40V20H20z&quot;/%3E%3C/g%3E%3C/svg%3E')"></div>
                            <div class="relative z-10">
                                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                                    <span class="text-3xl">🎯</span>
                                </div>
                                <h3 class="text-xl font-bold text-white mb-2">Acompañamiento en tu preparación</h3>
                                <p class="text-orange-100 text-sm">Organizate y mantente enfocado en tu objetivo</p>
                            </div>
                        </div>
                        <div class="p-8 bg-gradient-to-br from-orange-50 to-red-50 dark:from-slate-800 dark:to-slate-800">
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                Recibe orientación paso a paso con nuestro chat inteligente, que te guía en la organización del estudio, resuelve tus dudas y te mantiene enfocado en tu objetivo: conseguir la plaza.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
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
                    <!-- Plan Gratis -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-gray-200 hover:border-blue-300 transition-all duration-300 transform hover:-translate-y-2 flex flex-col">
                        <div class="p-8 flex flex-col flex-grow">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Free</h3>
                                <p class="text-gray-600 mb-4">Ideal para probar la herramienta antes de dar el salto.</p>
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
                                    class="w-full bg-gray-200 dark:bg-slate-700 text-gray-800 dark:text-gray-100 py-3 px-6 rounded-xl font-semibold text-center hover:bg-gray-300 dark:hover:bg-slate-600 transition-colors duration-300 block h-12 flex items-center justify-center whitespace-nowrap"
                                >
                                    Get Started Free
                                </Link>
                                <Link 
                                    v-else
                                    :href="route('dashboard')"
                                    class="w-full bg-gray-200 dark:bg-slate-700 text-gray-800 dark:text-gray-100 py-3 px-6 rounded-xl font-semibold text-center hover:bg-gray-300 dark:hover:bg-slate-600 transition-colors duration-300 block h-12 flex items-center justify-center whitespace-nowrap"
                                >
                                    Empieza con el estudio
                                </Link>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pro Plan -->
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
                                    <span class="text-gray-700">200 mensajes por mes</span>
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
                                    Start Free Trial
                                </Link>
                                <button
                                    v-else
                                    type="button"
                                    @click="handlePremiumCtaClick"
                                    class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-blue-600 hover:to-purple-700 transition-all duration-300 block h-12 flex items-center justify-center whitespace-nowrap"
                                >
                                    {{
                                        $page.props.auth.user?.subscription_type === 'premium'
                                            ? 'Manage Plan'
                                            : $page.props.auth.user?.subscription_type === 'plus'
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
                                    Empieza gratis
                                </Link>
                                <button
                                    v-else
                                    type="button"
                                    @click="handlePlusCtaClick"
                                    class="w-full bg-gradient-to-r from-purple-500 to-pink-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-purple-600 hover:to-pink-700 transition-all duration-300 block h-12 flex items-center justify-center whitespace-nowrap"
                                >
                                    {{ homepagePlusUpgradeLabel }}
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Plan para Academias -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-gray-200 hover:border-orange-300 transition-all duration-300 transform hover:-translate-y-2 flex flex-col">
                        <div class="p-8 flex flex-col flex-grow">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Academias</h3>
                                <p class="text-gray-600 mb-4">Pensado para academias y grupos de preparación de oposiciones</p>
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
                                    <span class="text-gray-700">Advanced analytics</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Unlimited messages</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Upload files</span>
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
                    <p class="text-gray-600 dark:text-gray-300 mb-4">Todos los planes incluyen una prueba gratuita de 7 días.</p>
                    <Link 
                        :href="route('pricing')"
                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-semibold underline"
                    >
                        Compara las diferentes tarifas →
                    </Link>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 bg-gradient-to-r from-indigo-900 via-purple-900 to-pink-900 relative overflow-hidden">
            <div class="absolute inset-0 opacity-20" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;0.1&quot;%3E%3Cpath d=&quot;M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')"></div>
            
            <div class="container mx-auto px-4 text-center relative z-10">
                <div class="max-w-4xl mx-auto">
                    <h2 class="text-4xl lg:text-6xl font-bold text-white mb-6">
                        ¿Preparado para enfrentar tu <span class="text-yellow-400">examen?</span>
                    </h2>
                    <p class="text-xl text-gray-300 mb-10 leading-relaxed">
                        Miles de estudiantes ya confían en OposChat ¿Te atreves?
                    </p>
                    
                    <div class="flex flex-col sm:flex-row gap-6 justify-center items-center mb-8">
                        <button class="bg-gradient-to-r from-yellow-400 to-orange-500 text-gray-900 px-10 py-4 rounded-2xl font-bold text-lg hover:from-yellow-500 hover:to-orange-600 transition-all duration-300 shadow-2xl hover:shadow-3xl transform hover:-translate-y-1 hover:scale-105">
                            Empieza tu plan de estudio HOY!
                        </button>
                        <a href="#pricing" class="border-2 border-white text-white px-10 py-4 rounded-2xl font-bold text-lg hover:bg-white hover:text-gray-900 transition-all duration-300 inline-block">
                            Mirar precio de los planes de estudio 
                        </a>
                    </div>
                    
                    <p class="text-sm text-gray-400">
                        (Utiliza el método de pago que mejor se adapte a ti)
                    </p>
                    
                    <div class="flex justify-center items-center space-x-8 mt-12 opacity-60">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-white">50K+</div>
                            <div class="text-gray-300 text-sm">Estudiantes</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-white">95%</div>
                            <div class="text-gray-300 text-sm">Tasa de éxito</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-white">4.9★</div>
                            <div class="text-gray-300 text-sm">Valoración</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="py-20 bg-gradient-to-br from-gray-50 to-blue-50 dark:from-slate-900 dark:to-slate-800">
            <div class="container mx-auto px-4">
                <div class="text-center mb-16">
                    <h2 class="text-4xl lg:text-5xl font-bold text-gray-900 dark:text-white mb-6">
                       Preguntas <span class="text-blue-600">frecuentes</span>
                    </h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                        Obten respuestas a las preguntas más comunes sobre nuestra plataforma
                    </p>
                </div>
                
                <div class="max-w-4xl mx-auto space-y-4">
                    <div v-for="(faq, index) in faqData" :key="index" class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg overflow-hidden">
                        <button 
                            @click="toggleFaq(index)"
                            class="w-full p-6 text-left hover:bg-blue-50 dark:hover:bg-slate-700 transition-all duration-300 flex justify-between items-center group"
                        >
                            <span class="font-semibold text-gray-800 dark:text-gray-100 text-lg group-hover:text-blue-600 dark:group-hover:text-blue-400">{{ faq.question }}</span>
                            <svg 
                                class="w-6 h-6 text-gray-600 dark:text-gray-300 transform transition-transform duration-300"
                                :class="{ 'rotate-180': openFaq === index }"
                                fill="currentColor" 
                                viewBox="0 0 20 20"
                            >
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div 
                            v-if="openFaq === index"
                            class="px-6 pb-6 text-gray-600 dark:text-gray-300 leading-relaxed border-t border-gray-100 dark:border-slate-700 pt-4 animate-fadeIn"
                        >
                            {{ faq.answer }}
                        </div>
                    </div>
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
            @confirm="confirmPlanChangeFromHome"
            @cancel="cancelPlanChangeFromHome"
        />

        <SubscriptionSuccessModal
            :show="showPlanSuccessModal"
            :plan-name="planSuccessModalData.planName ?? undefined"
            :title="planSuccessModalData.title"
            :description="planSuccessModalData.description"
            :status-label="planSuccessModalData.statusLabel"
            @close="closePlanSuccessModal"
        />
    </div>
</template>

<style scoped>
.font-inter {
    font-family: 'Inter', sans-serif;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fadeIn {
    animation: fadeIn 0.3s ease-out;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f5f9;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(to bottom, #3b82f6, #8b5cf6);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(to bottom, #2563eb, #7c3aed);
}

/* Smooth transitions */
* {
    transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
}

/* Enhanced focus states */
button:focus,
a:focus,
input:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

/* Gradient text animation */
@keyframes gradient {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.animate-gradient {
    background-size: 200% 200%;
    animation: gradient 3s ease infinite;
}
</style>
