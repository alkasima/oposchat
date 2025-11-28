<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Loader2, BookOpen, Clock, Target, TrendingUp } from 'lucide-vue-next';

interface Course {
  id: number;
  name: string;
  slug: string;
}

interface Quiz {
  id: number;
  title: string;
  description: string;
  total_questions: number;
  duration_minutes: number | null;
  type: string;
}

interface Statistics {
  overall_accuracy: number;
  total_quizzes_completed: number;
  total_questions_answered: number;
  strong_topics: string[];
  weak_topics: string[];
}

const props = defineProps<{
  courses: Course[];
}>();

const selectedCourse = ref<number | null>(null);
const quizzes = ref<Quiz[]>([]);
const statistics = ref<Statistics | null>(null);
const loading = ref(false);
const statsLoading = ref(false);

// Fetch quizzes when course is selected
const fetchQuizzes = async () => {
  if (!selectedCourse.value) return;
  
  loading.value = true;
  try {
    const response = await axios.get('/api/quizzes', {
      params: { course_id: selectedCourse.value }
    });
    quizzes.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch quizzes:', error);
  } finally {
    loading.value = false;
  }
};

// Fetch statistics
const fetchStatistics = async () => {
  if (!selectedCourse.value) return;
  
  statsLoading.value = true;
  try {
    const response = await axios.get('/api/quiz-statistics', {
      params: { course_id: selectedCourse.value }
    });
    statistics.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch statistics:', error);
  } finally {
    statsLoading.value = false;
  }
};

// Start a quiz
const startQuiz = async (quiz: Quiz) => {
  try {
    const response = await axios.post(`/api/quizzes/${quiz.id}/start`, {
      settings: {
        question_count: quiz.total_questions
      }
    });
    
    if (response.data.success && response.data.data.redirect_url) {
      window.location.href = response.data.data.redirect_url;
    }
  } catch (error) {
    console.error('Failed to start quiz:', error);
    alert('Failed to start quiz. Please try again.');
  }
};

// Watch for course selection
const onCourseChange = (event: Event) => {
  const target = event.target as HTMLSelectElement;
  selectedCourse.value = parseInt(target.value);
  fetchQuizzes();
  fetchStatistics();
};

onMounted(() => {
  if (props.courses.length > 0) {
    selectedCourse.value = props.courses[0].id;
    fetchQuizzes();
    fetchStatistics();
  }
});
</script>

<template>
  <AppLayout>
    <div class="container mx-auto px-4 py-8 bg-gray-50 dark:bg-gray-900 min-h-screen">
      <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2">Quizzes</h1>
        <p class="text-muted-foreground">Test your knowledge and track your progress</p>
      </div>

      <!-- Course Selection -->
      <div class="mb-6">
        <label class="block text-sm font-medium mb-2 dark:text-gray-200">Select Course</label>
        <select
          v-model="selectedCourse"
          @change="onCourseChange"
          class="w-full md:w-64 px-3 py-2 border rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary focus:border-transparent"
        >
          <option v-for="course in courses" :key="course.id" :value="course.id">
            {{ course.name }}
          </option>
        </select>
      </div>

      <!-- Statistics Cards -->
      <div v-if="statistics && !statsLoading" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <Card>
          <CardHeader class="pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">Overall Accuracy</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="flex items-center">
              <Target class="h-4 w-4 mr-2 text-primary" />
              <span class="text-2xl font-bold">{{ statistics.overall_accuracy }}%</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">Quizzes Completed</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="flex items-center">
              <BookOpen class="h-4 w-4 mr-2 text-primary" />
              <span class="text-2xl font-bold">{{ statistics.total_quizzes_completed }}</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">Questions Answered</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="flex items-center">
              <TrendingUp class="h-4 w-4 mr-2 text-primary" />
              <span class="text-2xl font-bold">{{ statistics.total_questions_answered }}</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="pb-2">
            <CardTitle class="text-sm font-medium text-muted-foreground">Strong Topics</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="flex flex-wrap gap-1">
              <Badge v-for="topic in statistics.strong_topics.slice(0, 2)" :key="topic" variant="secondary" class="text-xs">
                {{ topic }}
              </Badge>
              <Badge v-if="statistics.strong_topics.length > 2" variant="outline" class="text-xs">
                +{{ statistics.strong_topics.length - 2 }}
              </Badge>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <Loader2 class="h-8 w-8 animate-spin text-primary" />
      </div>

      <!-- Quizzes List -->
      <div v-else-if="quizzes.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <Card v-for="quiz in quizzes" :key="quiz.id" class="hover:shadow-lg transition-shadow">
          <CardHeader>
            <CardTitle>{{ quiz.title }}</CardTitle>
            <CardDescription>{{ quiz.description }}</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-2 text-sm">
              <div class="flex items-center text-muted-foreground">
                <BookOpen class="h-4 w-4 mr-2" />
                <span>{{ quiz.total_questions }} questions</span>
              </div>
              <div v-if="quiz.duration_minutes" class="flex items-center text-muted-foreground">
                <Clock class="h-4 w-4 mr-2" />
                <span>{{ quiz.duration_minutes }} minutes</span>
              </div>
            </div>
          </CardContent>
          <CardFooter>
            <Button @click="startQuiz(quiz)" class="w-full">
              Start Quiz
            </Button>
          </CardFooter>
        </Card>
      </div>

      <!-- Empty State -->
      <div v-else-if="!loading && selectedCourse" class="text-center py-12">
        <BookOpen class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
        <h3 class="text-lg font-semibold mb-2">No Quizzes Available</h3>
        <p class="text-muted-foreground">There are no quizzes available for this course yet.</p>
      </div>
    </div>
  </AppLayout>
</template>
