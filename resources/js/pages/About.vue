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
               Oposchat nace de estudiantes por y para estudiantes. Somos un grupo de amigos de carrera y durante los años en los que no paramos de estudiar , nos percatamos de la ineficiencia de nuestros métodos de estudio. Cientos de horas empleadas para finalmente no plasmar todo ese ese esfuerzo en los exámenes finales. 

Hacia final de carrera comenzaron a emerger las primeras inteligencias artificiales y rápidamente las incorporamos a nuestros hábitos de estudio, dándonos cuenta de que reteníamos más en menos tiempo.

Así nace oposchat, de la necesidad de 4 amigos de encontrar una forma de estudio rápida, eficiente y entretenida.

Desde oposchat, integramos el  mejor temario junto con nuestro chat impulsado por IA, especialmente diseñada para el estudio de oposiciones .

Prueba nuestro servicio y empieza a disfrutar de las ventajas que oposchat tiene para ti.
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


