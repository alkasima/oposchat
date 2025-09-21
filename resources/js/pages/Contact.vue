<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import SiteHeader from '@/components/SiteHeader.vue';
import SiteFooter from '@/components/SiteFooter.vue';
import { useAppearance } from '@/composables/useAppearance';

const { isDark } = useAppearance();

// Contact form data
const contactForm = ref({
    subject: '',
    name: '',
    email: '',
    message: '',
    termsAccepted: false
});

// New oppositions request form data
const oppositionsForm = ref({
    request: '',
    termsAccepted: false
});

// Form submission states
const isSubmittingContact = ref(false);
const isSubmittingOppositions = ref(false);
const contactSuccess = ref(false);
const oppositionsSuccess = ref(false);

const submitContactForm = async () => {
    if (!contactForm.value.termsAccepted) {
        alert('Please accept the terms and conditions');
        return;
    }

    isSubmittingContact.value = true;
    
    try {
        const response = await fetch('/contact/submit', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                subject: contactForm.value.subject,
                name: contactForm.value.name,
                email: contactForm.value.email,
                message: contactForm.value.message,
                terms_accepted: contactForm.value.termsAccepted
            })
        });

        const data = await response.json();

        if (data.success) {
            contactSuccess.value = true;
            contactForm.value = {
                subject: '',
                name: '',
                email: '',
                message: '',
                termsAccepted: false
            };
        } else {
            alert(data.message || 'Error sending message. Please try again.');
        }
    } catch (error) {
        console.error('Error submitting contact form:', error);
        alert('Error sending message. Please try again.');
    } finally {
        isSubmittingContact.value = false;
    }
};

const submitOppositionsForm = async () => {
    if (!oppositionsForm.value.termsAccepted) {
        alert('Please accept the terms and conditions');
        return;
    }

    isSubmittingOppositions.value = true;
    
    try {
        const response = await fetch('/contact/oppositions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({
                request: oppositionsForm.value.request,
                terms_accepted: oppositionsForm.value.termsAccepted
            })
        });

        const data = await response.json();

        if (data.success) {
            oppositionsSuccess.value = true;
            oppositionsForm.value = {
                request: '',
                termsAccepted: false
            };
        } else {
            alert(data.message || 'Error sending request. Please try again.');
        }
    } catch (error) {
        console.error('Error submitting oppositions form:', error);
        alert('Error sending request. Please try again.');
    } finally {
        isSubmittingOppositions.value = false;
    }
};
</script>

