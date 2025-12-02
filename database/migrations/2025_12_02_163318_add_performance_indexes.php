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
        $connection = Schema::getConnection();
        $sm = $connection->getDoctrineSchemaManager();

        // ---------------- CHATS TABLE ----------------
        $existingIndexes = array_map(fn($idx) => $idx->getName(), $sm->listTableIndexes('chats'));
        Schema::table('chats', function (Blueprint $table) use ($existingIndexes) {
            if (!in_array('chats_user_id_index', $existingIndexes)) {
                $table->index('user_id');
            }
            if (!in_array('chats_user_id_created_at_index', $existingIndexes)) {
                $table->index(['user_id', 'created_at']);
            }
            if (!in_array('chats_course_id_index', $existingIndexes)) {
                $table->index('course_id');
            }
            if (!in_array('chats_user_id_course_id_index', $existingIndexes)) {
                $table->index(['user_id', 'course_id']);
            }
        });

        // ---------------- MESSAGES TABLE ----------------
        $existingIndexes = array_map(fn($idx) => $idx->getName(), $sm->listTableIndexes('messages'));
        Schema::table('messages', function (Blueprint $table) use ($existingIndexes) {
            if (!in_array('messages_chat_id_index', $existingIndexes)) {
                $table->index('chat_id');
            }
            if (!in_array('messages_chat_id_created_at_index', $existingIndexes)) {
                $table->index(['chat_id', 'created_at']);
            }
            if (!in_array('messages_role_index', $existingIndexes)) {
                $table->index('role');
            }
        });

        // ---------------- COURSES TABLE ----------------
        $existingIndexes = array_map(fn($idx) => $idx->getName(), $sm->listTableIndexes('courses'));
        Schema::table('courses', function (Blueprint $table) use ($existingIndexes) {
            if (!in_array('courses_is_active_index', $existingIndexes)) {
                $table->index('is_active');
            }
            if (!in_array('courses_is_active_name_index', $existingIndexes)) {
                $table->index(['is_active', 'name']);
            }
        });

        // ---------------- COURSE_DOCUMENTS TABLE ----------------
        $existingIndexes = array_map(fn($idx) => $idx->getName(), $sm->listTableIndexes('course_documents'));
        Schema::table('course_documents', function (Blueprint $table) use ($existingIndexes) {
            if (!in_array('course_documents_course_id_index', $existingIndexes)) {
                $table->index('course_id');
            }
            if (!in_array('course_documents_course_id_created_at_index', $existingIndexes)) {
                $table->index(['course_id', 'created_at']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = Schema::getConnection();
        $sm = $connection->getDoctrineSchemaManager();

        // ---------------- CHATS TABLE ----------------
        $existingIndexes = array_map(fn($idx) => $idx->getName(), $sm->listTableIndexes('chats'));
        Schema::table('chats', function (Blueprint $table) use ($existingIndexes) {
            if (in_array('chats_user_id_index', $existingIndexes)) {
                $table->dropIndex(['user_id']);
            }
            if (in_array('chats_user_id_created_at_index', $existingIndexes)) {
                $table->dropIndex(['user_id', 'created_at']);
            }
            if (in_array('chats_course_id_index', $existingIndexes)) {
                $table->dropIndex(['course_id']);
            }
            if (in_array('chats_user_id_course_id_index', $existingIndexes)) {
                $table->dropIndex(['user_id', 'course_id']);
            }
        });

        // ---------------- MESSAGES TABLE ----------------
        $existingIndexes = array_map(fn($idx) => $idx->getName(), $sm->listTableIndexes('messages'));
        Schema::table('messages', function (Blueprint $table) use ($existingIndexes) {
            if (in_array('messages_chat_id_index', $existingIndexes)) {
                $table->dropIndex(['chat_id']);
            }
            if (in_array('messages_chat_id_created_at_index', $existingIndexes)) {
                $table->dropIndex(['chat_id', 'created_at']);
            }
            if (in_array('messages_role_index', $existingIndexes)) {
                $table->dropIndex(['role']);
            }
        });

        // ---------------- COURSES TABLE ----------------
        $existingIndexes = array_map(fn($idx) => $idx->getName(), $sm->listTableIndexes('courses'));
        Schema::table('courses', function (Blueprint $table) use ($existingIndexes) {
            if (in_array('courses_is_active_index', $existingIndexes)) {
                $table->dropIndex(['is_active']);
            }
            if (in_array('courses_is_active_name_index', $existingIndexes)) {
                $table->dropIndex(['is_active', 'name']);
            }
        });

        // ---------------- COURSE_DOCUMENTS TABLE ----------------
        $existingIndexes = array_map(fn($idx) => $idx->getName(), $sm->listTableIndexes('course_documents'));
        Schema::table('course_documents', function (Blueprint $table) use ($existingIndexes) {
            if (in_array('course_documents_course_id_index', $existingIndexes)) {
                $table->dropIndex(['course_id']);
            }
            if (in_array('course_documents_course_id_created_at_index', $existingIndexes)) {
                $table->dropIndex(['course_id', 'created_at']);
            }
        });
    }
};
