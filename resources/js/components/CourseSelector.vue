<template>
  <div class="relative">
    <!-- Enhanced Course Selection Button -->
    <button
      @click="toggleDropdown"
      class="flex items-center space-x-2 px-3 py-2 bg-gradient-to-r from-white to-gray-50 dark:from-slate-700 dark:to-slate-600 border border-gray-200 dark:border-slate-600 rounded-xl hover:from-gray-50 hover:to-gray-100 dark:hover:from-slate-600 dark:hover:to-slate-500 transition-all duration-200 shadow-sm hover:shadow-md transform hover:scale-105"
    >
      <div v-if="selectedCourse" class="flex items-center space-x-2">
        <span v-if="selectedCourse.icon" class="text-lg">{{ selectedCourse.icon }}</span>
        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
          {{ selectedCourse.name }}
        </span>
      </div>
      <div v-else class="flex items-center space-x-2">
        <div class="w-5 h-5 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center">
          <span class="text-white text-xs font-bold">+</span>
        </div>
        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Select Exam</span>
      </div>
      <svg
        class="w-4 h-4 text-gray-500 dark:text-gray-400 transition-all duration-200"
        :class="{ 'rotate-180': isOpen }"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <!-- Enhanced Dropdown Menu -->
    <div
      v-if="isOpen"
      class="absolute top-full left-0 mt-2 w-64 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-xl shadow-xl z-[9999] backdrop-blur-sm"
    >
      <div class="p-2">
        <!-- Clear Selection -->
        <button
          @click="clearSelection"
          class="w-full text-left px-3 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-700 rounded-lg transition-all duration-200 group"
        >
          <span class="flex items-center space-x-2">
            <div class="w-6 h-6 bg-gray-100 dark:bg-slate-600 rounded-full flex items-center justify-center group-hover:bg-gray-200 dark:group-hover:bg-slate-500 transition-colors">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </div>
            <span class="font-medium">No specific exam</span>
          </span>
        </button>

        <!-- Course List -->
        <div v-if="courses.length > 0" class="border-t border-gray-200 dark:border-slate-600 mt-2 pt-2">
          <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 px-3 py-1 mb-1 uppercase tracking-wide">
            Available Exams
          </div>
          <button
            v-for="course in courses"
            :key="course.id"
            @click="selectCourse(course)"
            class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-slate-700 rounded-lg transition-all duration-200 group"
            :class="{
              'bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-900/20 dark:to-purple-900/20 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800': selectedCourse?.id === course.id,
              'text-gray-700 dark:text-gray-200': selectedCourse?.id !== course.id
            }"
          >
            <div class="flex items-center space-x-2">
              <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold text-sm shadow-sm">
                <span v-if="course.icon">{{ course.icon }}</span>
                <span v-else>{{ course.name.charAt(0) }}</span>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold truncate text-sm">{{ course.name }}</div>
                <div v-if="course.description" class="text-xs text-gray-500 dark:text-gray-400 truncate">
                  {{ course.description }}
                </div>
              </div>
              <div v-if="selectedCourse?.id === course.id" class="w-4 h-4 bg-blue-500 rounded-full flex items-center justify-center">
                <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
            </div>
          </button>
        </div>

        <!-- Loading State -->
        <div v-else-if="loading" class="px-3 py-2 text-center">
          <div class="inline-flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
            <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Loading courses...</span>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="px-3 py-2 text-center">
          <div class="text-sm text-gray-500 dark:text-gray-400">
            No courses available
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';

interface Course {
  id: number;
  name: string;
  slug: string;
  description: string;
  namespace: string;
  icon?: string;
  color?: string;
}

interface Props {
  chatId?: number;
  initialCourseId?: number;
}

interface Emits {
  (e: 'course-selected', course: Course | null): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const isOpen = ref(false);
const courses = ref<Course[]>([]);
const selectedCourse = ref<Course | null>(null);
const loading = ref(false);

const toggleDropdown = () => {
  isOpen.value = !isOpen.value;
  if (isOpen.value && courses.value.length === 0) {
    fetchCourses();
  }
};

const fetchCourses = async () => {
  loading.value = true;
  try {
    const response = await fetch('/api/courses', { headers: { 'Accept': 'application/json' } });
    if (response.ok) {
      courses.value = await response.json();
      
      // Set initial course if provided
      if (props.initialCourseId) {
        const course = courses.value.find(c => c.id === props.initialCourseId);
        if (course) {
          selectedCourse.value = course;
          emit('course-selected', course);
        }
      }
    }
  } catch (error) {
    console.error('Failed to fetch courses:', error);
  } finally {
    loading.value = false;
  }
};

const selectCourse = async (course: Course) => {
  selectedCourse.value = course;
  isOpen.value = false;
  emit('course-selected', course);
  
  // Update chat with selected course
  if (props.chatId) {
    try {
      await fetch(`/api/chats/${props.chatId}`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          course_id: course.id,
        }),
      });
    } catch (error) {
      console.error('Failed to update chat course:', error);
    }
  }
};

const clearSelection = async () => {
  selectedCourse.value = null;
  isOpen.value = false;
  emit('course-selected', null);
  
  // Update chat to remove course selection
  if (props.chatId) {
    try {
      await fetch(`/api/chats/${props.chatId}`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          course_id: null,
        }),
      });
    } catch (error) {
      console.error('Failed to clear chat course:', error);
    }
  }
};

// Close dropdown when clicking outside
const handleClickOutside = (event: Event) => {
  const target = event.target as HTMLElement;
  if (!target.closest('.relative')) {
    isOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  
  // Always fetch courses to ensure we can show the selected course
  fetchCourses();
});

// Watch for prop changes
watch(() => props.initialCourseId, (newCourseId) => {
  if (newCourseId && courses.value.length > 0) {
    const course = courses.value.find(c => c.id === newCourseId);
    if (course) {
      selectedCourse.value = course;
      emit('course-selected', course);
    }
  }
});

// Watch for courses to be loaded and set initial course if needed
watch(() => courses.value, (newCourses) => {
  if (newCourses.length > 0 && props.initialCourseId && !selectedCourse.value) {
    const course = newCourses.find(c => c.id === props.initialCourseId);
    if (course) {
      selectedCourse.value = course;
      emit('course-selected', course);
    }
  }
}, { immediate: true });

</script>
