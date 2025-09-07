<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class DatabaseRestoreService
{
    /**
     * Tables to restore from backup.
     * Order matters for foreign key dependencies.
     */
    private array $restoreTables = [
        'static_data',  // Must be first - referenced by users
        'users',
        'patients', 
        'medical_records',
        'record_transfers',
        'record_audit_log',
        'record_access_log',
        'transfer_workflow_steps'
    ];

    /**
     * Backup database connection name.
     */
    private string $backupConnection = 'backup';

    /**
     * Current database connection name.
     */
    private string $currentConnection = 'mysql';

    /**
     * Restore missing records from backup database to current database.
     *
     * @param array|null $specificTables Optional array of specific tables to restore
     * @return array
     * @throws Exception
     */
    public function restoreMissingRecords(?array $specificTables = null): array
    {
        $startTime = now();
        $totalRestoredRecords = 0;
        $processedTables = [];
        $tablesToProcess = $specificTables ?? $this->restoreTables;

        try {
            // Ensure backup database connection is available
            $this->ensureBackupConnection();

            foreach ($tablesToProcess as $table) {
                if (!in_array($table, $this->restoreTables)) {
                    Log::warning("Skipping table '{$table}' - not in allowed restore tables list");
                    continue;
                }

                $restoredCount = $this->restoreTableMissingRecords($table);
                $totalRestoredRecords += $restoredCount;
                $processedTables[$table] = $restoredCount;
                
                Log::info("Restored {$restoredCount} missing records to table: {$table}");
            }

            return [
                'success' => true,
                'total_restored_records' => $totalRestoredRecords,
                'tables' => $processedTables,
                'restore_time' => $startTime->toDateTimeString(),
                'duration' => now()->diffInSeconds($startTime) . ' seconds'
            ];

        } catch (Exception $e) {
            Log::error('Database restore failed', [
                'error' => $e->getMessage(),
                'processed_tables' => $processedTables
            ]);
            throw $e;
        }
    }

    /**
     * Restore missing records for a specific table.
     *
     * @param string $table
     * @return int
     */
    private function restoreTableMissingRecords(string $table): int
    {
        // Get primary key for the table
        $primaryKey = $this->getPrimaryKey($table);
        
        if (!$primaryKey) {
            Log::warning("Cannot restore table '{$table}' - no primary key found");
            return 0;
        }

        // Check if both tables exist
        if (!$this->tableExistsInBothDatabases($table)) {
            Log::warning("Table '{$table}' does not exist in both databases");
            return 0;
        }

        // Find missing records
        $missingRecords = $this->findMissingRecords($table, $primaryKey);
        
        if ($missingRecords->isEmpty()) {
            return 0;
        }

        // Insert missing records into current database
        return $this->insertMissingRecords($table, $missingRecords);
    }

    /**
     * Find records that exist in backup but are missing from current database.
     *
     * @param string $table
     * @param string $primaryKey
     * @return \Illuminate\Support\Collection
     */
    private function findMissingRecords(string $table, string $primaryKey)
    {
        $backupDb = DB::connection($this->backupConnection);
        $currentDb = DB::connection($this->currentConnection);

        // Get all primary keys from current database
        $currentIds = $currentDb->table($table)
            ->pluck($primaryKey)
            ->toArray();

        // Get records from backup that are not in current database
        $query = $backupDb->table($table);
        
        if (!empty($currentIds)) {
            $query->whereNotIn($primaryKey, $currentIds);
        }
        
        return $query->get();
    }

    /**
     * Insert missing records into current database.
     *
     * @param string $table
     * @param \Illuminate\Support\Collection $records
     * @return int
     */
    private function insertMissingRecords(string $table, $records): int
    {
        $currentDb = DB::connection($this->currentConnection);
        $insertedCount = 0;
        
        // Process records in chunks for better performance
        $chunks = $records->chunk(100);
        
        foreach ($chunks as $chunk) {
            try {
                // Convert records to arrays
                $recordsArray = $chunk->map(function ($record) {
                    return (array) $record;
                })->toArray();
                
                // Insert records
                $currentDb->table($table)->insert($recordsArray);
                $insertedCount += $chunk->count();
                
            } catch (Exception $e) {
                Log::error("Failed to insert chunk of records into table {$table}", [
                    'error' => $e->getMessage(),
                    'chunk_size' => $chunk->count()
                ]);
                
                // Try inserting records one by one to identify problematic records
                foreach ($chunk as $record) {
                    try {
                        $recordArray = (array) $record;
                        $currentDb->table($table)->insert($recordArray);
                        $insertedCount++;
                    } catch (Exception $singleRecordError) {
                        Log::warning("Failed to insert single record into table {$table}", [
                            'error' => $singleRecordError->getMessage(),
                            'record' => $recordArray
                        ]);
                    }
                }
            }
        }
        
        return $insertedCount;
    }

    /**
     * Check if table exists in both databases.
     *
     * @param string $table
     * @return bool
     */
    private function tableExistsInBothDatabases(string $table): bool
    {
        $backupExists = Schema::connection($this->backupConnection)->hasTable($table);
        $currentExists = Schema::connection($this->currentConnection)->hasTable($table);
        
        return $backupExists && $currentExists;
    }

    /**
     * Get primary key column for a table.
     *
     * @param string $table
     * @return string|null
     */
    private function getPrimaryKey(string $table): ?string
    {
        $columns = Schema::getColumnListing($table);
        
        // Handle special cases where primary key is not 'id'
        $specialCases = [
            'medical_records' => 'record_id',
            'record_transfers' => 'transfer_id',
        ];
        
        if (isset($specialCases[$table]) && in_array($specialCases[$table], $columns)) {
            return $specialCases[$table];
        }
        
        // Check for common primary key patterns
        $primaryKeyPatterns = [
            'id',
            $table . '_id', // e.g., patient_id for patients table
            rtrim($table, 's') . '_id', // e.g., patient_id for patients table (remove trailing 's')
        ];
        
        foreach ($primaryKeyPatterns as $pattern) {
            if (in_array($pattern, $columns)) {
                return $pattern;
            }
        }
        
        // Try to get primary key from database schema
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = 'PRIMARY'");
            if (!empty($indexes)) {
                return $indexes[0]->Column_name;
            }
        } catch (Exception $e) {
            Log::warning("Could not determine primary key for table {$table}", ['error' => $e->getMessage()]);
        }
        
        return null;
    }

    /**
     * Ensure backup database connection is available.
     *
     * @return void
     * @throws Exception
     */
    private function ensureBackupConnection(): void
    {
        try {
            DB::connection($this->backupConnection)->getPdo();
        } catch (Exception $e) {
            throw new Exception("Backup database connection '{$this->backupConnection}' is not available: " . $e->getMessage());
        }
    }

    /**
     * Get comparison summary between backup and current database.
     *
     * @param array|null $specificTables
     * @return array
     */
    public function getComparisonSummary(?array $specificTables = null): array
    {
        $tablesToProcess = $specificTables ?? $this->restoreTables;
        $summary = [];
        
        foreach ($tablesToProcess as $table) {
            if (!in_array($table, $this->restoreTables)) {
                continue;
            }
            
            $primaryKey = $this->getPrimaryKey($table);
            
            if (!$primaryKey || !$this->tableExistsInBothDatabases($table)) {
                $summary[$table] = [
                    'status' => 'unavailable',
                    'reason' => !$primaryKey ? 'No primary key found' : 'Table missing in one database'
                ];
                continue;
            }
            
            $backupCount = DB::connection($this->backupConnection)->table($table)->count();
            $currentCount = DB::connection($this->currentConnection)->table($table)->count();
            $missingCount = $this->findMissingRecords($table, $primaryKey)->count();
            
            $summary[$table] = [
                'backup_records' => $backupCount,
                'current_records' => $currentCount,
                'missing_records' => $missingCount,
                'status' => $missingCount > 0 ? 'has_missing' : 'synchronized'
            ];
        }
        
        return $summary;
    }

    /**
     * Restore specific records by their IDs.
     *
     * @param string $table
     * @param array $recordIds
     * @return array
     */
    public function restoreSpecificRecords(string $table, array $recordIds): array
    {
        if (!in_array($table, $this->restoreTables)) {
            throw new Exception("Table '{$table}' is not in the allowed restore tables list");
        }
        
        $primaryKey = $this->getPrimaryKey($table);
        
        if (!$primaryKey) {
            throw new Exception("Cannot restore from table '{$table}' - no primary key found");
        }
        
        if (!$this->tableExistsInBothDatabases($table)) {
            throw new Exception("Table '{$table}' does not exist in both databases");
        }
        
        // Get specific records from backup
        $backupRecords = DB::connection($this->backupConnection)
            ->table($table)
            ->whereIn($primaryKey, $recordIds)
            ->get();
            
        if ($backupRecords->isEmpty()) {
            return [
                'success' => true,
                'restored_records' => 0,
                'message' => 'No records found in backup with the specified IDs'
            ];
        }
        
        // Insert records (this will handle duplicates gracefully)
        $restoredCount = $this->insertMissingRecords($table, $backupRecords);
        
        return [
            'success' => true,
            'restored_records' => $restoredCount,
            'requested_records' => count($recordIds),
            'found_in_backup' => $backupRecords->count()
        ];
    }
}