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
            // Drop the foreign key constraint first
            $table->dropForeign(['health_center_code']);
            
            // Drop the index
            $table->dropIndex(['health_center_code']);
            
            // Drop the column
            $table->dropColumn('health_center_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            // Re-add the column
            $table->string('health_center_code', 50)->after('patient_id');
            
            // Re-add the index
            $table->index('health_center_code');
            
            // Re-add the foreign key constraint
            $table->foreign('health_center_code')->references('code')->on('static_data');
        });
    }
};
