<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_quiz_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->integer('total_quizzes_attempted')->default(0);
            $table->integer('total_quizzes_completed')->default(0);
            $table->integer('total_questions_answered')->default(0);
            $table->integer('total_correct_answers')->default(0);
            $table->decimal('overall_accuracy', 5, 2)->default(0); // Percentage
            $table->json('topic_performance')->nullable(); // {"algebra": {"correct": 10, "total": 15}, ...}
            $table->json('difficulty_performance')->nullable(); // {"easy": 0.9, "medium": 0.7, "hard": 0.5}
            $table->json('common_mistakes')->nullable(); // Question IDs frequently missed
            $table->json('strong_topics')->nullable(); // Topics with >80% accuracy
            $table->json('weak_topics')->nullable(); // Topics with <60% accuracy
            $table->decimal('average_time_per_question', 8, 2)->nullable(); // Seconds
            $table->timestamp('last_quiz_at')->nullable();
            $table->timestamps();
            
            // Ensure one stats record per user per course
            $table->unique(['user_id', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_quiz_statistics');
    }
};
