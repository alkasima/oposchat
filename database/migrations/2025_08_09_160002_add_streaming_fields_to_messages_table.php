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
        Schema::table('messages', function (Blueprint $table) {
            $table->uuid('streaming_session_id')->nullable()->after('metadata');
            $table->boolean('is_streaming')->default(false)->after('streaming_session_id');
            $table->timestamp('stream_completed_at')->nullable()->after('is_streaming');
            
            $table->foreign('streaming_session_id')->references('id')->on('streaming_sessions')->onDelete('set null');
            $table->index('streaming_session_id');
            $table->index(['is_streaming', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['streaming_session_id']);
            $table->dropIndex(['streaming_session_id']);
            $table->dropIndex(['is_streaming', 'created_at']);
            $table->dropColumn(['streaming_session_id', 'is_streaming', 'stream_completed_at']);
        });
    }
};
