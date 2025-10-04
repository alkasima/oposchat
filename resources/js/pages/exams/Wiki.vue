<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import SiteHeader from '@/components/SiteHeader.vue';
import SiteFooter from '@/components/SiteFooter.vue';

type Course = {
    id: number;
    name: string;
    slug: string;
    namespace?: string;
};

const courses = ref<Course[]>([]);
const loading = ref<boolean>(false);
const error = ref<string | null>(null);

const fetchCourses = async () => {
    loading.value = true;
    error.value = null;
    try {
        const res = await fetch('/public/courses', { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error('Failed to load courses');
        courses.value = await res.json();
    } catch (e: any) {
        error.value = e?.message || 'Could not load courses';
        courses.value = [];
    } finally {
        loading.value = false;
    }
};

const startChatFor = async (slug: string, fallbackTitle: string) => {
    try {
        if (!courses.value.length) {
            await fetchCourses();
        }
        const course = courses.value.find(c => c.slug === slug || c.namespace === slug);
        const body: any = {
            exam_type: slug,
            title: `${(course?.name || fallbackTitle)} Chat`,
        };
        if (course?.id) {
            body.course_id = course.id;
        }

        const response = await fetch('/api/chats', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify(body)
        });
        if (response.ok) {
            const data = await response.json();
            router.visit(route('chat', { chat: data.chat.id }));
        } else {
            router.visit(route('dashboard'));
        }
    } catch (e) {
        router.visit(route('dashboard'));
    }
};

onMounted(() => {
    if (typeof window !== 'undefined' && window.location.hash) {
        const id = window.location.hash.replace('#', '');
        const el = document.getElementById(id);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
    // Load courses list for CTAs
    fetchCourses();
});
</script>

<template>
    <Head title="Exam Wiki" />
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 dark:from-slate-900 dark:to-slate-950">
        <SiteHeader />
        <div class="container mx-auto px-4 py-12">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-6">Wiki de oposiciones</h1>
            <p class="text-lg text-gray-700 dark:text-gray-300 max-w-3xl mb-8">
                Infórmate sobre todas las oposiciones que tenemos en nuestra plataforma. 
                Encontaras información relevante como fechas de exmámen, sistema de puntuación, plazos de inscripción...
                        </p>

            <div class="bg-white/70 dark:bg-white/10 backdrop-blur-sm rounded-xl p-4 mb-8">
                <div class="text-sm text-gray-700 dark:text-gray-300 mb-2 font-semibold">Quick links</div>
                <div class="flex flex-wrap gap-3">
                    <a href="#sat-preparation" class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 text-sm">Seguridad Social</a>
                    <a href="#gre-preparation" class="px-3 py-1 rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 text-sm">Policia Nacional</a>
                    <a href="#gmat-preparation" class="px-3 py-1 rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300 text-sm">Maestro de infantil</a>
                    <a href="#custom-preparation" class="px-3 py-1 rounded-full bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300 text-sm">Guardia Civil</a>
                    <Link :href="route('home') + '#courses'" class="px-3 py-1 rounded-full bg-gray-100 text-gray-800 dark:bg-slate-800 dark:text-gray-200 text-sm">Elige otra oposición</Link>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <div id="sat-preparation" class="bg-white dark:bg-slate-800 rounded-2xl shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Seguridad social</h2>
                    <p class="text-gray-700 dark:text-gray-300">Esto es un examen de seguridad social.</p>
                    <div class="mt-4 flex items-center gap-3">
                        <button @click="startChatFor('sat-preparation', 'SAT')" class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 text-sm">Ir al chat</button>
                        <Link :href="route('exams.wiki.course', { slug: 'sat-preparation' })" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700 text-sm">View details</Link>
                    </div>
                </div>
                <div id="gre-preparation" class="bg-white dark:bg-slate-800 rounded-2xl shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Policia Nacional</h2>
                    <p class="text-gray-700 dark:text-gray-300">Esto es un examen de policia nacional.</p>
                    <div class="mt-4 flex items-center gap-3">
                        <button @click="startChatFor('gre-preparation', 'GRE')" class="inline-flex items-center px-4 py-2 rounded-lg bg-purple-600 text-white hover:bg-purple-700 text-sm">Ir al chat</button>
                        <Link :href="route('exams.wiki.course', { slug: 'gre-preparation' })" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700 text-sm">View details</Link>
                    </div>
                </div>
                <div id="gmat-preparation" class="bg-white dark:bg-slate-800 rounded-2xl shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Maestro infantil</h2>
                    <p class="text-gray-700 dark:text-gray-300">Esto es un examen de maestro infantil.</p>
                    <div class="mt-4 flex items-center gap-3">
                        <button @click="startChatFor('gmat-preparation', 'GMAT')" class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 text-sm">Ir al chat</button>
                        <Link :href="route('exams.wiki.course', { slug: 'gmat-preparation' })" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700 text-sm">View details</Link>
                    </div>
                </div>
                <div id="custom-preparation" class="bg-white dark:bg-slate-800 rounded-2xl shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Guardia Civil</h2>
                    <p class="text-gray-700 dark:text-gray-300">Esto es un examen de Guardia civil.</p>
                    <div class="mt-4 flex items-center gap-3">
                        <button @click="startChatFor('custom-preparation', 'Custom Exam')" class="inline-flex items-center px-4 py-2 rounded-lg bg-orange-600 text-white hover:bg-orange-700 text-sm">Ir al chat</button>
                        <Link :href="route('exams.wiki.course', { slug: 'custom-preparation' })" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-slate-700 text-sm">View details</Link>
                    </div>
                </div>
            </div>

            <!-- Dynamic Courses (always visible) -->
            <div class="mt-12">
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-white mb-4">Oposiciones disponibles </h2>
                <div v-if="error" class="text-sm text-red-600 dark:text-red-400 mb-4">{{ error }}</div>
                <div v-if="loading" class="text-gray-600 dark:text-gray-300">Loading courses…</div>
                <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <Link 
                        v-for="c in courses" :key="c.id"
                        :href="route('exams.wiki.course', { slug: c.slug || c.namespace })"
                        class="group bg-white dark:bg-slate-800 rounded-xl shadow hover:shadow-md transition p-5 flex items-center gap-4"
                    >
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center bg-gradient-to-br from-orange-500 to-orange-600 text-white font-semibold">
                            {{ (c.name || '').substring(0,2).toUpperCase() }}
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-gray-100">{{ c.name }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Ver información</div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
        <SiteFooter />
    </div>
</template>

<style scoped>
</style>


