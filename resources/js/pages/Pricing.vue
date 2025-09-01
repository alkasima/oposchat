<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const plans = ref([]);
const freePlan = ref({});
const featureComparison = ref({});
const isLoading = ref(true);

// Fetch pricing data from API
const fetchPricingData = async () => {
    try {
        const response = await fetch('/api/subscriptions/plans');
        const data = await response.json();
        
        if (data.success) {
            plans.value = data.data.plans;
            freePlan.value = data.data.free_plan;
            featureComparison.value = data.data.feature_comparison;
        }
    } catch (error) {
        console.error('Failed to fetch pricing data:', error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchPricingData();
});

const formatPrice = (price) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(price);
};

const getFeatureValue = (feature, plan) => {
    const value = featureComparison.value[feature]?.[plan];
    if (typeof value === 'boolean') {
        return value ? '✓' : '✗';
    }
    return value || '—';
};
</script>

<template>
    <Head title="Pricing - OposChat" />
    
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
        <!-- Header -->
        <header class="bg-gradient-to-r from-indigo-900 via-blue-900 to-purple-900 shadow-2xl">
            <div class="container mx-auto px-4 py-4">
                <nav class="flex items-center justify-between">
                    <Link :href="route('home')" class="flex items-center space-x-4">
                        <div class="relative">
                            <div class="w-14 h-14 bg-gradient-to-br from-white to-white rounded-full flex items-center justify-center shadow-lg transform hover:scale-105 transition-transform duration-300 p-2">
                                <img src="/images/logo.png" alt="OposChat" class="w-full h-full rounded-full" />
                            </div>
                            <div class="absolute -top-1 -right-1 w-4 h-4 bg-green-400 rounded-full animate-pulse"></div>
                        </div>
                        <div>
                            <h1 class="text-white text-2xl font-bold bg-gradient-to-r from-white to-blue-200 bg-clip-text text-transparent">
                                OposChat
                            </h1>
                            <p class="text-blue-200 text-xs">AI-Powered Learning</p>
                        </div>
                    </Link>
                    
                    <div class="flex items-center space-x-4">
                        <Link :href="route('home')" class="text-white hover:text-yellow-300 transition-colors">
                            Home
                        </Link>
                        <Link 
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-6 py-2.5 rounded-full hover:from-yellow-500 hover:to-orange-600 transition-all duration-300 font-semibold shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                        >
                            Dashboard
                        </Link>
                        <Link 
                            v-else
                            :href="route('login')"
                            class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-6 py-2.5 rounded-full hover:from-yellow-500 hover:to-orange-600 transition-all duration-300 font-semibold shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                        >
                            Sign In
                        </Link>
                    </div>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="py-20">
            <div class="container mx-auto px-4 text-center">
                <h1 class="text-5xl lg:text-6xl font-bold text-gray-900 mb-6">
                    Simple, <span class="text-blue-600">Transparent</span> Pricing
                </h1>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto mb-12">
                    Choose the perfect plan for your learning journey. Start free and upgrade as you grow.
                </p>
            </div>
        </section>

        <!-- Loading State -->
        <div v-if="isLoading" class="flex justify-center items-center py-20">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
        </div>

        <!-- Pricing Cards -->
        <section v-else class="pb-20">
            <div class="container mx-auto px-4">
                <div class="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto mb-16">
                    <!-- Free Plan -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-gray-200 hover:border-blue-300 transition-all duration-300 transform hover:-translate-y-2">
                        <div class="p-8">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ freePlan.name }}</h3>
                                <p class="text-gray-600 mb-4">{{ freePlan.description }}</p>
                                <div class="text-4xl font-bold text-gray-900 mb-2">
                                    {{ formatPrice(freePlan.price) }}
                                    <span class="text-lg font-normal text-gray-600">/month</span>
                                </div>
                            </div>
                            
                            <ul class="space-y-4 mb-8">
                                <li v-for="feature in freePlan.features" :key="feature" class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">{{ feature }}</span>
                                </li>
                            </ul>
                            
                            <Link 
                                v-if="!$page.props.auth.user"
                                :href="route('register')"
                                class="w-full bg-gray-200 text-gray-800 py-3 px-6 rounded-xl font-semibold text-center hover:bg-gray-300 transition-colors duration-300 block"
                            >
                                Get Started Free
                            </Link>
                            <Link 
                                v-else
                                :href="route('dashboard')"
                                class="w-full bg-gray-200 text-gray-800 py-3 px-6 rounded-xl font-semibold text-center hover:bg-gray-300 transition-colors duration-300 block"
                            >
                                Current Plan
                            </Link>
                        </div>
                    </div>
                    
                    <!-- Pro Plan -->
                    <div v-if="plans.pro" class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-blue-500 relative transform hover:-translate-y-2 transition-all duration-300">
                        <div class="absolute top-0 left-0 right-0 bg-gradient-to-r from-blue-500 to-purple-600 text-white text-center py-2 text-sm font-semibold">
                            MOST POPULAR
                        </div>
                        <div class="p-8 pt-12">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ plans.pro.name }}</h3>
                                <p class="text-gray-600 mb-4">{{ plans.pro.description }}</p>
                                <div class="text-4xl font-bold text-blue-600 mb-2">
                                    {{ formatPrice(plans.pro.price) }}
                                    <span class="text-lg font-normal text-gray-600">/{{ plans.pro.interval }}</span>
                                </div>
                            </div>
                            
                            <ul class="space-y-4 mb-8">
                                <li v-for="feature in plans.pro.features" :key="feature" class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">{{ feature }}</span>
                                </li>
                            </ul>
                            
                            <Link 
                                v-if="!$page.props.auth.user"
                                :href="route('register')"
                                class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-blue-600 hover:to-purple-700 transition-all duration-300 block"
                            >
                                Start Free Trial
                            </Link>
                            <Link 
                                v-else
                                :href="route('dashboard')"
                                class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-blue-600 hover:to-purple-700 transition-all duration-300 block"
                            >
                                Upgrade to Pro
                            </Link>
                        </div>
                    </div>
                    
                    <!-- Team Plan -->
                    <div v-if="plans.team" class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-gray-200 hover:border-purple-300 transition-all duration-300 transform hover:-translate-y-2">
                        <div class="p-8">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ plans.team.name }}</h3>
                                <p class="text-gray-600 mb-4">{{ plans.team.description }}</p>
                                <div class="text-4xl font-bold text-purple-600 mb-2">
                                    {{ formatPrice(plans.team.price) }}
                                    <span class="text-lg font-normal text-gray-600">/{{ plans.team.interval }}</span>
                                </div>
                            </div>
                            
                            <ul class="space-y-4 mb-8">
                                <li v-for="feature in plans.team.features" :key="feature" class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">{{ feature }}</span>
                                </li>
                            </ul>
                            
                            <Link 
                                v-if="!$page.props.auth.user"
                                :href="route('register')"
                                class="w-full bg-gradient-to-r from-purple-500 to-pink-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-purple-600 hover:to-pink-700 transition-all duration-300 block"
                            >
                                Start Free Trial
                            </Link>
                            <Link 
                                v-else
                                :href="route('dashboard')"
                                class="w-full bg-gradient-to-r from-purple-500 to-pink-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-purple-600 hover:to-pink-700 transition-all duration-300 block"
                            >
                                Upgrade to Team
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Feature Comparison Table -->
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                    <div class="p-8">
                        <h3 class="text-2xl font-bold text-gray-900 mb-8 text-center">Feature Comparison</h3>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-4 px-6 font-semibold text-gray-900">Features</th>
                                        <th class="text-center py-4 px-6 font-semibold text-gray-900">Free</th>
                                        <th class="text-center py-4 px-6 font-semibold text-blue-600">Pro</th>
                                        <th class="text-center py-4 px-6 font-semibold text-purple-600">Team</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="border-b border-gray-100">
                                        <td class="py-4 px-6 font-medium text-gray-900">Chat Messages</td>
                                        <td class="py-4 px-6 text-center text-gray-600">{{ getFeatureValue('chat_messages', 'free') }}</td>
                                        <td class="py-4 px-6 text-center text-blue-600 font-semibold">{{ getFeatureValue('chat_messages', 'pro') }}</td>
                                        <td class="py-4 px-6 text-center text-purple-600 font-semibold">{{ getFeatureValue('chat_messages', 'team') }}</td>
                                    </tr>
                                    <tr class="border-b border-gray-100">
                                        <td class="py-4 px-6 font-medium text-gray-900">File Uploads</td>
                                        <td class="py-4 px-6 text-center text-gray-600">{{ getFeatureValue('file_uploads', 'free') }}</td>
                                        <td class="py-4 px-6 text-center text-blue-600 font-semibold">{{ getFeatureValue('file_uploads', 'pro') }}</td>
                                        <td class="py-4 px-6 text-center text-purple-600 font-semibold">{{ getFeatureValue('file_uploads', 'team') }}</td>
                                    </tr>
                                    <tr class="border-b border-gray-100">
                                        <td class="py-4 px-6 font-medium text-gray-900">Support</td>
                                        <td class="py-4 px-6 text-center text-gray-600">{{ getFeatureValue('support', 'free') }}</td>
                                        <td class="py-4 px-6 text-center text-blue-600 font-semibold">{{ getFeatureValue('support', 'pro') }}</td>
                                        <td class="py-4 px-6 text-center text-purple-600 font-semibold">{{ getFeatureValue('support', 'team') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="py-4 px-6 font-medium text-gray-900">Advanced Analytics</td>
                                        <td class="py-4 px-6 text-center text-red-500">{{ getFeatureValue('analytics', 'free') }}</td>
                                        <td class="py-4 px-6 text-center text-green-500">{{ getFeatureValue('analytics', 'pro') }}</td>
                                        <td class="py-4 px-6 text-center text-green-500">{{ getFeatureValue('analytics', 'team') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- FAQ Section -->
                <div class="mt-16">
                    <h3 class="text-2xl font-bold text-gray-900 mb-8 text-center">Frequently Asked Questions</h3>
                    
                    <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                        <div class="bg-white rounded-xl p-6 shadow-lg">
                            <h4 class="font-semibold text-gray-900 mb-2">Can I change plans anytime?</h4>
                            <p class="text-gray-600">Yes, you can upgrade or downgrade your plan at any time. Changes take effect immediately.</p>
                        </div>
                        
                        <div class="bg-white rounded-xl p-6 shadow-lg">
                            <h4 class="font-semibold text-gray-900 mb-2">Is there a free trial?</h4>
                            <p class="text-gray-600">Yes, all paid plans include a 14-day free trial. No credit card required to start.</p>
                        </div>
                        
                        <div class="bg-white rounded-xl p-6 shadow-lg">
                            <h4 class="font-semibold text-gray-900 mb-2">What payment methods do you accept?</h4>
                            <p class="text-gray-600">We accept all major credit cards and PayPal through our secure payment processor.</p>
                        </div>
                        
                        <div class="bg-white rounded-xl p-6 shadow-lg">
                            <h4 class="font-semibold text-gray-900 mb-2">Can I cancel anytime?</h4>
                            <p class="text-gray-600">Yes, you can cancel your subscription at any time. You'll continue to have access until the end of your billing period.</p>
                        </div>
                    </div>
                </div>

                <!-- CTA Section -->
                <div class="text-center mt-16 bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-12 text-white">
                    <h3 class="text-3xl font-bold mb-4">Ready to Get Started?</h3>
                    <p class="text-xl mb-8 opacity-90">Join thousands of students already using OposChat to ace their exams.</p>
                    
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <Link 
                            v-if="!$page.props.auth.user"
                            :href="route('register')"
                            class="bg-white text-blue-600 px-8 py-3 rounded-xl font-semibold hover:bg-gray-100 transition-colors duration-300"
                        >
                            Start Free Trial
                        </Link>
                        <Link 
                            v-else
                            :href="route('dashboard')"
                            class="bg-white text-blue-600 px-8 py-3 rounded-xl font-semibold hover:bg-gray-100 transition-colors duration-300"
                        >
                            Go to Dashboard
                        </Link>
                        <Link 
                            :href="route('home')"
                            class="border-2 border-white text-white px-8 py-3 rounded-xl font-semibold hover:bg-white hover:text-blue-600 transition-all duration-300"
                        >
                            Learn More
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>