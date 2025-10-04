<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Course Content Management</h1>
        <p class="mt-2 text-gray-600 dark:text-gray-400">
          Cargue y administre materiales del curso para la preparación de exámenes con tecnología de IA
        </p>
      </div>

      <!-- Course Selection -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Select Course</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <button
            v-for="course in courses"
            :key="course.id"
            @click="selectCourse(course)"
            class="p-4 border-2 rounded-lg text-left transition-all duration-200"
            :class="{
              'border-blue-500 bg-blue-50 dark:bg-blue-900/20': selectedCourse?.id === course.id,
              'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600': selectedCourse?.id !== course.id
            }"
          >
            <div class="flex items-center space-x-3">
              <span v-if="course.icon" class="text-2xl">{{ course.icon }}</span>
              <div>
                <h3 class="font-medium text-gray-900 dark:text-white">{{ course.name }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ course.description }}</p>
              </div>
            </div>
          </button>
        </div>
      </div>

      <!-- Content Upload Section -->
      <div v-if="selectedCourse" class="space-y-8">
        <!-- Text Content Upload -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
            Upload Text Content
          </h2>
          <form @submit.prevent="uploadTextContent" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Title
              </label>
              <input
                v-model="textForm.title"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="Enter content title"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Description
              </label>
              <input
                v-model="textForm.description"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="Enter content description"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Content
              </label>
              <textarea
                v-model="textForm.content"
                rows="10"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="Paste your course content here..."
                required
              ></textarea>
            </div>
            <div class="flex justify-end">
              <button
                type="submit"
                :disabled="isUploading || !textForm.content.trim()"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="isUploading">Uploading...</span>
                <span v-else>Upload Content</span>
              </button>
            </div>
          </form>
        </div>

        <!-- File Upload -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
           Subir archivo
          </h2>
          <form @submit.prevent="uploadFile" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Title
              </label>
              <input
                v-model="fileForm.title"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="Enter file title"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Description
              </label>
              <input
                v-model="fileForm.description"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="Enter file description"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                File
              </label>
              <input
                ref="fileInput"
                type="file"
                @change="handleFileSelect"
                accept=".txt,.pdf,.doc,.docx,.md"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                required
              />
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Supported formats: TXT, PDF, DOC, DOCX, MD (Max 10MB)
              </p>
            </div>
            <div class="flex justify-end">
              <button
                type="submit"
                :disabled="isUploading || !selectedFile"
                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="isUploading">Uploading...</span>
                <span v-else>Upload File</span>
              </button>
            </div>
          </form>
        </div>

        <!-- Course Actions -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
            Acciones del curso
          </h2>
          <div class="flex space-x-4">
            <button
              @click="getContentStats"
              class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700"
            >
              Obtener estadísticas
            </button>
            <button
              @click="deleteCourseContent"
              class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
            >
            Eliminar todo el contenido
            </button>
          </div>
        </div>
      </div>

      <!-- Success/Error Messages -->
      <div v-if="message" class="mt-4 p-4 rounded-md" :class="messageType === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'">
        {{ message }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';

interface Course {
  id: number;
  name: string;
  description: string;
  icon?: string;
  namespace: string;
}

interface Props {
  courses: Course[];
}

const props = defineProps<Props>();

const selectedCourse = ref<Course | null>(null);
const selectedFile = ref<File | null>(null);
const isUploading = ref(false);
const message = ref('');
const messageType = ref<'success' | 'error'>('success');
const fileInput = ref<HTMLInputElement | null>(null);

const textForm = reactive({
  title: '',
  description: '',
  content: ''
});

const fileForm = reactive({
  title: '',
  description: ''
});

const selectCourse = (course: Course) => {
  selectedCourse.value = course;
  message.value = '';
};

const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  if (file) {
    selectedFile.value = file;
    if (!fileForm.title) {
      fileForm.title = file.name.replace(/\.[^/.]+$/, ''); // Remove extension
    }
  }
};

const showMessage = (text: string, type: 'success' | 'error') => {
  message.value = text;
  messageType.value = type;
  setTimeout(() => {
    message.value = '';
  }, 5000);
};

const uploadTextContent = async () => {
  if (!selectedCourse.value || !textForm.content.trim()) return;

  isUploading.value = true;
  try {
    const response = await fetch('/admin/course-content/upload', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        course_id: selectedCourse.value.id,
        title: textForm.title,
        description: textForm.description,
        content: textForm.content,
      }),
    });

    const data = await response.json();

    if (data.success) {
      showMessage(`Content uploaded successfully! Processed ${data.data.chunks_processed} chunks.`, 'success');
      // Reset form
      textForm.title = '';
      textForm.description = '';
      textForm.content = '';
    } else {
      showMessage(data.message || 'Upload failed', 'error');
    }
  } catch (error) {
    showMessage('Upload failed: ' + error.message, 'error');
  } finally {
    isUploading.value = false;
  }
};

const uploadFile = async () => {
  if (!selectedCourse.value || !selectedFile.value) return;

  isUploading.value = true;
  try {
    const formData = new FormData();
    formData.append('course_id', selectedCourse.value.id.toString());
    formData.append('file', selectedFile.value);
    formData.append('title', fileForm.title);
    formData.append('description', fileForm.description);

    const response = await fetch('/admin/course-content/upload-file', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: formData,
    });

    const data = await response.json();

    if (data.success) {
      showMessage(`File uploaded successfully! Processed ${data.data.chunks_processed} chunks.`, 'success');
      // Reset form
      fileForm.title = '';
      fileForm.description = '';
      selectedFile.value = null;
      if (fileInput.value) {
        fileInput.value.value = '';
      }
    } else {
      showMessage(data.message || 'Upload failed', 'error');
    }
  } catch (error) {
    showMessage('Upload failed: ' + error.message, 'error');
  } finally {
    isUploading.value = false;
  }
};

const getContentStats = async () => {
  if (!selectedCourse.value) return;

  try {
    const response = await fetch(`/admin/course-content/stats?course_id=${selectedCourse.value.id}`);
    const data = await response.json();

    if (data.success) {
      showMessage(`Course: ${data.data.course_name} (${data.data.namespace})`, 'success');
    } else {
      showMessage(data.message || 'Failed to get stats', 'error');
    }
  } catch (error) {
    showMessage('Failed to get stats: ' + error.message, 'error');
  }
};

const deleteCourseContent = async () => {
  if (!selectedCourse.value) return;

  if (!confirm(`Are you sure you want to delete all content for "${selectedCourse.value.name}"? This action cannot be undone.`)) {
    return;
  }

  try {
    const response = await fetch('/admin/course-content/delete', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        course_id: selectedCourse.value.id,
      }),
    });

    const data = await response.json();

    if (data.success) {
      showMessage('Course content deleted successfully', 'success');
    } else {
      showMessage(data.message || 'Deletion failed', 'error');
    }
  } catch (error) {
    showMessage('Deletion failed: ' + error.message, 'error');
  }
};
</script>
