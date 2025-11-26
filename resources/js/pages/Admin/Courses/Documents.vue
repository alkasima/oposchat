<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
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

// Upload form (still supports single-file title if you only pick one)
const uploadForm = ref({
    file: null,
    title: '',
    description: '',
    document_type: 'study_material'
});

// Local selection & per-file upload state
const selectedFiles = ref<File[]>([]);
const uploadItems = ref<any[]>([]);

const documentTypes = [
    { value: 'study_material', label: 'Study Material' },
    { value: 'past_exam', label: 'Past Exam' },
    { value: 'boe_extract', label: 'BOE Extract' },
    { value: 'syllabus', label: 'Syllabus' },
    { value: 'practice_test', label: 'Practice Test' },
];

let pollingInterval: number | null = null;

onMounted(() => {
    fetchDocuments();
    fetchStats();

    // Poll periodically so admin can leave and return to see updated status
    pollingInterval = window.setInterval(() => {
        fetchDocuments(false);
        fetchStats();
    }, 10000);
});

onUnmounted(() => {
    if (pollingInterval) {
        window.clearInterval(pollingInterval);
    }
});

const fetchDocuments = async (withLoading: boolean = true) => {
    try {
        if (withLoading) {
            isLoading.value = true;
        }
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
        if (withLoading) {
            isLoading.value = false;
        }
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
    const files: File[] = Array.from(event.target.files || []);
    selectedFiles.value = files;

    uploadItems.value = files.map((file) => ({
        id: `${file.name}-${file.size}-${file.lastModified}-${Math.random().toString(36).slice(2)}`,
        file,
        name: file.name,
        size: file.size,
        status: 'pending', // pending | uploading | queued | processing | completed | failed
        progress: 0,
        message: '',
        documentId: null,
    }));

    if (files.length === 1) {
        uploadForm.value.file = files[0];
        if (!uploadForm.value.title) {
            uploadForm.value.title = files[0].name.replace(/\.[^/.]+$/, ""); // Remove extension
        }
    } else {
        uploadForm.value.file = null;
        uploadForm.value.title = '';
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
    if (!selectedFiles.value.length) {
        alert('Please select at least one file');
        return;
    }

    try {
        isUploading.value = true;

        // mark all as uploading
        uploadItems.value = uploadItems.value.map(item => ({
            ...item,
            status: 'uploading',
            progress: 25,
            message: 'Uploading...',
        }));

        // Refresh CSRF token before upload
        await refreshCSRFToken();

        const formData = new FormData();
        selectedFiles.value.forEach((file) => {
            formData.append('files[]', file);
        });
        formData.append('document_type', uploadForm.value.document_type);
        formData.append('description', uploadForm.value.description || '');

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
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Upload failed:', response.status, errorText);
            throw new Error(`Upload failed: ${response.status} ${errorText}`);
        }
        
        const data = await response.json();

        if (data.success && Array.isArray(data.results)) {
            uploadItems.value = uploadItems.value.map(item => {
                const match = data.results.find((r: any) => r.original_filename === item.name);
                if (!match) return item;

                const isSuccess = !!match.success;

                return {
                    ...item,
                    status: isSuccess ? 'queued' : 'failed',
                    progress: isSuccess ? 50 : 100,
                    message: match.message || (isSuccess ? 'Queued for processing' : 'Failed to queue'),
                    documentId: match.document?.id ?? null,
                };
            });

            await fetchDocuments();
            await fetchStats();

            alert('Files uploaded. They are now being processed in the background.');
        } else {
            alert('Failed to upload documents: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Upload error:', error);
        uploadItems.value = uploadItems.value.map(item => ({
            ...item,
            status: 'failed',
            progress: 100,
            message: 'Upload failed',
        }));
        alert('Failed to upload documents');
    } finally {
        isUploading.value = false;
    }
};

const getProcessingStatus = (document) => {
    if (document.is_processed) {
        return 'completed';
    }
    if (document.metadata && document.metadata.processing_status === 'failed') {
        return 'failed';
    }
    if (document.metadata && document.metadata.processing_status === 'queued') {
        return 'queued';
    }
    return 'processing';
};

const getStatusLabel = (status: string) => {
    switch (status) {
        case 'pending':
            return 'Pending';
        case 'uploading':
            return 'Uploading';
        case 'queued':
            return 'Queued';
        case 'processing':
            return 'Processing';
        case 'completed':
            return 'Completed';
        case 'failed':
            return 'Failed';
        default:
            return status;
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
                                multiple
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
                            placeholder="Enter document title (used when a single file is selected)"
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
                        :disabled="isUploading || !selectedFiles.length"
                        class="w-full"
                    >
                        <Upload class="w-4 h-4 mr-2" />
                        {{ isUploading ? 'Uploading...' : 'Upload Documents' }}
                    </Button>

                    <!-- Per-file upload status -->
                    <div v-if="uploadItems.length" class="space-y-2 mt-4">
                        <div
                            v-for="item in uploadItems"
                            :key="item.id"
                            class="border border-gray-200 rounded-md p-2"
                        >
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-medium truncate">{{ item.name }}</span>
                                <span class="text-xs text-gray-500">{{ getStatusLabel(item.status) }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                                <div
                                    class="h-2 rounded-full transition-all duration-300"
                                    :class="{
                                        'bg-blue-500': item.status === 'uploading' || item.status === 'queued' || item.status === 'processing',
                                        'bg-green-500': item.status === 'completed',
                                        'bg-red-500': item.status === 'failed',
                                        'bg-gray-400': item.status === 'pending',
                                    }"
                                    :style="{ width: (item.progress || 0) + '%' }"
                                ></div>
                            </div>
                            <p v-if="item.message" class="mt-1 text-xs text-gray-500">
                                {{ item.message }}
                            </p>
                        </div>
                    </div>
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
                            
                            <div class="flex items-center space-x-4">
                                <div class="flex flex-col items-end text-xs">
                                    <div v-if="getProcessingStatus(document) === 'completed'" class="flex items-center text-green-600">
                                        <CheckCircle class="w-4 h-4 mr-1" />
                                        <span>Processed</span>
                                    </div>
                                    <div v-else-if="getProcessingStatus(document) === 'failed'" class="flex items-center text-red-600">
                                        <XCircle class="w-4 h-4 mr-1" />
                                        <span>Failed</span>
                                    </div>
                                    <div v-else-if="getProcessingStatus(document) === 'queued'" class="flex items-center text-blue-600">
                                        <Clock class="w-4 h-4 mr-1" />
                                        <span>Queued</span>
                                    </div>
                                    <div v-else class="flex items-center text-orange-600">
                                        <Clock class="w-4 h-4 mr-1" />
                                        <span>Processing</span>
                                    </div>
                                    <span
                                        v-if="document.metadata && document.metadata.processing_error"
                                        class="mt-1 text-red-500 max-w-xs text-right"
                                    >
                                        {{ document.metadata.processing_error }}
                                    </span>
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
