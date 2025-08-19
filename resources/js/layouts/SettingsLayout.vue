<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import ChatSidebar from '@/components/ChatSidebar.vue';

import { 
    ArrowLeft,
    User,
    Lock,
    CreditCard,
    Shield,
    HelpCircle,
    Mail,
    X
} from 'lucide-vue-next';

interface Props {
    title?: string;
    description?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Settings',
    description: 'Manage your account settings and preferences'
});

const page = usePage();
const currentPath = computed(() => {
    return page.props.ziggy?.location ? new URL(page.props.ziggy.location).pathname : '';
});

const settingsNavItems = [
    {
        title: 'Profile',
        href: '/settings/profile',
        icon: User,
        description: 'Manage your personal information'
    },
    {
        title: 'Password',
        href: '/settings/password', 
        icon: Lock,
        description: 'Update your password and security'
    },
    {
        title: 'Subscription',
        href: '/settings/subscription',
        icon: CreditCard,
        description: 'Manage your plan and billing'
    },
    {
        title: 'Privacy',
        href: '/settings/privacy',
        icon: Shield,
        description: 'Control your privacy settings'
    }
];

const showMobileSidebar = ref(false);
const showSupportModal = ref(false);
</script>

<template>
    <Head :title="title" />
    
    <div class="flex h-screen bg-gray-50 dark:bg-gray-900">
        <!-- Left Sidebar - Hidden on mobile, shown on desktop -->
        <div class="hidden lg:block">
            <ChatSidebar />
        </div>
        
        <!-- Mobile Sidebar Overlay -->
        <div v-if="showMobileSidebar" class="fixed inset-0 z-50 lg:hidden">
            <div class="absolute inset-0 bg-black bg-opacity-50" @click="showMobileSidebar = false"></div>
            <div class="relative">
                <ChatSidebar />
            </div>
        </div>

        <!-- Main Settings Area -->
        <div class="flex-1 flex flex-col">
            <!-- Settings Header -->
            <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <Link :href="route('dashboard')" 
                              class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <ArrowLeft class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                        </Link>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ title }}</h1>
                            <p class="text-gray-600 dark:text-gray-400 mt-1">{{ description }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-1 flex overflow-hidden">
                <!-- Settings Navigation -->
                <div class="w-80 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 overflow-y-auto">
                    <div class="p-6">
                        <nav class="space-y-2">
                            <Link 
                                v-for="item in settingsNavItems" 
                                :key="item.href"
                                :href="item.href"
                                class="flex items-center space-x-3 p-3 rounded-lg transition-colors hover:bg-gray-100 dark:hover:bg-gray-700"
                                :class="{
                                    'bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400 border border-orange-200 dark:border-orange-800': currentPath === item.href,
                                    'text-gray-700 dark:text-gray-300': currentPath !== item.href
                                }"
                            >
                                <component :is="item.icon" class="w-5 h-5" />
                                <div class="flex-1">
                                    <div class="font-medium">{{ item.title }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ item.description }}
                                    </div>
                                </div>
                            </Link>
                        </nav>

                        <Separator class="my-6" />

                        <!-- Help Section -->
                        <div class="space-y-2">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Support</h3>
                            <button @click="showSupportModal = true"
                                    class="w-full flex items-center space-x-3 p-3 rounded-lg transition-colors hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
                                <HelpCircle class="w-5 h-5" />
                                <div class="text-left">
                                    <div class="font-medium">Help Center</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        Get help and support
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Settings Content -->
                <div class="flex-1 overflow-y-auto bg-gray-50 dark:bg-gray-900">
                    <div class="max-w-4xl mx-auto p-6">
                        <slot />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Support Modal -->
    <div v-if="showSupportModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black bg-opacity-50" @click="showSupportModal = false"></div>
        
        <!-- Modal Content -->
        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Support</h2>
                <Button @click="showSupportModal = false" variant="ghost" size="sm" class="p-2">
                    <X class="w-5 h-5" />
                </Button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-4">
                <div class="text-center">
                    <div class="w-16 h-16 bg-orange-100 dark:bg-orange-900/20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <Mail class="w-8 h-8 text-orange-600 dark:text-orange-400" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Need Help?</h3>
                    <p class="text-gray-600 dark:text-gray-400 text-sm mb-6">
                        We're here to help! Contact our support team for any questions or assistance.
                    </p>
                </div>

                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                    <div class="flex items-center space-x-3">
                        <Mail class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Email Support</p>
                            <a href="mailto:info@oposchat.com" 
                               class="text-sm text-orange-600 dark:text-orange-400 hover:underline">
                                info@oposchat.com
                            </a>
                        </div>
                    </div>
                </div>

                <div class="text-center text-xs text-gray-500 dark:text-gray-400">
                    We typically respond within 24 hours
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex justify-end space-x-3 p-6 border-t border-gray-200 dark:border-gray-700">
                <Button @click="showSupportModal = false" variant="outline">
                    Close
                </Button>
                <Button @click="window.open('mailto:info@oposchat.com', '_blank')" class="bg-orange-500 hover:bg-orange-600 text-white">
                    Send Email
                </Button>
            </div>
        </div>
    </div>
</template>