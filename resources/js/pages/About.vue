<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import SiteHeader from '@/components/SiteHeader.vue';
import SiteFooter from '@/components/SiteFooter.vue';
</script>

<template>
    <Head title="About OposChat" />
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
        <SiteHeader />

        <main class="max-w-4xl mx-auto px-4 py-12">
            <h1 class="text-3xl font-bold text-gray-900">About OposChat</h1>
            <p class="text-gray-600 mt-3">
                OposChat helps students prepare for exams using AI-driven guidance, curated content, and practice.
            </p>

            <section class="mt-8 grid md:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900">Our Mission</h2>
                    <p class="text-gray-700 mt-2">Make high-quality exam preparation accessible and effective for everyone.</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900">What We Offer</h2>
                    <p class="text-gray-700 mt-2">Personalized AI assistance, updated content, and analytics to track progress.</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900">Get In Touch</h2>
                    <p class="text-gray-700 mt-2">Email us at <a href="mailto:info@oposchat.com" class="text-orange-600 hover:underline">info@oposchat.com</a>.</p>
                </div>
            </section>
        </main>

        <SiteFooter />
    </div>
</template>


