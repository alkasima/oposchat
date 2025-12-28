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
        Schema::table('chats', function (Blueprint $table) {
            // Index for fetching user's chats ordered by updated_at
            $table->index(['user_id', 'updated_at'], 'chats_user_updated_idx');
            
            // Index for fetching chats by last_message_at
            $table->index(['user_id', 'last_message_at'], 'chats_user_last_message_idx');
        });

        Schema::table('messages', function (Blueprint $table) {
            // Index for fetching latest message per chat
            $table->index(['chat_id', 'created_at'], 'messages_chat_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropIndex('chats_user_updated_idx');
            $table->dropIndex('chats_user_last_message_idx');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_chat_created_idx');
        });
    }
};
