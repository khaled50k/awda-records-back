<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class TransferWorkflowStep extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transfer_workflow_steps';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'step_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transfer_id',
        'step_name',
        'step_status_code',
        'step_notes',
        'completed_by',
        'step_order',
        'started_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'transfer_id' => 'integer',
        'completed_by' => 'integer',
        'step_order' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the record transfer associated with this workflow step.
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(RecordTransfer::class, 'transfer_id', 'transfer_id');
    }

    /**
     * Get the user who completed this workflow step.
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by', 'user_id');
    }

    /**
     * Get the step status information.
     */
    public function stepStatus(): BelongsTo
    {
        return $this->belongsTo(StaticData::class, 'step_status_code', 'code')
            ->where('type', 'workflow_step_status');
    }

    /**
     * Scope a query to only include workflow steps for a specific transfer.
     */
    public function scopeForTransfer(Builder $query, int $transferId): void
    {
        $query->where('transfer_id', $transferId);
    }

    /**
     * Scope a query to only include workflow steps with a specific status.
     */
    public function scopeWithStatus(Builder $query, string $statusCode): void
    {
        $query->where('step_status_code', $statusCode);
    }

    /**
     * Scope a query to only include workflow steps completed by a specific user.
     */
    public function scopeCompletedBy(Builder $query, int $userId): void
    {
        $query->where('completed_by', $userId);
    }

    /**
     * Scope a query to only include pending workflow steps.
     */
    public function scopePending(Builder $query): void
    {
        $query->where('step_status_code', 'pending');
    }

    /**
     * Scope a query to only include completed workflow steps.
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('step_status_code', 'completed');
    }

    /**
     * Scope a query to only include in-progress workflow steps.
     */
    public function scopeInProgress(Builder $query): void
    {
        $query->where('step_status_code', 'in_progress');
    }

    /**
     * Scope a query to only include failed workflow steps.
     */
    public function scopeFailed(Builder $query): void
    {
        $query->where('step_status_code', 'failed');
    }

    /**
     * Scope a query to only include skipped workflow steps.
     */
    public function scopeSkipped(Builder $query): void
    {
        $query->where('step_status_code', 'skipped');
    }

    /**
     * Scope a query to order workflow steps by their order.
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('step_order');
    }

    /**
     * Get the step status label.
     */
    public function getStepStatusLabelAttribute(): string
    {
        return $this->stepStatus?->label_en ?? 'Unknown';
    }

    /**
     * Get the step status label in Arabic.
     */
    public function getStepStatusLabelArAttribute(): string
    {
        return $this->stepStatus?->label_ar ?? 'غير محدد';
    }

    /**
     * Get the user's display name who completed this step.
     */
    public function getCompletedByDisplayNameAttribute(): string
    {
        return $this->completedBy?->display_name ?? 'Unknown User';
    }

    /**
     * Check if this workflow step is pending.
     */
    public function isPending(): bool
    {
        return $this->step_status_code === 'pending';
    }

    /**
     * Check if this workflow step is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->step_status_code === 'in_progress';
    }

    /**
     * Check if this workflow step is completed.
     */
    public function isCompleted(): bool
    {
        return $this->step_status_code === 'completed';
    }

    /**
     * Check if this workflow step is skipped.
     */
    public function isSkipped(): bool
    {
        return $this->step_status_code === 'skipped';
    }

    /**
     * Check if this workflow step is failed.
     */
    public function isFailed(): bool
    {
        return $this->step_status_code === 'failed';
    }

    /**
     * Check if this workflow step has been started.
     */
    public function hasStarted(): bool
    {
        return !is_null($this->started_at);
    }

    /**
     * Check if this workflow step has been completed.
     */
    public function hasBeenCompleted(): bool
    {
        return !is_null($this->completed_at);
    }

    /**
     * Mark the workflow step as started.
     */
    public function markAsStarted(): void
    {
        $this->update([
            'step_status_code' => 'in_progress',
            'started_at' => now()
        ]);
    }

    /**
     * Mark the workflow step as completed.
     */
    public function markAsCompleted(int $completedByUserId, ?string $notes = null): void
    {
        $this->update([
            'step_status_code' => 'completed',
            'completed_by' => $completedByUserId,
            'completed_at' => now(),
            'step_notes' => $notes
        ]);
    }

    /**
     * Mark the workflow step as failed.
     */
    public function markAsFailed(?string $notes = null): void
    {
        $this->update([
            'step_status_code' => 'failed',
            'step_notes' => $notes
        ]);
    }

    /**
     * Mark the workflow step as skipped.
     */
    public function markAsSkipped(?string $notes = null): void
    {
        $this->update([
            'step_status_code' => 'skipped',
            'step_notes' => $notes
        ]);
    }

    /**
     * Get the step duration in minutes.
     */
    public function getStepDurationAttribute(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endDate = $this->completed_at ?? now();
        return $this->started_at->diffInMinutes($endDate);
    }

    /**
     * Get the step duration in hours.
     */
    public function getStepDurationHoursAttribute(): ?float
    {
        $duration = $this->stepDuration;
        return $duration ? round($duration / 60, 2) : null;
    }

    /**
     * Get the step duration in days.
     */
    public function getStepDurationDaysAttribute(): ?float
    {
        $duration = $this->stepDuration;
        return $duration ? round($duration / 1440, 2) : null;
    }

    /**
     * Check if this is the first step in the workflow.
     */
    public function isFirstStep(): bool
    {
        return $this->step_order === 1;
    }

    /**
     * Check if this is the last step in the workflow.
     */
    public function isLastStep(): bool
    {
        // This would need to be implemented based on the total number of steps
        // For now, we'll assume it's the last step if it has the highest order
        return $this->transfer->workflowSteps()->max('step_order') === $this->step_order;
    }

    /**
     * Get the next step in the workflow.
     */
    public function getNextStepAttribute()
    {
        return $this->transfer->workflowSteps()
            ->where('step_order', '>', $this->step_order)
            ->orderBy('step_order')
            ->first();
    }

    /**
     * Get the previous step in the workflow.
     */
    public function getPreviousStepAttribute()
    {
        return $this->transfer->workflowSteps()
            ->where('step_order', '<', $this->step_order)
            ->orderByDesc('step_order')
            ->first();
    }
}
