<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Upload } from 'lucide-vue-next';

const page = usePage();
const course = computed(() => page.props.course);
const isUploading = ref(false);

const form = ref({
    title: '',
    description: '',
    duration_minutes: 30,
    file: null as File | null
});

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        form.value.file = target.files[0];
        // Auto-fill title if empty
        if (!form.value.title) {
            form.value.title = target.files[0].name.replace(/\.[^/.]+$/, "");
        }
    }
};

const uploadQuiz = async () => {
    if (!form.value.file) {
        alert('Please select a JSON file.');
        return;
    }

    try {
        isUploading.value = true;
        const formData = new FormData();
        formData.append('title', form.value.title);
        formData.append('description', form.value.description);
        formData.append('duration_minutes', String(form.value.duration_minutes));
        formData.append('file', form.value.file);

        // Get CSRF Token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        const response = await fetch(`/admin/courses/${course.value.id}/quizzes`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: formData
        });

        const data = await response.json();

        if (response.ok && data.success) {
            alert('Quiz created successfully!');
            // Redirect to course documents as a fallback or stay
            // Maybe redirect to course index?
            // checking history, maybe best to go back or stay here and clear form
             form.value.title = '';
             form.value.description = '';
             form.value.file = null;
             // Reset file input
             const fileInput = document.querySelector('input[type="file"]') as HTMLInputElement;
             if(fileInput) fileInput.value = '';
             
        } else {
            throw new Error(data.message || 'Upload failed');
        }

    } catch (error: any) {
        console.error(error);
        alert('Error: ' + error.message);
    } finally {
        isUploading.value = false;
    }
};
</script>

<template>
    <AdminLayout title="Upload Quiz">
        <Head title="Upload Quiz" />
        
        <template #header>
            Crear Quiz para {{ course.name }}
        </template>

        <div class="max-w-3xl mx-auto space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Importar Quiz desde JSON</CardTitle>
                    <CardDescription>
                        Sube un archivo JSON con la estructura del quiz.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="bg-slate-100 p-4 rounded-md text-xs font-mono overflow-auto max-h-40 border">
                        <p class="mb-2 font-bold text-slate-700">// Ejemplo de estructura JSON requerida:</p>
                        <pre>{
  "questions": [
    {
      "text": "¿Cuál es la capital de España?",
      "difficulty": "easy",
      "options": [
        {"text": "Madrid", "is_correct": true},
        {"text": "Barcelona", "is_correct": false},
        {"text": "Valencia", "is_correct": false},
        {"text": "Sevilla", "is_correct": false}
      ],
      "explanation": "Madrid es la capital desde 1561."
    }
  ]
}</pre>
                    </div>

                     <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Título del Quiz</label>
                        <Input v-model="form.title" placeholder="Ej: Examen de Legislación - Tema 1" />
                     </div>
                     
                     <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Descripción</label>
                        <Textarea v-model="form.description" placeholder="Breve descripción del contenido..." />
                     </div>

                     <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Duración (Minutos)</label>
                        <Input v-model="form.duration_minutes" type="number" min="1" />
                     </div>

                     <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Archivo JSON</label>
                         <input
                                type="file"
                                @change="handleFileSelect"
                                accept=".json,.txt"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            />
                     </div>

                     <Button @click="uploadQuiz" :disabled="isUploading || !form.file" class="w-full">
                        <Upload class="w-4 h-4 mr-2" />
                        {{ isUploading ? 'Subiendo...' : 'Crear Quiz' }}
                     </Button>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
