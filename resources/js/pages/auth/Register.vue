<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, LoaderCircle, Mail, Lock, User, Check, X } from 'lucide-vue-next';
import { ref, computed } from 'vue';

const showPassword = ref(false);
const showConfirmPassword = ref(false);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
};

const toggleConfirmPasswordVisibility = () => {
    showConfirmPassword.value = !showConfirmPassword.value;
};

// Password strength validation
const passwordStrength = computed(() => {
    const password = form.password;
    const checks = {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /\d/.test(password),
        special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
    };
    
    const score = Object.values(checks).filter(Boolean).length;
    return { checks, score };
});

const passwordsMatch = computed(() => {
    return form.password && form.password_confirmation && form.password === form.password_confirmation;
});
</script>

<template>
    <AuthBase title="Create your account" description="Join us today and start your journey">
        <Head title="Register" />

        <Card class="p-8 shadow-lg border-0 bg-white/80 backdrop-blur-sm">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Name Field -->
                <div class="space-y-2">
                    <Label for="name" class="text-sm font-medium text-gray-700">
                        Full name
                    </Label>
                    <div class="relative">
                        <User class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-white" />
                        <Input
                            id="name"
                            type="text"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="name"
                            v-model="form.name"
                            placeholder="Enter your full name"
                            class="pl-10 h-12 border-gray-200 focus:border-blue-500 focus:ring-blue-500 transition-colors dark:text-black"
                            :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.name }"
                        />
                    </div>
                    <InputError :message="form.errors.name" class="text-xs" />
                </div>

                <!-- Email Field -->
                <div class="space-y-2">
                    <Label for="email" class="text-sm font-medium text-gray-700">
                        Email address
                    </Label>
                    <div class="relative">
                        <Mail class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-white" />
                        <Input
                            id="email"
                            type="email"
                            required
                            :tabindex="2"
                            autocomplete="email"
                            v-model="form.email"
                            placeholder="Enter your email"
                            class="pl-10 h-12 border-gray-200 focus:border-blue-500 focus:ring-blue-500 transition-colors dark:text-black"
                            :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.email }"
                        />
                    </div>
                    <InputError :message="form.errors.email" class="text-xs" />
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <Label for="password" class="text-sm font-medium text-gray-700">
                        Password
                    </Label>
                    <div class="relative">
                        <Lock class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-white" />
                        <Input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            :tabindex="3"
                            autocomplete="new-password"
                            v-model="form.password"
                            placeholder="Create a strong password"
                            class="pl-10 pr-10 h-12 border-gray-200 focus:border-blue-500 focus:ring-blue-500 transition-colors dark:text-black"
                            :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.password }"
                        />
                        <button
                            type="button"
                            @click="togglePasswordVisibility"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:text-white dark:hover:text-gray-200 transition-colors"
                        >
                            <Eye v-if="!showPassword" class="h-4 w-4" />
                            <EyeOff v-else class="h-4 w-4" />
                        </button>
                    </div>
                    
                    <!-- Password Strength Indicator -->
                    <div v-if="form.password" class="space-y-2">
                        <div class="flex space-x-1">
                            <div 
                                v-for="i in 5" 
                                :key="i"
                                class="h-1 flex-1 rounded-full transition-colors"
                                :class="{
                                    'bg-red-300': passwordStrength.score < 2,
                                    'bg-yellow-300': passwordStrength.score >= 2 && passwordStrength.score < 4,
                                    'bg-green-400': passwordStrength.score >= 4,
                                    'bg-gray-200': i > passwordStrength.score
                                }"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="flex items-center space-x-1">
                                <Check v-if="passwordStrength.checks.length" class="h-3 w-3 text-green-500" />
                                <X v-else class="h-3 w-3 text-gray-400" />
                                <span :class="passwordStrength.checks.length ? 'text-green-600' : 'text-gray-500'">
                                    8+ characters
                                </span>
                            </div>
                            <div class="flex items-center space-x-1">
                                <Check v-if="passwordStrength.checks.uppercase" class="h-3 w-3 text-green-500" />
                                <X v-else class="h-3 w-3 text-gray-400" />
                                <span :class="passwordStrength.checks.uppercase ? 'text-green-600' : 'text-gray-500'">
                                    Uppercase
                                </span>
                            </div>
                            <div class="flex items-center space-x-1">
                                <Check v-if="passwordStrength.checks.number" class="h-3 w-3 text-green-500" />
                                <X v-else class="h-3 w-3 text-gray-400" />
                                <span :class="passwordStrength.checks.number ? 'text-green-600' : 'text-gray-500'">
                                    Number
                                </span>
                            </div>
                            <div class="flex items-center space-x-1">
                                <Check v-if="passwordStrength.checks.special" class="h-3 w-3 text-green-500" />
                                <X v-else class="h-3 w-3 text-gray-400" />
                                <span :class="passwordStrength.checks.special ? 'text-green-600' : 'text-gray-500'">
                                    Special char
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <InputError :message="form.errors.password" class="text-xs" />
                </div>

                <!-- Confirm Password Field -->
                <div class="space-y-2">
                    <Label for="password_confirmation" class="text-sm font-medium text-gray-700">
                        Confirm password
                    </Label>
                    <div class="relative">
                        <Lock class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-white" />
                        <Input
                            id="password_confirmation"
                            :type="showConfirmPassword ? 'text' : 'password'"
                            required
                            :tabindex="4"
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            placeholder="Confirm your password"
                            class="pl-10 pr-10 h-12 border-gray-200 focus:border-blue-500 focus:ring-blue-500 transition-colors dark:text-black"
                            :class="{ 
                                'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.password_confirmation,
                                'border-green-300 focus:border-green-500 focus:ring-green-500': passwordsMatch && form.password_confirmation
                            }"
                        />
                        <button
                            type="button"
                            @click="toggleConfirmPasswordVisibility"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:text-white dark:hover:text-gray-200 transition-colors"
                        >
                            <Eye v-if="!showConfirmPassword" class="h-4 w-4" />
                            <EyeOff v-else class="h-4 w-4" />
                        </button>
                    </div>
                    
                    <!-- Password Match Indicator -->
                    <div v-if="form.password_confirmation" class="flex items-center space-x-2 text-xs">
                        <Check v-if="passwordsMatch" class="h-3 w-3 text-green-500" />
                        <X v-else class="h-3 w-3 text-red-500" />
                        <span :class="passwordsMatch ? 'text-green-600' : 'text-red-600'">
                            {{ passwordsMatch ? 'Passwords match' : 'Passwords do not match' }}
                        </span>
                    </div>
                    
                    <InputError :message="form.errors.password_confirmation" class="text-xs" />
                </div>

                <!-- Terms and Privacy -->
                <div class="text-xs text-gray-500 dark:text-gray-300 bg-gray-50 dark:bg-gray-800 p-3 rounded-lg">
                    By creating an account, you agree to our 
                    <TextLink href="#" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">Terms of Service</TextLink>
                    and 
                    <TextLink href="#" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">Privacy Policy</TextLink>.
                </div>

                <!-- Submit Button -->
                <Button 
                    type="submit" 
                    class="w-full h-12 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-medium rounded-lg transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98]" 
                    :tabindex="5" 
                    :disabled="form.processing"
                >
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
                    <span v-if="!form.processing">Create your account</span>
                    <span v-else>Creating account...</span>
                </Button>
            </form>
        </Card>

        <!-- Divider -->
        <div class="relative my-6">
            <Separator class="bg-gray-200" />
            <div class="absolute inset-0 flex items-center justify-center">
                <span class="bg-gray-50 px-4 text-xs text-gray-500 font-medium">
                    Already have an account?
                </span>
            </div>
        </div>

        <!-- Sign In Link -->
        <div class="text-center">
            <p class="text-sm text-gray-600">
                Ready to sign in?
                <TextLink 
                    :href="route('login')" 
                    class="font-medium text-blue-600 hover:text-blue-800 transition-colors ml-1" 
                    :tabindex="6"
                >
                    Sign in to your account
                </TextLink>
            </p>
        </div>
    </AuthBase>
</template>
