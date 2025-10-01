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
                        1. Seguridad de datos
                    </h4>
                    <p class="leading-relaxed">
                        Se utilizan medidas de seguridad apropiadas para proteger tus datos contra manipulación, pérdida, destrucción y acceso no autorizado por parte de terceros.
                        


                    </p>
                </div>

                <!-- Data Collection -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                        <FileText class="w-5 h-5 mr-2 text-green-600" />
                        2. Recolección y almacenamiento de datos
                    </h4>
                    <p class="leading-relaxed mb-3">
                   Todos los datos y chats se almacenarán en tu historial de chat para poder acceder a conversaciones anteriores. Los datos almacenados también se utilizan para mejorar la calidad del sitio web y optimizar su uso por parte de los usuarios:
                    </p>
                    <div class="ml-4 space-y-1">
                        <p><strong>a.</strong> Datos de cuenta: nombre de usuario, correo electrónico e IP.</p>
                        <p><strong>b.</strong> Historial de chats.</p>
                    </div>
                </div>

                <!-- Data Deletion -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                        <Trash2 class="w-5 h-5 mr-2 text-red-600" />
                        3. Eliminación de datos
                    </h4>
                    <p class="leading-relaxed">
                        Tienes derecho a que tus datos y cuenta sean eliminados por completo e inmediatamente tras tu solicitud.
                    </p>
                </div>

                <!-- Third Party Disclosure -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                        <Users class="w-5 h-5 mr-2 text-orange-600" />
                        4. Divulgación a terceros
                    </h4>
                    <p class="leading-relaxed mb-3">
                        El sitio web envía información a un chat de IA para laborar respuestas.
                    </p>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                        <p class="text-yellow-800 dark:text-yellow-200 font-medium">
                            ⚠️ No divulgues datos sensibles como contraseñas, tarjetas de crédito e información privada.
                        </p>
                    </div>
                </div>

                <!-- Rights -->
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                        <Scale class="w-5 h-5 mr-2 text-purple-600" />
                        5. Derechos
                    </h4>
                    <p class="leading-relaxed text-gray-600 dark:text-gray-400">
                        Seguiremos redactando esta sección y la actualizaremos.
                    </p>
                </div>
            </div>

            <!-- Contact Section -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center">
                    <Mail class="w-5 h-5 mr-2 text-orange-600" />
                    Preguntas o dudas
                </h4>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Si tienes preguntas sobre nuestra política de privacidad o prácticas de manejo de datos, contáctanos.
                </p>
                <div class="flex items-center space-x-4">
                    <Button @click="window.open('mailto:info@oposchat.com', '_blank')" 
                            class="bg-orange-500 hover:bg-orange-600 text-white">
                        <Mail class="w-4 h-4 mr-2" />
                        Contactar soporte 
                    </Button>
                    <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                        <Clock class="w-4 h-4 mr-1" />
                        Respuesta en 24 horas oposchat@gmail.com
                    </div>
                </div>
            </div>
        </div>
    </SettingsLayout>
</template>
