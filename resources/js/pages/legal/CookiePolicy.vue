<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import SiteHeader from '@/components/SiteHeader.vue';
import SiteFooter from '@/components/SiteFooter.vue';
</script>

<template>
    <Head title="Política de Cookies" />
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
        <SiteHeader />

        <div class="max-w-5xl mx-auto px-4 py-12">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Política de Cookies</h1>
                <p class="text-gray-600 mt-2">Última actualización: {{ new Date().toLocaleDateString('es-ES') }}</p>
            </div>

            <div class="bg-white rounded-2xl shadow-md border border-gray-200 p-8 space-y-8 leading-relaxed text-gray-800">
                
                <section>
                    <h2 class="text-2xl font-semibold text-gray-900 mb-3">1. Responsable del tratamiento</h2>
                    <p>
                        La presente Política de Cookies es aplicable al sitio web 
                        <strong>https://oposchat.duckdns.org/</strong> (en adelante, “OposChat” o “la Plataforma”), 
                        titularidad de <strong>[NOMBRE DE LA EMPRESA / AUTÓNOMO]</strong>, con domicilio en 
                        [DIRECCIÓN COMPLETA] y NIF [XXXXXXXXX]. 
                        Para cualquier duda, puede contactarnos a través del correo electrónico 
                        <a href="mailto:[EMAIL DE CONTACTO]" class="text-blue-700 hover:underline">[EMAIL DE CONTACTO]</a>.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold text-gray-900 mb-3">2. ¿Qué son las cookies?</h2>
                    <p>
                        Las cookies son pequeños archivos de texto que los sitios web almacenan en el dispositivo del usuario al navegar. 
                        Permiten reconocer al usuario, recordar sus preferencias o facilitar la navegación.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold text-gray-900 mb-3">3. Tipos de cookies utilizadas</h2>
                    <p>
                        En OposChat utilizamos cookies propias y de terceros con distintas finalidades. 
                        A continuación, se detalla la tipología y finalidad de cada una:
                    </p>

                    <!-- Tabla: Cookies Técnicas -->
                    <div class="mt-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-2">Cookies técnicas o necesarias</h3>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-gray-100 text-gray-800 font-semibold">
                                    <tr>
                                        <th class="px-4 py-3">Nombre</th>
                                        <th class="px-4 py-3">Duración</th>
                                        <th class="px-4 py-3">Finalidad</th>
                                        <th class="px-4 py-3">Proveedor</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">XSRF-TOKEN</td>
                                        <td class="px-4 py-3">Sesión</td>
                                        <td class="px-4 py-3">Previene ataques de tipo CSRF en Laravel.</td>
                                        <td class="px-4 py-3">OposChat</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">laravel_session</td>
                                        <td class="px-4 py-3">Sesión</td>
                                        <td class="px-4 py-3">Mantiene la sesión de usuario iniciada.</td>
                                        <td class="px-4 py-3">OposChat</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">cookie_consent</td>
                                        <td class="px-4 py-3">1 año</td>
                                        <td class="px-4 py-3">Guarda la preferencia del usuario sobre el uso de cookies.</td>
                                        <td class="px-4 py-3">OposChat</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">__stripe_mid</td>
                                        <td class="px-4 py-3">1 año</td>
                                        <td class="px-4 py-3">Identifica al usuario y previene fraude en pagos.</td>
                                        <td class="px-4 py-3">Stripe, Inc.</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">__stripe_sid</td>
                                        <td class="px-4 py-3">Sesión</td>
                                        <td class="px-4 py-3">Controla la sesión de pago.</td>
                                        <td class="px-4 py-3">Stripe, Inc.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tabla: Cookies de personalización -->
                    <div class="mt-10">
                        <h3 class="text-xl font-semibold text-gray-800 mb-2">Cookies de personalización</h3>
                        <p class="text-gray-700 mb-4">
                            Se utilizan para recordar las preferencias del usuario, como el idioma o el modo visual.
                        </p>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-gray-100 text-gray-800 font-semibold">
                                    <tr>
                                        <th class="px-4 py-3">Nombre</th>
                                        <th class="px-4 py-3">Duración</th>
                                        <th class="px-4 py-3">Finalidad</th>
                                        <th class="px-4 py-3">Proveedor</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">user_lang</td>
                                        <td class="px-4 py-3">1 año</td>
                                        <td class="px-4 py-3">Guarda el idioma seleccionado por el usuario.</td>
                                        <td class="px-4 py-3">OposChat</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-900">theme_mode</td>
                                        <td class="px-4 py-3">1 año</td>
                                        <td class="px-4 py-3">Guarda la preferencia de modo oscuro o claro.</td>
                                        <td class="px-4 py-3">OposChat</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold text-gray-900 mb-3">4. Gestión y desactivación de cookies</h2>
                    <p>
                        El usuario puede configurar o rechazar las cookies (excepto las estrictamente necesarias) 
                        mediante el panel de configuración disponible en el pie de página del sitio web, o 
                        a través de la configuración de su navegador.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold text-gray-900 mb-3">5. Transferencias internacionales de datos</h2>
                    <p>
                        Algunos de los proveedores utilizados por OposChat, como <strong>Stripe, Inc.</strong>, 
                        pueden realizar transferencias internacionales de datos fuera del Espacio Económico Europeo (EEE). 
                        Estas transferencias se realizan bajo las <strong>Cláusulas Contractuales Tipo</strong> aprobadas por la Comisión Europea, 
                        garantizando un nivel adecuado de protección de los datos personales.
                    </p>
                </section>

                <section>
                    <h2 class="text-2xl font-semibold text-gray-900 mb-3">6. Actualizaciones y cambios</h2>
                    <p>
                        OposChat podrá modificar esta Política de Cookies en función de nuevas exigencias legislativas 
                        o con la finalidad de adaptar dicha política a las instrucciones dictadas por la AEPD. 
                        La fecha de la última revisión se actualizará automáticamente en el pie de página del sitio web.
                    </p>
                </section>
            </div>

            <div class="mt-8">
                <Link :href="route('home')" class="text-sm text-gray-600 hover:text-gray-900 underline">← Volver al inicio</Link>
            </div>
        </div>

        <SiteFooter />
    </div>
</template>

