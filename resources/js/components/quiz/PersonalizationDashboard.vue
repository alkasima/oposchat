<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { 
  TrendingUp, 
  TrendingDown, 
  Minus, 
  Target, 
  AlertCircle, 
  CheckCircle2, 
  BookOpen,
  ArrowRight,
  Sparkles
} from 'lucide-vue-next';

interface Props {
  courseId: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'start-quiz', config: any): void;
  (e: 'generate-ai-quiz', config: any): void;
}>();

const recommendations = ref<any>(null);
const loading = ref(true);
const error = ref('');

const fetchRecommendations = async () => {
  loading.value = true;
  error.value = '';
  
  try {
    const response = await axios.get('/api/personalization/recommendations', {
      params: { course_id: props.courseId }
    });
    
    recommendations.value = response.data.data;
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load recommendations';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchRecommendations();
});

const trendIcon = computed(() => {
  const trend = recommendations.value?.performance_trend?.trend;
  if (trend === 'improving') return TrendingUp;
  if (trend === 'declining') return TrendingDown;
  return Minus;
});

const trendColor = computed(() => {
  const trend = recommendations.value?.performance_trend?.trend;
  if (trend === 'improving') return 'text-green-600';
  if (trend === 'declining') return 'text-red-600';
  return 'text-gray-600';
});

const accuracyColor = computed(() => {
  const accuracy = recommendations.value?.overall_accuracy || 0;
  if (accuracy >= 80) return 'text-green-600';
  if (accuracy >= 60) return 'text-yellow-600';
  return 'text-red-600';
});

const startRecommendedQuiz = (suggestedQuiz: any) => {
  if (suggestedQuiz.type === 'ai_generated') {
    emit('generate-ai-quiz', suggestedQuiz);
  } else {
    emit('start-quiz', suggestedQuiz);
  }
};
</script>

