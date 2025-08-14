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
        Schema::create('record_transfers', function (Blueprint $table) {
            $table->mediumInteger('transfer_id')->autoIncrement()->primary();
            $table->mediumInteger('record_id');
            $table->mediumInteger('sender_id');
            $table->mediumInteger('recipient_id');
            $table->text('transfer_notes');
            $table->timestamps();
            
            // Indexes
            $table->index('record_id');
            $table->index('sender_id');
            $table->index('recipient_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_transfers');
    }
};
