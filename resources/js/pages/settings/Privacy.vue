<script setup lang="ts">
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import { Button } from '@/components/ui/button';
import { 
    Shield,
    FileText,
    Trash2,
    Users,
    Scale,
    Clock,
    Mail
} from 'lucide-vue-next';
</script>

<template>
    <SettingsLayout 
        title="Privacy" 
        description="Information about how we handle your data and privacy"
    >
        <div class="space-y-6">
            <div class="space-y-6 text-sm text-gray-700 dark:text-gray-300">
                <!-- Data Security -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                        <Shield class="w-5 h-5 mr-2 text-blue-600" />
                        1. Data Security
                    </h4>
                    <p class="leading-relaxed">
                        Appropriate security measures are used to protect your data against manipulation, loss, destruction, and unauthorized access by third parties. Security is constantly being updated.
                    </p>
                </div>

                <!-- Data Collection -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                        <FileText class="w-5 h-5 mr-2 text-green-600" />
                        2. Data Collection and Storage
                    </h4>
                    <p class="leading-relaxed mb-3">
                        All data and chats will be stored in your chat history in order to access previous conversations. The stored data is also used to improve the quality of the website and improve usage by users:
                    </p>
                    <div class="ml-4 space-y-1">
                        <p><strong>a.</strong> Account data: username, email address, and IP.</p>
                        <p><strong>b.</strong> Chat history.</p>
                    </div>
                </div>

                <!-- Data Deletion -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                        <Trash2 class="w-5 h-5 mr-2 text-red-600" />
                        3. Data Deletion
                    </h4>
                    <p class="leading-relaxed">
                        You have the right to have your data and account completely deleted immediately upon your request.
                    </p>
                </div>

                <!-- Third Party Disclosure -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                        <Users class="w-5 h-5 mr-2 text-orange-600" />
                        4. Third Party Disclosure
                    </h4>
                    <p class="leading-relaxed mb-3">
                        The website sends information to an AI chat for the elaboration of responses.
                    </p>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                        <p class="text-yellow-800 dark:text-yellow-200 font-medium">
                            ⚠️ Do not disclose sensitive data such as passwords, credit cards, and private information.
                        </p>
                    </div>
                </div>

                <!-- Rights -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                        <Scale class="w-5 h-5 mr-2 text-purple-600" />
                        5. Rights
                    </h4>
                    <p class="leading-relaxed text-gray-600 dark:text-gray-400">
                        We will continue drafting this section and will be updating it accordingly.
                    </p>
                </div>
            </div>

            <!-- Contact Section -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                    <Mail class="w-5 h-5 mr-2 text-orange-600" />
                    Questions or Concerns?
                </h4>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    If you have any questions about our privacy policy or data handling practices, please contact us.
                </p>
                <div class="flex items-center space-x-4">
                    <Button @click="window.open('mailto:info@oposchat.com', '_blank')" 
                            class="bg-orange-500 hover:bg-orange-600 text-white">
                        <Mail class="w-4 h-4 mr-2" />
                        Contact Support
                    </Button>
                    <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                        <Clock class="w-4 h-4 mr-1" />
                        Response within 24 hours
                    </div>
                </div>
            </div>
        </div>
    </SettingsLayout>
</template>