<template>
  <div class="space-y-6">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="text-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
        <p class="text-muted-foreground">Loading your recommendations...</p>
      </div>
    </div>

    <!-- Error State -->
    <Card v-else-if="error" class="border-red-200">
      <CardContent class="pt-6">
        <div class="flex items-center gap-2 text-red-600">
          <AlertCircle class="h-5 w-5" />
          <p>{{ error }}</p>
        </div>
      </CardContent>
    </Card>

    <!-- Insufficient Data -->
    <template v-else-if="!recommendations?.has_sufficient_data">
      <Card>
        <CardHeader>
          <CardTitle>Complete More Quizzes to Unlock Recommendations</CardTitle>
          <CardDescription>
            You need at least {{ recommendations?.attempts_needed || 3 }} more quiz attempts to get personalized recommendations
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="space-y-4">
            <div v-for="(rec, index) in recommendations?.general_recommendations" :key="index" class="p-4 border rounded-lg">
              <h4 class="font-semibold mb-2">{{ rec.title }}</h4>
              <p class="text-sm text-muted-foreground mb-2">{{ rec.description }}</p>
              <p class="text-sm font-medium text-primary">{{ rec.action }}</p>
            </div>
          </div>
        </CardContent>
      </Card>
    </template>

    <!-- Full Recommendations -->
    <template v-else>
      <!-- Performance Overview -->
      <Card>
        <CardHeader>
          <CardTitle>Performance Overview</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Overall Accuracy -->
            <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
              <div class="flex items-center justify-center mb-2">
                <Target class="h-5 w-5 mr-2 text-primary" />
                <span class="text-sm font-medium text-muted-foreground">Overall Accuracy</span>
              </div>
              <div :class="['text-4xl font-bold', accuracyColor]">
                {{ recommendations.overall_accuracy.toFixed(1) }}%
              </div>
            </div>

            <!-- Performance Trend -->
            <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
              <div class="flex items-center justify-center mb-2">
                <component :is="trendIcon" :class="['h-5 w-5 mr-2', trendColor]" />
                <span class="text-sm font-medium text-muted-foreground">Trend</span>
              </div>
              <p :class="['text-sm font-medium mt-2', trendColor]">
                {{ recommendations.performance_trend?.message }}
              </p>
            </div>
          </div>

          <!-- Topic Performance -->
          <Separator class="my-6" />

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Weak Topics -->
            <div v-if="recommendations.weak_topics?.length > 0">
              <h4 class="font-semibold mb-3 flex items-center">
                <AlertCircle class="h-4 w-4 mr-2 text-red-600" />
                Topics to Improve
              </h4>
              <div class="flex flex-wrap gap-2">
                <Badge v-for="topic in recommendations.weak_topics" :key="topic" variant="destructive">
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
                <Badge v-for="topic in recommendations.strong_topics" :key="topic" variant="secondary">
                  {{ topic }}
                </Badge>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Next Steps -->
      <Card>
        <CardHeader>
          <CardTitle>Recommended Next Steps</CardTitle>
          <CardDescription>Prioritized actions to improve your performance</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="space-y-4">
            <div
              v-for="(step, index) in recommendations.next_steps"
              :key="index"
              class="p-4 border rounded-lg hover:border-primary transition-colors"
            >
              <div class="flex items-start justify-between mb-2">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-2">
                    <Badge :variant="step.priority === 'high' ? 'destructive' : step.priority === 'medium' ? 'default' : 'secondary'">
                      {{ step.priority }} priority
                    </Badge>
                    <h4 class="font-semibold">{{ step.action }}</h4>
                  </div>
                  <p class="text-sm text-muted-foreground mb-3">{{ step.description }}</p>
                  
                  <!-- Suggested Quiz Info -->
                  <div v-if="step.suggested_quiz" class="flex items-center gap-4 text-xs text-muted-foreground">
                    <span class="capitalize">{{ step.suggested_quiz.difficulty }} difficulty</span>
                    <span v-if="step.suggested_quiz.topics?.length">
                      Topics: {{ step.suggested_quiz.topics.join(', ') }}
                    </span>
                    <span v-if="step.suggested_quiz.question_count">
                      {{ step.suggested_quiz.question_count }} questions
                    </span>
                  </div>
                </div>
                
                <Button
                  size="sm"
                  @click="startRecommendedQuiz(step.suggested_quiz)"
                  class="ml-4"
                >
                  <Sparkles v-if="step.suggested_quiz?.type === 'ai_generated'" class="h-4 w-4 mr-2" />
                  <BookOpen v-else class="h-4 w-4 mr-2" />
                  Start
                  <ArrowRight class="h-4 w-4 ml-1" />
                </Button>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Study Plan -->
      <Card v-if="recommendations.study_plan?.length > 0">
        <CardHeader>
          <CardTitle>Personalized Study Plan</CardTitle>
          <CardDescription>Follow this plan for optimal improvement</CardDescription>
        </CardHeader>
        <CardContent>
          <Accordion type="single" collapsible>
            <AccordionItem
              v-for="phase in recommendations.study_plan"
              :key="phase.phase"
              :value="`phase-${phase.phase}`"
            >
              <AccordionTrigger>
                <div class="flex items-center gap-3">
                  <Badge variant="outline">Phase {{ phase.phase }}</Badge>
                  <span class="font-semibold">{{ phase.title }}</span>
                </div>
              </AccordionTrigger>
              <AccordionContent>
                <div class="space-y-3 pt-2">
                  <p class="text-sm text-muted-foreground">{{ phase.description }}</p>
                  
                  <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <span class="font-medium">Topics:</span>
                      <div class="flex flex-wrap gap-1 mt-1">
                        <Badge v-for="topic in phase.topics" :key="topic" variant="secondary" class="text-xs">
                          {{ topic }}
                        </Badge>
                      </div>
                    </div>
                    
                    <div>
                      <span class="font-medium">Recommended Difficulty:</span>
                      <p class="text-muted-foreground capitalize mt-1">{{ phase.recommended_difficulty }}</p>
                    </div>
                  </div>
                  
                  <div class="text-sm">
                    <span class="font-medium">Duration:</span>
                    <p class="text-muted-foreground">{{ phase.recommended_duration }}</p>
                  </div>
                </div>
              </AccordionContent>
            </AccordionItem>
          </Accordion>
        </CardContent>
      </Card>

      <!-- Common Mistakes -->
      <Card v-if="recommendations.common_mistakes?.length > 0">
        <CardHeader>
          <CardTitle>Common Mistakes</CardTitle>
          <CardDescription>Questions you frequently get wrong</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="space-y-3">
            <div
              v-for="(mistake, index) in recommendations.common_mistakes.slice(0, 5)"
              :key="index"
              class="p-3 border rounded-lg"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <p class="text-sm font-medium mb-1">{{ mistake.question_text }}</p>
                  <div class="flex items-center gap-4 text-xs text-muted-foreground">
                    <span>Incorrect {{ mistake.times_incorrect }} times</span>
                    <span>{{ (mistake.accuracy_rate * 100).toFixed(0) }}% accuracy</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </template>
  </div>
</template>
