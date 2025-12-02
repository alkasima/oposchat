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
        // Add indexes to chats table for faster queries
        Schema::table('chats', function (Blueprint $table) {
            $table->index('user_id'); // For user's chat list
            $table->index(['user_id', 'created_at']); // For ordered chat list
            $table->index('course_id'); // For course filtering
            $table->index(['user_id', 'course_id']); // For user's course chats
        });

        // Add indexes to messages table for faster chat history
        Schema::table('messages', function (Blueprint $table) {
            $table->index('chat_id'); // For chat messages
            $table->index(['chat_id', 'created_at']); // For ordered messages
            $table->index('role'); // For filtering by role
        });

        // Add indexes to courses table
        Schema::table('courses', function (Blueprint $table) {
            $table->index('is_active'); // For active courses
            $table->index(['is_active', 'name']); // For active course list
        });

        // Add indexes to course_documents table
        Schema::table('course_documents', function (Blueprint $table) {
            $table->index('course_id'); // For course documents
            $table->index(['course_id', 'created_at']); // For ordered documents
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['course_id']);
            $table->dropIndex(['user_id', 'course_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['chat_id']);
            $table->dropIndex(['chat_id', 'created_at']);
            $table->dropIndex(['role']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['is_active', 'name']);
        });

        Schema::table('course_documents', function (Blueprint $table) {
            $table->dropIndex(['course_id']);
            $table->dropIndex(['course_id', 'created_at']);
        });
    }
};
