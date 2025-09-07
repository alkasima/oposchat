<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Course extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'exam_type',
        'icon',
        'color',
        'badge',
        'badge_color',
        'full_description',
        'is_active',
        'sort_order',
        'namespace',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($course) {
            if (empty($course->slug)) {
                $course->slug = Str::slug($course->name);
            }
            if (empty($course->namespace)) {
                $course->namespace = $course->slug ?? Str::slug($course->name);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }
}
