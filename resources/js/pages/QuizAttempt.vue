<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppHeaderLayout from '@/layouts/AppHeaderLayout.vue'; // Layout with header only, no sidebar
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Clock, ChevronLeft, ChevronRight, Flag } from 'lucide-vue-next';

interface QuizAttempt {
  id: number;
  quiz: {
    id: number;
    title: string;
    duration_minutes: number | null;
  };
  answers: QuizAnswer[];
  started_at: string;
}

interface QuizAnswer {
  id: number;
  question_order: number;
  selected_option: string | null;
  is_bookmarked: boolean;
  question: {
    id: number;
    question_text: string;
    options: QuizOption[];
  };
}

interface QuizOption {
  id: number;
  option_letter: string;
  option_text: string;
}

const props = defineProps<{
  attempt: QuizAttempt;
}>();

const currentQuestionIndex = ref(0);
const timeElapsed = ref(0);
const timerInterval = ref<number | null>(null);
const submitting = ref(false);

const currentAnswer = computed(() => props.attempt.answers[currentQuestionIndex.value]);
const currentQuestion = computed(() => currentAnswer.value.question);
const totalQuestions = computed(() => props.attempt.answers.length);
const progress = computed(() => ((currentQuestionIndex.value + 1) / totalQuestions.value) * 100);

const answeredCount = computed(() => 
  props.attempt.answers.filter(a => a.selected_option).length
);

// Format time as MM:SS
const formattedTime = computed(() => {
  const minutes = Math.floor(timeElapsed.value / 60);
  const seconds = timeElapsed.value % 60;
  return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
});

// Start timer
onMounted(() => {
  timerInterval.value = window.setInterval(() => {
    timeElapsed.value++;
  }, 1000);
});

// Clear timer on unmount
onBeforeUnmount(() => {
  if (timerInterval.value) {
    clearInterval(timerInterval.value);
  }
});

// Select an option
const selectOption = async (optionLetter: string) => {
  try {
    await axios.post(`/api/quiz-attempts/${props.attempt.id}/answer`, {
      question_id: currentQuestion.value.id,
      selected_option: optionLetter,
      time_spent_seconds: timeElapsed.value
    });
    
    // Update local state
    currentAnswer.value.selected_option = optionLetter;
  } catch (error) {
    console.error('Failed to submit answer:', error);
  }
};

// Navigate to question
const goToQuestion = (index: number) => {
  if (index >= 0 && index < totalQuestions.value) {
    currentQuestionIndex.value = index;
  }
};

// Previous question
const previousQuestion = () => {
  if (currentQuestionIndex.value > 0) {
    currentQuestionIndex.value--;
  }
};

// Next question
const nextQuestion = () => {
  if (currentQuestionIndex.value < totalQuestions.value - 1) {
    currentQuestionIndex.value++;
  }
};

// Toggle bookmark
const toggleBookmark = async () => {
  try {
    await axios.post(`/api/quiz-attempts/${props.attempt.id}/bookmark`, {
      question_id: currentQuestion.value.id
    });
    
    currentAnswer.value.is_bookmarked = !currentAnswer.value.is_bookmarked;
  } catch (error) {
    console.error('Failed to toggle bookmark:', error);
  }
};

// Complete quiz
const completeQuiz = async () => {
  if (!confirm(`You have answered ${answeredCount.value} out of ${totalQuestions.value} questions. Are you sure you want to submit?`)) {
    return;
  }
  
  submitting.value = true;
  try {
    await axios.post(`/api/quiz-attempts/${props.attempt.id}/complete`, {
      time_spent_seconds: timeElapsed.value
    });
    
    // Redirect to results page
    window.location.href = `/quiz-results/${props.attempt.id}`;
  } catch (error) {
    console.error('Failed to complete quiz:', error);
    submitting.value = false;
  }
};
</script>

