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
        Schema::table('users', function (Blueprint $table) {
            // Add email verification token (expires in 24 hours)
            $table->string('email_verification_token')->nullable()->after('email_verified_at');
            
            // Track when verification email was sent
            $table->timestamp('verification_email_sent_at')->nullable()->after('email_verification_token');
            
            // Track verification attempts/resend count
            $table->integer('verification_attempts')->default(0)->after('verification_email_sent_at');
            
            // Index for fast token lookups
            $table->index('email_verification_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email_verification_token']);
            $table->dropColumn([
                'email_verification_token',
                'verification_email_sent_at', 
                'verification_attempts'
            ]);
        });
    }
};