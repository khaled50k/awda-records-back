<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DatabaseRestoreService;
use Exception;

class DatabaseRestoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:restore 
                            {--table= : Specific table to restore}
                            {--ids= : Comma-separated list of record IDs to restore}
                            {--summary : Show comparison summary without restoring}
                            {--confirm : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore missing records from backup database to current database';

    /**
     * Database restore service.
     *
     * @var DatabaseRestoreService
     */
    private DatabaseRestoreService $restoreService;

    /**
     * Create a new command instance.
     *
     * @param DatabaseRestoreService $restoreService
     */
    public function __construct(DatabaseRestoreService $restoreService)
    {
        parent::__construct();
        $this->restoreService = $restoreService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        try {
            // Handle summary option
            if ($this->option('summary')) {
                return $this->showSummary();
            }

            // Handle specific table restoration
            $table = $this->option('table');
            $ids = $this->option('ids');

            if ($table && $ids) {
                return $this->restoreSpecificRecords($table, $ids);
            }

            // Handle full restoration
            return $this->restoreAllMissingRecords($table);

        } catch (Exception $e) {
            $this->error('Restore failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Show comparison summary.
     *
     * @return int
     */
    private function showSummary(): int
    {
        $this->info('Analyzing databases...');
        
        $table = $this->option('table');
        $specificTables = $table ? [$table] : null;
        
        $summary = $this->restoreService->getComparisonSummary($specificTables);
        
        $this->info('\n=== Database Comparison Summary ===');
        
        $totalMissing = 0;
        foreach ($summary as $tableName => $data) {
            $this->line("\nTable: {$tableName}");
            
            if ($data['status'] === 'unavailable') {
                $this->warn("  Status: Unavailable - {$data['reason']}");
                continue;
            }
            
            $this->line("  Backup records: {$data['backup_records']}");
            $this->line("  Current records: {$data['current_records']}");
            $this->line("  Missing records: {$data['missing_records']}");
            
            if ($data['status'] === 'has_missing') {
                $this->warn("  Status: Has missing records");
                $totalMissing += $data['missing_records'];
            } else {
                $this->info("  Status: Synchronized");
            }
        }
        
        $this->info("\n=== Summary ===");
        $this->info("Total missing records: {$totalMissing}");
        
        if ($totalMissing > 0) {
            $this->warn("Run 'php artisan backup:restore --confirm' to restore missing records.");
        } else {
            $this->info("All databases are synchronized.");
        }
        
        return 0;
    }

    /**
     * Restore specific records by IDs.
     *
     * @param string $table
     * @param string $ids
     * @return int
     */
    private function restoreSpecificRecords(string $table, string $ids): int
    {
        $recordIds = array_map('trim', explode(',', $ids));
        
        $this->info("Restoring specific records from table '{$table}'...");
        $this->line("Record IDs: " . implode(', ', $recordIds));
        
        if (!$this->option('confirm') && !$this->confirm('Do you want to proceed?')) {
            $this->info('Restore cancelled.');
            return 0;
        }
        
        $result = $this->restoreService->restoreSpecificRecords($table, $recordIds);
        
        $this->info("\n=== Restore Results ===");
        $this->info("Requested records: {$result['requested_records']}");
        $this->info("Found in backup: {$result['found_in_backup']}");
        $this->info("Successfully restored: {$result['restored_records']}");
        
        return 0;
    }

    /**
     * Restore all missing records.
     *
     * @param string|null $table
     * @return int
     */
    private function restoreAllMissingRecords(?string $table): int
    {
        $specificTables = $table ? [$table] : null;
        
        // Show summary first
        $summary = $this->restoreService->getComparisonSummary($specificTables);
        $totalMissing = array_sum(array_column($summary, 'missing_records'));
        
        if ($totalMissing === 0) {
            $this->info('No missing records found. All databases are synchronized.');
            return 0;
        }
        
        $this->info("Found {$totalMissing} missing records to restore.");
        
        if (!$this->option('confirm') && !$this->confirm('Do you want to proceed with the restoration?')) {
            $this->info('Restore cancelled.');
            return 0;
        }
        
        $this->info('Starting restoration...');
        
        $result = $this->restoreService->restoreMissingRecords($specificTables);
        
        $this->info("\n=== Restore Results ===");
        $this->info("Total restored records: {$result['total_restored_records']}");
        $this->info("Restore time: {$result['restore_time']}");
        $this->info("Duration: {$result['duration']}");
        
        $this->info("\n=== Per Table Results ===");
        foreach ($result['tables'] as $tableName => $count) {
            $this->line("{$tableName}: {$count} records restored");
        }
        
        $this->info('\nRestore completed successfully!');
        
        return 0;
    }
}