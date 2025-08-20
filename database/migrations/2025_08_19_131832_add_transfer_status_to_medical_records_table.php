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
            // Add transfer status field (references static_data)
            $table->string('transfer_status_code', 50)->nullable()->after('status_code');
            
            // Add index for transfer status
            $table->index('transfer_status_code');
        });

        // Add foreign key constraint after the column is created
        Schema::table('medical_records', function (Blueprint $table) {
            $table->foreign('transfer_status_code')->references('code')->on('static_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['transfer_status_code']);
            
            // Drop index
            $table->dropIndex(['transfer_status_code']);
            
            // Drop column
            $table->dropColumn('transfer_status_code');
        });
    }
};
