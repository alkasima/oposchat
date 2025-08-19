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
        Schema::table('users', function (Blueprint $table) {
            // Check if stripe_id column exists before trying to copy data
            if (Schema::hasColumn('users', 'stripe_id')) {
                // Copy data from stripe_id to stripe_customer_id if needed
                DB::statement('UPDATE users SET stripe_customer_id = stripe_id WHERE stripe_customer_id IS NULL AND stripe_id IS NOT NULL');
                
                // Drop the old stripe_id column and its index
                $table->dropIndex(['stripe_id']);
                $table->dropColumn('stripe_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add back the stripe_id column
            $table->string('stripe_id')->nullable()->index()->after('stripe_customer_id');
            
            // Copy data back
            DB::statement('UPDATE users SET stripe_id = stripe_customer_id WHERE stripe_id IS NULL AND stripe_customer_id IS NOT NULL');
        });
    }
};