<template>
    <Head title="Contact Us - OposChat" />
    
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
        <SiteHeader />
        
        <!-- Header -->
        <div class="text-white py-16" style="background-color: #FFA900;">
            <div class="container mx-auto px-4 text-center">
                <h1 class="text-4xl md:text-5xl font-bold mb-4">Contact Us</h1>
                <p class="text-xl text-white/90 max-w-2xl mx-auto">
                    Have any questions or suggestions? We're here to help you
                </p>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container mx-auto px-4 py-16">
            <div class="grid lg:grid-cols-2 gap-8 max-w-6xl mx-auto">
                <!-- Contact Form -->
                <Card class="shadow-lg">
                    <CardHeader class="bg-gradient-to-r from-gray-800 to-gray-900 text-white rounded-t-lg">
                        <CardTitle class="text-center text-lg font-semibold">
                            In case of doubt, contact us
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-6">
                        <form @submit.prevent="submitContactForm" class="space-y-4">
                            <!-- Subject -->
                            <div>
                                <Input
                                    v-model="contactForm.subject"
                                    placeholder="SUBJECT"
                                    required
                                    class="text-center border-2 border-gray-300 focus:border-orange-500"
                                />
                            </div>

                            <!-- Name -->
                            <div>
                                <Input
                                    v-model="contactForm.name"
                                    placeholder="NAME"
                                    required
                                    class="text-center border-2 border-gray-300 focus:border-orange-500"
                                />
                            </div>

                            <!-- Email -->
                            <div>
                                <Input
                                    v-model="contactForm.email"
                                    type="email"
                                    placeholder="EMAIL"
                                    required
                                    class="text-center border-2 border-gray-300 focus:border-orange-500"
                                />
                            </div>

                            <!-- Message -->
                            <div>
                                <Textarea
                                    v-model="contactForm.message"
                                    placeholder="WRITE YOUR MESSAGE"
                                    rows="4"
                                    required
                                    class="text-center border-2 border-gray-300 focus:border-orange-500 resize-none"
                                />
                            </div>

                            <!-- Terms and Submit -->
                            <div class="space-y-4">
                                <div class="flex items-center space-x-2">
                                    <Checkbox
                                        v-model:checked="contactForm.termsAccepted"
                                        id="contact-terms"
                                    />
                                    <label for="contact-terms" class="text-sm text-gray-600">
                                        I have read the terms and conditions
                                    </label>
                                </div>

                                <Button
                                    type="submit"
                                    :disabled="isSubmittingContact"
                                    class="w-full bg-black hover:bg-gray-800 text-white font-semibold py-3 rounded-lg transition-colors"
                                >
                                    <span v-if="isSubmittingContact">Sending...</span>
                                    <span v-else>SEND</span>
                                </Button>

                                <div v-if="contactSuccess" class="text-center text-green-600 font-semibold">
                                    Message sent successfully!
                                </div>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- New Oppositions Request Form -->
                <Card class="shadow-lg">
                    <CardHeader class="bg-gradient-to-r from-gray-800 to-gray-900 text-white rounded-t-lg">
                        <CardTitle class="text-center text-lg font-semibold">
                            What other exams should we incorporate?
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-6">
                        <form @submit.prevent="submitOppositionsForm" class="space-y-4">
                            <!-- Request -->
                            <div>
                                <Textarea
                                    v-model="oppositionsForm.request"
                                    placeholder="REQUEST NEW EXAMS"
                                    rows="6"
                                    required
                                    class="text-center border-2 border-gray-300 focus:border-orange-500 resize-none"
                                />
                            </div>

                            <!-- Terms and Submit -->
                            <div class="space-y-4">
                                <div class="flex items-center space-x-2">
                                    <Checkbox
                                        v-model:checked="oppositionsForm.termsAccepted"
                                        id="oppositions-terms"
                                    />
                                    <label for="oppositions-terms" class="text-sm text-gray-600">
                                        I have read the terms and conditions
                                    </label>
                                </div>

                                <Button
                                    type="submit"
                                    :disabled="isSubmittingOppositions"
                                    class="w-full bg-black hover:bg-gray-800 text-white font-semibold py-3 rounded-lg transition-colors"
                                >
                                    <span v-if="isSubmittingOppositions">Sending...</span>
                                    <span v-else>SEND</span>
                                </Button>

                                <div v-if="oppositionsSuccess" class="text-center text-green-600 font-semibold">
                                    Request sent successfully!
                                </div>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <!-- Additional Info -->
            <div class="mt-16 text-center">
                <div class="max-w-3xl mx-auto">
                    <h2 class="text-2xl font-bold mb-4 text-gray-900">
                        Other ways to contact us
                    </h2>
                    <div class="grid md:grid-cols-3 gap-6 mt-8">
                        <div class="text-center">
                            <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <span class="text-2xl">📧</span>
                            </div>
                            <h3 class="font-semibold mb-2 text-gray-900">Email</h3>
                            <p class="text-gray-600">contact@oposchat.com</p>
                        </div>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <span class="text-2xl">📱</span>
                            </div>
                            <h3 class="font-semibold mb-2 text-gray-900">Phone</h3>
                            <p class="text-gray-600">+34 900 123 456</p>
                        </div>
                        <div class="text-center">
                            <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <span class="text-2xl">⏰</span>
                            </div>
                            <h3 class="font-semibold mb-2 text-gray-900">Hours</h3>
                            <p class="text-gray-600">Monday to Friday<br>9:00 - 18:00</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <SiteFooter />
    </div>
</template>
