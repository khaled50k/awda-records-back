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
        Schema::create('patients', function (Blueprint $table) {
            $table->mediumInteger('patient_id')->autoIncrement()->primary();
            $table->string('full_name');
            $table->integer('national_id')->unique();
            $table->string('gender_code', 50)->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('full_name');
            $table->index('national_id');
            $table->index('gender_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
