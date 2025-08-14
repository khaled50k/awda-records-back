<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class RecordAuditLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'record_audit_log';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'audit_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'record_id',
        'user_id',
        'action_type_code',
        'action_description',
        'old_value',
        'new_value',
        'notes',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'record_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get the medical record associated with this audit log entry.
     */
    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class, 'record_id', 'record_id');
    }

    /**
     * Get the user who performed this action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get the action type information.
     */
    public function actionType(): BelongsTo
    {
        return $this->belongsTo(StaticData::class, 'action_type_code', 'code')
            ->where('type', 'action_type');
    }

    /**
     * Scope a query to only include audit logs for a specific medical record.
     */
    public function scopeForRecord(Builder $query, int $recordId): void
    {
        $query->where('record_id', $recordId);
    }

    /**
     * Scope a query to only include audit logs by a specific user.
     */
    public function scopeByUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include audit logs of a specific action type.
     */
    public function scopeWithActionType(Builder $query, string $actionTypeCode): void
    {
        $query->where('action_type_code', $actionTypeCode);
    }

    /**
     * Scope a query to only include audit logs within a date range.
     */
    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): void
    {
        $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include audit logs from a specific IP address.
     */
    public function scopeFromIp(Builder $query, string $ipAddress): void
    {
        $query->where('ip_address', $ipAddress);
    }

    /**
     * Get the action type label.
     */
    public function getActionTypeLabelAttribute(): string
    {
        return $this->actionType?->label_en ?? 'Unknown';
    }

    /**
     * Get the action type label in Arabic.
     */
    public function getActionTypeLabelArAttribute(): string
    {
        return $this->actionType?->label_ar ?? 'غير محدد';
    }

    /**
     * Check if this audit log entry has changed values.
     */
    public function hasValueChanges(): bool
    {
        return !is_null($this->old_value) || !is_null($this->new_value);
    }

    /**
     * Get the change description.
     */
    public function getChangeDescriptionAttribute(): string
    {
        if (!$this->hasValueChanges()) {
            return $this->action_description ?? 'Action performed';
        }

        if (is_null($this->old_value) && !is_null($this->new_value)) {
            return "Set to: {$this->new_value}";
        }

        if (!is_null($this->old_value) && is_null($this->new_value)) {
            return "Removed: {$this->old_value}";
        }

        return "Changed from '{$this->old_value}' to '{$this->new_value}'";
    }

    /**
     * Get the user's display name.
     */
    public function getUserDisplayNameAttribute(): string
    {
        return $this->user?->display_name ?? 'Unknown User';
    }

    /**
     * Get the patient name from the medical record.
     */
    public function getPatientNameAttribute(): string
    {
        return $this->medicalRecord?->patient?->full_name ?? 'Unknown Patient';
    }

    /**
     * Check if this audit log entry is from today.
     */
    public function isFromToday(): bool
    {
        return $this->created_at->isToday();
    }

    /**
     * Check if this audit log entry is from yesterday.
     */
    public function isFromYesterday(): bool
    {
        return $this->created_at->isYesterday();
    }

    /**
     * Get the time ago description.
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }
}
