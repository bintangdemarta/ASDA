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
        Schema::create('claim_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('insurance_policy_id')->constrained()->onDelete('cascade');
            $table->string('claim_number', 50)->unique();
            $table->string('claim_type', 100);
            $table->string('status', 50)->default('pending');
            $table->decimal('claim_amount', 15, 2);
            $table->text('reason')->nullable();
            $table->text('description')->nullable();
            $table->json('documents')->nullable(); // JSON array of document paths
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->string('reviewer_notes')->nullable();
            $table->string('admin_id')->nullable(); // ID of admin who reviewed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_requests');
    }
};
