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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add columns to support scheduled plan changes
            $table->string('scheduled_plan_change_price_id')->nullable()->after('stripe_price_id');
            $table->timestamp('scheduled_plan_change_at')->nullable()->after('scheduled_plan_change_price_id');
            
            // Add index for scheduled changes
            $table->index('scheduled_plan_change_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['scheduled_plan_change_at']);
            $table->dropColumn(['scheduled_plan_change_price_id', 'scheduled_plan_change_at']);
        });
    }
};
