<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppHeaderLayout from '@/layouts/AppHeaderLayout.vue'; // Layout with header only, no sidebar
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CheckCircle2, XCircle, Clock, Target, RotateCcw, Home, Sparkles, Loader2, TrendingUp, AlertCircle } from 'lucide-vue-next';

interface QuizResults {
  id: number;
  course_id: number;
  quiz: {
    title: string;
  };
  score_percentage: number;
  correct_answers: number;
  incorrect_answers: number;
  total_questions: number;
  time_spent_seconds: number;
  answers: ResultAnswer[];
}

interface ResultAnswer {
  id: number;
  question_order: number;
  selected_option: string | null;
  correct_option: string;
  is_correct: boolean;
  question: {
    id: number;
    question_text: string;
    explanation: string;
    topic: string;
    options: QuizOption[];
  };
}

interface QuizOption {
  option_letter: string;
  option_text: string;
  is_correct: boolean;
}

const props = defineProps<{
  results: QuizResults;
}>();

const recommendations = ref<any>(null);
const aiExplanations = ref<Record<number, string>>({});
const loadingExplanation = ref<Record<number, boolean>>({});

onMounted(async () => {
  // Fetch recommendations
  try {
    const response = await axios.get('/api/personalization/recommendations', {
      params: { course_id: props.results.course_id }
    });
    recommendations.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch recommendations:', error);
  }
});

const formattedTime = computed(() => {
  const minutes = Math.floor(props.results.time_spent_seconds / 60);
  const seconds = props.results.time_spent_seconds % 60;
  return `${minutes}m ${seconds}s`;
});

const scoreColor = computed(() => {
  const score = props.results.score_percentage;
  if (score >= 80) return 'text-green-600';
  if (score >= 60) return 'text-yellow-600';
  return 'text-red-600';
});

const retakeQuiz = () => {
  router.visit('/quizzes');
};

const goHome = () => {
  router.visit('/quizzes');
};

const generateSimilarQuiz = async () => {
  try {
    const response = await axios.get(`/api/personalization/similar-quiz/${props.results.id}`);
    if (response.data.success) {
      alert('Similar quiz configuration ready! Feature integration in progress.');
      // You can navigate to quiz page with this config or start quiz directly
    }
  } catch (error) {
    console.error('Failed to generate similar quiz:', error);
  }
};

const getAIExplanation = async (questionId: number, selectedOption: string) => {
  loadingExplanation.value[questionId] = true;
  
  try {
    const response = await axios.post('/api/ai-quiz/generate-explanation', {
      question_id: questionId,
      selected_option: selectedOption
    });
    
    if (response.data.success) {
      aiExplanations.value[questionId] = response.data.data.explanation;
    }
  } catch (error) {
    console.error('Failed to generate explanation:', error);
    aiExplanations.value[questionId] = 'Failed to generate explanation. Please try again.';
  } finally {
    loadingExplanation.value[questionId] = false;
  }
};
</script>

