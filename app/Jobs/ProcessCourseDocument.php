<?php

namespace App\Jobs;

use App\Models\CourseDocument;
use App\Services\DocumentProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCourseDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $documentId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $documentId)
    {
        $this->documentId = $documentId;
    }

    /**
     * Execute the job.
     */
    public function handle(DocumentProcessingService $documentProcessor): void
    {
        $document = CourseDocument::with('course')->find($this->documentId);

        if (! $document) {
            Log::warning('ProcessCourseDocument job: document not found', [
                'document_id' => $this->documentId,
            ]);

            return;
        }

        try {
            $filePath = storage_path('app/private/course-documents/' . $document->filename);

            if (! file_exists($filePath)) {
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
                'uploaded_at' => $document->created_at?->toISOString(),
            ];

            $result = $documentProcessor->processCourseDocument(
                $content,
                $document->course->namespace,
                $metadata
            );

            if (! ($result['success'] ?? false)) {
                throw new \Exception($result['error'] ?? 'Unknown processing error');
            }

            $document->update([
                'is_processed' => true,
                'processed_at' => now(),
                'chunks_count' => $result['chunks_processed'] ?? 0,
                'metadata' => array_merge($document->metadata ?? [], [
                    'processing_status' => 'completed',
                    'processing_completed_at' => now()->toISOString(),
                ]),
            ]);

            Log::info('Course document processed successfully (queued job)', [
                'document_id' => $document->id,
                'course_namespace' => $document->course->namespace ?? null,
                'chunks_processed' => $result['chunks_processed'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process course document (queued job)', [
                'document_id' => $document->id ?? $this->documentId,
                'error' => $e->getMessage(),
            ]);

            $document->update([
                'is_processed' => false,
                'metadata' => array_merge($document->metadata ?? [], [
                    'processing_status' => 'failed',
                    'processing_error' => $e->getMessage(),
                    'processing_failed_at' => now()->toISOString(),
                ]),
            ]);
        }
    }

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
                return file_get_contents($filePath);
        }
    }

    private function extractPDFContent(string $filePath): string
    {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (empty($text)) {
            throw new \Exception('No text content found in PDF');
        }

        Log::info('PDF content extracted successfully (job)', [
            'file_path' => $filePath,
            'text_length' => strlen($text),
        ]);

        return $text;
    }

    private function extractWordContent(string $filePath): string
    {
        // Simple placeholder for Word extraction – same behaviour as controller
        return 'Word document content extraction not implemented yet. Please use text files for now.';
    }
}


