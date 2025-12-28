<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Edit, Trash2, Eye, EyeOff, FileText, FileQuestion } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface Course {
    id: number;
    name: string;
    slug: string;
    description: string;
    exam_type: string;
    icon: string;
    color: string;
    badge: string;
    badge_color: string;
    full_description: string;
    is_active: boolean;
    sort_order: number;
    created_at: string;
    updated_at: string;
}

defineProps<{
    courses: Course[];
}>();

const deleteCourse = (course: Course) => {
    if (confirm(`Are you sure you want to delete "${course.name}"?`)) {
        router.delete(route('admin.courses.destroy', course.id));
    }
};

const toggleActive = (course: Course) => {
    router.put(route('admin.courses.update', course.id), {
        ...course,
        is_active: !course.is_active
    });
};
</script>

<template>
    <AdminLayout title="Courses">
        <template #header>
           Gestión de cursos
        </template>
        
        <template #subtitle>
         Administrar cursos de exámenes y sus configuraciones
        </template>

        <template #actions>
            <Link 
                :href="route('admin.courses.create')"
                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
            >
                <Plus class="w-4 h-4 mr-2" />
              Añadir curso
            </Link>
        </template>

        <!-- Courses Table -->
        <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-md">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                   Todos los cursos
                </h3>
                <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
Gestiona tus cursos de preparación para exámenes                </p>
            </div>
            
            <div v-if="courses.length === 0" class="text-center py-12">
                <div class="text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No courses</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new course.</p>
                    <div class="mt-6">
                        <Link 
                            :href="route('admin.courses.create')"
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <Plus class="w-4 h-4 mr-2" />
                           Añadir curso
                        </Link>
                    </div>
                </div>
            </div>

            <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                <li v-for="course in courses" :key="course.id" class="px-4 py-4 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <!-- Course Icon -->
                            <div 
                                v-if="course.icon"
                                class="flex-shrink-0 w-12 h-12 rounded-lg flex items-center justify-center text-2xl"
                                :class="course.color ? `bg-gradient-to-br ${course.color}` : 'bg-gray-100 dark:bg-gray-700'"
                            >
                                {{ course.icon }}
                            </div>
                            
                            <!-- Course Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center space-x-2">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {{ course.name }}
                                    </p>
                                    <span 
                                        v-if="course.badge"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="course.badge_color ? course.badge_color : 'bg-gray-100 text-gray-800'"
                                    >
                                        {{ course.badge }}
                                    </span>
                                    <span 
                                        v-if="!course.is_active"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"
                                    >
                                        Inactive
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                    {{ course.description || 'No description' }}
                                </p>
                                <div class="flex items-center space-x-4 mt-1">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        Type: {{ course.exam_type || 'General' }}
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        Order: {{ course.sort_order }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex items-center space-x-2">
                            <Link
                                :href="route('admin.courses.documents.page', course.id)"
                                title="Gestionar Documentos"
                                class="p-2 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400"
                            >
                                <FileText class="w-4 h-4" />
                            </Link>

                            <Link
                                :href="route('admin.courses.quizzes.create', course.id)"
                                title="Importar Quiz"
                                class="p-2 text-gray-400 hover:text-green-600 dark:hover:text-green-400"
                            >
                                <FileQuestion class="w-4 h-4" />
                            </Link>
                            
                            <button
                                @click="toggleActive(course)"
                                :title="course.is_active ? 'Deactivate' : 'Activate'"
                                class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            >
                                <Eye v-if="course.is_active" class="w-4 h-4" />
                                <EyeOff v-else class="w-4 h-4" />
                            </button>
                            
                            <Link 
                                :href="route('admin.courses.edit', course.id)"
                                class="p-2 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400"
                                title="Edit"
                            >
                                <Edit class="w-4 h-4" />
                            </Link>
                            
                            <button
                                @click="deleteCourse(course)"
                                class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                title="Delete"
                            >
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </AdminLayout>
</template>
