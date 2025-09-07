<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\DocumentProcessingService;
use App\Services\VectorStoreService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class CourseContentController extends Controller
{
    public function __construct(
        private DocumentProcessingService $documentProcessor,
        private VectorStoreService $vectorStore
    ) {}

    /**
     * Show course content management page
     */
    public function index()
    {
        $courses = Course::active()->ordered()->get();
        return inertia('Admin/CourseContent/Index', [
            'courses' => $courses
        ]);
    }

    /**
     * Upload and process course content
     */
    public function uploadContent(Request $request): JsonResponse
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'content' => 'required|string|min:100',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $course = Course::findOrFail($request->course_id);
            
            // Prepare metadata
            $metadata = [
                'title' => $request->title ?? 'Uploaded Content',
                'description' => $request->description ?? '',
                'uploaded_at' => now()->toISOString(),
                'course_name' => $course->name,
            ];

            // Process the document
            $result = $this->documentProcessor->processDocument(
                $request->content,
                $course->namespace,
                $metadata
            );

            if ($result['success']) {
                Log::info('Course content uploaded successfully', [
                    'course_id' => $course->id,
                    'course_namespace' => $course->namespace,
                    'chunks_processed' => $result['chunks_processed']
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Content uploaded and processed successfully',
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process content: ' . $result['error']
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Course content upload failed', [
                'course_id' => $request->course_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload file content
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'file' => 'required|file|mimes:txt,pdf,doc,docx,md|max:10240', // 10MB max
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $course = Course::findOrFail($request->course_id);
            $file = $request->file('file');
            
            // Extract content from file
            $content = $this->extractFileContent($file);
            
            if (empty($content)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not extract content from file'
                ], 400);
            }

            // Prepare metadata
            $metadata = [
                'title' => $request->title ?? $file->getClientOriginalName(),
                'description' => $request->description ?? '',
                'filename' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_at' => now()->toISOString(),
                'course_name' => $course->name,
            ];

            // Process the document
            $result = $this->documentProcessor->processDocument(
                $content,
                $course->namespace,
                $metadata
            );

            if ($result['success']) {
                Log::info('Course file uploaded successfully', [
                    'course_id' => $course->id,
                    'course_namespace' => $course->namespace,
                    'filename' => $file->getClientOriginalName(),
                    'chunks_processed' => $result['chunks_processed']
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'File uploaded and processed successfully',
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process file: ' . $result['error']
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Course file upload failed', [
                'course_id' => $request->course_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete course content
     */
    public function deleteContent(Request $request): JsonResponse
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        try {
            $course = Course::findOrFail($request->course_id);
            
            $result = $this->documentProcessor->deleteCourseContent($course->namespace);

            if ($result['success']) {
                Log::info('Course content deleted successfully', [
                    'course_id' => $course->id,
                    'course_namespace' => $course->namespace
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Course content deleted successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete content: ' . $result['error']
                ], 500);
            }

        } catch (Exception $e) {
            Log::error('Course content deletion failed', [
                'course_id' => $request->course_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Deletion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get course content stats
     */
    public function getContentStats(Request $request): JsonResponse
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        try {
            $course = Course::findOrFail($request->course_id);
            
            // Get vector store stats
            $stats = $this->vectorStore->getStats();
            $connectionTest = $this->vectorStore->testConnection();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'course_id' => $course->id,
                    'course_name' => $course->name,
                    'namespace' => $course->namespace,
                    'storage_type' => $this->vectorStore->getStorageType(),
                    'storage_stats' => $stats,
                    'connection_status' => $connectionTest,
                    'status' => 'Content management available'
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get stats: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract content from uploaded file
     */
    private function extractFileContent($file): string
    {
        $mimeType = $file->getMimeType();
        $content = '';

        switch ($mimeType) {
            case 'text/plain':
            case 'text/markdown':
                $content = file_get_contents($file->getPathname());
                break;

            case 'application/pdf':
                $content = $this->extractPdfContent($file);
                break;

            case 'application/msword':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                $content = $this->extractWordContent($file);
                break;

            default:
                // Try to read as text
                $content = file_get_contents($file->getPathname());
                break;
        }

        return $content;
    }

    /**
     * Extract content from PDF
     */
    private function extractPdfContent($file): string
    {
        // Simple PDF text extraction - in production you'd use a proper PDF library
        try {
            $content = file_get_contents($file->getPathname());
            // Basic PDF text extraction (this is simplified)
            $content = preg_replace('/[^\w\s\.\,\!\?\;\:\-\(\)\[\]\"\']/', ' ', $content);
            return trim($content);
        } catch (Exception $e) {
            Log::error('PDF extraction failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Extract content from Word document
     */
    private function extractWordContent($file): string
    {
        // Simple Word document extraction - in production you'd use a proper library
        try {
            $content = file_get_contents($file->getPathname());
            // Basic Word document text extraction (this is simplified)
            $content = preg_replace('/[^\w\s\.\,\!\?\;\:\-\(\)\[\]\"\']/', ' ', $content);
            return trim($content);
        } catch (Exception $e) {
            Log::error('Word document extraction failed', ['error' => $e->getMessage()]);
            return '';
        }
    }
}
