<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
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

const props = defineProps<{
    course: Course;
}>();

const form = useForm({
    name: props.course.name,
    description: props.course.description,
    exam_type: props.course.exam_type,
    icon: props.course.icon,
    color: props.course.color,
    badge: props.course.badge,
    badge_color: props.course.badge_color,
    full_description: props.course.full_description,
    is_active: props.course.is_active,
    sort_order: props.course.sort_order,
});

const submit = () => {
    form.put(route('admin.courses.update', props.course.id));
};

const examTypes = [
    { value: 'sat', label: 'SAT Preparation' },
    { value: 'gre', label: 'GRE Preparation' },
    { value: 'gmat', label: 'GMAT Preparation' },
    { value: 'custom', label: 'Custom Preparation' },
];

const colorOptions = [
    { value: 'from-blue-400 to-blue-600', label: 'Blue' },
    { value: 'from-purple-400 to-purple-600', label: 'Purple' },
    { value: 'from-green-400 to-green-600', label: 'Green' },
    { value: 'from-orange-400 to-orange-600', label: 'Orange' },
    { value: 'from-red-400 to-red-600', label: 'Red' },
    { value: 'from-indigo-400 to-indigo-600', label: 'Indigo' },
];

const badgeColorOptions = [
    { value: 'bg-blue-500', label: 'Blue' },
    { value: 'bg-purple-500', label: 'Purple' },
    { value: 'bg-green-500', label: 'Green' },
    { value: 'bg-orange-500', label: 'Orange' },
    { value: 'bg-red-500', label: 'Red' },
    { value: 'bg-indigo-500', label: 'Indigo' },
];
</script>

<template>
    <AdminLayout title="Edit Course">
        <template #header>
            Edit Course
        </template>
        
        <template #subtitle>
            Update course information and settings
        </template>

        <template #actions>
            <a 
                :href="route('admin.courses.index')"
                class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150"
            >
                <ArrowLeft class="w-4 h-4 mr-2" />
                Back to Courses
            </a>
        </template>

        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="space-y-6">
                <div class="bg-white dark:bg-gray-800 shadow px-4 py-5 sm:rounded-lg sm:p-6">
                    <div class="md:grid md:grid-cols-3 md:gap-6">
                        <div class="md:col-span-1">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                                Course Information
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Basic information about the course.
                            </p>
                        </div>
                        <div class="mt-5 md:mt-0 md:col-span-2">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6">
                                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Course Name *
                                    </label>
                                    <input
                                        type="text"
                                        id="name"
                                        v-model="form.name"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                        :class="{ 'border-red-300': form.errors.name }"
                                    />
                                    <p v-if="form.errors.name" class="mt-2 text-sm text-red-600">
                                        {{ form.errors.name }}
                                    </p>
                                </div>

                                <div class="col-span-6">
                                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Short Description
                                    </label>
                                    <input
                                        type="text"
                                        id="description"
                                        v-model="form.description"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                    />
                                </div>

                                <div class="col-span-6">
                                    <label for="full_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Full Description
                                    </label>
                                    <textarea
                                        id="full_description"
                                        v-model="form.full_description"
                                        rows="3"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                    ></textarea>
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="exam_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Exam Type
                                    </label>
                                    <select
                                        id="exam_type"
                                        v-model="form.exam_type"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                    >
                                        <option value="">Select exam type</option>
                                        <option v-for="type in examTypes" :key="type.value" :value="type.value">
                                            {{ type.label }}
                                        </option>
                                    </select>
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="sort_order" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Sort Order
                                    </label>
                                    <input
                                        type="number"
                                        id="sort_order"
                                        v-model="form.sort_order"
                                        min="0"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow px-4 py-5 sm:rounded-lg sm:p-6">
                    <div class="md:grid md:grid-cols-3 md:gap-6">
                        <div class="md:col-span-1">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                                Visual Settings
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Customize the appearance of the course.
                            </p>
                        </div>
                        <div class="mt-5 md:mt-0 md:col-span-2">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="icon" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Icon (Emoji)
                                    </label>
                                    <input
                                        type="text"
                                        id="icon"
                                        v-model="form.icon"
                                        placeholder="📊"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                    />
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Color Gradient
                                    </label>
                                    <select
                                        id="color"
                                        v-model="form.color"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                    >
                                        <option value="">Select color</option>
                                        <option v-for="color in colorOptions" :key="color.value" :value="color.value">
                                            {{ color.label }}
                                        </option>
                                    </select>
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="badge" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Badge Text
                                    </label>
                                    <input
                                        type="text"
                                        id="badge"
                                        v-model="form.badge"
                                        placeholder="POPULAR"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                    />
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="badge_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Badge Color
                                    </label>
                                    <select
                                        id="badge_color"
                                        v-model="form.badge_color"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                    >
                                        <option value="">Select badge color</option>
                                        <option v-for="color in badgeColorOptions" :key="color.value" :value="color.value">
                                            {{ color.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow px-4 py-5 sm:rounded-lg sm:p-6">
                    <div class="md:grid md:grid-cols-3 md:gap-6">
                        <div class="md:col-span-1">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                                Status
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Control the visibility of the course.
                            </p>
                        </div>
                        <div class="mt-5 md:mt-0 md:col-span-2">
                            <div class="flex items-center">
                                <input
                                    id="is_active"
                                    type="checkbox"
                                    v-model="form.is_active"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                />
                                <label for="is_active" class="ml-2 block text-sm text-gray-900 dark:text-white">
                                    Active (visible to users)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-25"
                    >
                        <Save class="w-4 h-4 mr-2" />
                        {{ form.processing ? 'Updating...' : 'Update Course' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
