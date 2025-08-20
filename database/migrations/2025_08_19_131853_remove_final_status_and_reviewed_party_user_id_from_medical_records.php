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
            // Drop final_status_code field (if it exists) - drop foreign key and index first
            if (Schema::hasColumn('medical_records', 'final_status_code')) {
                // Drop foreign key constraint first
                $table->dropForeign(['final_status_code']);
                // Drop index
                $table->dropIndex(['final_status_code']);
                // Now drop the column
                $table->dropColumn('final_status_code');
            }
            
            // Drop reviewed_party_user_id field (if it exists) - drop foreign key first
            if (Schema::hasColumn('medical_records', 'reviewed_party_user_id')) {
                // Drop foreign key constraint first
                $table->dropForeign(['reviewed_party_user_id']);
                // Now drop the column
                $table->dropColumn('reviewed_party_user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            // Add back final_status_code field
            $table->string('final_status_code', 50)->nullable()->after('transfer_status_code');
            $table->index('final_status_code');
            $table->foreign('final_status_code')->references('code')->on('static_data');
            
            // Add back reviewed_party_user_id field
            $table->mediumInteger('reviewed_party_user_id')->nullable()->after('danger_level_code');
            $table->foreign('reviewed_party_user_id')->references('user_id')->on('users');
        });
    }
};
