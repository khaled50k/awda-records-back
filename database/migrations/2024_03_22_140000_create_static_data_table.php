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
        Schema::create('static_data', function (Blueprint $table) {
            $table->tinyInteger('id')->autoIncrement()->primary();
            $table->string('type', 50)->comment('Category: status, role, action_type, hire_type, permission_group, document_type, priority_level, gender, health_center_type, transfer_status, workflow_step_status, access_type');
            $table->string('code', 50)->comment('Machine-readable unique identifier');
            $table->string('label_en', 100)->comment('English display name');
            $table->string('label_ar', 100)->comment('Arabic display name');
            $table->text('description')->nullable()->comment('Detailed description of this data item');
            $table->boolean('is_active')->default(true)->comment('1: active, 0: inactive');
            $table->json('metadata')->nullable()->comment('Additional configuration data');
            $table->timestamps();
            
            // Unique constraint and indexes
            $table->unique(['type', 'code']);
            $table->index('type');
            $table->index('is_active');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('static_data');
    }
};
