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
        Schema::create('insurance_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('policy_number', 50)->unique();
            $table->string('policy_type', 100);
            $table->string('status', 50)->default('active');
            $table->decimal('premium_amount', 15, 2);
            $table->decimal('coverage_amount', 15, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->json('beneficiaries')->nullable(); // JSON for multiple beneficiaries
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_policies');
    }
};
