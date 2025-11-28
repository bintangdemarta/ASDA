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
        Schema::create('transaction_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('transaction_type', 100); // premium_payment, claim_disbursement, policy_change, etc.
            $table->string('reference_number', 50)->nullable();
            $table->foreignId('claim_request_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('insurance_policy_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('amount', 15, 2);
            $table->string('status', 50); // success, pending, failed, refunded
            $table->text('description')->nullable();
            $table->json('details')->nullable(); // Additional transaction details
            $table->timestamp('transaction_date');
            $table->string('payment_method')->nullable();
            $table->string('processed_by')->nullable(); // Who processed the transaction
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_history');
    }
};
