<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppHeaderLayout from '@/layouts/AppHeaderLayout.vue'; // Layout with header only, no sidebar
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Loader2, BookOpen, Clock, Target, TrendingUp, Sparkles } from 'lucide-vue-next';
import AIQuizGeneratorModal from '@/components/quiz/AIQuizGeneratorModal.vue';
import PersonalizationDashboard from '@/components/quiz/PersonalizationDashboard.vue';

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
const showAIQuizModal = ref(false);
const availableTopics = ref<string[]>([]);
const activeTab = ref('quizzes');

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

// Fetch available topics
const fetchTopics = async () => {
  if (!selectedCourse.value) return;
  
  try {
    const response = await axios.get('/api/quizzes/topics', {
      params: { course_id: selectedCourse.value }
    });
    availableTopics.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch topics:', error);
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

// Handle AI quiz generation
const handleAIQuizGenerated = (questions: any[]) => {
  console.log('AI quiz generated:', questions);
  // You could create a temporary quiz attempt with these questions
  // or navigate to a special AI quiz page
  alert(`${questions.length} questions generated successfully! Feature integration in progress.`);
};

// Handle starting quiz from recommendations
const handleStartRecommendedQuiz = (config: any) => {
  if (config.type === 'ai_generated') {
    showAIQuizModal.value = true;
  } else {
    // Start a regular quiz with the recommended configuration
    alert('Starting quiz with recommended configuration...');
  }
};

// Watch for course selection
const onCourseChange = (event: Event) => {
  const target = event.target as HTMLSelectElement;
  selectedCourse.value = parseInt(target.value);
  fetchQuizzes();
  fetchStatistics();
  fetchTopics();
};

onMounted(() => {
  if (props.courses.length > 0) {
    selectedCourse.value = props.courses[0].id;
    fetchQuizzes();
    fetchStatistics();
    fetchTopics();
  }
});
</script>

<template>
  <AppHeaderLayout>
    <div class="container mx-auto px-4 py-8 pt-20 min-h-screen bg-gray-50 dark:bg-gray-900">
      <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2">Quizzes</h1>
        <p class="text-muted-foreground">Test your knowledge and track your progress</p>
      </div>

      <!-- Course Selection -->
      <div class="mb-6">
        <label class="block text-sm font-medium mb-2 dark:text-gray-200">Select Course</label>
        <div class="flex gap-4 items-center">
          <select
            v-model="selectedCourse"
            @change="onCourseChange"
            class="flex-1 md:flex-none md:w-64 px-3 py-2 border rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary focus:border-transparent"
          >
            <option v-for="course in courses" :key="course.id" :value="course.id">
              {{ course.name }}
            </option>
          </select>
          
          <Button @click="showAIQuizModal = true" variant="default" v-if="selectedCourse">
            <Sparkles class="h-4 w-4 mr-2" />
            Generate AI Quiz
          </Button>
        </div>
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

      <!-- Tabs for Quizzes and Recommendations -->
      <Tabs v-model="activeTab" v-if="selectedCourse">
        <TabsList class="grid w-full grid-cols-2 mb-6">
          <TabsTrigger value="quizzes">
            <BookOpen class="h-4 w-4 mr-2" />
            Available Quizzes
          </TabsTrigger>
          <TabsTrigger value="recommendations">
            <TrendingUp class="h-4 w-4 mr-2" />
            My Recommendations
          </TabsTrigger>
        </TabsList>

        <TabsContent value="quizzes">
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
            <p class="text-muted-foreground mb-4">There are no quizzes available for this course yet.</p>
            <Button @click="showAIQuizModal = true" variant="default">
              <Sparkles class="h-4 w-4 mr-2" />
              Generate AI Quiz
            </Button>
          </div>
        </TabsContent>

        <TabsContent value="recommendations">
          <PersonalizationDashboard 
            :course-id="selectedCourse"
            @start-quiz="handleStartRecommendedQuiz"
            @generate-ai-quiz="handleStartRecommendedQuiz"
          />
        </TabsContent>
      </Tabs>
    </div>

    <!-- AI Quiz Generator Modal -->
    <AIQuizGeneratorModal
      v-model:open="showAIQuizModal"
      :course-id="selectedCourse || 0"
      :available-topics="availableTopics"
      @quiz-generated="handleAIQuizGenerated"
    />
  </AppHeaderLayout>
</template>
