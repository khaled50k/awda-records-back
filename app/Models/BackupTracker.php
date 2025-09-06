<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class BackupTracker extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_name',
        'last_backup_at',
        'last_record_id',
        'backup_status',
        'records_count',
        'notes'
    ];

    protected $casts = [
        'last_backup_at' => 'datetime',
        'records_count' => 'integer',
        'last_record_id' => 'integer'
    ];

    /**
     * Get the last backup timestamp for a specific table.
     *
     * @param string $tableName
     * @return Carbon|null
     */
    public static function getLastBackupTime(string $tableName): ?Carbon
    {
        $tracker = static::where('table_name', $tableName)->first();
        return $tracker ? $tracker->last_backup_at : null;
    }

    /**
     * Update the backup tracker for a specific table.
     *
     * @param string $tableName
     * @param int $recordsCount
     * @param int|null $lastRecordId
     * @param string $status
     * @param string|null $notes
     * @return static
     */
    public static function updateBackupTracker(
        string $tableName,
        int $recordsCount,
        ?int $lastRecordId = null,
        string $status = 'completed',
        ?string $notes = null
    ): static {
        return static::updateOrCreate(
            ['table_name' => $tableName],
            [
                'last_backup_at' => now(),
                'last_record_id' => $lastRecordId,
                'backup_status' => $status,
                'records_count' => $recordsCount,
                'notes' => $notes
            ]
        );
    }

    /**
     * Get all tables that need backup based on the interval.
     *
     * @param int $intervalHours
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getTablesNeedingBackup(int $intervalHours = 3)
    {
        $cutoffTime = now()->subHours($intervalHours);
        
        return static::where('last_backup_at', '<', $cutoffTime)
            ->orWhereNull('last_backup_at')
            ->get();
    }

    /**
     * Mark backup as failed for a specific table.
     *
     * @param string $tableName
     * @param string $errorMessage
     * @return static
     */
    public static function markBackupFailed(string $tableName, string $errorMessage): static
    {
        return static::updateOrCreate(
            ['table_name' => $tableName],
            [
                'backup_status' => 'failed',
                'notes' => $errorMessage,
                'last_backup_at' => now()
            ]
        );
    }

    /**
     * Get backup statistics.
     *
     * @return array
     */
    public static function getBackupStats(): array
    {
        $total = static::count();
        $completed = static::where('backup_status', 'completed')->count();
        $failed = static::where('backup_status', 'failed')->count();
        $pending = static::whereNull('last_backup_at')->count();
        
        return [
            'total_tables' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'pending' => $pending,
            'success_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0
        ];
    }
}