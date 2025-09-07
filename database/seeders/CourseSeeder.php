<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Course;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $courses = [
            [
                'name' => 'SAT Preparation',
                'slug' => 'sat-preparation',
                'description' => 'College Admission Test',
                'exam_type' => 'sat',
                'icon' => '📊',
                'color' => 'from-blue-400 to-blue-600',
                'badge' => 'POPULAR',
                'badge_color' => 'bg-blue-500',
                'full_description' => 'Comprehensive SAT preparation with AI-powered practice tests and personalized study plans.',
                'is_active' => true,
                'sort_order' => 1,
                'namespace' => 'sat-preparation',
            ],
            [
                'name' => 'GRE Preparation',
                'slug' => 'gre-preparation',
                'description' => 'Graduate School Admission',
                'exam_type' => 'gre',
                'icon' => '🎓',
                'color' => 'from-purple-400 to-purple-600',
                'badge' => 'ADVANCED',
                'badge_color' => 'bg-purple-500',
                'full_description' => 'Advanced GRE preparation with adaptive learning technology and expert guidance.',
                'is_active' => true,
                'sort_order' => 2,
                'namespace' => 'gre-preparation',
            ],
            [
                'name' => 'GMAT Preparation',
                'slug' => 'gmat-preparation',
                'description' => 'Business School Admission',
                'exam_type' => 'gmat',
                'icon' => '💼',
                'color' => 'from-green-400 to-green-600',
                'badge' => 'BUSINESS',
                'badge_color' => 'bg-green-500',
                'full_description' => 'Strategic GMAT preparation focused on business school admission requirements.',
                'is_active' => true,
                'sort_order' => 3,
                'namespace' => 'gmat-preparation',
            ],
            [
                'name' => 'Custom Preparation',
                'slug' => 'custom-preparation',
                'description' => 'Personalized Learning',
                'exam_type' => 'custom',
                'icon' => '💬',
                'color' => 'from-orange-400 to-orange-600',
                'badge' => 'CUSTOM',
                'badge_color' => 'bg-orange-500',
                'full_description' => 'Contact us for suggestions about specific exams you want to prepare for.',
                'is_active' => true,
                'sort_order' => 4,
                'namespace' => 'custom-preparation',
            ],
        ];

        foreach ($courses as $courseData) {
            Course::updateOrCreate(
                ['slug' => $courseData['slug']],
                $courseData
            );
        }
    }
}