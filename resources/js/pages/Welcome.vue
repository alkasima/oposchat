<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import SiteHeader from '@/components/SiteHeader.vue';
import SiteFooter from '@/components/SiteFooter.vue';

const isMenuOpen = ref(false);
const openFaq = ref(null);

const toggleFaq = (index) => {
    openFaq.value = openFaq.value === index ? null : index;
};

// Courses loaded from admin via API
const courses = ref<any[]>([]);
const loadingCourses = ref<boolean>(false);
const coursesError = ref<string | null>(null);

onMounted(async () => {
    try {
        loadingCourses.value = true;
        coursesError.value = null;
        const res = await fetch('/public/courses', { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error('Failed to load courses');
        const data = await res.json();
        // Expecting: id, name, slug, description, namespace, icon, color
        courses.value = Array.isArray(data) ? data : [];
    } catch (e: any) {
        coursesError.value = e?.message || 'Could not load courses';
        courses.value = [];
    } finally {
        loadingCourses.value = false;
    }
});

// Handle exam selection for logged-in users
const selectCourse = async (course: any) => {
    // This will be called only for authenticated users due to the template condition

    try {
        const courseId = course.id;

        // Create or get exam-specific chat
        const response = await fetch('/api/chats', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                exam_type: course.slug || course.namespace || course.name,
                title: `${course.name} Chat`,
                course_id: courseId
            })
        });

        if (response.ok) {
            const data = await response.json();
            // Redirect to the chat with the exam context
            router.visit(route('chat', { chat: data.chat.id }));
        } else {
            console.error('Failed to create exam chat');
            // Fallback to general dashboard
            router.visit(route('dashboard'));
        }
    } catch (error) {
        console.error('Error selecting course:', error);
        // Fallback to general dashboard
        router.visit(route('dashboard'));
    }
};

const openMoreInfo = ref<string | null>(null);

