<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseDocument;
use App\Services\DocumentProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
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
     * Upload a document for a course
     */
    public function store(Request $request, Course $course)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,txt,md|max:10240', // 10MB max
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'document_type' => 'required|string|in:study_material,past_exam,boe_extract,syllabus,practice_test',
        ]);

        try {
            $file = $request->file('file');
            
            // Generate unique filename
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('course-documents', $filename, 'private');

            // Create document record
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
                ]
            ]);

            // Process document in background (you might want to use a queue for this)
            $this->processDocument($document);

            return response()->json([
                'success' => true,
                'message' => '🎉 Document uploaded successfully! The system is now processing your document to make it searchable. This may take a few moments.',
                'document' => $document,
                'processing_info' => [
                    'status' => 'Processing',
                    'next_steps' => 'The document will be chunked, embedded, and stored in the vector database for RAG functionality.',
                    'estimated_time' => 'Usually takes 30-60 seconds depending on document size'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to upload course document', [
                'course_id' => $course->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '❌ Failed to upload document. Please try again or contact support if the issue persists.',
                'error_details' => $e->getMessage(),
                'suggestions' => [
                    'Check if the file is not corrupted',
                    'Ensure the file size is under 10MB',
                    'Try uploading a different file format (PDF, DOC, TXT, MD)',
                    'Contact support if the problem continues'
                ]
            ], 500);
        }
    }

    /**
     * Process a document for RAG
     */
    private function processDocument(CourseDocument $document)
    {
        try {
            // Get file content
            $filePath = storage_path('app/private/course-documents/' . $document->filename);
            
            if (!file_exists($filePath)) {
                throw new \Exception('File not found');
            }

            // Extract content based on file type
            $content = $this->extractFileContent($filePath, $document->mime_type);
            
            if (empty($content)) {
                throw new \Exception('Could not extract content from file');
            }

            // Prepare metadata
            $metadata = [
                'document_id' => $document->id,
                'title' => $document->title,
                'document_type' => $document->document_type,
                'original_filename' => $document->original_filename,
                'uploaded_at' => $document->created_at->toISOString(),
            ];

            // Process the document
            $result = $this->documentProcessor->processCourseDocument(
                $content,
                $document->course->namespace,
                $metadata
            );

            if ($result['success']) {
                // Update document record
                $document->update([
                    'is_processed' => true,
                    'processed_at' => now(),
                    'chunks_count' => $result['chunks_processed'],
                ]);

                Log::info('Course document processed successfully', [
                    'document_id' => $document->id,
                    'course_namespace' => $document->course->namespace,
                    'chunks_processed' => $result['chunks_processed']
                ]);
            } else {
                throw new \Exception($result['error'] ?? 'Unknown processing error');
            }

        } catch (\Exception $e) {
            Log::error('Failed to process course document', [
                'document_id' => $document->id,
                'error' => $e->getMessage()
            ]);

            // Update document record to reflect failure
            $document->update([
                'is_processed' => false,
                'metadata' => array_merge($document->metadata ?? [], [
                    'processing_error' => $e->getMessage(),
                    'processing_failed_at' => now()->toISOString(),
                ])
            ]);
        }
    }

    /**
     * Extract content from file
     */
    private function extractFileContent(string $filePath, string $mimeType): string
    {
        switch ($mimeType) {
            case 'application/pdf':
                return $this->extractPDFContent($filePath);
            case 'text/plain':
            case 'text/markdown':
                return file_get_contents($filePath);
            case 'application/msword':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                return $this->extractWordContent($filePath);
            default:
                // Try to read as text
                return file_get_contents($filePath);
        }
    }

    /**
     * Extract content from PDF
     */
    private function extractPDFContent(string $filePath): string
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            
            // Clean up the text
            $text = preg_replace('/\s+/', ' ', $text); // Replace multiple whitespace with single space
            $text = trim($text);
            
            if (empty($text)) {
                throw new \Exception('No text content found in PDF');
            }
            
            Log::info('PDF content extracted successfully', [
                'file_path' => $filePath,
                'text_length' => strlen($text)
            ]);
            
            return $text;
        } catch (\Exception $e) {
            Log::error('Failed to extract PDF content', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to extract PDF content: ' . $e->getMessage());
        }
    }

    /**
     * Extract content from Word document
     */
    private function extractWordContent(string $filePath): string
    {
        // You might want to use a proper Word document library
        // For now, we'll use a simple approach
        try {
            // This is a placeholder - you should implement proper Word document text extraction
            return "Word document content extraction not implemented yet. Please use text files for now.";
        } catch (\Exception $e) {
            throw new \Exception('Failed to extract Word content: ' . $e->getMessage());
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
