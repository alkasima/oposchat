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
            // Rename existing columns to match our design
            $table->renameColumn('stripe_id', 'stripe_subscription_id');
            $table->renameColumn('stripe_status', 'status');
            $table->renameColumn('stripe_price', 'stripe_price_id');
            
            // Add new required columns
            $table->string('stripe_customer_id')->after('stripe_subscription_id');
            $table->timestamp('current_period_start')->nullable()->after('stripe_price_id');
            $table->timestamp('current_period_end')->nullable()->after('current_period_start');
            $table->timestamp('trial_start')->nullable()->after('current_period_end');
            $table->timestamp('trial_end')->nullable()->after('trial_start');
            $table->boolean('cancel_at_period_end')->default(false)->after('trial_end');
            $table->timestamp('canceled_at')->nullable()->after('cancel_at_period_end');
            
            // Drop columns we don't need
            $table->dropColumn(['type', 'quantity', 'trial_ends_at', 'ends_at']);
            
            // Add indexes
            $table->index('stripe_subscription_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['stripe_subscription_id']);
            $table->dropIndex(['status']);
            
            // Add back dropped columns
            $table->string('type')->after('user_id');
            $table->integer('quantity')->nullable()->after('stripe_price_id');
            $table->timestamp('trial_ends_at')->nullable()->after('quantity');
            $table->timestamp('ends_at')->nullable()->after('trial_ends_at');
            
            // Drop new columns
            $table->dropColumn([
                'stripe_customer_id',
                'current_period_start',
                'current_period_end',
                'trial_start',
                'trial_end',
                'cancel_at_period_end',
                'canceled_at'
            ]);
            
            // Rename columns back
            $table->renameColumn('stripe_subscription_id', 'stripe_id');
            $table->renameColumn('status', 'stripe_status');
            $table->renameColumn('stripe_price_id', 'stripe_price');
        });
    }
};
