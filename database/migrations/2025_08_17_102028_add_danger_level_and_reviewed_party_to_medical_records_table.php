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
            // Add danger level code field (references static_data)
            $table->string('danger_level_code', 50)->nullable()->after('problem_type_code');
            
            // Add reviewed party field (references users table - the user who reviewed this record)
            $table->mediumInteger('reviewed_party_user_id')->nullable()->after('danger_level_code');
            
            // Add indexes for better performance
            $table->index('danger_level_code');
            $table->index('reviewed_party_user_id');
            
            // Add foreign key constraints
            $table->foreign('danger_level_code')->references('code')->on('static_data');
            $table->foreign('reviewed_party_user_id')->references('user_id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['danger_level_code']);
            $table->dropForeign(['reviewed_party_user_id']);
            
            // Drop indexes
            $table->dropIndex(['danger_level_code']);
            $table->dropIndex(['reviewed_party_user_id']);
            
            // Drop columns
            $table->dropColumn('danger_level_code');
            $table->dropColumn('reviewed_party_user_id');
        });
    }
};
