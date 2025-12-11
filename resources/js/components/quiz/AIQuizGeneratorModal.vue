<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Slider } from '@/components/ui/slider';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Loader2, Sparkles, AlertCircle, CheckCircle2 } from 'lucide-vue-next';

interface Props {
  open: boolean;
  courseId: number;
  availableTopics?: string[];
}

const props = withDefaults(defineProps<Props>(), {
  availableTopics: () => []
});

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'quiz-generated', questions: any[]): void;
}>();

const questionCount = ref([10]);
const difficulty = ref('medium');
const selectedTopics = ref<string[]>([]);
const focusOnWeakAreas = ref(false);
const loading = ref(false);
const error = ref('');
const success = ref(false);
const rateLimit = ref({ used: 0, limit: 3 });

// Fetch rate limit on mount
const fetchRateLimit = async () => {
  try {
    const today = new Date().toISOString().split('T')[0];
    const response = await axios.get('/api/ai-quiz/rate-limit', {
      params: { course_id: props.courseId, date: today }
    });
    rateLimit.value = response.data.data;
  } catch (err) {
    console.error('Failed to fetch rate limit:', err);
  }
};

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    fetchRateLimit();
    error.value = '';
    success.value = false;
  }
});

const remainingQuizzes = computed(() => rateLimit.value.limit - rateLimit.value.used);
const canGenerate = computed(() => remainingQuizzes.value > 0);

const toggleTopic = (topic: string) => {
  const index = selectedTopics.value.indexOf(topic);
  if (index > -1) {
    selectedTopics.value.splice(index, 1);
  } else {
    selectedTopics.value.push(topic);
  }
};

const generateQuiz = async () => {
  if (!canGenerate.value) {
    error.value = 'Daily AI quiz limit reached. Please try again tomorrow.';
    return;
  }

  loading.value = true;
  error.value = '';
  success.value = false;

  try {
    const response = await axios.post('/api/ai-quiz/generate', {
      course_id: props.courseId,
      question_count: questionCount.value[0],
      difficulty: difficulty.value,
      topics: selectedTopics.value,
      focus_on_weak_areas: focusOnWeakAreas.value
    });

    if (response.data.success) {
      success.value = true;
      
      // Redirect to quiz attempt page
      setTimeout(() => {
        window.location.href = response.data.data.redirect_url;
      }, 1000);
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to generate AI quiz. Please try again.';
  } finally {
    loading.value = false;
  }
};

const closeModal = () => {
  if (!loading.value) {
    emit('update:open', false);
  }
};
</script>

<template>
  <Dialog :open="open" @update:open="closeModal">
    <DialogContent class="sm:max-w-[600px]">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          <Sparkles class="h-5 w-5 text-purple-600" />
          Generate AI Quiz
        </DialogTitle>
        <DialogDescription>
          Create a personalized quiz with AI-generated questions based on your syllabus
        </DialogDescription>
      </DialogHeader>

      <div class="space-y-6 py-4">
        <!-- Rate Limit Info -->
        <Alert :variant="canGenerate ? 'default' : 'destructive'">
          <AlertCircle class="h-4 w-4" />
          <AlertDescription>
            <template v-if="canGenerate">
              {{ remainingQuizzes }} of {{ rateLimit.limit }} AI quizzes remaining today
            </template>
            <template v-else>
              Daily limit reached. Try again tomorrow!
            </template>
          </AlertDescription>
        </Alert>

        <!-- Question Count -->
        <div class="space-y-2">
          <Label>Number of Questions: {{ questionCount[0] }}</Label>
          <Slider
            v-model="questionCount"
            :min="5"
            :max="20"
            :step="1"
            :disabled="loading"
          />
          <p class="text-xs text-muted-foreground">
            More questions = longer generation time (~{{ Math.ceil(questionCount[0] / 5) * 30 }}-{{ Math.ceil(questionCount[0] / 5) * 60 }} seconds)
          </p>
        </div>

        <!-- Difficulty -->
        <div class="space-y-2">
          <Label>Difficulty Level</Label>
          <div class="flex gap-2">
            <Button
              v-for="level in ['easy', 'medium', 'hard']"
              :key="level"
              :variant="difficulty === level ? 'default' : 'outline'"
              size="sm"
              @click="difficulty = level"
              :disabled="loading"
              class="flex-1 capitalize"
            >
              {{ level }}
            </Button>
          </div>
        </div>

        <!-- Topics -->
        <div v-if="availableTopics.length > 0" class="space-y-2">
          <Label>Topics (optional)</Label>
          <div class="flex flex-wrap gap-2 max-h-32 overflow-y-auto p-2 border rounded-md">
            <Badge
              v-for="topic in availableTopics"
              :key="topic"
              :variant="selectedTopics.includes(topic) ? 'default' : 'outline'"
              class="cursor-pointer"
              @click="toggleTopic(topic)"
            >
              {{ topic }}
            </Badge>
          </div>
          <p class="text-xs text-muted-foreground">
            Leave empty for random topics
          </p>
        </div>

        <!-- Focus on Weak Areas -->
        <div class="flex items-center justify-between space-x-2 p-3 border rounded-lg">
          <div class="space-y-0.5">
            <Label>Focus on Weak Areas</Label>
            <p class="text-xs text-muted-foreground">
              Generate questions on topics you need to practice
            </p>
          </div>
          <Switch
            v-model:checked="focusOnWeakAreas"
            :disabled="loading"
          />
        </div>

        <!-- Error Message -->
        <Alert v-if="error" variant="destructive">
          <AlertCircle class="h-4 w-4" />
          <AlertDescription>{{ error }}</AlertDescription>
        </Alert>

        <!-- Success Message -->
        <Alert v-if="success" variant="default" class="border-green-500">
          <CheckCircle2 class="h-4 w-4 text-green-600" />
          <AlertDescription class="text-green-600">
            Quiz generated successfully! Redirecting...
          </AlertDescription>
        </Alert>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="closeModal" :disabled="loading">
          Cancel
        </Button>
        <Button
          @click="generateQuiz"
          :disabled="loading || !canGenerate"
        >
          <Loader2 v-if="loading" class="h-4 w-4 mr-2 animate-spin" />
          <Sparkles v-else class="h-4 w-4 mr-2" />
          {{ loading ? 'Generating...' : 'Generate Quiz' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
