<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class RecordAccessLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'record_access_log';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'access_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'patient_id',
        'user_id',
        'access_type_code',
        'accessed_at',
        'ip_address',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'patient_id' => 'integer',
        'user_id' => 'integer',
        'accessed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the patient associated with this access log entry.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    /**
     * Get the user who accessed the patient record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get the access type information.
     */
    public function accessType(): BelongsTo
    {
        return $this->belongsTo(StaticData::class, 'access_type_code', 'code')
            ->where('type', 'access_type');
    }

    /**
     * Scope a query to only include access logs for a specific patient.
     */
    public function scopeForPatient(Builder $query, int $patientId): void
    {
        $query->where('patient_id', $patientId);
    }

    /**
     * Scope a query to only include access logs by a specific user.
     */
    public function scopeByUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include access logs of a specific access type.
     */
    public function scopeWithAccessType(Builder $query, string $accessTypeCode): void
    {
        $query->where('access_type_code', $accessTypeCode);
    }

    /**
     * Scope a query to only include access logs within a date range.
     */
    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): void
    {
        $query->whereBetween('accessed_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include access logs from a specific IP address.
     */
    public function scopeFromIp(Builder $query, string $ipAddress): void
    {
        $query->where('ip_address', $ipAddress);
    }

    /**
     * Scope a query to only include access logs from today.
     */
    public function scopeToday(Builder $query): void
    {
        $query->whereDate('accessed_at', today());
    }

    /**
     * Scope a query to only include access logs from yesterday.
     */
    public function scopeYesterday(Builder $query): void
    {
        $query->whereDate('accessed_at', today()->subDay());
    }

    /**
     * Scope a query to only include access logs from this week.
     */
    public function scopeThisWeek(Builder $query): void
    {
        $query->whereBetween('accessed_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    /**
     * Scope a query to only include access logs from this month.
     */
    public function scopeThisMonth(Builder $query): void
    {
        $query->whereMonth('accessed_at', now()->month)
              ->whereYear('accessed_at', now()->year);
    }

    /**
     * Get the access type label.
     */
    public function getAccessTypeLabelAttribute(): string
    {
        return $this->accessType?->label_en ?? 'Unknown';
    }

    /**
     * Get the access type label in Arabic.
     */
    public function getAccessTypeLabelArAttribute(): string
    {
        return $this->accessType?->label_ar ?? 'غير محدد';
    }

    /**
     * Get the user's display name.
     */
    public function getUserDisplayNameAttribute(): string
    {
        return $this->user?->display_name ?? 'Unknown User';
    }

    /**
     * Get the patient's display name.
     */
    public function getPatientDisplayNameAttribute(): string
    {
        return $this->patient?->full_name ?? 'Unknown Patient';
    }

    /**
     * Check if this access log entry is from today.
     */
    public function isFromToday(): bool
    {
        return $this->accessed_at->isToday();
    }

    /**
     * Check if this access log entry is from yesterday.
     */
    public function isFromYesterday(): bool
    {
        return $this->accessed_at->isYesterday();
    }

    /**
     * Get the time ago description.
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->accessed_at->diffForHumans();
    }

    /**
     * Check if this is an EHR view access.
     */
    public function isEhrView(): bool
    {
        return $this->access_type_code === 'view_ehr';
    }

    /**
     * Check if this is an EHR notes addition access.
     */
    public function isEhrNotesAddition(): bool
    {
        return $this->access_type_code === 'add_notes_ehr';
    }

    /**
     * Check if this is an EHR status update access.
     */
    public function isEhrStatusUpdate(): bool
    {
        return $this->access_type_code === 'update_status_ehr';
    }

    /**
     * Get the access description.
     */
    public function getAccessDescriptionAttribute(): string
    {
        $patientName = $this->patientDisplayName;
        $accessType = $this->accessTypeLabel;
        
        return "{$accessType} for patient: {$patientName}";
    }
}
