<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import SiteHeader from '@/components/SiteHeader.vue';
import SiteFooter from '@/components/SiteFooter.vue';
</script>

<template>
    <Head title="Privacy Policy" />
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
        <SiteHeader />

        <div class="max-w-4xl mx-auto px-4 py-12">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Privacy Policy</h1>
                <p class="text-gray-600 mt-2">Last updated: {{ new Date().toLocaleDateString() }}</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-6">
                <p class="text-gray-700">
                    We value your privacy. This Privacy Policy explains how we collect, use, and safeguard your information when you use OposChat.
                </p>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Information We Collect</h2>
                    <ul class="list-disc pl-6 mt-2 text-gray-700 space-y-1">
                        <li>Account information such as name, email, and password.</li>
                        <li>Usage data including interactions with the app and features.</li>
                        <li>Payment and subscription details processed securely by our providers.</li>
                        <li>Content you upload or share while using the service.</li>
                    </ul>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900">How We Use Information</h2>
                    <ul class="list-disc pl-6 mt-2 text-gray-700 space-y-1">
                        <li>To operate, maintain, and improve OposChat.</li>
                        <li>To provide customer support and communicate important updates.</li>
                        <li>To process payments and manage subscriptions.</li>
                        <li>To ensure security, prevent fraud, and comply with legal obligations.</li>
                    </ul>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Data Sharing</h2>
                    <p class="text-gray-700 mt-2">
                        We do not sell your personal data. We may share information with trusted service providers (e.g., payment processors, hosting) under strict confidentiality and data-processing agreements.
                    </p>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Your Rights</h2>
                    <ul class="list-disc pl-6 mt-2 text-gray-700 space-y-1">
                        <li>Access, correct, or delete your personal information.</li>
                        <li>Export your data where applicable.</li>
                        <li>Object to or restrict certain processing activities.</li>
                    </ul>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Contact</h2>
                    <p class="text-gray-700 mt-2">
                        For privacy inquiries, contact us at <a href="mailto:info@oposchat.com" class="text-orange-600 hover:underline">info@oposchat.com</a>.
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


