<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;
use App\Models\BackupTracker;
use Exception;

class IncrementalBackupService
{
    /**
     * Tables to include in incremental backup.
     * Order matters for foreign key dependencies.
     */
    private array $backupTables = [
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
     * Perform incremental backup of database changes.
     *
     * @return array
     * @throws Exception
     */
    public function performIncrementalBackup(): array
    {
        $startTime = now();
        $lastBackupTime = $this->getLastBackupTimestamp();
        $totalRecords = 0;
        $processedTables = [];

        try {
            // Ensure backup database connection is available
            $this->ensureBackupConnection();

            foreach ($this->backupTables as $table) {
                $recordCount = $this->backupTableChanges($table, $lastBackupTime);
                $totalRecords += $recordCount;
                $processedTables[$table] = $recordCount;
                
                // Update BackupTracker for this table
                BackupTracker::updateBackupTracker(
                    $table,
                    $recordCount,
                    null, // last_record_id not used in this implementation
                    'completed',
                    "Incremental backup completed at {$startTime}"
                );
                
                Log::info("Backed up {$recordCount} records from table: {$table}");
            }

            // Update last backup timestamp in backup database
            $this->updateLastBackupTimestamp($startTime);

            return [
                'success' => true,
                'total_records' => $totalRecords,
                'tables' => $processedTables,
                'backup_time' => $startTime->toDateTimeString(),
                'last_backup_time' => $lastBackupTime ? $lastBackupTime->toDateTimeString() : null
            ];

        } catch (Exception $e) {
            Log::error('Incremental backup failed', [
                'error' => $e->getMessage(),
                'processed_tables' => $processedTables
            ]);
            throw $e;
        }
    }

    /**
     * Backup changes for a specific table.
     *
     * @param string $table
     * @param Carbon|null $lastBackupTime
     * @return int
     */
    private function backupTableChanges(string $table, ?Carbon $lastBackupTime): int
    {
        // Check if table has timestamp columns for incremental backup
        $timestampColumn = $this->getTimestampColumn($table);
        
        if (!$timestampColumn) {
            // If no timestamp column, backup all records (full backup for this table)
            return $this->backupAllRecords($table);
        }

        // Build query for incremental backup
        $query = DB::table($table);
        
        if ($lastBackupTime) {
            $query->where($timestampColumn, '>', $lastBackupTime);
        }

        $records = $query->get();
        
        if ($records->isEmpty()) {
            return 0;
        }

        // Insert or update records in backup database
        return $this->insertOrUpdateBackupRecords($table, $records);
    }

    /**
     * Get the appropriate timestamp column for incremental backup.
     *
     * @param string $table
     * @return string|null
     */
    private function getTimestampColumn(string $table): ?string
    {
        $columns = Schema::getColumnListing($table);
        
        // Priority order for timestamp columns
        $timestampColumns = ['updated_at', 'created_at', 'timestamp'];
        
        foreach ($timestampColumns as $column) {
            if (in_array($column, $columns)) {
                return $column;
            }
        }
        
        return null;
    }

    /**
     * Backup all records from a table (for tables without timestamp columns).
     *
     * @param string $table
     * @return int
     */
    private function backupAllRecords(string $table): int
    {
        $records = DB::table($table)->get();
        
        if ($records->isEmpty()) {
            return 0;
        }

        return $this->insertOrUpdateBackupRecords($table, $records);
    }

    /**
     * Insert or update records in backup database.
     *
     * @param string $table
     * @param \Illuminate\Support\Collection $records
     * @return int
     */
    private function insertOrUpdateBackupRecords(string $table, $records): int
    {
        $backupDb = DB::connection($this->backupConnection);
        
        // Ensure backup table exists
        $this->ensureBackupTableExists($table);
        
        $primaryKey = $this->getPrimaryKey($table);
        $insertedCount = 0;
        
        // Process records in chunks for better performance
        $chunks = $records->chunk(100);
        
        foreach ($chunks as $chunk) {
            if ($primaryKey) {
                // Use INSERT ... ON DUPLICATE KEY UPDATE for better performance
                $this->insertOrUpdateChunk($backupDb, $table, $chunk, $primaryKey);
            } else {
                // Fallback to INSERT IGNORE for tables without identifiable primary key
                $this->insertIgnoreChunk($backupDb, $table, $chunk);
            }
            
            $insertedCount += $chunk->count();
        }
        
        return $insertedCount;
    }
    
    /**
     * Insert or update a chunk of records using ON DUPLICATE KEY UPDATE.
     *
     * @param \Illuminate\Database\Connection $connection
     * @param string $table
     * @param \Illuminate\Support\Collection $records
     * @param string $primaryKey
     * @return void
     */
    private function insertOrUpdateChunk($connection, string $table, $records, string $primaryKey): void
    {
        foreach ($records as $record) {
            $recordArray = (array) $record;
            
            try {
                // Build column names and values
                $columns = array_keys($recordArray);
                $values = array_values($recordArray);
                
                // Build UPDATE clause for duplicate key
                $updateClauses = [];
                foreach ($columns as $column) {
                    if ($column !== $primaryKey) {
                        $updateClauses[] = "`{$column}` = VALUES(`{$column}`)"; 
                    }
                }
                
                $columnsList = '`' . implode('`, `', $columns) . '`';
                $placeholders = str_repeat('?,', count($values) - 1) . '?';
                $updateClause = implode(', ', $updateClauses);
                
                $sql = "INSERT INTO `{$table}` ({$columnsList}) VALUES ({$placeholders})";
                if (!empty($updateClause)) {
                    $sql .= " ON DUPLICATE KEY UPDATE {$updateClause}";
                }
                
                $connection->statement($sql, $values);
                
            } catch (Exception $e) {
                Log::warning("Failed to insert/update record in table {$table}", [
                    'error' => $e->getMessage(),
                    'record' => $recordArray
                ]);
            }
        }
    }
    
    /**
     * Insert records using INSERT IGNORE.
     *
     * @param \Illuminate\Database\Connection $connection
     * @param string $table
     * @param \Illuminate\Support\Collection $records
     * @return void
     */
    private function insertIgnoreChunk($connection, string $table, $records): void
    {
        foreach ($records as $record) {
            $recordArray = (array) $record;
            
            try {
                $columns = array_keys($recordArray);
                $values = array_values($recordArray);
                
                $columnsList = '`' . implode('`, `', $columns) . '`';
                $placeholders = str_repeat('?,', count($values) - 1) . '?';
                
                $sql = "INSERT IGNORE INTO `{$table}` ({$columnsList}) VALUES ({$placeholders})";
                $connection->statement($sql, $values);
                
            } catch (Exception $e) {
                Log::warning("Failed to insert record in table {$table}", [
                    'error' => $e->getMessage(),
                    'record' => $recordArray
                ]);
            }
        }
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
     * Ensure backup table exists with same structure as source table.
     *
     * @param string $table
     * @return void
     */
    private function ensureBackupTableExists(string $table): void
    {
        $backupDb = DB::connection($this->backupConnection);
        
        if (!Schema::connection($this->backupConnection)->hasTable($table)) {
            try {
                // Temporarily disable foreign key checks
                $backupDb->statement('SET FOREIGN_KEY_CHECKS=0');
                
                // Create table structure from source database
                $createTableSql = $this->getCreateTableStatement($table);
                $backupDb->statement($createTableSql);
                
                Log::info("Created backup table: {$table}");
                
            } catch (Exception $e) {
                Log::error("Failed to create backup table {$table}", ['error' => $e->getMessage()]);
                throw $e;
            } finally {
                // Re-enable foreign key checks
                $backupDb->statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }

    /**
     * Get CREATE TABLE statement for a table.
     *
     * @param string $table
     * @return string
     */
    private function getCreateTableStatement(string $table): string
    {
        $result = DB::select("SHOW CREATE TABLE `{$table}`");
        return $result[0]->{'Create Table'};
    }

    /**
     * Get the last backup timestamp.
     *
     * @return Carbon|null
     */
    private function getLastBackupTimestamp(): ?Carbon
    {
        try {
            $backupDb = DB::connection($this->backupConnection);
            
            // Check if backup_metadata table exists
            if (!Schema::connection($this->backupConnection)->hasTable('backup_metadata')) {
                $this->createBackupMetadataTable();
                return null;
            }
            
            $lastBackup = $backupDb->table('backup_metadata')
                ->where('key', 'last_backup_timestamp')
                ->first();
                
            return $lastBackup ? Carbon::parse($lastBackup->value) : null;
            
        } catch (Exception $e) {
            Log::warning('Could not retrieve last backup timestamp', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Update the last backup timestamp.
     *
     * @param Carbon $timestamp
     * @return void
     */
    private function updateLastBackupTimestamp(Carbon $timestamp): void
    {
        $backupDb = DB::connection($this->backupConnection);
        
        $backupDb->table('backup_metadata')
            ->updateOrInsert(
                ['key' => 'last_backup_timestamp'],
                ['value' => $timestamp->toDateTimeString(), 'updated_at' => now()]
            );
    }

    /**
     * Create backup metadata table.
     *
     * @return void
     */
    private function createBackupMetadataTable(): void
    {
        $backupDb = DB::connection($this->backupConnection);
        
        $backupDb->statement("
            CREATE TABLE backup_metadata (
                id INT AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(255) NOT NULL UNIQUE,
                `value` TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
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
            // First, try to connect to the backup database
            DB::connection($this->backupConnection)->getPdo();
        } catch (Exception $e) {
            // If database doesn't exist, try to create it
            if (str_contains($e->getMessage(), 'Unknown database')) {
                $this->createBackupDatabase();
            } else {
                throw new Exception("Backup database connection '{$this->backupConnection}' is not available: " . $e->getMessage());
            }
        }
    }

    /**
     * Create the backup database if it doesn't exist.
     *
     * @return void
     * @throws Exception
     */
    private function createBackupDatabase(): void
    {
        try {
            $backupConfig = config("database.connections.{$this->backupConnection}");
            
            if (!$backupConfig || !isset($backupConfig['database'])) {
                throw new Exception("Backup database configuration not found or missing database name");
            }
            
            $databaseName = $backupConfig['database'];
            
            // Create a temporary connection without specifying database
            $tempConfig = $backupConfig;
            unset($tempConfig['database']);
            
            config(['database.connections.temp_backup' => $tempConfig]);
            
            // Create the database
            DB::connection('temp_backup')->statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            Log::info("Created backup database: {$databaseName}");
            
            // Now test the backup connection
            DB::connection($this->backupConnection)->getPdo();
            
        } catch (Exception $e) {
            throw new Exception("Failed to create backup database: " . $e->getMessage());
        }
    }
}