<template>
  <AppHeaderLayout>
    <div class="container mx-auto px-4 py-6 pt-20 bg-gray-50 dark:bg-gray-900 min-h-screen">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold mb-2">{{ attempt.quiz.title }}</h1>
        <div class="flex items-center gap-4 text-sm text-muted-foreground">
          <div class="flex items-center">
            <Clock class="h-4 w-4 mr-1" />
            <span>{{ formattedTime }}</span>
          </div>
          <span>Question {{ currentQuestionIndex + 1 }} of {{ totalQuestions }}</span>
          <span>{{ answeredCount }} answered</span>
        </div>
      </div>

      <!-- Progress Bar -->
      <div class="w-full bg-gray-200 rounded-full h-2 mb-6">
        <div class="bg-primary h-2 rounded-full transition-all" :style="{ width: progress + '%' }"></div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Question Panel -->
        <div class="lg:col-span-3">
          <Card>
            <CardContent class="p-6">
              <!-- Question Text -->
              <div class="mb-6">
                <div class="flex items-start justify-between mb-4">
                  <h2 class="text-lg font-semibold">
                    {{ currentQuestionIndex + 1 }}. {{ currentQuestion.question_text }}
                  </h2>
                  <Button
                    variant="ghost"
                    size="sm"
                    @click="toggleBookmark"
                    :class="{ 'text-yellow-500': currentAnswer.is_bookmarked }"
                  >
                    <Flag :class="{ 'fill-current': currentAnswer.is_bookmarked }" class="h-4 w-4" />
                  </Button>
                </div>
              </div>

              <!-- Options -->
              <div class="space-y-3">
                <div
                  v-for="option in currentQuestion.options"
                  :key="option.id"
                  class="flex items-center space-x-3 p-4 rounded-lg border cursor-pointer hover:bg-accent transition-colors"
                  :class="{ 'border-primary bg-primary/5 border-2': currentAnswer.selected_option === option.option_letter }"
                  @click="selectOption(option.option_letter)"
                >
                  <input
                    type="radio"
                    :name="`question-${currentQuestion.id}`"
                    :value="option.option_letter"
                    :checked="currentAnswer.selected_option === option.option_letter"
                    @change="selectOption(option.option_letter)"
                    class="h-4 w-4"
                  />
                  <label class="flex-1 cursor-pointer">
                    <span class="font-semibold mr-2">{{ option.option_letter }}.</span>
                    {{ option.option_text }}
                  </label>
                </div>
              </div>

              <!-- Navigation Buttons -->
              <div class="flex justify-between mt-6">
                <Button
                  variant="outline"
                  @click="previousQuestion"
                  :disabled="currentQuestionIndex === 0"
                >
                  <ChevronLeft class="h-4 w-4 mr-1" />
                  Previous
                </Button>
                
                <Button
                  v-if="currentQuestionIndex < totalQuestions - 1"
                  @click="nextQuestion"
                >
                  Next
                  <ChevronRight class="h-4 w-4 ml-1" />
                </Button>
                
                <Button
                  v-else
                  @click="completeQuiz"
                  :disabled="submitting"
                  class="bg-green-600 hover:bg-green-700"
                >
                  {{ submitting ? 'Submitting...' : 'Submit Quiz' }}
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Question Navigation Panel -->
        <div class="lg:col-span-1">
          <Card>
            <CardContent class="p-4">
              <h3 class="font-semibold mb-4">Questions</h3>
              <div class="grid grid-cols-5 gap-2">
                <Button
                  v-for="(answer, index) in attempt.answers"
                  :key="answer.id"
                  variant="outline"
                  size="sm"
                  @click="goToQuestion(index)"
                  :class="{
                    'bg-primary text-primary-foreground': index === currentQuestionIndex,
                    'bg-green-100 border-green-500': answer.selected_option && index !== currentQuestionIndex,
                    'border-yellow-500': answer.is_bookmarked
                  }"
                >
                  {{ index + 1 }}
                </Button>
              </div>
              
              <div class="mt-4 space-y-2 text-xs">
                <div class="flex items-center gap-2">
                  <div class="w-4 h-4 bg-primary rounded"></div>
                  <span>Current</span>
                </div>
                <div class="flex items-center gap-2">
                  <div class="w-4 h-4 bg-green-100 border border-green-500 rounded"></div>
                  <span>Answered</span>
                </div>
                <div class="flex items-center gap-2">
                  <div class="w-4 h-4 border-2 border-yellow-500 rounded"></div>
                  <span>Bookmarked</span>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  </AppHeaderLayout>
</template>
