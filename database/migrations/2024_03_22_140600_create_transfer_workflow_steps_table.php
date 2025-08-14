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
        Schema::create('transfer_workflow_steps', function (Blueprint $table) {
            $table->mediumInteger('step_id')->autoIncrement()->primary();
            $table->mediumInteger('transfer_id');
            $table->string('step_name', 100);
            $table->string('step_status_code', 50)->default('pending');
            $table->text('step_notes')->nullable();
            $table->mediumInteger('completed_by')->nullable();
            $table->integer('step_order');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('transfer_id');
            $table->index('step_status_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_workflow_steps');
    }
};
