<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Exam Wiki" />
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 dark:from-slate-900 dark:to-slate-950">
        <div class="container mx-auto px-4 py-12">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-6">Exam Preparation Wiki</h1>
            <p class="text-lg text-gray-700 dark:text-gray-300 max-w-3xl mb-8">
                This wiki gathers general information about the competitive examinations supported in the platform. 
                You'll find eligibility, structure, scoring, registration timelines, and official resources for each exam.
            </p>

            <div class="grid md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">SAT</h2>
                    <p class="text-gray-700 dark:text-gray-300">The SAT assesses reading, writing, and math. It is widely used for undergraduate admissions. Sections include Reading & Writing and Math. Scores range from 400–1600.</p>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">GRE</h2>
                    <p class="text-gray-700 dark:text-gray-300">The GRE General Test evaluates verbal reasoning, quantitative reasoning, and analytical writing. Used for many graduate programs worldwide.</p>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">GMAT</h2>
                    <p class="text-gray-700 dark:text-gray-300">The GMAT measures reasoning and analytical skills for business school admissions. It includes Quantitative, Verbal, and Data Insights sections.</p>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Custom/Other Exams</h2>
                    <p class="text-gray-700 dark:text-gray-300">If your target exam is not listed, contact us. We can tailor content and guidance to your needs and add it to this wiki.</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
</style>


