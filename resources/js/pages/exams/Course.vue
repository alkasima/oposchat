<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { onMounted, ref, computed } from 'vue';
import SiteHeader from '@/components/SiteHeader.vue';
import SiteFooter from '@/components/SiteFooter.vue';

const props = defineProps<{ slug: string }>();

type Course = {
    id: number;
    name: string;
    slug: string;
    namespace?: string;
    description?: string;
    full_description?: string;
    icon?: string;
    color?: string;
    badge?: string;
    badge_color?: string;
};

const course = ref<Course | null>(null);
const loading = ref<boolean>(false);
const error = ref<string | null>(null);

const fetchCourse = async () => {
    loading.value = true;
    error.value = null;
    try {
        const res = await fetch(`/public/courses/${encodeURIComponent(props.slug)}`, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error('Failed to load course');
        course.value = await res.json();
    } catch (e: any) {
        error.value = e?.message || 'Could not load course';
    } finally {
        loading.value = false;
    }
};

onMounted(fetchCourse);
</script>

<template>
    <Head :title="course?.name ? `${course.name} – Exam Wiki` : 'Exam Wiki'" />
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 dark:from-slate-900 dark:to-slate-950">
        <SiteHeader />
        <div class="container mx-auto px-4 py-10">
            <div class="mb-6 text-sm">
                <Link :href="route('exams.wiki')" class="text-blue-600 dark:text-blue-300 hover:underline">← Back to Wiki</Link>
            </div>

            <div v-if="loading" class="text-gray-600 dark:text-gray-300">Loading…</div>
            <div v-else-if="error" class="text-red-600 dark:text-red-400">{{ error }}</div>
            <div v-else-if="course" class="space-y-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ course.name }}</h1>
                    <p v-if="course.description" class="text-gray-700 dark:text-gray-300 mt-2">{{ course.description }}</p>
                    <div v-if="course.badge" class="mt-2 inline-flex px-2 py-0.5 rounded-full text-xs" :style="course.badge_color ? `background:${course.badge_color}; color:#000` : ''">
                        {{ course.badge }}
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow p-6">
                    <div class="prose dark:prose-invert max-w-none">
                        <p v-if="!course.full_description" class="text-gray-600 dark:text-gray-300">No detailed content yet. Check back soon.</p>
                        <div v-else v-html="course.full_description"></div>
                    </div>
                </div>
            </div>
        </div>
        <SiteFooter />
    </div>
    
</template>

<style scoped>
</style>


