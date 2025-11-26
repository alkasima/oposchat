<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { useSubscription } from '@/composables/useSubscription.js';
import { Sun, Moon, User, Settings, LogOut, CreditCard, BarChart3 } from 'lucide-vue-next';

const isMenuOpen = ref(false);
const isProfileOpen = ref(false);
const { appearance, updateAppearance } = useAppearance();
const { hasPremium, refreshSubscriptionData } = useSubscription();
const page = usePage();
const headerUser = computed(() => page.props.auth?.user || null);

const headerSubscriptionType = computed(() => (headerUser.value?.subscription_type as string) || 'free');

const headerPlanName = computed(() => {
    const key = headerSubscriptionType.value || 'free';
    return key.charAt(0).toUpperCase() + key.slice(1);
});
const isDark = computed(() => appearance.value === 'dark');

const cycleTheme = () => {
    // Cycle through light -> dark -> light ...
    const current = appearance.value || 'light';
    const next = current === 'light' ? 'dark' : 'light';
    updateAppearance(next);
};

// Close profile dropdown when clicking outside
const closeProfileDropdown = () => {
    isProfileOpen.value = false;
};

// Courses for header dropdown (public)
type HeaderCourse = {
    id: number;
    name: string;
    slug?: string;
    namespace?: string;
};
const headerCourses = ref<HeaderCourse[]>([]);
const loadingHeaderCourses = ref<boolean>(false);
const headerCoursesError = ref<string | null>(null);

