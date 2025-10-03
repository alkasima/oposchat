<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import SiteHeader from '@/components/SiteHeader.vue';
import SiteFooter from '@/components/SiteFooter.vue';
</script>

<template>
    <Head title="Sobre OposChat" />
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
        <SiteHeader />

        <main class="max-w-4xl mx-auto px-4 py-12">
            <h1 class="text-3xl font-bold text-gray-900">About OposChat</h1>
            <p class="text-gray-600 mt-3">
                OposChat te ayuda a preparar tus oposiciones utilizando nuestro chat basado en IA, contenido seleccionado para que tengas el mejor acceso al temario y práctica.
            </p>

            <section class="mt-8 grid md:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900"> Nuestra misión</h2>
                    <p class="text-gray-700 mt-2">Hacer que el estudio de tu oposición sea accesible y sencillo.</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900">Qué ofrecemos</h2>
                    <p class="text-gray-700 mt-2">Asistencia de IA personalizada, contenido actualizado y análisis para seguir el progreso..</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900">Contáctanos</h2>
                    <p class="text-gray-700 mt-2">Envíanos un correo <a href="mailto:info@oposchat.com" class="text-orange-600 hover:underline">info@oposchat.com</a>.</p>
                </div>
            </section>
        </main>

        <SiteFooter />
    </div>
</template>


