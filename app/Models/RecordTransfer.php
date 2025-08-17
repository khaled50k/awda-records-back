<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;


class RecordTransfer extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'record_transfers';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'transfer_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'record_id',
        'sender_id',
        'recipient_id',
        'transfer_notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'record_id' => 'integer',
        'sender_id' => 'integer',
        'recipient_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the medical record associated with this transfer.
     */
    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class, 'record_id', 'record_id');
    }

 

    /**
     * Get the user who sent this transfer.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id', 'user_id');
    }

    /**
     * Get the user who received this transfer.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id', 'user_id');
    }

    /**
     * Get all workflow steps for this transfer.
     */
    public function workflowSteps(): HasMany
    {
        return $this->hasMany(TransferWorkflowStep::class, 'transfer_id', 'transfer_id');
    }

    /**
     * Scope a query to only include transfers for a specific medical record.
     */
    public function scopeForRecord(Builder $query, int $recordId): void
    {
        $query->where('record_id', $recordId);
    }

    /**
     * Scope a query to only include transfers sent by a specific user.
     */
    public function scopeSentBy(Builder $query, int $userId): void
    {
        $query->where('sender_id', $userId);
    }

    /**
     * Scope a query to only include transfers received by a specific user.
     */
    public function scopeReceivedBy(Builder $query, int $userId): void
    {
        $query->where('recipient_id', $userId);
    }

    /**
     * Scope a query to only include transfers within a date range.
     */
    public function scopeBetweenDates(Builder $query, string $fromDate, string $toDate): void
    {
        $query->whereBetween('created_at', [$fromDate, $toDate]);
    }

    /**
     * Scope a query to only include transfers on a specific date.
     */
    public function scopeOnDate(Builder $query, string $date): void
    {
        $query->whereDate('created_at', $date);
    }

    /**
     * Scope a query to only include transfers with notes containing specific text.
     */
    public function scopeWithNotesContaining(Builder $query, string $searchTerm): void
    {
        $query->where('transfer_notes', 'like', '%' . $searchTerm . '%');
    }

    /**
     * Scope a query to only include transfers for medical records with specific status.
     */
    public function scopeForRecordsWithStatus(Builder $query, string $statusCode): void
    {
        $query->whereHas('medicalRecord', function($q) use ($statusCode) {
            $q->where('status_code', $statusCode);
        });
    }

    /**
     * Scope a query to only include transfers for medical records from specific health center.
     */
    public function scopeForRecordsFromHealthCenter(Builder $query, string $healthCenterCode): void
    {
        $query->whereHas('medicalRecord.patient', function($q) use ($healthCenterCode) {
            $q->where('health_center_code', $healthCenterCode);
        });
    }

    /**
     * Scope a query to only include transfers for medical records with specific problem type.
     */
    public function scopeForRecordsWithProblemType(Builder $query, string $problemTypeCode): void
    {
        $query->whereHas('medicalRecord', function($q) use ($problemTypeCode) {
            $q->where('problem_type_code', $problemTypeCode);
        });
    }

    /**
     * Scope a query to only include transfers with completed workflow steps.
     */
    public function scopeWithCompletedWorkflow(Builder $query): void
    {
        $query->whereHas('workflowSteps', function($q) {
            $q->whereNotNull('completed_at');
        });
    }

    /**
     * Scope a query to only include transfers with pending workflow steps.
     */
    public function scopeWithPendingWorkflow(Builder $query): void
    {
        $query->whereHas('workflowSteps', function($q) {
            $q->whereNull('completed_at');
        });
    }

    /**
     * Scope a query to only include transfers with specific workflow step status.
     */
    public function scopeWithWorkflowStepStatus(Builder $query, string $stepStatusCode): void
    {
        $query->whereHas('workflowSteps', function($q) use ($stepStatusCode) {
            $q->where('step_status_code', $stepStatusCode);
        });
    }

    /**
     * Scope a query to include transfers with all related data.
     */
    public function scopeWithFullDetails(Builder $query): void
    {
        $query->with([
            'medicalRecord.patient.healthCenter',
            'medicalRecord.status',
            'medicalRecord.problemType',
            'sender',
            'recipient',
            'workflowSteps'
        ]);
    }

    /**
     * Check if the transfer is pending.
     */
    public function isPending(): bool
    {
        return true; // All transfers are considered pending since we removed status tracking
    }

    /**
     * Get the total number of workflow steps for this transfer.
     */
    public function getWorkflowStepsCountAttribute(): int
    {
        return $this->workflowSteps()->count();
    }

    /**
     * Get the completed workflow steps count for this transfer.
     */
    public function getCompletedWorkflowStepsCountAttribute(): int
    {
        return $this->workflowSteps()->whereNotNull('completed_at')->count();
    }

    /**
     * Get the pending workflow steps count for this transfer.
     */
    public function getPendingWorkflowStepsCountAttribute(): int
    {
        return $this->workflowSteps()->whereNull('completed_at')->count();
    }
}
