<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import SiteHeader from '@/components/SiteHeader.vue';
import SiteFooter from '@/components/SiteFooter.vue';
</script>

<template>
    <Head title="Terms of Service" />
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
        <SiteHeader />

        <div class="max-w-4xl mx-auto px-4 py-12">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Terms of Service</h1>
                <p class="text-gray-600 mt-2">Last updated: {{ new Date().toLocaleDateString() }}</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-6">
                <p class="text-gray-700">
                    By accessing or using OposChat, you agree to these Terms of Service. Please read them carefully.
                </p>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Use of Service</h2>
                    <ul class="list-disc pl-6 mt-2 text-gray-700 space-y-1">
                        <li>You must be at least 13 years old or meet your jurisdiction’s minimum age.</li>
                        <li>Provide accurate registration information and keep your account secure.</li>
                        <li>Do not misuse the service or violate applicable laws.</li>
                    </ul>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Subscriptions and Billing</h2>
                    <p class="text-gray-700 mt-2">
                        Paid plans may auto-renew unless cancelled. Prices, features, and billing cycles are shown at checkout. You can manage your subscription from account settings.
                    </p>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Content and Acceptable Use</h2>
                    <ul class="list-disc pl-6 mt-2 text-gray-700 space-y-1">
                        <li>You are responsible for content you upload or share.</li>
                        <li>No illegal, infringing, or harmful content.</li>
                        <li>No attempts to disrupt, reverse engineer, or abuse the platform.</li>
                    </ul>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Disclaimers and Liability</h2>
                    <p class="text-gray-700 mt-2">
                        The service is provided "as is" without warranties. To the extent permitted by law, OposChat is not liable for indirect or consequential damages.
                    </p>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Termination</h2>
                    <p class="text-gray-700 mt-2">
                        We may suspend or terminate accounts that violate these terms. You may cancel at any time from your settings.
                    </p>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Contact</h2>
                    <p class="text-gray-700 mt-2">
                        Questions about these terms? Contact <a href="mailto:info@oposchat.com" class="text-orange-600 hover:underline">info@oposchat.com</a>.
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


