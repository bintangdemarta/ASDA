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
        Schema::table('users', function (Blueprint $table) {
            $table->string('asabri_member_number', 50)->unique()->nullable()->after('email');
            $table->string('tni_rank', 100)->nullable()->after('asabri_member_number');
            $table->string('tni_unit', 200)->nullable()->after('tni_rank');
            $table->string('tni_id_number', 50)->unique()->nullable()->after('tni_unit');
            $table->date('enrollment_date')->nullable()->after('tni_id_number');
            $table->boolean('is_verified')->default(false)->after('enrollment_date');

            // Make email nullable to allow for TNI members who might not have email
            $table->string('email', 100)->nullable(true)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'asabri_member_number',
                'tni_rank',
                'tni_unit',
                'tni_id_number',
                'enrollment_date',
                'is_verified'
            ]);

            // Revert email to non-nullable
            $table->string('email', 100)->nullable(false)->change();
        });
    }
};
