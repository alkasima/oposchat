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
        Schema::create('quiz_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->onDelete('cascade');
            $table->foreignId('quiz_question_id')->constrained()->onDelete('cascade');
            $table->integer('question_order')->default(0); // Order shown to user
            $table->char('selected_option', 1)->nullable(); // A, B, C, D
            $table->char('correct_option', 1); // Store for historical accuracy
            $table->boolean('is_correct')->default(false);
            $table->boolean('is_bookmarked')->default(false);
            $table->integer('time_spent_seconds')->nullable();
            $table->timestamps();
            
            // Index for quick lookup
            $table->index(['quiz_attempt_id', 'question_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_attempt_answers');
    }
};
