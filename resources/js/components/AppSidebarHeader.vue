<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Button } from '@/components/ui/button';
import { Sun, Moon, ArrowLeft, Settings } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { useAppearance } from '@/composables/useAppearance';
import { computed } from 'vue';
import type { BreadcrumbItemType } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { appearance, updateAppearance } = useAppearance();
const isDark = computed(() => appearance.value === 'dark');

const cycleTheme = () => {
    const current = appearance.value || 'light';
    const next = current === 'light' ? 'dark' : 'light';
    updateAppearance(next);
};
</script>

<template>
    <header
        class="bg-gradient-to-r from-white via-gray-50 to-white dark:from-gray-800 dark:via-gray-850 dark:to-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm flex h-16 shrink-0 items-center gap-2 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2 flex-1">
            <SidebarTrigger class="-ml-1" />
            
            <!-- Back to Study Link -->
            <Link 
                :href="route('dashboard')"
                class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-800 transition-all duration-200"
            >
                <ArrowLeft class="w-4 h-4" />
                <span>Volver al Estudio</span>
            </Link>
            
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        
        <!-- Right Section Actions -->
        <div class="flex items-center space-x-2">
            <!-- Settings Button -->
            <Button 
                as-child
                variant="ghost" 
                size="sm" 
                class="p-2.5 rounded-xl text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200"
                title="Settings"
            >
                <Link :href="route('profile.edit')">
                    <Settings class="w-4 h-4" />
                </Link>
            </Button>
            
            <!-- Theme Toggle Button -->
            <Button 
                @click="cycleTheme" 
                variant="ghost" 
                size="sm" 
                class="p-2.5 rounded-xl text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200"
                :title="`Theme: ${appearance}`"
            >
                <Sun v-if="isDark" class="w-4 h-4" />
                <Moon v-else class="w-4 h-4" />
            </Button>
        </div>
    </header>
</template>
