<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const name = page.props.name;
const quote = page.props.quote;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="relative grid min-h-screen flex-col items-center justify-center px-4 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
        <!-- Left Side - Brand/Hero Section -->
        <div class="relative hidden h-full flex-col bg-gradient-to-br from-blue-600 via-blue-700 to-purple-800 p-10 text-white lg:flex">
            <!-- Background Pattern -->
            <div class="absolute inset-0 bg-gradient-to-br from-blue-600/90 via-blue-700/90 to-purple-800/90" />
            <div class="absolute inset-0 opacity-20" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;0.05&quot;%3E%3Ccircle cx=&quot;30&quot; cy=&quot;30&quot; r=&quot;2&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')" />
            
            <!-- Logo and Brand -->
            <Link :href="route('home')" class="relative z-20 flex items-center text-xl font-bold tracking-tight">
                <div class="flex items-center gap-3">
                    <div class="w-14 h-14 bg-gradient-to-br from-white to-white rounded-full flex items-center justify-center shadow-lg transform hover:scale-105 transition-transform duration-300 p-2">
                        <img src="/images/logo.png" alt="OposChat" class="w-full h-full rounded-full" />
                    </div>
                    <span>{{ name || 'OposChat' }}</span>
                </div>

            </Link>
            
            <!-- Hero Content -->
            <div class="relative z-20 mt-auto space-y-6">
                <div class="space-y-4">
                    <h2 class="text-3xl font-bold leading-tight">
                        Connect, Chat, and Collaborate
                    </h2>
                    <p class="text-lg text-blue-100 leading-relaxed">
                        Join thousands of users who trust our platform for seamless communication and collaboration.
                    </p>
                </div>
                
                <!-- Features List -->
                <div class="space-y-3">
                    <div class="flex items-center space-x-3">
                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-white/20">
                            <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <span class="text-blue-100">Real-time messaging</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-white/20">
                            <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <span class="text-blue-100">Secure & private</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-white/20">
                            <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <span class="text-blue-100">Cross-platform support</span>
                    </div>
                </div>

                <!-- Quote Section -->
                <div v-if="quote" class="border-l-4 border-white/30 pl-4">
                    <blockquote class="space-y-2">
                        <p class="text-lg italic">&ldquo;{{ quote.message }}&rdquo;</p>
                        <footer class="text-sm text-blue-200">— {{ quote.author }}</footer>
                    </blockquote>
                </div>
            </div>
        </div>

        <!-- Right Side - Form Section -->
        <div class="flex items-center justify-center p-8 bg-gray-50 dark:bg-gray-900">
            <div class="mx-auto w-full max-w-md space-y-8">
                <!-- Mobile Logo -->
                <div class="flex justify-center lg:hidden">
                    <Link :href="route('home')" class="flex items-center space-x-2">
                        <AppLogoIcon class="size-8 fill-current text-blue-600" />
                        <span class="text-xl font-bold text-gray-900 dark:text-white">{{ name || 'OposChat' }}</span>
                    </Link>
                </div>

                <!-- Form Header -->
                <div class="text-center space-y-2">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white" v-if="title">{{ title }}</h1>
                    <p class="text-gray-600 dark:text-gray-300" v-if="description">{{ description }}</p>
                </div>

                <!-- Form Content -->
                <slot />
            </div>
        </div>
    </div>
</template>
