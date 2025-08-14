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
        Schema::create('record_access_log', function (Blueprint $table) {
            $table->mediumInteger('access_id')->autoIncrement()->primary();
            $table->mediumInteger('patient_id');
            $table->mediumInteger('user_id');
            $table->string('access_type_code', 50);
            $table->timestamp('accessed_at')->useCurrent();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('patient_id');
            $table->index('user_id');
            $table->index('access_type_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_access_log');
    }
};
