<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppHeaderLayout from '@/layouts/AppHeaderLayout.vue'; // Layout with header only, no sidebar
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { CheckCircle2, XCircle, Clock, Target, RotateCcw, Home } from 'lucide-vue-next';

interface QuizResults {
  id: number;
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

      <!-- Action Buttons -->
      <div class="flex gap-4 mb-8 justify-center">
        <Button @click="retakeQuiz" variant="outline">
          <RotateCcw class="h-4 w-4 mr-2" />
          Retake Quiz
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
            <div class="bg-blue-50 p-4 rounded-lg">
              <h4 class="font-semibold mb-2 text-blue-900">Explanation</h4>
              <p class="text-sm text-blue-800">{{ answer.question.explanation }}</p>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppHeaderLayout>
</template>
