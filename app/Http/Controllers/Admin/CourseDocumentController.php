<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCourseDocument;
use App\Models\Course;
use App\Models\CourseDocument;
use App\Services\DocumentProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CourseDocumentController extends Controller
{
    private DocumentProcessingService $documentProcessor;

    public function __construct(DocumentProcessingService $documentProcessor)
    {
        $this->documentProcessor = $documentProcessor;
    }

    /**
     * Display documents for a specific course
     */
    public function index(Course $course)
    {
        $documents = $course->documents()
            ->orderBy('created_at', 'desc')
            ->get();

        Log::info('Course documents fetched', [
            'course_id' => $course->id,
            'documents_count' => $documents->count(),
            'documents' => $documents->toArray()
        ]);

        return response()->json([
            'success' => true,
            'course' => $course,
            'documents' => $documents
        ]);
    }

    /**
     * Upload a document (or multiple documents) for a course.
     *
     * Supports:
     * - Single upload (backwards compatible with existing frontend)
     * - Bulk upload using "files[]" input
     */
    public function store(Request $request, Course $course)
    {
        try {
            // Bulk upload branch
            if ($request->hasFile('files')) {
                $request->validate([
                    'files' => 'required|array',
                    'files.*' => 'file|mimes:pdf,doc,docx,txt,md|max:10240',
                    'document_type' => 'required|string|in:study_material,past_exam,boe_extract,syllabus,practice_test',
                    'description' => 'nullable|string|max:1000',
                ]);

                $results = [];

                foreach ($request->file('files') as $file) {
                    try {
                        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                        $file->storeAs('course-documents', $filename, 'private');

                        $titleFromName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

                        $document = CourseDocument::create([
                            'course_id' => $course->id,
                            'title' => $titleFromName,
                            'filename' => $filename,
                            'original_filename' => $file->getClientOriginalName(),
                            'mime_type' => $file->getMimeType(),
                            'file_size' => $file->getSize(),
                            'description' => $request->description,
                            'document_type' => $request->document_type,
                            'metadata' => [
                                'uploaded_by' => auth()->id(),
                                'uploaded_at' => now()->toISOString(),
                                'processing_status' => 'queued',
                            ],
                        ]);

                        ProcessCourseDocument::dispatch($document->id);

                        $results[] = [
                            'original_filename' => $file->getClientOriginalName(),
                            'document' => $document,
                            'status' => 'queued',
                            'message' => 'Queued for processing',
                            'success' => true,
                        ];
                    } catch (\Exception $e) {
                        Log::error('Failed to upload one of the course documents (bulk)', [
                            'course_id' => $course->id,
                            'file_name' => $file->getClientOriginalName(),
                            'error' => $e->getMessage(),
                        ]);

                        $results[] = [
                            'original_filename' => $file->getClientOriginalName(),
                            'status' => 'failed',
                            'message' => 'Failed to queue document: ' . $e->getMessage(),
                            'success' => false,
                        ];
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Files uploaded. Each file is being processed in the background.',
                    'results' => $results,
                ]);
            }

            // Single upload branch (existing behaviour)
            $request->validate([
                'file' => 'required|file|mimes:pdf,doc,docx,txt,md|max:10240', // 10MB max
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'document_type' => 'required|string|in:study_material,past_exam,boe_extract,syllabus,practice_test',
            ]);

            $file = $request->file('file');

            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('course-documents', $filename, 'private');

            $document = CourseDocument::create([
                'course_id' => $course->id,
                'title' => $request->title,
                'filename' => $filename,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'description' => $request->description,
                'document_type' => $request->document_type,
                'metadata' => [
                    'uploaded_by' => auth()->id(),
                    'uploaded_at' => now()->toISOString(),
                    'processing_status' => 'queued',
                ],
            ]);

            ProcessCourseDocument::dispatch($document->id);

            return response()->json([
                'success' => true,
                'message' => '🎉 Document uploaded successfully! The system is now processing your document in the background.',
                'document' => $document,
                'processing_info' => [
                    'status' => 'Queued',
                    'next_steps' => 'The document will be chunked, embedded, and stored in the vector database for RAG functionality.',
                    'estimated_time' => 'Usually takes 30-60 seconds depending on document size',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upload course document', [
                'course_id' => $course->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '❌ Failed to upload document(s). Please try again or contact support if the issue persists.',
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a document
     */
    public function destroy(CourseDocument $document)
    {
        try {
            Log::info('Attempting to delete document', [
                'document_id' => $document->id,
                'filename' => $document->filename,
                'title' => $document->title
            ]);

            // Delete file from storage
            $filePath = 'course-documents/' . $document->filename;
            if (Storage::disk('private')->exists($filePath)) {
                Storage::disk('private')->delete($filePath);
                Log::info('File deleted from storage', ['file_path' => $filePath]);
            } else {
                Log::warning('File not found in storage', ['file_path' => $filePath]);
            }

            // Delete document record
            $document->delete();
            Log::info('Document record deleted', ['document_id' => $document->id]);

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete course document', [
                'document_id' => $document->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get document statistics for a course
     */
    public function stats(Course $course)
    {
        $stats = [
            'total_documents' => $course->documents()->count(),
            'processed_documents' => $course->documents()->processed()->count(),
            'total_chunks' => $course->documents()->processed()->sum('chunks_count'),
            'documents_by_type' => $course->documents()
                ->selectRaw('document_type, count(*) as count')
                ->groupBy('document_type')
                ->pluck('count', 'document_type')
                ->toArray(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }
}
