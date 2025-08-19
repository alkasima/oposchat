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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_id')->nullable()->constrained()->onDelete('set null');
            $table->string('stripe_invoice_id')->unique();
            $table->integer('amount_paid');
            $table->string('currency', 3)->default('usd');
            $table->string('status', 50);
            $table->string('invoice_pdf', 500)->nullable();
            $table->string('hosted_invoice_url', 500)->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('stripe_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