<template>
  <AppHeaderLayout>
    <div class="container mx-auto px-4 py-8 pt-20 max-w-4xl bg-gray-50 dark:bg-gray-900 min-h-screen">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold mb-2">Quiz Results</h1>
        <p class="text-muted-foreground">{{ results.quiz.title }}</p>
      </div>

      <!-- Score Card -->
      <Card class="mb-8">
        <CardContent class="p-8">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-6 text-center">
            <div>
              <div class="flex items-center justify-center mb-2">
                <Target class="h-5 w-5 mr-2 text-primary" />
                <span class="text-sm font-medium text-muted-foreground">Score</span>
              </div>
              <div :class="['text-4xl font-bold', scoreColor]">
                {{ results.score_percentage }}%
              </div>
            </div>

            <div>
              <div class="flex items-center justify-center mb-2">
                <CheckCircle2 class="h-5 w-5 mr-2 text-green-600" />
                <span class="text-sm font-medium text-muted-foreground">Correct</span>
              </div>
              <div class="text-4xl font-bold text-green-600">
                {{ results.correct_answers }}
              </div>
            </div>

            <div>
              <div class="flex items-center justify-center mb-2">
                <XCircle class="h-5 w-5 mr-2 text-red-600" />
                <span class="text-sm font-medium text-muted-foreground">Incorrect</span>
              </div>
              <div class="text-4xl font-bold text-red-600">
                {{ results.incorrect_answers }}
              </div>
            </div>

            <div>
              <div class="flex items-center justify-center mb-2">
                <Clock class="h-5 w-5 mr-2 text-primary" />
                <span class="text-sm font-medium text-muted-foreground">Time</span>
              </div>
              <div class="text-4xl font-bold">
                {{ formattedTime }}
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Performance Insights -->
      <Card v-if="recommendations?.has_sufficient_data" class="mb-8">
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <TrendingUp class="h-5 w-5" />
            Your Performance Insights
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Weak Topics -->
            <div v-if="recommendations.weak_topics?.length > 0">
              <h4 class="font-semibold mb-3 flex items-center">
                <AlertCircle class="h-4 w-4 mr-2 text-red-600" />
                Topics to Improve
              </h4>
              <div class="flex flex-wrap gap-2">
                <Badge v-for="topic in recommendations.weak_topics.slice(0, 5)" 
                       :key="topic" 
                       variant="destructive">
                  {{ topic }}
                </Badge>
              </div>
            </div>
            
            <!-- Strong Topics -->
            <div v-if="recommendations.strong_topics?.length > 0">
              <h4 class="font-semibold mb-3 flex items-center">
                <CheckCircle2 class="h-4 w-4 mr-2 text-green-600" />
                Your Strengths
              </h4>
              <div class="flex flex-wrap gap-2">
                <Badge v-for="topic in recommendations.strong_topics.slice(0, 5)" 
                       :key="topic" 
                       variant="secondary">
                  {{ topic }}
                </Badge>
              </div>
            </div>
          </div>
          
          <!-- Performance Trend -->
          <div v-if="recommendations.performance_trend" class="mt-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <p class="text-sm">
              <span class="font-medium">Trend: </span>
              <span :class="{
                'text-green-600': recommendations.performance_trend.trend === 'improving',
                'text-red-600': recommendations.performance_trend.trend === 'declining',
                'text-gray-600': recommendations.performance_trend.trend === 'stable'
              }">
                {{ recommendations.performance_trend.message }}
              </span>
            </p>
          </div>
        </CardContent>
      </Card>

      <!-- Action Buttons -->
      <div class="flex gap-4 mb-8 justify-center flex-wrap">
        <Button @click="retakeQuiz" variant="outline">
          <RotateCcw class="h-4 w-4 mr-2" />
          Retake Quiz
        </Button>
        <Button @click="generateSimilarQuiz" variant="secondary">
          <Sparkles class="h-4 w-4 mr-2" />
          Generate Similar Quiz
        </Button>
        <Button @click="goHome">
          <Home class="h-4 w-4 mr-2" />
          Back to Quizzes
        </Button>
      </div>

      <!-- Question Review -->
      <div class="space-y-6">
        <h2 class="text-2xl font-bold">Question Review</h2>
        
        <Card v-for="answer in results.answers" :key="answer.id">
          <CardHeader>
            <div class="flex items-start justify-between">
              <CardTitle class="text-lg">
                {{ answer.question_order }}. {{ answer.question.question_text }}
              </CardTitle>
              <Badge :variant="answer.is_correct ? 'default' : 'destructive'">
                {{ answer.is_correct ? 'Correct' : 'Incorrect' }}
              </Badge>
            </div>
          </CardHeader>
          <CardContent>
            <!-- Options -->
            <div class="space-y-2 mb-4">
              <div
                v-for="option in answer.question.options"
                :key="option.option_letter"
                class="p-3 rounded-lg border"
                :class="{
                  'bg-green-50 border-green-500': option.is_correct,
                  'bg-red-50 border-red-500': answer.selected_option === option.option_letter && !option.is_correct,
                  'border-gray-200': answer.selected_option !== option.option_letter && !option.is_correct
                }"
              >
                <div class="flex items-center">
                  <span class="font-semibold mr-2">{{ option.option_letter }}.</span>
                  <span>{{ option.option_text }}</span>
                  <CheckCircle2
                    v-if="option.is_correct"
                    class="h-4 w-4 ml-auto text-green-600"
                  />
                  <XCircle
                    v-if="answer.selected_option === option.option_letter && !option.is_correct"
                    class="h-4 w-4 ml-auto text-red-600"
                  />
                </div>
              </div>
            </div>

            <!-- Explanation -->
            <Separator class="my-4" />
            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
              <h4 class="font-semibold mb-2 text-blue-900 dark:text-blue-100">Explanation</h4>
              <p class="text-sm text-blue-800 dark:text-blue-200">{{ answer.question.explanation }}</p>
            </div>
            
            <!-- AI Explanation (for incorrect answers) -->
            <div v-if="!answer.is_correct" class="mt-4">
              <Button 
                v-if="!aiExplanations[answer.question.id]"
                @click="getAIExplanation(answer.question.id, answer.selected_option!)"
                variant="outline"
                size="sm"
                :disabled="loadingExplanation[answer.question.id]"
              >
                <Loader2 v-if="loadingExplanation[answer.question.id]" class="h-4 w-4 mr-2 animate-spin" />
                <Sparkles v-else class="h-4 w-4 mr-2" />
                {{ loadingExplanation[answer.question.id] ? 'Generating...' : 'Get AI Tutor Explanation' }}
              </Button>
              
              <!-- AI Explanation Display -->
              <Alert v-if="aiExplanations[answer.question.id]" class="mt-3 border-purple-200 bg-purple-50 dark:bg-purple-900/20">
                <Sparkles class="h-4 w-4" />
                <AlertDescription class="text-sm text-purple-900 dark:text-purple-100">
                  <strong>AI Tutor:</strong> {{ aiExplanations[answer.question.id] }}
                </AlertDescription>
              </Alert>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppHeaderLayout>
</template>