const toggleMoreInfo = (id: string) => {
    openMoreInfo.value = openMoreInfo.value === id ? null : id;
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
    
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 dark:from-slate-900 dark:to-slate-950 font-inter">
        <SiteHeader />

        <!-- Hero Section -->
        <section id="home" class="relative py-20 overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50"></div>
            <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-900 to-black opacity-0 dark:opacity-100"></div>
            <div class="absolute inset-0 opacity-40" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;60&quot; height=&quot;60&quot; viewBox=&quot;0 0 60 60&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cg fill=&quot;none&quot; fill-rule=&quot;evenodd&quot;%3E%3Cg fill=&quot;%239C92AC&quot; fill-opacity=&quot;0.05&quot;%3E%3Ccircle cx=&quot;30&quot; cy=&quot;30&quot; r=&quot;4&quot;/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')"></div>
            
            <div class="container mx-auto px-4 relative z-10">
                <div class="flex flex-col lg:flex-row items-center justify-between">
                    <div class="lg:w-3/5 mb-12 lg:mb-0">
                        <div class="inline-flex items-center bg-white/80 dark:bg-white/10 backdrop-blur-sm rounded-full px-4 py-2 mb-6 shadow-lg">
                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">AI-Powered Learning Platform</span>
                        </div>
                        
                        <h1 class="text-5xl lg:text-7xl font-bold text-gray-900 dark:text-white mb-6 leading-tight">
                            <span v-if="$page.props.auth.user">
                                Welcome Back,
                                <span class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent">
                                    {{ $page.props.auth.user.name }}
                                </span>
                                <br />Choose Your Exam
                            </span>
                            <span v-else>
                                Master Your 
                                <span class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 bg-clip-text text-transparent">
                                    Exams
                                </span>
                                <br />with AI
                            </span>
                        </h1>
                        
                        <p class="text-xl text-gray-600 dark:text-gray-300 leading-relaxed mb-8 max-w-2xl">
                            <span v-if="$page.props.auth.user">
                                Select the exam you want to prepare for below and start your personalized AI-powered learning journey. 
                                Each exam has a dedicated chat where our AI tutor will help you master the material.
                            </span>
                            <span v-else>
                                From <span class="font-semibold text-blue-600">StudyChat</span>, we provide you with a powerful combination of 
                                <span class="font-semibold text-purple-600">Artificial Intelligence</span> along with 
                                <span class="font-semibold text-cyan-600">updated curriculum</span> and 
                                <span class="font-semibold text-green-600">practice exams</span> 
                                from various <span class="font-semibold text-orange-600">standardized tests</span> to 
                                <span class="font-semibold text-red-600">help you</span> 
                                <span class="font-semibold text-blue-600">succeed</span> and achieve your goals.
                            </span>
                        </p>

                        <div class="flex flex-col sm:flex-row gap-4">
                            <a 
                                v-if="$page.props.auth.user"
                                href="#courses"
                                class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-4 rounded-2xl font-semibold text-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-300 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 inline-block text-center"
                            >
                                Choose Your Exam
                            </a>
                            <Link 
                                v-else
                                :href="route('exams.wiki')"
                                class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-4 rounded-2xl font-semibold text-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-300 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 inline-block text-center"
                            >
                                Start Learning Free
                            </Link>
                            <Link 
                                v-if="$page.props.auth.user"
                                :href="route('dashboard')"
                                class="border-2 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 px-8 py-4 rounded-2xl font-semibold text-lg hover:border-blue-500 dark:hover:border-blue-400 hover:text-blue-600 dark:hover:text-blue-300 transition-all duration-300 bg-white/80 dark:bg-white/10 backdrop-blur-sm inline-block text-center"
                            >
                                Go to Dashboard
                            </Link>
                            <button 
                                v-else
                                class="border-2 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 px-8 py-4 rounded-2xl font-semibold text-lg hover:border-blue-500 dark:hover:border-blue-400 hover:text-blue-600 dark:hover:text-blue-300 transition-all duration-300 bg-white/80 dark:bg-white/10 backdrop-blur-sm"
                            >
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
                        <span v-if="$page.props.auth.user">Choose Your <span class="text-yellow-400">Exam</span></span>
                        <span v-else>Available <span class="text-yellow-400">Courses</span></span>
                    </h2>
                    <p class="text-xl text-gray-300 max-w-3xl mx-auto">
                        <span v-if="$page.props.auth.user">Select the exam you want to prepare for and start chatting with our AI tutor</span>
                        <span v-else>Choose from our comprehensive selection of exam preparation courses</span>
                    </p>
                    <div class="mt-4">
                        <Link :href="route('exams.wiki')" class="text-yellow-300 hover:text-yellow-200 font-semibold underline">
                            General information about competitive examinations →
                        </Link>
                    </div>
                </div>
                
                <div class="grid md:grid-cols-2 gap-8 max-w-6xl mx-auto mb-12">
                    <div 
                        v-if="!loadingCourses && !coursesError"
                        v-for="course in courses" 
                        :key="course.id"
                        @click="$page.props.auth.user ? selectCourse(course) : null"
                        class="group bg-white/10 backdrop-blur-sm border border-white/20 rounded-2xl p-8 hover:bg-white/20 transition-all duration-300 transform hover:-translate-y-2 hover:shadow-2xl"
                        :class="{ 'cursor-pointer': $page.props.auth.user, 'cursor-default': !$page.props.auth.user }"
                    >
                        <div class="flex items-center mb-4">
                            <div :class="`w-12 h-12 bg-gradient-to-br ${course.color || 'from-blue-400 to-blue-600'} rounded-xl flex items-center justify-center mr-4`">
                                <span class="text-white text-xl">{{ course.icon || '📘' }}</span>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white group-hover:text-yellow-300 transition-colors">
                                    {{ course.name }}
                                </h3>
                                <p class="text-gray-300 text-sm line-clamp-2">{{ course.description || 'Course description' }}</p>
                            </div>
                        </div>
                        <p class="text-gray-300 mb-4" v-if="course.full_description">{{ course.full_description }}</p>
                        <div class="flex items-center justify-between">
                            <span class="inline-block bg-blue-600 text-white px-3 py-1 rounded-full text-sm font-medium">
                                More info
                            </span>
                            <span class="text-yellow-400 font-semibold">&nbsp;</span>
                        </div>
                        <div class="mt-4">
                            <button type="button" @click.stop="toggleMoreInfo(course.id)" class="inline-flex items-center px-3 py-1 text-sm rounded-full bg-blue-500 text-white hover:bg-blue-600">
                                + MORE INFO
                            </button>
                        </div>
                        <div v-if="openMoreInfo === course.id" class="mt-4 p-4 rounded-xl bg-white/5 border border-white/10 text-gray-2 00">
                            <p class="mb-3">{{ course.full_description || course.description || 'Learn more about this exam in our Wiki.' }}</p>
                            <Link @click.stop :href="`${route('exams.wiki')}#${course.slug || course.namespace || ''}`" class="text-yellow-300 hover:text-yellow-200 font-semibold underline">Read detailed info in the Wiki →</Link>
                        </div>
                <div v-if="loadingCourses" class="text-center text-gray-300 mb-8">Loading courses...</div>
                <div v-if="coursesError" class="text-center text-red-300 mb-8">{{ coursesError }}</div>
                        
                        <!-- Action indicator for logged-in users -->
                        <div v-if="$page.props.auth.user" class="mt-4 pt-4 border-t border-white/20">
                            <div class="flex items-center justify-center text-yellow-300 font-medium text-sm">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                                Click to Start Learning
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <Link 
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="bg-gradient-to-r from-yellow-400 to-orange-500 text-gray-900 px-10 py-4 rounded-2xl font-bold text-lg hover:from-yellow-500 hover:to-orange-600 transition-all duration-300 shadow-2xl hover:shadow-3xl transform hover:-translate-y-1 inline-block"
                    >
                        GO TO DASHBOARD
                    </Link>
                    <Link 
                        v-else
                        :href="route('register')"
                        class="bg-gradient-to-r from-yellow-400 to-orange-500 text-gray-900 px-10 py-4 rounded-2xl font-bold text-lg hover:from-yellow-500 hover:to-orange-600 transition-all duration-300 shadow-2xl hover:shadow-3xl transform hover:-translate-y-1 inline-block"
                    >
                        GET STARTED FREE
                    </Link>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="py-20 bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-slate-900 dark:to-slate-800">
            <div class="container mx-auto px-4">
                <div class="text-center mb-16">
                    <h2 class="text-4xl lg:text-5xl font-bold text-gray-900 dark:text-white mb-6">
                        What We <span class="text-blue-600">Offer</span>
                    </h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                        Discover our comprehensive approach to exam preparation with cutting-edge technology
                    </p>
                </div>
                
                <div class="grid md:grid-cols-3 gap-8 max-w-7xl mx-auto">
                    <div class="group bg-white dark:bg-slate-800 rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2">
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
                        <div class="p-8 bg-gradient-to-br from-blue-50 to-purple-50 dark:from-slate-800 dark:to-slate-800">
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                Our advanced AI analyzes your performance and creates customized study plans that adapt to your strengths and weaknesses, ensuring optimal learning efficiency.
                            </p>
                        </div>
                    </div>
                    
                    <div class="group bg-white dark:bg-slate-800 rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2">
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
                        <div class="p-8 bg-gradient-to-br from-green-50 to-teal-50 dark:from-slate-800 dark:to-slate-800">
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                Stay ahead with our continuously updated content library featuring the latest exam patterns, questions, and study materials from official sources.
                            </p>
                        </div>
                    </div>
                    
                    <div class="group bg-white dark:bg-slate-800 rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2">
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
                        <div class="p-8 bg-gradient-to-br from-orange-50 to-red-50 dark:from-slate-800 dark:to-slate-800">
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                Get personalized guidance from experienced tutors and mentors who have helped thousands of students achieve their target scores.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="py-20 bg-gradient-to-br from-gray-50 to-blue-50 dark:from-slate-900 dark:to-slate-800">
            <div class="container mx-auto px-4">
                <div class="text-center mb-16">
                    <h2 class="text-4xl lg:text-5xl font-bold text-gray-900 dark:text-white mb-6">
                        Choose Your <span class="text-blue-600">Plan</span>
                    </h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                        Start free and upgrade as you grow. All plans include our core AI-powered learning features.
                    </p>
                </div>
                
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8 max-w-7xl mx-auto">
                    <!-- Free Plan -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-gray-200 hover:border-blue-300 transition-all duration-300 transform hover:-translate-y-2">
                        <div class="p-8">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Free</h3>
                                <p class="text-gray-600 mb-4">Get started with basic features</p>
                                <div class="text-4xl font-bold text-gray-900 mb-2">
                                    €0
                                    <span class="text-lg font-normal text-gray-600">/month</span>
                                </div>
                            </div>
                            
                            <ul class="space-y-4 mb-8">
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">3 messages per day</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Community support</span>
                                </li>
                            </ul>
                            
                            <Link 
                                v-if="!$page.props.auth.user"
                                :href="route('register')"
                                class="w-full bg-gray-200 dark:bg-slate-700 text-gray-800 dark:text-gray-100 py-3 px-6 rounded-xl font-semibold text-center hover:bg-gray-300 dark:hover:bg-slate-600 transition-colors duration-300 block"
                            >
                                Get Started Free
                            </Link>
                            <Link 
                                v-else
                                :href="route('dashboard')"
                                class="w-full bg-gray-200 dark:bg-slate-700 text-gray-800 dark:text-gray-100 py-3 px-6 rounded-xl font-semibold text-center hover:bg-gray-300 dark:hover:bg-slate-600 transition-colors duration-300 block"
                            >
                                Go to Dashboard
                            </Link>
                        </div>
                    </div>
                    
                    <!-- Pro Plan -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-blue-500 relative transform hover:-translate-y-2 transition-all duration-300">
                        <div class="absolute top-0 left-0 right-0 bg-gradient-to-r from-blue-500 to-purple-600 text-white text-center py-2 text-sm font-semibold">
                            POPULAR
                        </div>
                        <div class="p-8 pt-12">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Premium</h3>
                                <p class="text-gray-600 mb-4">Perfect for individuals and small teams</p>
                                <div class="text-4xl font-bold text-blue-600 mb-2">
                                    €9.99
                                    <span class="text-lg font-normal text-gray-600">/month</span>
                                </div>
                            </div>
                            
                            <ul class="space-y-4 mb-8">
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">200 messages per month</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Upload files</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Access to exams</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Priority technical support</span>
                                </li>
                            </ul>
                            
                            <Link 
                                v-if="!$page.props.auth.user"
                                :href="route('register')"
                                class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-blue-600 hover:to-purple-700 transition-all duration-300 block"
                            >
                                Start Free Trial
                            </Link>
                            <Link 
                                v-else
                                :href="route('dashboard')"
                                class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-blue-600 hover:to-purple-700 transition-all duration-300 block"
                            >
                                Upgrade to Premium
                            </Link>
                        </div>
                    </div>
                    
                    <!-- Plus Plan -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-gray-200 hover:border-purple-300 transition-all duration-300 transform hover:-translate-y-2">
                        <div class="p-8">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Plus</h3>
                                <p class="text-gray-600 mb-4">For growing teams and businesses</p>
                                <div class="text-4xl font-bold text-purple-600 mb-2">
                                    €14.99
                                    <span class="text-lg font-normal text-gray-600">/month</span>
                                </div>
                            </div>
                            
                            <ul class="space-y-4 mb-8">
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Unlimited messages</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Upload files</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Access to exams</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Priority technical support</span>
                                </li>
                            </ul>
                            
                            <Link 
                                v-if="!$page.props.auth.user"
                                :href="route('register')"
                                class="w-full bg-gradient-to-r from-purple-500 to-pink-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-purple-600 hover:to-pink-700 transition-all duration-300 block"
                            >
                                Start Free Trial
                            </Link>
                            <Link 
                                v-else
                                :href="route('dashboard')"
                                class="w-full bg-gradient-to-r from-purple-500 to-pink-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-purple-600 hover:to-pink-700 transition-all duration-300 block"
                            >
                                Upgrade to Plus
                            </Link>
                        </div>
                    </div>
                    
                    <!-- Academy Plan -->
                    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-gray-200 hover:border-orange-300 transition-all duration-300 transform hover:-translate-y-2">
                        <div class="p-8">
                            <div class="text-center mb-8">
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Academy</h3>
                                <p class="text-gray-600 mb-4">For institutions and large organizations</p>
                                <div class="text-2xl font-bold text-orange-600 mb-2">
                                    Custom Pricing
                                </div>
                                <p class="text-sm text-gray-500">Tailored to your institution's needs</p>
                            </div>
                            
                            <ul class="space-y-4 mb-8">
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Advanced analytics</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Unlimited messages</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Upload files</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Access to exams</span>
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-gray-700">Priority technical support</span>
                                </li>
                            </ul>
                            
                            <Link 
                                href="/contact"
                                class="w-full bg-gradient-to-r from-orange-500 to-red-600 text-white py-3 px-6 rounded-xl font-semibold text-center hover:from-orange-600 hover:to-red-700 transition-all duration-300 block"
                            >
                                Contact Sales
                            </Link>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-12">
                    <p class="text-gray-600 dark:text-gray-300 mb-4">All plans include a 14-day free trial. No credit card required.</p>
                    <Link 
                        :href="route('pricing')"
                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-semibold underline"
                    >
                        View detailed pricing comparison →
                    </Link>
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
                        <a href="#pricing" class="border-2 border-white text-white px-10 py-4 rounded-2xl font-bold text-lg hover:bg-white hover:text-gray-900 transition-all duration-300 inline-block">
                            View Pricing Plans
                        </a>
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
        <section class="py-20 bg-gradient-to-br from-gray-50 to-blue-50 dark:from-slate-900 dark:to-slate-800">
            <div class="container mx-auto px-4">
                <div class="text-center mb-16">
                    <h2 class="text-4xl lg:text-5xl font-bold text-gray-900 dark:text-white mb-6">
                        Frequently Asked <span class="text-blue-600">Questions</span>
                    </h2>
                    <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
                        Get answers to common questions about our platform and services
                    </p>
                </div>
                
                <div class="max-w-4xl mx-auto space-y-4">
                    <div v-for="(faq, index) in faqData" :key="index" class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg overflow-hidden">
                        <button 
                            @click="toggleFaq(index)"
                            class="w-full p-6 text-left hover:bg-blue-50 dark:hover:bg-slate-700 transition-all duration-300 flex justify-between items-center group"
                        >
                            <span class="font-semibold text-gray-800 dark:text-gray-100 text-lg group-hover:text-blue-600 dark:group-hover:text-blue-400">{{ faq.question }}</span>
                            <svg 
                                class="w-6 h-6 text-gray-600 dark:text-gray-300 transform transition-transform duration-300"
                                :class="{ 'rotate-180': openFaq === index }"
                                fill="currentColor" 
                                viewBox="0 0 20 20"
                            >
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div 
                            v-if="openFaq === index"
                            class="px-6 pb-6 text-gray-600 dark:text-gray-300 leading-relaxed border-t border-gray-100 dark:border-slate-700 pt-4 animate-fadeIn"
                        >
                            {{ faq.answer }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <SiteFooter />
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