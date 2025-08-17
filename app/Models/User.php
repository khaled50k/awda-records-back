<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'user_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'password_hash',
        'role_code',
        'full_name',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password_hash',
    ];

    /**
     * Get the password for the user.
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Get the name of the password field.
     */
    public function getPasswordName()
    {
        return 'password_hash';
    }

    /**
     * Get the user's role information.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(StaticData::class, 'role_code', 'code')
            ->where('type', 'role');
    }

  

    /**
     * Get all medical records created by this user.
     */
    public function createdMedicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class, 'created_by', 'user_id');
    }

    /**
     * Get all medical records last modified by this user.
     */
    public function lastModifiedMedicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class, 'last_modified_by', 'user_id');
    }

    /**
     * Get all medical records reviewed by this user.
     */
    public function reviewedMedicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class, 'reviewed_party_user_id', 'user_id');
    }

    /**
     * Get all transfers sent by this user.
     */
    public function sentTransfers(): HasMany
    {
        return $this->hasMany(RecordTransfer::class, 'sender_id', 'user_id');
    }

    /**
     * Get all transfers received by this user.
     */
    public function receivedTransfers(): HasMany
    {
        return $this->hasMany(RecordTransfer::class, 'recipient_id', 'user_id');
    }


    /**
     * Get all audit log entries for this user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(RecordAuditLog::class, 'user_id', 'user_id');
    }

    /**
     * Get all access log entries for this user.
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(RecordAccessLog::class, 'user_id', 'user_id');
    }

    /**
     * Get all workflow steps completed by this user.
     */
    public function completedWorkflowSteps(): HasMany
    {
        return $this->hasMany(TransferWorkflowStep::class, 'completed_by', 'user_id');
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope a query to only include users with a specific role.
     */
    public function scopeWithRole(Builder $query, string $roleCode): void
    {
        $query->where('role_code', $roleCode);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role_code === 'admin';
    }

    /**
     * Check if user is employee
     */
    public function isEmployee(): bool
    {
        return $this->role_code === 'employee';
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $roleCode): bool
    {
        return $this->role_code === $roleCode;
    }

    /**
     * Get the user's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: $this->username;
    }
}