onMounted(async () => {
    try {
        loadingHeaderCourses.value = true;
        headerCoursesError.value = null;

        // Refresh subscription data for logged-in users so header plan is in sync everywhere
        if ((window as any).Laravel?.page?.props?.auth?.user) {
            try {
                await refreshSubscriptionData();
            } catch (e) {
                // Non-blocking: header should still render even if refresh fails
                console.error('Failed to refresh subscription data in header:', e);
            }
        }

        const res = await fetch('/public/courses', { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error('Failed to load courses');
        const data = await res.json();
        headerCourses.value = Array.isArray(data) ? data : [];
    } catch (e: any) {
        headerCoursesError.value = e?.message || 'Could not load courses';
        headerCourses.value = [];
    } finally {
        loadingHeaderCourses.value = false;
    }
});
</script>

<template>
    <header class="bg-gradient-to-r from-indigo-900 via-blue-900 to-purple-900 shadow-2xl sticky top-0 z-50 backdrop-blur-sm" @click="closeProfileDropdown">
        <div class="container mx-auto px-4 py-4">
            <nav class="flex items-center justify-between">
                <Link :href="route('home')" class="flex items-center space-x-4 hover:opacity-90 transition-opacity duration-300">
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
                        <p class="text-blue-200 text-xs">Tu aliado para la plaza</p>
                    </div>
                </Link>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-8">
                    <Link :href="route('home')" class="text-white hover:text-yellow-300 transition-all duration-300 font-medium relative group">
                        Inicio
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-yellow-300 transition-all duration-300 group-hover:w-full"></span>
                    </Link>
                    <div class="relative group">
                        <button class="text-white hover:text-yellow-300 transition-all duration-300 flex items-center font-medium">
                            Oposiciones 
                            <svg class="w-4 h-4 ml-1 transform group-hover:rotate-180 transition-transform duration-300" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div class="absolute top-full left-0 mt-2 w-64 bg-white rounded-xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform translate-y-2 group-hover:translate-y-0">
                            <div class="py-2 max-h-80 overflow-auto">
                                <div v-if="loadingHeaderCourses" class="px-4 py-2 text-sm text-gray-500">Loading…</div>
                                <div v-else-if="headerCoursesError" class="px-4 py-2 text-sm text-red-600">{{ headerCoursesError }}</div>
                                <template v-else>
                                    <Link 
                                        v-for="c in headerCourses.slice(0, 4)" :key="c.id"
                                        :href="route('exams.wiki.course', { slug: c.slug || c.namespace })"
                                        class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors"
                                    >
                                        {{ c.name }}
                                    </Link>
                                </template>
                                <div class="border-t border-gray-100 mt-2"></div>
                                <Link :href="route('exams.wiki')" class="block px-4 py-2 text-sm text-blue-600 hover:bg-blue-50">Ver todas las oposiciones →</Link>
                            </div>
                        </div>
                    </div>
                    <Link :href="route('about')" class="text-white hover:text-yellow-300 transition-all duration-300 font-medium relative group">
                        Sobre OposChat
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-yellow-300 transition-all duration-300 group-hover:w-full"></span>
                    </Link>
                    <Link :href="route('contact')" class="text-white hover:text-yellow-300 transition-all duration-300 font-medium relative group">
                        Contacto
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-yellow-300 transition-all duration-300 group-hover:w-full"></span>
                    </Link>
                    <Link :href="route('pricing')" class="text-white hover:text-yellow-300 transition-all duration-300 font-medium relative group">
                        Precios
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-yellow-300 transition-all duration-300 group-hover:w-full"></span>
                    </Link>
                    <div v-if="$page.props.auth.user" class="flex items-center space-x-4">
                        <!-- Profile Dropdown -->
                        <div class="relative" @click.stop>
                            <button 
                                @click="isProfileOpen = !isProfileOpen"
                                class="flex items-center space-x-2 bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-full transition-all duration-300"
                            >
                                <div class="w-8 h-8 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full flex items-center justify-center">
                                    <span class="text-white font-semibold text-sm">
                                        {{ $page.props.auth.user.name?.charAt(0)?.toUpperCase() || 'U' }}
                                    </span>
                                </div>
                                <span class="font-medium">{{ $page.props.auth.user.name || 'User' }}</span>
                                <svg class="w-4 h-4 transform transition-transform duration-200" :class="{ 'rotate-180': isProfileOpen }" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div 
                                v-if="isProfileOpen" 
                                class="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-2xl border border-gray-200 z-50"
                            >
                                <div class="py-2">
                                    <!-- User Info -->
                                    <div class="px-4 py-3 border-b border-gray-100">
                                        <p class="text-sm font-medium text-gray-900">{{ $page.props.auth.user.name || 'User' }}</p>
                                        <p class="text-sm text-gray-500">{{ $page.props.auth.user.email }}</p>
                                        <p class="text-xs text-gray-600 mt-1">
                                            Plan:
                                            <span
                                                class="font-medium"
                                                :class="headerSubscriptionType !== 'free' ? 'text-yellow-600' : 'text-gray-500'"
                                            >
                                                {{ headerPlanName }}
                                            </span>
                                        </p>
                                    </div>
                                    
                                    <!-- Menu Items -->
                                    <div class="py-1">
                                        <Link 
                                            :href="route('dashboard')"
                                            class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                                            @click="isProfileOpen = false"
                                        >
                                            <BarChart3 class="w-4 h-4 mr-3" />
                                            Mi Chat
                                        </Link>
                                        <Link 
                                            :href="route('profile.edit')"
                                            class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                                            @click="isProfileOpen = false"
                                        >
                                            <User class="w-4 h-4 mr-3" />
                                            Profile Settings
                                        </Link>
                                        <Link 
                                            :href="route('subscription')"
                                            class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                                            @click="isProfileOpen = false"
                                        >
                                            <CreditCard class="w-4 h-4 mr-3" />
                                            Planes y precios
                                        </Link>
                                        <Link 
                                            :href="route('profile.edit')"
                                            class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors"
                                            @click="isProfileOpen = false"
                                        >
                                            <Settings class="w-4 h-4 mr-3" />
                                            Ajustes
                                        </Link>
                                    </div>
                                    
                                    <!-- Logout -->
                                    <div class="border-t border-gray-100 py-1">
                                        <Link 
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                            class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors"
                                            @click="isProfileOpen = false"
                                        >
                                            <LogOut class="w-4 h-4 mr-3" />
                                            Cerrar sesión
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <Link 
                        v-else
                        :href="route('login')"
                        class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-6 py-2.5 rounded-full hover:from-yellow-500 hover:to-orange-600 transition-all duration-300 font-semibold shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                    >
                        Iniciar sesión / Crear cuenta
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
                    <Link :href="route('home')" class="text-white hover:text-yellow-300 transition-colors">Inicio</Link>
                    <Link href="#courses" class="text-white hover:text-yellow-300 transition-colors">Oposiciones</Link>
                    <Link :href="route('about')" class="text-white hover:text-yellow-300 transition-colors">Sobre OposChat</Link>
                    <Link :href="route('contact')" class="text-white hover:text-yellow-300 transition-colors">Contacto</Link>
                    <Link :href="route('pricing')" class="text-white hover:text-yellow-300 transition-colors">Precios</Link>
                    <div v-if="$page.props.auth.user" class="space-y-2">
                        <div class="px-4 py-2 border-b border-blue-800 mb-2">
                            <p class="text-sm font-medium text-white">{{ $page.props.auth.user.name || 'User' }}</p>
                            <p class="text-xs text-blue-200">{{ $page.props.auth.user.email }}</p>
                            <p class="text-xs text-blue-300 mt-1">
                                Plan:
                                <span
                                    class="font-medium"
                                    :class="headerSubscriptionType !== 'free' ? 'text-yellow-300' : 'text-blue-200'"
                                >
                                    {{ headerPlanName }}
                                </span>
                            </p>
                        </div>
                        <Link 
                            :href="route('dashboard')"
                            class="flex items-center text-white hover:text-yellow-300 transition-colors px-4 py-2"
                            @click="isMenuOpen = false"
                        >
                            <BarChart3 class="w-4 h-4 mr-3" />
                            Mi Chat
                        </Link>
                        <Link 
                            :href="route('profile.edit')"
                            class="flex items-center text-white hover:text-yellow-300 transition-colors px-4 py-2"
                            @click="isMenuOpen = false"
                        >
                            <User class="w-4 h-4 mr-3" />
                            Ajustes de perfil
                        </Link>
                        <Link 
                            :href="route('subscription')"
                            class="flex items-center text-white hover:text-yellow-300 transition-colors px-4 py-2"
                            @click="isMenuOpen = false"
                        >
                            <CreditCard class="w-4 h-4 mr-3" />
                            Planes y precios
                        </Link>
                        <Link 
                            :href="route('profile.edit')"
                            class="flex items-center text-white hover:text-yellow-300 transition-colors px-4 py-2"
                            @click="isMenuOpen = false"
                        >
                            <Settings class="w-4 h-4 mr-3" />
                            Ajustes
                        </Link>
                        <Link 
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="flex items-center w-full text-red-300 hover:text-red-200 transition-colors px-4 py-2"
                            @click="isMenuOpen = false"
                        >
                            <LogOut class="w-4 h-4 mr-3" />
                            Cerrar sesión
                        </Link>
                    </div>
                    <Link 
                        v-else
                        :href="route('login')"
                        class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-4 py-2 rounded-full text-center"
                    >
                        Iniciar sesión / Crear cuenta
                    </Link>
                    <button @click="cycleTheme" class="text-white border border-white/30 rounded-lg px-4 py-2">
                        <span v-if="appearance === 'light'">Modo claro</span>
                        <span v-else>Modo oscuro</span>
                    </button>
                </div>
            </div>
        </div>
    </header>
</template>


