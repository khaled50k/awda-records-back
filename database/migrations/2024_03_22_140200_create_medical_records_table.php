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
        Schema::create('medical_records', function (Blueprint $table) {
            $table->mediumInteger('record_id')->autoIncrement()->primary();
            $table->mediumInteger('patient_id');
            $table->string('health_center_code', 50);
            $table->string('status_code', 50)->default('initiated');
            $table->string('problem_type_code', 50);
            $table->mediumInteger('created_by');
            $table->mediumInteger('last_modified_by');
            $table->timestamps();
            
            // Indexes
            $table->index('patient_id');
            $table->index('health_center_code');
            $table->index('status_code');
            $table->index('problem_type_code');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
