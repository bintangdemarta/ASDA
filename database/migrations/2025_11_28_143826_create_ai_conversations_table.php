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
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('user_input');
            $table->text('ai_response');
            $table->string('intent', 100)->nullable(); // Intent classification
            $table->json('context')->nullable(); // Store conversation context
            $table->string('status', 50)->default('completed'); // completed, escalated, pending
            $table->timestamp('escalated_at')->nullable(); // When escalated to admin
            $table->foreignId('escalated_to_admin_id')->nullable()->constrained('users')->onDelete('set null'); // Admin assigned to handle
            $table->text('resolution')->nullable(); // Resolution when escalated
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
