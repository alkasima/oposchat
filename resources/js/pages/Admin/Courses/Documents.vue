<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Upload, FileText, Trash2, Eye, Download, CheckCircle, XCircle, Clock } from 'lucide-vue-next';

const page = usePage();
const course = computed(() => page.props.course);
const documents = ref([]);
const stats = ref({});
const isLoading = ref(false);
const isUploading = ref(false);

// Upload form
const uploadForm = ref({
    file: null,
    title: '',
    description: '',
    document_type: 'study_material'
});

const documentTypes = [
    { value: 'study_material', label: 'Study Material' },
    { value: 'past_exam', label: 'Past Exam' },
    { value: 'boe_extract', label: 'BOE Extract' },
    { value: 'syllabus', label: 'Syllabus' },
    { value: 'practice_test', label: 'Practice Test' },
];

onMounted(() => {
    fetchDocuments();
    fetchStats();
});

const fetchDocuments = async () => {
    try {
        isLoading.value = true;
        const response = await fetch(`/admin/courses/${course.value.id}/documents/api`);
        const data = await response.json();
        
        if (data.success) {
            console.log('Documents fetched:', data.documents);
            documents.value = data.documents;
        } else {
            console.error('Failed to fetch documents:', data);
        }
    } catch (error) {
        console.error('Failed to fetch documents:', error);
    } finally {
        isLoading.value = false;
    }
};

const fetchStats = async () => {
    try {
        const response = await fetch(`/admin/courses/${course.value.id}/documents/stats`);
        const data = await response.json();
        
        if (data.success) {
            stats.value = data.stats;
        }
    } catch (error) {
        console.error('Failed to fetch stats:', error);
    }
};

const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
        uploadForm.value.file = file;
        if (!uploadForm.value.title) {
            uploadForm.value.title = file.name.replace(/\.[^/.]+$/, ""); // Remove extension
        }
    }
};

const refreshCSRFToken = async () => {
    try {
        const response = await fetch('/csrf-token', {
            method: 'GET',
            credentials: 'same-origin'
        });
        const data = await response.json();
        if (data.csrf_token) {
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                metaTag.setAttribute('content', data.csrf_token);
            }
            return data.csrf_token;
        }
    } catch (error) {
        console.error('Failed to refresh CSRF token:', error);
    }
    return null;
};

const uploadDocument = async () => {
    if (!uploadForm.value.file || !uploadForm.value.title) {
        alert('Please select a file and enter a title');
        return;
    }

    try {
        isUploading.value = true;
        
        // Refresh CSRF token before upload
        await refreshCSRFToken();
        
        const formData = new FormData();
        formData.append('file', uploadForm.value.file);
        formData.append('title', uploadForm.value.title);
        formData.append('description', uploadForm.value.description);
        formData.append('document_type', uploadForm.value.document_type);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        console.log('CSRF Token:', csrfToken);
        
        const response = await fetch(`/admin/courses/${course.value.id}/documents`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            credentials: 'same-origin',
            body: formData
        });

        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Upload failed:', response.status, errorText);
            throw new Error(`Upload failed: ${response.status} ${errorText}`);
        }
        
        const data = await response.json();

        if (data.success) {
            // Reset form
            uploadForm.value = {
                file: null,
                title: '',
                description: '',
                document_type: 'study_material'
            };
            
            // Refresh documents and stats
            await fetchDocuments();
            await fetchStats();
            
            alert('Document uploaded successfully!');
        } else {
            alert('Failed to upload document: ' + data.message);
        }
    } catch (error) {
        console.error('Upload error:', error);
        alert('Failed to upload document');
    } finally {
        isUploading.value = false;
    }
};

