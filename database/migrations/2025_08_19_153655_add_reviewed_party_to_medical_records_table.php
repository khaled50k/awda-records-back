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
        Schema::table('medical_records', function (Blueprint $table) {
            // Add reviewed_party field as string(50) - this is the name/description of who reviewed
            $table->string('reviewed_party', 50)->nullable()->after('danger_level_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            // Drop the reviewed_party column
            $table->dropColumn('reviewed_party');
        });
    }
};
