<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class StaticData extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'static_data';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'code',
        'label_en',
        'label_ar',
        'description',
        'is_active',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all users with this role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_code', 'code');
    }

    /**
     * Get all patients with this gender.
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'gender_code', 'code');
    }

    /**
     * Get all patients with this health center.
     */
    public function healthCenterPatients(): HasMany
    {
        return $this->hasMany(Patient::class, 'health_center_code', 'code');
    }



    /**
     * Get all medical records with this status.
     */
    public function statusMedicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class, 'status_code', 'code');
    }

  
    /**
     * Get all medical records with this danger level.
     */
    public function dangerLevelMedicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class, 'danger_level_code', 'code');
    }

    /**
     * Get all medical records with this final status.
     */
    public function finalStatusMedicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class, 'final_status_code', 'code');
    }

    /**
     * Get all audit logs with this action type.
     */
    public function actionAuditLogs(): HasMany
    {
        return $this->hasMany(RecordAuditLog::class, 'action_type_code', 'code');
    }

    /**
     * Get all access logs with this access type.
     */
    public function accessTypeLogs(): HasMany
    {
        return $this->hasMany(RecordAccessLog::class, 'access_type_code', 'code');
    }

    /**
     * Get all workflow steps with this status.
     */
    public function workflowStepStatuses(): HasMany
    {
        return $this->hasMany(TransferWorkflowStep::class, 'step_status_code', 'code');
    }

    /**
     * Scope a query to only include active static data.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope a query to only include static data of a specific type.
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Scope a query to only include static data with a specific code.
     */
    public function scopeWithCode(Builder $query, string $code): void
    {
        $query->where('code', $code);
    }

    /**
     * Get the label in the specified language.
     */
    public function getLabel(string $language = 'en'): string
    {
        return $language === 'ar' ? $this->label_ar : $this->label_en;
    }

    /**
     * Check if this static data is of a specific type.
     */
    public function isOfType(string $type): bool
    {
        return $this->type === $type;
    }

    /**
     * Check if this static data has a specific code.
     */
    public function hasCode(string $code): bool
    {
        return $this->code === $code;
    }

    /**
     * Get metadata value by key.
     */
    public function getMetadata(string $key, $default = null)
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Set metadata value by key.
     */
    public function setMetadata(string $key, $value): void
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
    }
}
