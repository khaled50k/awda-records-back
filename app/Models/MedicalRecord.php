<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class MedicalRecord extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'medical_records';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'record_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'patient_id',
        'health_center_code',
        'status_code',
        'problem_type_code',
        'created_by',
        'last_modified_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'patient_id' => 'integer',
        'created_by' => 'integer',
        'health_center_code' => 'string',
        'last_modified_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    /**
     * Get the patient associated with this medical record.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }



    /**
     * Get the health center type information.
     */
    public function healthCenter(): BelongsTo
    {
        return $this->belongsTo(StaticData::class, 'health_center_code', 'code')
            ->where('type', 'health_center_type');
    }

    /**
     * Get the last modified by user information.
     */
    public function lastModifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by', 'user_id');
    }

    /**
     * Get the current status information.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(StaticData::class, 'status_code', 'code')
            ->where('type', 'status');
    }

    /**
     * Get the problem type information.
     */
    public function problemType(): BelongsTo
    {
        return $this->belongsTo(StaticData::class, 'problem_type_code', 'code')
            ->where('type', 'problem_type');
    }

    /**
     * Get the user who created this medical record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /**
     * Get the user who last modified this medical record.
     */
    public function lastModifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by', 'user_id');
    }

    /**
     * Get all transfers for this medical record.
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(RecordTransfer::class, 'record_id', 'record_id');
    }

    /**
     * Get all audit log entries for this medical record.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(RecordAuditLog::class, 'record_id', 'record_id');
    }

    /**
     * Scope a query to only include medical records with a specific status.
     */
    public function scopeWithStatus(Builder $query, string $statusCode): void
    {
        $query->where('status_code', $statusCode);
    }

    /**
     * Scope a query to only include medical records from a specific health center type.
     */
    public function scopeFromHealthCenter(Builder $query, string $healthCenterCode): void
    {
        $query->where('health_center_code', $healthCenterCode);
    }

    /**
     * Scope a query to only include medical records with a specific problem type.
     */
    public function scopeWithProblemType(Builder $query, string $problemTypeCode): void
    {
        $query->where('problem_type_code', $problemTypeCode);
    }

    /**
     * Scope a query to only include medical records created by a specific user.
     */
    public function scopeCreatedBy(Builder $query, int $userId): void
    {
        $query->where('created_by', $userId);
    }

    /**
     * Scope a query to only include medical records for a specific patient.
     */
    public function scopeForPatient(Builder $query, int $patientId): void
    {
        $query->where('patient_id', $patientId);
    }

    /**
     * Scope a query to only include medical records created within a date range.
     */
    public function scopeCreatedBetween(Builder $query, string $fromDate, string $toDate): void
    {
        $query->whereBetween('created_at', [$fromDate, $toDate]);
    }

    /**
     * Scope a query to only include medical records modified within a date range.
     */
    public function scopeModifiedBetween(Builder $query, string $fromDate, string $toDate): void
    {
        $query->whereBetween('updated_at', [$fromDate, $toDate]);
    }

    /**
     * Scope a query to only include medical records created on a specific date.
     */
    public function scopeCreatedOn(Builder $query, string $date): void
    {
        $query->whereDate('created_at', $date);
    }

    /**
     * Scope a query to only include medical records modified on a specific date.
     */
    public function scopeModifiedOn(Builder $query, string $date): void
    {
        $query->whereDate('updated_at', $date);
    }

    /**
     * Scope a query to only include medical records with multiple statuses.
     */
    public function scopeWithStatuses(Builder $query, array $statusCodes): void
    {
        $query->whereIn('status_code', $statusCodes);
    }

    /**
     * Scope a query to only include medical records from multiple health centers.
     */
    public function scopeFromHealthCenters(Builder $query, array $healthCenterCodes): void
    {
        $query->whereIn('health_center_code', $healthCenterCodes);
    }

    /**
     * Scope a query to only include medical records with multiple problem types.
     */
    public function scopeWithProblemTypes(Builder $query, array $problemTypeCodes): void
    {
        $query->whereIn('problem_type_code', $problemTypeCodes);
    }

    /**
     * Scope a query to only include medical records that have transfers.
     */
    public function scopeHasTransfers(Builder $query): void
    {
        $query->whereHas('transfers');
    }

    /**
     * Scope a query to only include medical records that don't have transfers.
     */
    public function scopeNoTransfers(Builder $query): void
    {
        $query->whereDoesntHave('transfers');
    }

    /**
     * Scope a query to only include medical records with transfers to a specific user.
     */
    public function scopeTransferredTo(Builder $query, int $userId): void
    {
        $query->whereHas('transfers', function($q) use ($userId) {
            $q->where('recipient_id', $userId);
        });
    }

    /**
     * Scope a query to only include medical records with transfers from a specific user.
     */
    public function scopeTransferredFrom(Builder $query, int $userId): void
    {
        $query->whereHas('transfers', function($q) use ($userId) {
            $q->where('sender_id', $userId);
        });
    }

    /**
     * Scope a query to only include medical records with transfers within a date range.
     */
    public function scopeTransferredBetween(Builder $query, string $fromDate, string $toDate): void
    {
        $query->whereHas('transfers', function($q) use ($fromDate, $toDate) {
            $q->whereBetween('created_at', [$fromDate, $toDate]);
        });
    }

    /**
     * Scope a query to only include medical records with completed workflow steps.
     */
    public function scopeWithCompletedWorkflow(Builder $query): void
    {
        $query->whereHas('transfers.workflowSteps', function($q) {
            $q->whereNotNull('completed_at');
        });
    }

    /**
     * Scope a query to only include medical records with pending workflow steps.
     */
    public function scopeWithPendingWorkflow(Builder $query): void
    {
        $query->whereHas('transfers.workflowSteps', function($q) {
            $q->whereNull('completed_at');
        });
    }

    /**
     * Scope a query to search medical records by patient name or national ID.
     */
    public function scopeSearchByPatient(Builder $query, string $searchTerm): void
    {
        $query->whereHas('patient', function($q) use ($searchTerm) {
            $q->where('full_name', 'like', '%' . $searchTerm . '%')
              ->orWhere('national_id', 'like', '%' . $searchTerm . '%');
        });
    }

    /**
     * Scope a query to search medical records by transfer notes.
     */
    public function scopeSearchByTransferNotes(Builder $query, string $searchTerm): void
    {
        $query->whereHas('transfers', function($q) use ($searchTerm) {
            $q->where('transfer_notes', 'like', '%' . $searchTerm . '%');
        });
    }

    /**
     * Scope a query to include medical records with all related data for comprehensive view.
     */
    public function scopeWithFullDetails(Builder $query): void
    {
        $query->with([
            'patient',
            'healthCenter',
            'status',
            'problemType',
            'creator',
            'lastModifier',
            'transfers.recipient',
            'transfers.sender',
            'transfers.workflowSteps',
            'auditLogs.user',
            'auditLogs.actionType'
        ]);
    }

    /**
     * Get the health center type label.
     */
    public function getHealthCenterTypeLabelAttribute(): string
    {
        return $this->healthCenter?->label_en ?? 'Unknown';
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->status?->label_en ?? 'Unknown';
    }

    /**
     * Get the status label in Arabic.
     */
    public function getStatusLabelArAttribute(): string
    {
        return $this->status?->label_ar ?? 'غير محدد';
    }

    /**
     * Check if the medical record has a specific status.
     */
    public function hasStatus(string $statusCode): bool
    {
        return $this->status_code === $statusCode;
    }

    /**
     * Check if the medical record is completed.
     */
    public function isCompleted(): bool
    {
        return $this->hasStatus('completed');
    }

    /**
     * Check if the medical record is pending review.
     */
    public function isPendingReview(): bool
    {
        return $this->hasStatus('pending_review');
    }

    /**
     * Check if the medical record is under consultation.
     */
    public function isUnderConsultation(): bool
    {
        return $this->hasStatus('under_consultation');
    }

    /**
     * Check if the medical record is rejected.
     */
    public function isRejected(): bool
    {
        return $this->hasStatus('rejected');
    }

    /**
     * Check if the medical record is archived.
     */
    public function isArchived(): bool
    {
        return $this->hasStatus('archived');
    }

    /**
     * Get the total number of transfers for this medical record.
     */
    public function getTransfersCountAttribute(): int
    {
        return $this->transfers()->count();
    }

    /**
     * Get the total number of audit log entries for this medical record.
     */
    public function getAuditLogsCountAttribute(): int
    {
        return $this->auditLogs()->count();
    }

    /**
     * Get the latest transfer for this medical record.
     */
    public function getLatestTransferAttribute()
    {
        return $this->transfers()->latest('created_at')->first();
    }
}
