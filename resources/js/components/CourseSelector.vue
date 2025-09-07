<template>
  <div class="relative">
    <!-- Course Selection Button -->
    <button
      @click="toggleDropdown"
      class="flex items-center space-x-2 px-3 py-2 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors duration-200"
    >
      <div v-if="selectedCourse" class="flex items-center space-x-2">
        <span v-if="selectedCourse.icon" class="text-lg">{{ selectedCourse.icon }}</span>
        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
          {{ selectedCourse.name }}
        </span>
      </div>
      <div v-else class="flex items-center space-x-2">
        <span class="text-sm text-gray-500 dark:text-gray-400">Select Course</span>
      </div>
      <svg
        class="w-4 h-4 text-gray-500 dark:text-gray-400 transition-transform duration-200"
        :class="{ 'rotate-180': isOpen }"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <!-- Dropdown Menu -->
    <div
      v-if="isOpen"
      class="absolute top-full left-0 mt-1 w-64 bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg shadow-lg z-50"
    >
      <div class="p-2">
        <!-- Clear Selection -->
        <button
          @click="clearSelection"
          class="w-full text-left px-3 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md transition-colors duration-200"
        >
          <span class="flex items-center space-x-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <span>No specific course</span>
          </span>
        </button>

        <!-- Course List -->
        <div v-if="courses.length > 0" class="border-t border-gray-200 dark:border-slate-600 mt-2 pt-2">
          <div class="text-xs font-medium text-gray-500 dark:text-gray-400 px-3 py-1 mb-1">
            Available Courses
          </div>
          <button
            v-for="course in courses"
            :key="course.id"
            @click="selectCourse(course)"
            class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-slate-700 rounded-md transition-colors duration-200"
            :class="{
              'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300': selectedCourse?.id === course.id,
              'text-gray-700 dark:text-gray-200': selectedCourse?.id !== course.id
            }"
          >
            <div class="flex items-center space-x-3">
              <span v-if="course.icon" class="text-lg">{{ course.icon }}</span>
              <div class="flex-1 min-w-0">
                <div class="font-medium truncate">{{ course.name }}</div>
                <div v-if="course.description" class="text-xs text-gray-500 dark:text-gray-400 truncate">
                  {{ course.description }}
                </div>
              </div>
            </div>
          </button>
        </div>

        <!-- Loading State -->
        <div v-else-if="loading" class="px-3 py-4 text-center">
          <div class="inline-flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Loading courses...</span>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="px-3 py-4 text-center">
          <div class="text-sm text-gray-500 dark:text-gray-400">
            No courses available
          </div>
        </div>
      </div>
    </div>

    <!-- Course Scope Indicator -->
    <div v-if="selectedCourse" class="mt-2">
      <div class="inline-flex items-center space-x-1 px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full text-xs">
        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
        </svg>
        <span>Using: {{ selectedCourse.name }}</span>
        <button
          @click="clearSelection"
          class="ml-1 hover:text-blue-800 dark:hover:text-blue-200"
          title="Clear course selection"
        >
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
          </svg>
        </button>
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
    const response = await fetch('/api/courses');
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
  
  // Load initial course if provided
  if (props.initialCourseId) {
    fetchCourses();
  }
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
</script>
