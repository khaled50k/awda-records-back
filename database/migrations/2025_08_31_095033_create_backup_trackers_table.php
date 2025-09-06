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
        Schema::create('backup_trackers', function (Blueprint $table) {
            $table->id();
            $table->string('table_name')->unique();
            $table->timestamp('last_backup_at')->nullable();
            $table->unsignedBigInteger('last_record_id')->nullable();
            $table->enum('backup_status', ['pending', 'completed', 'failed', 'in_progress'])->default('pending');
            $table->unsignedInteger('records_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['table_name', 'last_backup_at']);
            $table->index('backup_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_trackers');
    }
};
