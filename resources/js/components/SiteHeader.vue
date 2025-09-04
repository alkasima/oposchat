<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { Sun, Moon } from 'lucide-vue-next';

const isMenuOpen = ref(false);
const { appearance, updateAppearance } = useAppearance();
const prefersDark = () => typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
const isDark = computed(() => appearance.value === 'dark' || (appearance.value === 'system' && prefersDark()));

const cycleTheme = () => {
    // Cycle through light -> dark -> system -> light ...
    const current = appearance.value || 'system';
    const next = current === 'light' ? 'dark' : current === 'dark' ? 'system' : 'light';
    updateAppearance(next);
};
</script>

<template>
    <header class="bg-gradient-to-r from-indigo-900 via-blue-900 to-purple-900 shadow-2xl sticky top-0 z-50 backdrop-blur-sm">
        <div class="container mx-auto px-4 py-4">
            <nav class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
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
                </div>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-8">
                    <Link :href="route('home')" class="text-white hover:text-yellow-300 transition-all duration-300 font-medium relative group">
                        Home
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-yellow-300 transition-all duration-300 group-hover:w-full"></span>
                    </Link>
                    <div class="relative group">
                        <button class="text-white hover:text-yellow-300 transition-all duration-300 flex items-center font-medium">
                            Courses 
                            <svg class="w-4 h-4 ml-1 transform group-hover:rotate-180 transition-transform duration-300" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div class="absolute top-full left-0 mt-2 w-48 bg-white rounded-xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform translate-y-2 group-hover:translate-y-0">
                            <div class="py-2">
                                <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">SAT Preparation</a>
                                <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">GRE Preparation</a>
                                <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">GMAT Preparation</a>
                            </div>
                        </div>
                    </div>
                    <Link :href="route('about')" class="text-white hover:text-yellow-300 transition-all duration-300 font-medium relative group">
                        About
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-yellow-300 transition-all duration-300 group-hover:w-full"></span>
                    </Link>
                    <Link :href="route('pricing')" class="text-white hover:text-yellow-300 transition-all duration-300 font-medium relative group">
                        Pricing
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-yellow-300 transition-all duration-300 group-hover:w-full"></span>
                    </Link>
                    <div v-if="$page.props.auth.user" class="flex items-center space-x-4">
                        <Link 
                            :href="route('dashboard')"
                            class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-6 py-2.5 rounded-full hover:from-yellow-500 hover:to-orange-600 transition-all duration-300 font-semibold shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                        >
                            Dashboard
                        </Link>
                    </div>
                    <Link 
                        v-else
                        :href="route('login')"
                        class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-6 py-2.5 rounded-full hover:from-yellow-500 hover:to-orange-600 transition-all duration-300 font-semibold shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                    >
                        Sign In / Register
                    </Link>
                    <!-- Theme Toggle -->
                    <button @click="cycleTheme" class="ml-4 p-2 rounded-lg bg-white/10 hover:bg-white/20 text-white" title="Theme: {{ appearance }}">
                        <Sun v-if="isDark" class="w-4 h-4" />
                        <Moon v-else class="w-4 h-4" />
                    </button>
                </div>

                <!-- Mobile Menu Button -->
                <button @click="isMenuOpen = !isMenuOpen" class="md:hidden text-white p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </nav>

            <!-- Mobile Menu -->
            <div v-if="isMenuOpen" class="md:hidden mt-4 pb-4 border-t border-blue-800">
                <div class="flex flex-col space-y-4 pt-4">
                    <Link :href="route('home')" class="text-white hover:text-yellow-300 transition-colors">Home</Link>
                    <Link href="#courses" class="text-white hover:text-yellow-300 transition-colors">Exams</Link>
                    <Link :href="route('about')" class="text-white hover:text-yellow-300 transition-colors">About</Link>
                    <Link :href="route('pricing')" class="text-white hover:text-yellow-300 transition-colors">Pricing</Link>
                    <div v-if="$page.props.auth.user" class="space-y-2">
                        <Link 
                            :href="route('dashboard')"
                            class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-4 py-2 rounded-full text-center block"
                        >
                            Dashboard
                        </Link>
                    </div>
                    <Link 
                        v-else
                        :href="route('login')"
                        class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-4 py-2 rounded-full text-center"
                    >
                        Sign In / Register
                    </Link>
                    <button @click="cycleTheme" class="text-white border border-white/30 rounded-lg px-4 py-2">
                        <span v-if="appearance === 'light'">Light mode</span>
                        <span v-else-if="appearance === 'dark'">Dark mode</span>
                        <span v-else>System</span>
                    </button>
                </div>
            </div>
        </div>
    </header>
</template>


