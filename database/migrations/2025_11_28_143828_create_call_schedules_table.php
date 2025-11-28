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
        Schema::create('call_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('admin_id')->nullable()->constrained('users', 'id')->onDelete('set null'); // Admin assigned to the call
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->timestamp('scheduled_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->nullable(); // in minutes
            $table->string('status', 50)->default('scheduled'); // scheduled, in_progress, completed, cancelled
            $table->string('meeting_link')->nullable(); // Link for VoIP or video call
            $table->string('meeting_id')->nullable(); // Meeting ID if using platform like Zoom
            $table->string('meeting_password')->nullable(); // Password for the meeting
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_schedules');
    }
};
