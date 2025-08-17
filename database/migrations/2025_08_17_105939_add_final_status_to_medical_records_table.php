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
            // Add final status code field (references static_data)
            $table->string('final_status_code', 50)->nullable()->after('reviewed_party_user_id');
            
            // Add index for better performance
            $table->index('final_status_code');
            
            // Add foreign key constraint
            $table->foreign('final_status_code')->references('code')->on('static_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['final_status_code']);
            
            // Drop index
            $table->dropIndex(['final_status_code']);
            
            // Drop column
            $table->dropColumn('final_status_code');
        });
    }
};
