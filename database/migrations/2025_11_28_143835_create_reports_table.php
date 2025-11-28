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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // User who generated the report (if any)
            $table->string('title', 200);
            $table->string('type', 100); // claim_summary, consultation_report, transaction_summary, etc.
            $table->string('format', 20)->default('pdf'); // pdf, excel, csv, etc.
            $table->text('description')->nullable();
            $table->json('filters')->nullable(); // Report filters used
            $table->string('status', 50)->default('generated'); // draft, generated, sent, etc.
            $table->string('file_path')->nullable(); // Path to the generated report file
            $table->string('file_name')->nullable(); // Original name of the report file
            $table->decimal('file_size', 10, 2)->nullable(); // File size in MB
            $table->timestamp('generated_at')->nullable(); // When the report was generated
            $table->timestamp('sent_at')->nullable(); // When the report was sent to user
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
