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
        // Add foreign key constraints to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_code')->references('code')->on('static_data');

        });

        // Add foreign key constraints to medical_records table
        Schema::table('medical_records', function (Blueprint $table) {
            $table->foreign('patient_id')->references('patient_id')->on('patients')->onDelete('cascade');
            $table->foreign('health_center_code')->references('code')->on('static_data');
            $table->foreign('status_code')->references('code')->on('static_data');
            $table->foreign('created_by')->references('user_id')->on('users');
            $table->foreign('last_modified_by')->references('user_id')->on('users');
        });

        // Add foreign key constraints to record_transfers table
        Schema::table('record_transfers', function (Blueprint $table) {
            $table->foreign('record_id')->references('record_id')->on('medical_records')->onDelete('cascade');
            $table->foreign('sender_id')->references('user_id')->on('users');
            $table->foreign('recipient_id')->references('user_id')->on('users');
        });

        // Add foreign key constraints to record_audit_log table
        Schema::table('record_audit_log', function (Blueprint $table) {
            $table->foreign('record_id')->references('record_id')->on('medical_records')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('users');
            $table->foreign('action_type_code')->references('code')->on('static_data');
        });

        // Add foreign key constraints to record_access_log table
        Schema::table('record_access_log', function (Blueprint $table) {
            $table->foreign('patient_id')->references('patient_id')->on('patients')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('users');
            $table->foreign('access_type_code')->references('code')->on('static_data');
        });

        // Add foreign key constraints to transfer_workflow_steps table
        Schema::table('transfer_workflow_steps', function (Blueprint $table) {
            $table->foreign('transfer_id')->references('transfer_id')->on('record_transfers')->onDelete('cascade');
            $table->foreign('completed_by')->references('user_id')->on('users');
            $table->foreign('step_status_code')->references('code')->on('static_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key constraints from transfer_workflow_steps table
        Schema::table('transfer_workflow_steps', function (Blueprint $table) {
            $table->dropForeign(['transfer_id']);
            $table->dropForeign(['completed_by']);
            $table->dropForeign(['step_status_code']);
        });

        // Drop foreign key constraints from record_access_log table
        Schema::table('record_access_log', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['access_type_code']);
        });

        // Drop foreign key constraints from record_audit_log table
        Schema::table('record_audit_log', function (Blueprint $table) {
            $table->dropForeign(['record_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['action_type_code']);
        });

        // Drop foreign key constraints from record_transfers table
        Schema::table('record_transfers', function (Blueprint $table) {
            $table->dropForeign(['record_id']);
            $table->dropForeign(['sender_id']);
            $table->dropForeign(['recipient_id']);
        });

        // Drop foreign key constraints from medical_records table
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->dropForeign(['health_center_code']);
            $table->dropForeign(['status_code']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['last_modified_by']);
        });

        // Drop foreign key constraints from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_code']);

        });
    }
};
