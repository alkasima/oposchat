<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SiteHeader from '@/components/SiteHeader.vue';
import SiteFooter from '@/components/SiteFooter.vue';
</script>

<template>
    <Head title="Academy Contact - OposChat" />
    
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 dark:from-slate-900 dark:to-slate-800">
        <SiteHeader />
        
        <!-- Header -->
        <div class="text-white py-16" style="background-color: #FFA900;">
            <div class="container mx-auto px-4 text-center">
                <h1 class="text-4xl md:text-5xl font-bold mb-4">Contacto para academias</h1>
                <p class="text-xl text-white/90 max-w-2xl mx-auto">
                   ¿Tienes una academia? Incorpora OposChat para brindar aprendizaje de vanguardia con inteligencia artificial integrada para tu empresa

                </p>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container mx-auto px-4 py-16">
            <div class="max-w-4xl mx-auto">
                <!-- Professional Content Section -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 md:p-12 mb-8">
                    <div class="text-center mb-8">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">
                            Lleva a tu academia al siguiente nivel
                        </h2>
                        
                        <div class="space-y-6 text-lg text-gray-700 dark:text-gray-300 max-w-3xl mx-auto">
                            <p>
                               Únete a la lista de academias líderes que confían en OposChat para mejorar la experiencia de aprendizaje de sus estudiantes con nuestra plataforma avanzada impulsada por IA.
                            </p>
                            
                            <p>
                               Nuestro plan para academias ofrece soluciones integrales adaptadas a las necesidades específicas de su empresa, que incluyen análisis avanzados, acceso ilimitado y soporte dedicado.
                            </p>
                            
                            <p>
                            Veamos cómo OposChat puede integrarse perfectamente en su ecosistema educativo y potenciarlo, ayudando a sus estudiantes a alcanzar sus objetivos.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="bg-gradient-to-r from-orange-500 to-red-600 rounded-2xl shadow-xl p-8 md:p-12 text-center">
                    <div class="text-white">
                        <h3 class="text-2xl font-bold mb-4">Listo para empezar?</h3>
                        <p class="text-xl mb-6 text-white/90">
                       Contacta con nuestro equipo para evaluar las necesidades de tu academia.
                        </p>
                        
                        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 inline-block">
                            <div class="flex items-center justify-center space-x-3">
                                <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                                    <span class="text-2xl">📧</span>
                                </div>
                                <div class="text-left">
                                    <p class="text-sm text-white/80 mb-1">Contacto para academias</p>
                                    <a 
                                        href="mailto:academy@oposchat.com" 
                                        class="text-2xl font-bold text-white hover:text-white/80 transition-colors"
                                    >
                                        academy@oposchat.com
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <p class="text-white/80 mt-6 text-sm">
                           Tu respuesta llegará dentro de las 24 horas durante los días hábiles.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <SiteFooter />
    </div>
</template>
