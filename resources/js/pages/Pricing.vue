<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import SiteHeader from '@/components/SiteHeader.vue';
import SiteFooter from '@/components/SiteFooter.vue';
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
                        Choose Your <span class="text-blue-600">Plan</span>
                    </h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                        Start free and upgrade as you grow. All plans include our core AI-powered learning features.
                    </p>
                </div>
                
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8 max-w-7xl mx-auto">
                    <!-- Free Plan -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-gray-200 hover:border-blue-300 transition-all duration-300 transform hover:-translate-y-2">
                        <div class="p-8">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Free</h3>
                                <p class="text-gray-600 mb-4">Get started with basic features</p>
                                <div class="text-4xl font-bold text-gray-900 mb-2">
                                    €0
                                    <span class="text-lg font-normal text-gray-600">/month</span>
                                </div>
                            </div>
                            
                            <ul class="space-y-4 mb-8">
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">3 messages per day</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Community support</span>
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
                                :href="route('subscription', { plan: 'free' })"
                                class="w-full bg-gray-200 text-gray-800 py-3 px-6 rounded-xl font-semibold text-center hover:bg-gray-300 transition-colors duration-300 block"
                            >
                                Manage Plan
                            </Link>
                        </div>
                    </div>
                    
                    <!-- Premium Plan -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-blue-500 relative transform hover:-translate-y-2 transition-all duration-300">
                        <div class="absolute top-0 left-0 right-0 bg-gradient-to-r from-blue-500 to-purple-600 text-white text-center py-2 text-sm font-semibold">
                            POPULAR
                        </div>
                        <div class="p-8 pt-12">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Premium</h3>
                                <p class="text-gray-600 mb-4">Perfect for individuals and small teams</p>
                                <div class="text-4xl font-bold text-blue-600 mb-2">
                                    €9.99
                                    <span class="text-lg font-normal text-gray-600">/month</span>
                                </div>
                            </div>
                            
                            <ul class="space-y-4 mb-8">
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">200 messages per month</span>
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
                            
                            <Link 
                                v-if="!$page.props.auth.user"
                                :href="route('register')"
                                class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-blue-600 hover:to-purple-700 transition-all duration-300 block"
                            >
                                Start Free Trial
                            </Link>
                            <Link 
                                v-else
                                :href="route('subscription', { plan: 'premium' })"
                                class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-blue-600 hover:to-purple-700 transition-all duration-300 block"
                            >
                                Upgrade to Premium
                            </Link>
                        </div>
                    </div>
                    
                    <!-- Plus Plan -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-gray-200 hover:border-purple-300 transition-all duration-300 transform hover:-translate-y-2">
                        <div class="p-8">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Plus</h3>
                                <p class="text-gray-600 mb-4">For growing teams and businesses</p>
                                <div class="text-4xl font-bold text-purple-600 mb-2">
                                    €14.99
                                    <span class="text-lg font-normal text-gray-600">/month</span>
                                </div>
                            </div>
                            
                            <ul class="space-y-4 mb-8">
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
                            
                            <Link 
                                v-if="!$page.props.auth.user"
                                :href="route('register')"
                                class="w-full bg-gradient-to-r from-purple-500 to-pink-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-purple-600 hover:to-pink-700 transition-all duration-300 block"
                            >
                                Start Free Trial
                            </Link>
                            <Link 
                                v-else
                                :href="route('subscription', { plan: 'plus' })"
                                class="w-full bg-gradient-to-r from-purple-500 to-pink-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-purple-600 hover:to-pink-700 transition-all duration-300 block"
                            >
                                Upgrade to Plus
                            </Link>
                        </div>
                    </div>
                    
                    <!-- Academy Plan -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-gray-200 hover:border-orange-300 transition-all duration-300 transform hover:-translate-y-2">
                        <div class="p-8">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Academy</h3>
                                <p class="text-gray-600 mb-4">For institutions and large organizations</p>
                                <div class="text-2xl font-bold text-orange-600 mb-2">
                                    Custom Pricing
                                </div>
                                <p class="text-sm text-gray-500">Tailored to your institution's needs</p>
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
                            
                            <Link 
                                :href="route('academy.contact')"
                                class="w-full bg-gradient-to-r from-orange-500 to-red-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-orange-600 hover:to-red-700 transition-all duration-300 block"
                            >
                                Contact Sales
                            </Link>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-12">
                    <p class="text-gray-600 dark:text-gray-300 mb-4">All plans include a 14-day free trial. No credit card required.</p>
                    <Link 
                        :href="route('pricing')"
                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-semibold underline"
                    >
                        View detailed pricing comparison →
                    </Link>
                </div>
            </div>
        </section>
        <SiteFooter />
    </div>
</template>