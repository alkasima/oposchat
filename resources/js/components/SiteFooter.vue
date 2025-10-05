<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <footer class="bg-gradient-to-r from-gray-900 via-blue-900 to-indigo-900 text-white py-16">
        <div class="container mx-auto px-4">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <div class="col-span-2 md:col-span-1">
                    <Link :href="route('home')" class="flex items-center space-x-4 mb-6 hover:opacity-90 transition-opacity duration-300">
                        <div class="w-14 h-14 bg-gradient-to-br from-white to-white rounded-full flex items-center justify-center shadow-lg transform hover:scale-105 transition-transform duration-300 p-2">
                            <img src="/images/logo.png" alt="OposChat" class="w-full h-full rounded-full" />
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold">OposChat</h3>
                            <p class="text-blue-200 text-sm">Tu aliado para la plaza</p>
                        </div>
                    </Link>
                    <p class="text-gray-300 leading-relaxed">
                        Tu futuro empieza con una preparación inteligente.
                    </p>
                </div>

                <div>
                    <h4 class="font-bold mb-6 text-lg">About Us</h4>
                    <ul class="space-y-3 text-gray-300">
                        <li><Link :href="route('about')" class="hover:text-yellow-400 transition-colors duration-300 flex items-center"><span class="mr-2">→</span>Nuestra historia</Link></li>
                        <li><Link :href="route('contact')" class="hover:text-yellow-400 transition-colors duration-300 flex items-center"><span class="mr-2">→</span>Contacta con nosotros</Link></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold mb-6 text-lg">Legal</h4>
                    <ul class="space-y-3 text-gray-300">
                        <li><Link :href="route('legal.privacy')" class="hover:text-yellow-400 transition-colors duration-300 flex items-center"><span class="mr-2">→</span>Política de privacidad</Link></li>
                        <li><Link :href="route('legal.terms')" class="hover:text-yellow-400 transition-colors duration-300 flex items-center"><span class="mr-2">→</span>Términos y Condiciones</Link></li>
                        <li><Link :href="route('legal.cookies')" class="hover:text-yellow-400 transition-colors duration-300 flex items-center"><span class="mr-2">→</span>Política de Cookies</Link></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold mb-6 text-lg">Conecta con nosotros</h4>
                    <div class="flex space-x-4 mb-6">
                        <a href="#" class="w-12 h-12 bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl flex items-center justify-center hover:from-blue-500 hover:to-blue-600 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg">
                            <span class="text-xl">📘</span>
                        </a>
                        <a href="#" class="w-12 h-12 bg-gradient-to-br from-pink-600 to-pink-700 rounded-xl flex items-center justify-center hover:from-pink-500 hover:to-pink-600 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg">
                            <span class="text-xl">📷</span>
                        </a>
                        <a href="#" class="w-12 h-12 bg-gradient-to-br from-blue-400 to-blue-500 rounded-xl flex items-center justify-center hover:from-blue-300 hover:to-blue-400 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg">
                            <span class="text-xl">🐦</span>
                        </a>
                        <a href="#" class="w-12 h-12 bg-gradient-to-br from-purple-600 to-purple-700 rounded-xl flex items-center justify-center hover:from-purple-500 hover:to-purple-600 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg">
                            <span class="text-xl">💼</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-700 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-400 mb-4 md:mb-0">
                    &copy; {{ new Date().getFullYear() }} OposChat. Todos los derechos reservados.
                </p>
            </div>
        </div>
    </footer>
</template>


