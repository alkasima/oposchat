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
        Schema::table('subscription_items', function (Blueprint $table) {
            // Rename existing columns to match our design
            $table->renameColumn('stripe_id', 'stripe_subscription_item_id');
            $table->renameColumn('stripe_price', 'stripe_price_id');
            
            // Drop columns we don't need
            $table->dropColumn('stripe_product');
            
            // Ensure quantity has default value
            $table->integer('quantity')->default(1)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            // Add back dropped column
            $table->string('stripe_product')->after('stripe_subscription_item_id');
            
            // Rename columns back
            $table->renameColumn('stripe_subscription_item_id', 'stripe_id');
            $table->renameColumn('stripe_price_id', 'stripe_price');
            
            // Remove default from quantity
            $table->integer('quantity')->nullable()->change();
        });
    }
};
