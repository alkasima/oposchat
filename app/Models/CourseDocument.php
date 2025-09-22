<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CourseDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'filename',
        'original_filename',
        'mime_type',
        'file_size',
        'description',
        'document_type',
        'chunks_count',
        'metadata',
        'is_processed',
        'processed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_processed' => 'boolean',
        'processed_at' => 'datetime',
        'file_size' => 'integer',
        'chunks_count' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getDocumentTypeLabelAttribute(): string
    {
        return match($this->document_type) {
            'study_material' => 'Study Material',
            'past_exam' => 'Past Exam',
            'boe_extract' => 'BOE Extract',
            'syllabus' => 'Syllabus',
            'practice_test' => 'Practice Test',
            default => ucfirst(str_replace('_', ' ', $this->document_type))
        };
    }

    public function scopeProcessed($query)
    {
        return $query->where('is_processed', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('document_type', $type);
    }
}
