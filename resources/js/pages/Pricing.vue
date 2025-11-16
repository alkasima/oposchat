<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import SiteHeader from '@/components/SiteHeader.vue';
import SiteFooter from '@/components/SiteFooter.vue';
import stripeService from '@/services/stripeService';

const page = usePage();

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
    }
};
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
                                    @click="$page.props.auth.user?.subscription_type === 'premium' ? managePlanFromPricing() : upgradeToPremiumFromPricing()"
                                    class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-blue-600 hover:to-purple-700 transition-all duración-300 block h-12 flex items-center justify-center whitespace-nowrap"
                                >
                                    {{ $page.props.auth.user?.subscription_type === 'premium' ? 'Manage Plan' : 'Mejorar al Premium' }}
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
                                    @click="$page.props.auth.user?.subscription_type === 'plus' ? managePlanFromPricing() : upgradeToPlusFromPricing()"
                                    class="w-full bg-gradient-to-r from-purple-500 to-pink-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-purple-600 hover:to-pink-700 transition-all duration-300 block h-12 flex items-center justify-center whitespace-nowrap"
                                >
                                    {{ $page.props.auth.user?.subscription_type === 'plus' ? 'Manage Plan' : 'Mejorar al Plus' }}
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
    </div>
</template>
