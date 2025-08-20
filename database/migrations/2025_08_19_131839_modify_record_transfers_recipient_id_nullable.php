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
        Schema::table('record_transfers', function (Blueprint $table) {
            // Drop the existing foreign key constraint first
            $table->dropForeign(['recipient_id']);
            
            // Now modify the column to be nullable
            $table->mediumInteger('recipient_id')->nullable()->change();
            
            // Recreate the foreign key constraint with the same name
            $table->foreign('recipient_id')->references('user_id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('record_transfers', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['recipient_id']);
            
            // Revert the column to not nullable
            $table->mediumInteger('recipient_id')->change();
            
            // Recreate the foreign key constraint
            $table->foreign('recipient_id')->references('user_id')->on('users');
        });
    }
};
