<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, LoaderCircle, Mail, Lock } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    console.log('Login form data:', {
        email: form.email,
        password: '***',
        remember: form.remember
    });
    
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
};
</script>

<template>
    <AuthBase title="Bienvenido" description="Inicia sesión para continuar">
        <Head title="Inicia sesión" />

        <!-- Status Messages -->
        <div
            v-if="status === 'verification-link-sent' || status === 'unverified'"
            class="mb-6 rounded-lg bg-green-50 border border-green-200 p-4 text-center"
        >
            <div class="text-sm font-medium text-green-800 mb-2">
                <span v-if="status === 'verification-link-sent'">
                    Te hemos enviado un correo de verificación. Revisa tu bandeja de entrada.
                </span>
                <span v-else>
                    Tu cuenta aún no está verificada. Por favor, verifica tu correo electrónico antes de iniciar sesión.
                </span>
            </div>
            <Link
                :href="route('email.verify.resend')"
                method="post"
                as="button"
                :data="{ email: form.email }"
                class="inline-flex items-center px-4 py-2 text-xs font-medium rounded-md bg-blue-600 text-white hover:bg-blue-700 transition-colors"
            >
                Reenviar correo de verificación
            </Link>
        </div>

        <!-- Generic status banner for other cases -->
        <div v-else-if="status" class="mb-6 rounded-lg bg-green-50 border border-green-200 p-4 text-center">
            <div class="text-sm font-medium text-green-800">
                {{ status }}
            </div>
        </div>

        <Card class="p-8 shadow-lg border-0 bg-white/80 backdrop-blur-sm">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Email Field -->
                <div class="space-y-2">
                    <Label for="email" class="text-sm font-medium text-gray-700">
                        Correo electrónico
                    </Label>
                    <div class="relative">
                        <Mail class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-white" />
                        <Input
                            id="email"
                            type="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            v-model="form.email"
                            placeholder="Introduce tu correo electrónico"
                            class="pl-10 h-12 border-gray-200 focus:border-blue-500 focus:ring-blue-500 transition-colors dark:text-black"
                            :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.email }"
                        />
                    </div>
                    <InputError :message="form.errors.email" class="text-xs" />
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label for="password" class="text-sm font-medium text-gray-700">
                            Contraseña
                        </Label>
                        <TextLink 
                            v-if="canResetPassword" 
                            :href="route('password.request')" 
                            class="text-xs text-blue-600 hover:text-blue-800 transition-colors" 
                            :tabindex="5"
                        >
                            ¿Olvidaste la contraseña?
                        </TextLink>
                    </div>
                    <div class="relative">
                        <Lock class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-white" />
                        <Input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            v-model="form.password"
                            placeholder="Introduce tu contraseña"
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
                    <InputError :message="form.errors.password" class="text-xs" />
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3 cursor-pointer">
                        <Checkbox 
                            id="remember" 
                            v-model="form.remember" 
                            :tabindex="3"
                            class="data-[state=checked]:bg-blue-600 data-[state=checked]:border-blue-600"
                        />
                        <span class="text-sm text-gray-600">Recuérdame durante 30 días</span>
                    </Label>
                </div>

                <!-- Submit Button -->
                <Button 
                    type="submit" 
                    class="w-full h-12 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-medium rounded-lg transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98]" 
                    :tabindex="4" 
                    :disabled="form.processing"
                >
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
                    <span v-if="!form.processing">Inicia sesión</span>
                    <span v-else>Iniciando sesión...</span>
                </Button>
            </form>
        </Card>

        <!-- Divider -->
        <div class="relative my-6">
            <Separator class="bg-gray-200" />
            <div class="absolute inset-0 flex items-center justify-center">
                <span class="bg-gray-50 px-4 text-xs text-gray-500 font-medium">
                    ¿Nuevo en nuestra plataforma?
                </span>
            </div>
        </div>

        <!-- Sign Up Link -->
        <div class="text-center">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                ¿No tienes cuenta todavía?
                <TextLink 
                    :href="route('register')" 
                    class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors ml-1" 
                    :tabindex="6"
                >
                    Crea una nueva cuenta
                </TextLink>
            </p>
        </div>
    </AuthBase>
</template>
