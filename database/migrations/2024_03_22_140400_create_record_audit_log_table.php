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
        Schema::create('record_audit_log', function (Blueprint $table) {
            $table->mediumInteger('audit_id')->autoIncrement()->primary();
            $table->mediumInteger('record_id');
            $table->mediumInteger('user_id');
            $table->string('action_type_code', 50);
            $table->text('action_description')->nullable();
            $table->string('old_value', 255)->nullable();
            $table->string('new_value', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes
            $table->index('record_id');
            $table->index('user_id');
            $table->index('action_type_code');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_audit_log');
    }
};
