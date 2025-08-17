<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Patient extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'patients';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'patient_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name',
        'national_id',
        'gender_code',
        'health_center_code',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'national_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the patient's gender information.
     */
    public function gender(): BelongsTo
    {
        return $this->belongsTo(StaticData::class, 'gender_code', 'code')
            ->where('type', 'gender');
    }

    /**
     * Get the patient's health center information.
     */
    public function healthCenter(): BelongsTo
    {
        return $this->belongsTo(StaticData::class, 'health_center_code', 'code')
            ->where('type', 'health_center_type');
    }

    /**
     * Get all medical records for this patient.
     */
    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class, 'patient_id', 'patient_id');
    }

    /**
     * Get all access logs for this patient.
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(RecordAccessLog::class, 'patient_id', 'patient_id');
    }

    /**
     * Scope a query to only include patients with a specific gender.
     */
    public function scopeWithGender(Builder $query, string $genderCode): void
    {
        $query->where('gender_code', $genderCode);
    }

    /**
     * Scope a query to search patients by name.
     */
    public function scopeSearchByName(Builder $query, string $name): void
    {
        $query->where('full_name', 'like', "%{$name}%")->orWhere('national_id','like', "%{$name}%");
    
    }

    /**
     * Scope a query to search patients by name or national ID (more flexible).
     */
    public function scopeSearchByNameOrNationalId(Builder $query, string $searchTerm): void
    {
        $query->where(function($q) use ($searchTerm) {
            $q->where('full_name', 'like', "%{$searchTerm}%")
              ->orWhere('national_id', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * Scope a query to find patients by partial national ID match.
     */
    public function scopeWithPartialNationalId(Builder $query, string $partialId): void
    {
        $query->where('national_id', 'like', "%{$partialId}%");
    }

    /**
     * Scope a query to find patients by partial name match.
     */
    public function scopeWithPartialName(Builder $query, string $partialName): void
    {
        $query->where('full_name', 'like', "%{$partialName}%");
    }

    /**
     * Scope a query to find patients with medical records.
     */
    public function scopeWithMedicalRecords(Builder $query): void
    {
        $query->whereHas('medicalRecords');
    }

    /**
     * Scope a query to find patients without medical records.
     */
    public function scopeWithoutMedicalRecords(Builder $query): void
    {
        $query->whereDoesntHave('medicalRecords');
    }

    /**
     * Scope a query to find patients with medical records in a specific status.
     */
    public function scopeWithMedicalRecordsInStatus(Builder $query, string $statusCode): void
    {
        $query->whereHas('medicalRecords', function($q) use ($statusCode) {
            $q->where('status_code', $statusCode);
        });
    }

    /**
     * Scope a query to find patients from a specific health center.
     */
    public function scopeFromHealthCenter(Builder $query, string $healthCenterCode): void
    {
        $query->where('health_center_code', $healthCenterCode);
    }

    /**
     * Scope a query to find patients with medical records from a specific health center.
     * @deprecated Use scopeFromHealthCenter instead
     */
    public function scopeWithMedicalRecordsFromHealthCenter(Builder $query, string $healthCenterCode): void
    {
        $query->whereHas('medicalRecords', function($q) use ($healthCenterCode) {
            $q->where('health_center_code', $healthCenterCode);
        });
    }

    /**
     * Get the patient's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->full_name;
    }

    /**
     * Get the patient's gender label.
     */
    public function getGenderLabelAttribute(): string
    {
        return $this->gender?->label_en ?? 'Unknown';
    }

    /**
     * Get the patient's gender label in Arabic.
     */
    public function getGenderLabelArAttribute(): string
    {
        return $this->gender?->label_ar ?? 'غير محدد';
    }

    /**
     * Get the patient's health center label.
     */
    public function getHealthCenterLabelAttribute(): string
    {
        return $this->healthCenter?->label_en ?? 'Unknown';
    }

    /**
     * Get the patient's health center label in Arabic.
     */
    public function getHealthCenterLabelArAttribute(): string
    {
        return $this->healthCenter?->label_ar ?? 'غير محدد';
    }

    /**
     * Check if the patient has any medical records.
     */
    public function hasMedicalRecords(): bool
    {
        return $this->medicalRecords()->exists();
    }

    /**
     * Get the patient's latest medical record.
     */
    public function getLatestMedicalRecordAttribute()
    {
        return $this->medicalRecords()->latest()->first();
    }

    /**
     * Get the total number of medical records for this patient.
     */
    public function getMedicalRecordsCountAttribute(): int
    {
        return $this->medicalRecords()->count();
    }

    /**
     * Get the total number of access logs for this patient.
     */
    public function getAccessLogsCountAttribute(): int
    {
        return $this->accessLogs()->count();
    }
}
