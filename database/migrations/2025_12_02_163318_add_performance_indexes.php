<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes to chats table for faster queries
        Schema::table('chats', function (Blueprint $table) {
            if (!$this->indexExists('chats', 'chats_user_id_index')) {
                $table->index('user_id');
            }
            if (!$this->indexExists('chats', 'chats_user_id_created_at_index')) {
                $table->index(['user_id', 'created_at']);
            }
            if (!$this->indexExists('chats', 'chats_course_id_index')) {
                $table->index('course_id');
            }
            if (!$this->indexExists('chats', 'chats_user_id_course_id_index')) {
                $table->index(['user_id', 'course_id']);
            }
        });

        // Add indexes to messages table for faster chat history
        Schema::table('messages', function (Blueprint $table) {
            if (!$this->indexExists('messages', 'messages_chat_id_index')) {
                $table->index('chat_id');
            }
            if (!$this->indexExists('messages', 'messages_chat_id_created_at_index')) {
                $table->index(['chat_id', 'created_at']);
            }
            if (!$this->indexExists('messages', 'messages_role_index')) {
                $table->index('role');
            }
        });

        // Add indexes to courses table
        Schema::table('courses', function (Blueprint $table) {
            if (!$this->indexExists('courses', 'courses_is_active_index')) {
                $table->index('is_active');
            }
            if (!$this->indexExists('courses', 'courses_is_active_name_index')) {
                $table->index(['is_active', 'name']);
            }
        });

        // Add indexes to course_documents table
        Schema::table('course_documents', function (Blueprint $table) {
            if (!$this->indexExists('course_documents', 'course_documents_course_id_index')) {
                $table->index('course_id');
            }
            if (!$this->indexExists('course_documents', 'course_documents_course_id_created_at_index')) {
                $table->index(['course_id', 'created_at']);
            }
        });
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        
        $result = DB::select(
            "SELECT COUNT(*) as count FROM information_schema.statistics 
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$database, $table, $index]
        );
        
        return $result[0]->count > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            if ($this->indexExists('chats', 'chats_user_id_index')) {
                $table->dropIndex(['user_id']);
            }
            if ($this->indexExists('chats', 'chats_user_id_created_at_index')) {
                $table->dropIndex(['user_id', 'created_at']);
            }
            if ($this->indexExists('chats', 'chats_course_id_index')) {
                $table->dropIndex(['course_id']);
            }
            if ($this->indexExists('chats', 'chats_user_id_course_id_index')) {
                $table->dropIndex(['user_id', 'course_id']);
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            if ($this->indexExists('messages', 'messages_chat_id_index')) {
                $table->dropIndex(['chat_id']);
            }
            if ($this->indexExists('messages', 'messages_chat_id_created_at_index')) {
                $table->dropIndex(['chat_id', 'created_at']);
            }
            if ($this->indexExists('messages', 'messages_role_index')) {
                $table->dropIndex(['role']);
            }
        });

        Schema::table('courses', function (Blueprint $table) {
            if ($this->indexExists('courses', 'courses_is_active_index')) {
                $table->dropIndex(['is_active']);
            }
            if ($this->indexExists('courses', 'courses_is_active_name_index')) {
                $table->dropIndex(['is_active', 'name']);
            }
        });

        Schema::table('course_documents', function (Blueprint $table) {
            if ($this->indexExists('course_documents', 'course_documents_course_id_index')) {
                $table->dropIndex(['course_id']);
            }
            if ($this->indexExists('course_documents', 'course_documents_course_id_created_at_index')) {
                $table->dropIndex(['course_id', 'created_at']);
            }
        });
    }
};