const deleteDocument = async (doc) => {
    if (!confirm(`Are you sure you want to delete "${doc.title}"?`)) {
        return;
    }

    try {
        console.log('Attempting to delete document:', doc);
        const response = await fetch(`/admin/course-documents/${doc.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        });

        console.log('Delete response status:', response.status);
        const data = await response.json();
        console.log('Delete response data:', data);

        if (data.success) {
            await fetchDocuments();
            await fetchStats();
            alert('Document deleted successfully!');
        } else {
            alert('Failed to delete document: ' + data.message);
        }
    } catch (error) {
        console.error('Delete error:', error);
        alert('Failed to delete document');
    }
};

const getDocumentTypeLabel = (type) => {
    const docType = documentTypes.find(dt => dt.value === type);
    return docType ? docType.label : type;
};

const getDocumentTypeColor = (type) => {
    const colors = {
        'study_material': 'bg-blue-100 text-blue-800',
        'past_exam': 'bg-green-100 text-green-800',
        'boe_extract': 'bg-purple-100 text-purple-800',
        'syllabus': 'bg-orange-100 text-orange-800',
        'practice_test': 'bg-red-100 text-red-800',
    };
    return colors[type] || 'bg-gray-100 text-gray-800';
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
</script>

<template>
    <AdminLayout title="Course Documents">
        <Head title="Course Documents" />
        
        <template #header>
            Documentos para {{ course.name }}
        </template>
        
        <template #subtitle>
            Gestionar documentos del curso y materiales de estudio
        </template>

        <div class="space-y-6">
            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center space-x-2">
                            <FileText class="w-5 h-5 text-blue-600" />
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Documents</p>
                                <p class="text-2xl font-bold">{{ stats.total_documents || 0 }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center space-x-2">
                            <CheckCircle class="w-5 h-5 text-green-600" />
                            <div>
                                <p class="text-sm font-medium text-gray-600">Processed</p>
                                <p class="text-2xl font-bold">{{ stats.processed_documents || 0 }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center space-x-2">
                            <FileText class="w-5 h-5 text-purple-600" />
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Chunks</p>
                                <p class="text-2xl font-bold">{{ stats.total_chunks || 0 }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center space-x-2">
                            <Clock class="w-5 h-5 text-orange-600" />
                            <div>
                                <p class="text-sm font-medium text-gray-600">Pending</p>
                                <p class="text-2xl font-bold">{{ (stats.total_documents || 0) - (stats.processed_documents || 0) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Upload Form -->
            <Card>
                <CardHeader>
                    <CardTitle>Upload New Document</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">File</label>
                            <input
                                type="file"
                                @change="handleFileSelect"
                                accept=".pdf,.doc,.docx,.txt,.md"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Document Type</label>
                            <select
                                v-model="uploadForm.document_type"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option v-for="type in documentTypes" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                        <Input
                            v-model="uploadForm.title"
                            placeholder="Enter document title"
                            required
                        />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
                        <Textarea
                            v-model="uploadForm.description"
                            placeholder="Enter document description"
                            rows="3"
                        />
                    </div>
                    
                    <Button
                        @click="uploadDocument"
                        :disabled="isUploading || !uploadForm.file || !uploadForm.title"
                        class="w-full"
                    >
                        <Upload class="w-4 h-4 mr-2" />
                        {{ isUploading ? 'Uploading...' : 'Upload Document' }}
                    </Button>
                </CardContent>
            </Card>

            <!-- Documents List -->
            <Card>
                <CardHeader>
                    <CardTitle>Course Documents</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="isLoading" class="text-center py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                        <p class="mt-2 text-gray-600">Loading documents...</p>
                    </div>
                    
                    <div v-else-if="documents.length === 0" class="text-center py-8">
                        <FileText class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                        <p class="text-gray-600">No documents uploaded yet</p>
                    </div>
                    
                    <div v-else class="space-y-4">
                        <div
                            v-for="document in documents"
                            :key="document.id"
                            class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50"
                        >
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <FileText class="w-8 h-8 text-blue-600" />
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-sm font-medium text-gray-900 truncate">
                                        {{ document.title }}
                                    </h3>
                                    <p class="text-sm text-gray-500 truncate">
                                        {{ document.original_filename }}
                                    </p>
                                    <div class="flex items-center space-x-2 mt-1">
                                        <Badge :class="getDocumentTypeColor(document.document_type)">
                                            {{ getDocumentTypeLabel(document.document_type) }}
                                        </Badge>
                                        <span class="text-xs text-gray-500">
                                            {{ formatFileSize(document.file_size) }}
                                        </span>
                                        <span v-if="document.chunks_count > 0" class="text-xs text-gray-500">
                                            {{ document.chunks_count }} chunks
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-2">
                                <div v-if="document.is_processed" class="flex items-center text-green-600">
                                    <CheckCircle class="w-4 h-4 mr-1" />
                                    <span class="text-xs">Processed</span>
                                </div>
                                <div v-else class="flex items-center text-orange-600">
                                    <Clock class="w-4 h-4 mr-1" />
                                    <span class="text-xs">Processing</span>
                                </div>
                                
                                <Button
                                    @click="deleteDocument(document)"
                                    variant="outline"
                                    size="sm"
                                    class="text-red-600 hover:text-red-700"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
