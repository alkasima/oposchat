<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const isMenuOpen = ref(false);
const openFaq = ref(null);

const toggleFaq = (index) => {
    openFaq.value = openFaq.value === index ? null : index;
};

const faqData = [
    { 
        question: 'How does the AI-powered learning system work?', 
        answer: 'Our AI analyzes your performance patterns, identifies knowledge gaps, and creates personalized study plans that adapt in real-time to optimize your learning efficiency.' 
    },
    { 
        question: 'What exams do you currently support?', 
        answer: 'We currently support SAT, GRE, GMAT, and offer custom preparation for other standardized tests. Our content library is continuously expanding.' 
    },
    { 
        question: 'Is there a free trial available?', 
        answer: 'Yes! We offer a 7-day free trial with full access to our platform features. No credit card required to get started.' 
    },
    { 
        question: 'How often is the content updated?', 
        answer: 'Our content is updated monthly to reflect the latest exam patterns and requirements. We work with official test prep organizations to ensure accuracy.' 
    },
    { 
        question: 'Can I get personalized tutoring?', 
        answer: 'Our premium plans include one-on-one sessions with certified tutors who specialize in your target exam.' 
    }
];
</script>

<template>
    <Head title="OposChat - AI-Powered Exam Preparation">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>
    
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 font-inter">
        <!-- Header -->
        <header class="bg-gradient-to-r from-indigo-900 via-blue-900 to-purple-900 shadow-2xl sticky top-0 z-50 backdrop-blur-sm">
            <div class="container mx-auto px-4 py-4">
                <nav class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                           <div class="w-14 h-14 bg-gradient-to-br from-white to-white rounded-full flex items-center justify-center shadow-lg transform hover:scale-105 transition-transform duration-300 p-2">
                                <img src="/images/logo.png" alt="OposChat" class="w-full h-full rounded-full" />
                            </div>
                            <div class="absolute -top-1 -right-1 w-4 h-4 bg-green-400 rounded-full animate-pulse"></div>
                        </div>
                        <div>
                            <h1 class="text-white text-2xl font-bold bg-gradient-to-r from-white to-blue-200 bg-clip-text text-transparent">
                                OposChat
                            </h1>
                            <p class="text-blue-200 text-xs">AI-Powered Learning</p>
                        </div>
                    </div>
                    
                    <!-- Desktop Navigation -->
                    <div class="hidden md:flex items-center space-x-8">
                        <Link href="#home" class="text-white hover:text-yellow-300 transition-all duration-300 font-medium relative group">
                            Home
                            <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-yellow-300 transition-all duration-300 group-hover:w-full"></span>
                        </Link>
                        <div class="relative group">
                            <button class="text-white hover:text-yellow-300 transition-all duration-300 flex items-center font-medium">
                                Courses 
                                <svg class="w-4 h-4 ml-1 transform group-hover:rotate-180 transition-transform duration-300" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div class="absolute top-full left-0 mt-2 w-48 bg-white rounded-xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform translate-y-2 group-hover:translate-y-0">
                                <div class="py-2">
                                    <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">SAT Preparation</a>
                                    <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">GRE Preparation</a>
                                    <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">GMAT Preparation</a>
                                </div>
                            </div>
                        </div>
                        <Link href="#about" class="text-white hover:text-yellow-300 transition-all duration-300 font-medium relative group">
                            About
                            <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-yellow-300 transition-all duration-300 group-hover:w-full"></span>
                        </Link>
                        <Link 
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-6 py-2.5 rounded-full hover:from-yellow-500 hover:to-orange-600 transition-all duration-300 font-semibold shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                        >
                            Dashboard
                        </Link>
                        <Link 
                            v-else
                            :href="route('login')"
                            class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-6 py-2.5 rounded-full hover:from-yellow-500 hover:to-orange-600 transition-all duration-300 font-semibold shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                        >
                            Sign In / Register
                        </Link>
                    </div>

                    <!-- Mobile Menu Button -->
                    <button @click="isMenuOpen = !isMenuOpen" class="md:hidden text-white p-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </nav>

                <!-- Mobile Menu -->
                <div v-if="isMenuOpen" class="md:hidden mt-4 pb-4 border-t border-blue-800">
                    <div class="flex flex-col space-y-4 pt-4">
                        <Link href="#home" class="text-white hover:text-yellow-300 transition-colors">Home</Link>
                        <Link href="#courses" class="text-white hover:text-yellow-300 transition-colors">Courses</Link>
                        <Link href="#about" class="text-white hover:text-yellow-300 transition-colors">About</Link>
                        <Link 
                            v-if="$page.props.auth.user"
                            :href="route('dashboard')"
                            class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-4 py-2 rounded-full text-center"
                        >
                            Dashboard
                        </Link>
                        <Link 
                            v-else
                            :href="route('login')"
                            class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-4 py-2 rounded-full text-center"
                        >
                            Sign In / Register
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section id="home" class="relative py-20 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50"></div>
            <div class="absolute inset-0 opacity-40" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%239C92AC&quot; fill-opacity=&quot;0.05&quot;%3E%3Ccircle cx=&quot;30&quot; cy=&quot;30&quot; r=&quot;4&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')"></div>
            
            <div class="container mx-auto px-4 relative z-10">
                <div class="flex flex-col lg:flex-row items-center justify-between">
                    <div class="lg:w-3/5 mb-12 lg:mb-0">
                        <div class="inline-flex items-center bg-white/80 backdrop-blur-sm rounded-full px-4 py-2 mb-6 shadow-lg">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span>
                            <span class="text-sm font-medium text-gray-700">AI-Powered Learning Platform</span>
                        </div>
                        
                        <h1 class="text-5xl lg:text-7xl font-bold text-gray-900 mb-6 leading-tight">
                            Master Your 
                            <span class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent">
                                Exams
                            </span>
                            <br />with AI
                        </h1>
                        
                        <p class="text-xl text-gray-600 leading-relaxed mb-8 max-w-2xl">
                            From <span class="font-semibold text-blue-600">StudyChat</span>, we provide you with a powerful combination of 
                            <span class="font-semibold text-purple-600">Artificial Intelligence</span> along with 
                            <span class="font-semibold text-cyan-600">updated curriculum</span> and 
                            <span class="font-semibold text-green-600">practice exams</span> 
                            from various <span class="font-semibold text-orange-600">standardized tests</span> to 
                            <span class="font-semibold text-red-600">help you</span> 
                            <span class="font-semibold text-blue-600">succeed</span> and achieve your goals.
                        </p>

                        <div class="flex flex-col sm:flex-row gap-4">
                            <button class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-4 rounded-2xl font-semibold text-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-300 shadow-xl hover:shadow-2xl transform hover:-translate-y-1">
                                Start Learning Free
                            </button>
                            <button class="border-2 border-gray-300 text-gray-700 px-8 py-4 rounded-2xl font-semibold text-lg hover:border-blue-500 hover:text-blue-600 transition-all duration-300 bg-white/80 backdrop-blur-sm">
                                Watch Demo
                            </button>
                        </div>
                    </div>
                    
                    <div class="lg:w-2/5 flex justify-center">
                        <div class="relative">
                            <div class="w-80 h-80 bg-gradient-to-br from-blue-400 via-purple-500 to-indigo-600 rounded-3xl flex items-center justify-center shadow-2xl transform hover:scale-105 transition-transform duration-500 hover:rotate-2">
                                <div class="text-center text-white">
                                    <div class="text-8xl mb-4 animate-bounce">🧠</div>
                                    <p class="text-2xl font-bold mb-2">StudyChat</p>
                                    <p class="text-blue-100">AI Learning Assistant</p>
                                </div>
                            </div>
                            <div class="absolute -top-4 -right-4 w-24 h-24 bg-yellow-400 rounded-full flex items-center justify-center shadow-lg animate-pulse">
                                <span class="text-2xl">⚡</span>
                            </div>
                            <div class="absolute -bottom-4 -left-4 w-20 h-20 bg-green-400 rounded-full flex items-center justify-center shadow-lg animate-bounce">
                                <span class="text-xl">📚</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Courses Section -->
        <section id="courses" class="py-20 relative">
            <div class="absolute inset-0 bg-gradient-to-r from-gray-900 via-blue-900 to-purple-900"></div>
            <div class="absolute inset-0 opacity-20" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;100&quot; height=&quot;100&quot; viewBox=&quot;0 0 100 100&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z&quot; fill=&quot;%23ffffff&quot; fill-opacity=&quot;0.05&quot; fill-rule=&quot;evenodd&quot;/%3E%3C/svg%3E')"></div>
            
            <div class="container mx-auto px-4 relative z-10">
                <div class="text-center mb-16">
                    <h2 class="text-4xl lg:text-5xl font-bold text-white mb-6">
                        Available <span class="text-yellow-400">Courses</span>
                    </h2>
                    <p class="text-xl text-gray-300 max-w-3xl mx-auto">
                        Choose from our comprehensive selection of exam preparation courses
                    </p>
                </div>
                
                <div class="grid md:grid-cols-2 gap-8 max-w-6xl mx-auto mb-12">
                    <div class="group bg-white/10 backdrop-blur-sm border border-white/20 rounded-2xl p-8 hover:bg-white/20 transition-all duration-300 transform hover:-translate-y-2 hover:shadow-2xl">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-blue-600 rounded-xl flex items-center justify-center mr-4">
                                <span class="text-white text-xl">📊</span>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white group-hover:text-yellow-300 transition-colors">SAT Preparation</h3>
                                <p class="text-gray-300 text-sm">College Admission Test</p>
                            </div>
                        </div>
                        <p class="text-gray-300 mb-4">Comprehensive SAT preparation with AI-powered practice tests and personalized study plans.</p>
                        <div class="flex items-center justify-between">
                            <span class="inline-block bg-blue-500 text-white px-3 py-1 rounded-full text-sm font-medium">POPULAR</span>
                            <span class="text-yellow-400 font-semibold">1,200+ Students</span>
                        </div>
                    </div>
                    
                    <div class="group bg-white/10 backdrop-blur-sm border border-white/20 rounded-2xl p-8 hover:bg-white/20 transition-all duration-300 transform hover:-translate-y-2 hover:shadow-2xl">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-purple-400 to-purple-600 rounded-xl flex items-center justify-center mr-4">
                                <span class="text-white text-xl">🎓</span>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white group-hover:text-yellow-300 transition-colors">GRE Preparation</h3>
                                <p class="text-gray-300 text-sm">Graduate School Admission</p>
                            </div>
                        </div>
                        <p class="text-gray-300 mb-4">Advanced GRE preparation with adaptive learning technology and expert guidance.</p>
                        <div class="flex items-center justify-between">
                            <span class="inline-block bg-purple-500 text-white px-3 py-1 rounded-full text-sm font-medium">ADVANCED</span>
                            <span class="text-yellow-400 font-semibold">800+ Students</span>
                        </div>
                    </div>
                    
                    <div class="group bg-white/10 backdrop-blur-sm border border-white/20 rounded-2xl p-8 hover:bg-white/20 transition-all duration-300 transform hover:-translate-y-2 hover:shadow-2xl">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-green-600 rounded-xl flex items-center justify-center mr-4">
                                <span class="text-white text-xl">💼</span>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white group-hover:text-yellow-300 transition-colors">GMAT Preparation</h3>
                                <p class="text-gray-300 text-sm">Business School Admission</p>
                            </div>
                        </div>
                        <p class="text-gray-300 mb-4">Strategic GMAT preparation focused on business school admission requirements.</p>
                        <div class="flex items-center justify-between">
                            <span class="inline-block bg-green-500 text-white px-3 py-1 rounded-full text-sm font-medium">BUSINESS</span>
                            <span class="text-yellow-400 font-semibold">600+ Students</span>
                        </div>
                    </div>
                    
                    <div class="group bg-white/10 backdrop-blur-sm border border-white/20 rounded-2xl p-8 hover:bg-white/20 transition-all duration-300 transform hover:-translate-y-2 hover:shadow-2xl">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-orange-400 to-orange-600 rounded-xl flex items-center justify-center mr-4">
                                <span class="text-white text-xl">💬</span>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white group-hover:text-yellow-300 transition-colors">Custom Preparation</h3>
                                <p class="text-gray-300 text-sm">Personalized Learning</p>
                            </div>
                        </div>
                        <p class="text-gray-300 mb-4">Contact us for suggestions about specific exams you want to prepare for.</p>
                        <div class="flex items-center justify-between">
                            <span class="inline-block bg-orange-500 text-white px-3 py-1 rounded-full text-sm font-medium">CUSTOM</span>
                            <span class="text-yellow-400 font-semibold">Request Info</span>
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <button class="bg-gradient-to-r from-yellow-400 to-orange-500 text-gray-900 px-10 py-4 rounded-2xl font-bold text-lg hover:from-yellow-500 hover:to-orange-600 transition-all duration-300 shadow-2xl hover:shadow-3xl transform hover:-translate-y-1">
                        EXPLORE ALL COURSES
                    </button>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="py-20 bg-gradient-to-br from-blue-50 to-indigo-100">
            <div class="container mx-auto px-4">
                <div class="text-center mb-16">
                    <h2 class="text-4xl lg:text-5xl font-bold text-gray-900 mb-6">
                        What We <span class="text-blue-600">Offer</span>
                    </h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Discover our comprehensive approach to exam preparation with cutting-edge technology
                    </p>
                </div>
                
                <div class="grid md:grid-cols-3 gap-8 max-w-7xl mx-auto">
                    <div class="group bg-white rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2">
                        <div class="bg-gradient-to-r from-blue-600 to-purple-600 p-8 text-center relative overflow-hidden">
                            <div class="absolute inset-0 opacity-30" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; viewBox=&quot;0 0 40 40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;0.1&quot;%3E%3Cpath d=&quot;M20 20c0 11.046-8.954 20-20 20v20h40V20H20z&quot;/%3E%3C/g%3E%3C/svg%3E')"></div>
                            <div class="relative z-10">
                                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                                    <span class="text-3xl">🧠</span>
                                </div>
                                <h3 class="text-xl font-bold text-white mb-2">AI-Powered Learning</h3>
                                <p class="text-blue-100 text-sm">Personalized study plans adapted to your learning style</p>
                            </div>
                        </div>
                        <div class="p-8 bg-gradient-to-br from-blue-50 to-purple-50">
                            <p class="text-gray-700 leading-relaxed">
                                Our advanced AI analyzes your performance and creates customized study plans that adapt to your strengths and weaknesses, ensuring optimal learning efficiency.
                            </p>
                        </div>
                    </div>
                    
                    <div class="group bg-white rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2">
                        <div class="bg-gradient-to-r from-green-600 to-teal-600 p-8 text-center relative overflow-hidden">
                            <div class="absolute inset-0 opacity-30" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; viewBox=&quot;0 0 40 40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;0.1&quot;%3E%3Cpath d=&quot;M20 20c0 11.046-8.954 20-20 20v20h40V20H20z&quot;/%3E%3C/g%3E%3C/svg%3E')"></div>
                            <div class="relative z-10">
                                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                                    <span class="text-3xl">📚</span>
                                </div>
                                <h3 class="text-xl font-bold text-white mb-2">Updated Content</h3>
                                <p class="text-green-100 text-sm">Latest exam patterns and practice materials</p>
                            </div>
                        </div>
                        <div class="p-8 bg-gradient-to-br from-green-50 to-teal-50">
                            <p class="text-gray-700 leading-relaxed">
                                Stay ahead with our continuously updated content library featuring the latest exam patterns, questions, and study materials from official sources.
                            </p>
                        </div>
                    </div>
                    
                    <div class="group bg-white rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2">
                        <div class="bg-gradient-to-r from-orange-600 to-red-600 p-8 text-center relative overflow-hidden">
                            <div class="absolute inset-0 opacity-30" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;40&quot; height=&quot;40&quot; viewBox=&quot;0 0 40 40&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;0.1&quot;%3E%3Cpath d=&quot;M20 20c0 11.046-8.954 20-20 20v20h40V20H20z&quot;/%3E%3C/g%3E%3C/svg%3E')"></div>
                            <div class="relative z-10">
                                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                                    <span class="text-3xl">🎯</span>
                                </div>
                                <h3 class="text-xl font-bold text-white mb-2">Expert Guidance</h3>
                                <p class="text-orange-100 text-sm">Professional tutors and mentorship</p>
                            </div>
                        </div>
                        <div class="p-8 bg-gradient-to-br from-orange-50 to-red-50">
                            <p class="text-gray-700 leading-relaxed">
                                Get personalized guidance from experienced tutors and mentors who have helped thousands of students achieve their target scores.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 bg-gradient-to-r from-indigo-900 via-purple-900 to-pink-900 relative overflow-hidden">
            <div class="absolute inset-0 opacity-20" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%23ffffff&quot; fill-opacity=&quot;0.1&quot;%3E%3Cpath d=&quot;M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')"></div>
            
            <div class="container mx-auto px-4 text-center relative z-10">
                <div class="max-w-4xl mx-auto">
                    <h2 class="text-4xl lg:text-6xl font-bold text-white mb-6">
                        Ready to Ace Your <span class="text-yellow-400">Exam?</span>
                    </h2>
                    <p class="text-xl text-gray-300 mb-10 leading-relaxed">
                        Join thousands of students who have achieved their target scores with StudyChat's AI-powered learning platform
                    </p>
                    
                    <div class="flex flex-col sm:flex-row gap-6 justify-center items-center mb-8">
                        <button class="bg-gradient-to-r from-yellow-400 to-orange-500 text-gray-900 px-10 py-4 rounded-2xl font-bold text-lg hover:from-yellow-500 hover:to-orange-600 transition-all duration-300 shadow-2xl hover:shadow-3xl transform hover:-translate-y-1 hover:scale-105">
                            Start Your Journey Today!
                        </button>
                        <button class="border-2 border-white text-white px-10 py-4 rounded-2xl font-bold text-lg hover:bg-white hover:text-gray-900 transition-all duration-300">
                            View Pricing Plans
                        </button>
                    </div>
                    
                    <p class="text-sm text-gray-400">
                        (Free trial available - No credit card required)
                    </p>
                    
                    <div class="flex justify-center items-center space-x-8 mt-12 opacity-60">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-white">50K+</div>
                            <div class="text-gray-300 text-sm">Students</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-white">95%</div>
                            <div class="text-gray-300 text-sm">Success Rate</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-white">4.9★</div>
                            <div class="text-gray-300 text-sm">Rating</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="py-20 bg-gradient-to-br from-gray-50 to-blue-50">
            <div class="container mx-auto px-4">
                <div class="text-center mb-16">
                    <h2 class="text-4xl lg:text-5xl font-bold text-gray-900 mb-6">
                        Frequently Asked <span class="text-blue-600">Questions</span>
                    </h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Get answers to common questions about our platform and services
                    </p>
                </div>
                
                <div class="max-w-4xl mx-auto space-y-4">
                    <div v-for="(faq, index) in faqData" :key="index" class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <button 
                            @click="toggleFaq(index)"
                            class="w-full p-6 text-left hover:bg-blue-50 transition-all duration-300 flex justify-between items-center group"
                        >
                            <span class="font-semibold text-gray-800 text-lg group-hover:text-blue-600">{{ faq.question }}</span>
                            <svg 
                                class="w-6 h-6 text-gray-600 transform transition-transform duration-300"
                                :class="{ 'rotate-180': openFaq === index }"
                                fill="currentColor" 
                                viewBox="0 0 20 20"
                            >
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div 
                            v-if="openFaq === index"
                            class="px-6 pb-6 text-gray-600 leading-relaxed border-t border-gray-100 pt-4 animate-fadeIn"
                        >
                            {{ faq.answer }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-gradient-to-r from-gray-900 via-blue-900 to-indigo-900 text-white py-16">
            <div class="container mx-auto px-4">
                <div class="grid md:grid-cols-4 gap-8 mb-12">
                    <div class="col-span-2 md:col-span-1">
                        <div class="flex items-center space-x-4 mb-6">
                            <div class="w-14 h-14 bg-gradient-to-br from-white to-white rounded-full flex items-center justify-center shadow-lg transform hover:scale-105 transition-transform duration-300 p-2">
                                <img src="/images/logo.png" alt="OposChat" class="w-full h-full rounded-full" />
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold">StudyChat</h3>
                                <p class="text-blue-200 text-sm">AI-Powered Learning</p>
                            </div>
                        </div>
                        <p class="text-gray-300 leading-relaxed">
                            Empowering students worldwide with AI-driven exam preparation and personalized learning experiences.
                        </p>
                    </div>
                    
                    <div>
                        <h4 class="font-bold mb-6 text-lg">About Us</h4>
                        <ul class="space-y-3 text-gray-300">
                            <li><a href="#" class="hover:text-yellow-400 transition-colors duration-300 flex items-center"><span class="mr-2">→</span>Our Story</a></li>
                            <li><a href="#" class="hover:text-yellow-400 transition-colors duration-300 flex items-center"><span class="mr-2">→</span>Mission & Vision</a></li>
                            <li><a href="#" class="hover:text-yellow-400 transition-colors duration-300 flex items-center"><span class="mr-2">→</span>Team</a></li>
                            <li><a href="#" class="hover:text-yellow-400 transition-colors duration-300 flex items-center"><span class="mr-2">→</span>Careers</a></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 class="font-bold mb-6 text-lg">Legal</h4>
                        <ul class="space-y-3 text-gray-300">
                            <li><a href="#" class="hover:text-yellow-400 transition-colors duration-300 flex items-center"><span class="mr-2">→</span>Privacy Policy</a></li>
                            <li><a href="#" class="hover:text-yellow-400 transition-colors duration-300 flex items-center"><span class="mr-2">→</span>Terms of Service</a></li>
                            <li><a href="#" class="hover:text-yellow-400 transition-colors duration-300 flex items-center"><span class="mr-2">→</span>Cookie Policy</a></li>
                            <li><a href="#" class="hover:text-yellow-400 transition-colors duration-300 flex items-center"><span class="mr-2">→</span>GDPR</a></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 class="font-bold mb-6 text-lg">Connect With Us</h4>
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
                        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                            <p class="text-sm text-gray-300 mb-2">Subscribe to our newsletter</p>
                            <div class="flex">
                                <input type="email" placeholder="Enter your email" class="flex-1 bg-white/20 border border-white/30 rounded-l-lg px-3 py-2 text-white placeholder-gray-300 focus:outline-none focus:border-yellow-400">
                                <button class="bg-gradient-to-r from-yellow-400 to-orange-500 text-gray-900 px-4 py-2 rounded-r-lg font-semibold hover:from-yellow-500 hover:to-orange-600 transition-all duration-300">
                                    →
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-gray-700 pt-8 flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-400 mb-4 md:mb-0">
                        &copy; 2024 StudyChat. All rights reserved. Made with ❤️ for students worldwide.
                    </p>
                    
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.font-inter {
    font-family: 'Inter', sans-serif;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fadeIn {
    animation: fadeIn 0.3s ease-out;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f5f9;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(to bottom, #3b82f6, #8b5cf6);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(to bottom, #2563eb, #7c3aed);
}

/* Smooth transitions */
* {
    transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
}

/* Enhanced focus states */
button:focus,
a:focus,
input:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

/* Gradient text animation */
@keyframes gradient {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.animate-gradient {
    background-size: 200% 200%;
    animation: gradient 3s ease infinite;
}
</style>