<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { 
    LayoutDashboard, 
    BookOpen, 
    Users, 
    MessageSquare, 
    CreditCard, 
    Settings,
    Plus,
    Edit,
    BarChart3,
    UserCheck
} from 'lucide-vue-next';

const isActive = (routeName: string) => {
    return route().current(routeName);
};
</script>

<template>
    <div class="flex flex-col w-64 bg-white dark:bg-gray-800 shadow-lg">
        <!-- Logo -->
        <div class="flex items-center justify-center h-16 px-4 bg-gray-900 dark:bg-gray-900">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                    <span class="text-white font-bold text-sm">A</span>
                </div>
                <span class="text-white font-bold text-lg">Admin Panel</span>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 py-6 space-y-2">
            <!-- Dashboard -->
            <Link 
                :href="route('admin.dashboard')"
                :class="[
                    'flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                    isActive('admin.dashboard') 
                        ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' 
                        : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                ]"
            >
                <LayoutDashboard class="w-5 h-5 mr-3" />
                Dashboard
            </Link>

            <!-- Courses Management -->
            <div class="space-y-1">
                <div class="flex items-center justify-between px-4 py-2">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Courses
                    </span>
                </div>
                
                <Link 
                    :href="route('admin.courses.index')"
                    :class="[
                        'flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                        isActive('admin.courses.index') 
                            ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' 
                            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                    ]"
                >
                    <BookOpen class="w-5 h-5 mr-3" />
                    All Courses
                </Link>

                <Link 
                    :href="route('admin.courses.create')"
                    :class="[
                        'flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                        isActive('admin.courses.create') 
                            ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' 
                            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                    ]"
                >
                    <Plus class="w-5 h-5 mr-3" />
                    Add Course
                </Link>
            </div>

            <!-- Users Management -->
            <div class="space-y-1">
                <div class="flex items-center justify-between px-4 py-2">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Users
                    </span>
                </div>
                
                <Link 
                    :href="route('admin.users.index')"
                    :class="[
                        'flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                        isActive('admin.users.index') 
                            ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' 
                            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                    ]"
                >
                    <Users class="w-5 h-5 mr-3" />
                    All Users
                </Link>

                <Link 
                    :href="route('admin.user-plans.index')"
                    :class="[
                        'flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                        isActive('admin.user-plans.index') 
                            ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' 
                            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                    ]"
                >
                    <UserCheck class="w-5 h-5 mr-3" />
                    Manage Plans
                </Link>
            </div>

            <!-- Analytics -->
            <div class="space-y-1">
                <div class="flex items-center justify-between px-4 py-2">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Analytics
                    </span>
                </div>
                
                <Link 
                    :href="route('admin.reports.index')"
                    :class="[
                        'flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                        isActive('admin.reports.index') 
                            ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' 
                            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                    ]"
                >
                    <BarChart3 class="w-5 h-5 mr-3" />
                    Reports
                </Link>
            </div>

            <!-- Settings -->
            <div class="space-y-1">
                <div class="flex items-center justify-between px-4 py-2">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        System
                    </span>
                </div>
                
                <Link 
                    :href="route('admin.settings.index')"
                    :class="[
                        'flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                        isActive('admin.settings.index') 
                            ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' 
                            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                    ]"
                >
                    <Settings class="w-5 h-5 mr-3" />
                    Settings
                </Link>
            </div>
        </nav>

        <!-- User Info -->
        <div class="p-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-blue-600 rounded-full flex items-center justify-center">
                    <span class="text-white font-bold text-sm">{{ $page.props.auth.user.name.charAt(0) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                        {{ $page.props.auth.user.name }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                        Administrator
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
