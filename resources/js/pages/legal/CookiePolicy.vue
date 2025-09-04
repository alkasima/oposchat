<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import SiteHeader from '@/components/SiteHeader.vue';
import SiteFooter from '@/components/SiteFooter.vue';
</script>

<template>
    <Head title="Cookie Policy" />
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
        <SiteHeader />

        <div class="max-w-4xl mx-auto px-4 py-12">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Cookie Policy</h1>
                <p class="text-gray-600 mt-2">Last updated: {{ new Date().toLocaleDateString() }}</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-6">
                <p class="text-gray-700">
                    This Cookie Policy explains how OposChat uses cookies and similar technologies to recognize you when you visit or use our services.
                </p>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900">What Are Cookies?</h2>
                    <p class="text-gray-700 mt-2">
                        Cookies are small text files placed on your device to store data that can be recalled by a web server.
                    </p>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900">How We Use Cookies</h2>
                    <ul class="list-disc pl-6 mt-2 text-gray-700 space-y-1">
                        <li>Authentication and session management.</li>
                        <li>Preference and theme settings.</li>
                        <li>Performance and analytics to improve the product.</li>
                    </ul>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Managing Cookies</h2>
                    <p class="text-gray-700 mt-2">
                        You can control cookies through your browser settings. Disabling cookies may affect certain features.
                    </p>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Contact</h2>
                    <p class="text-gray-700 mt-2">
                        For questions, contact <a href="mailto:info@oposchat.com" class="text-orange-600 hover:underline">info@oposchat.com</a>.
                    </p>
                </div>
            </div>

            <div class="mt-6">
                <Link :href="route('home')" class="text-sm text-gray-600 hover:text-gray-900 underline">← Back to Home</Link>
            </div>
        </div>

        <SiteFooter />
    </div>
</template>


