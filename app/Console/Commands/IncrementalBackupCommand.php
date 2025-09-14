<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\IncrementalDatabaseBackupJob;
use App\Services\IncrementalBackupService;
use Illuminate\Support\Facades\Log;
use Exception;

class IncrementalBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:incremental 
                            {--queue : Run the backup job in queue}
                            {--sync : Run the backup synchronously (default)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform incremental database backup';

    /**
     * Execute the console command.
     */
    public function handle(IncrementalBackupService $backupService): int
    {
        $this->info('Starting incremental database backup...');
        
        try {
            if ($this->option('queue')) {
                // Dispatch job to queue
                IncrementalDatabaseBackupJob::dispatch();
                $this->info('Incremental backup job has been queued successfully.');
                return Command::SUCCESS;
            }
            
            // Run backup synchronously
            $this->info('Running backup synchronously...');
            $this->withProgressBar(1, function () use ($backupService) {
                $result = $backupService->performIncrementalBackup();
                $this->displayBackupResults($result);
            });
            
            $this->newLine(2);
            $this->info('Incremental backup completed successfully!');
            
            return Command::SUCCESS;
            
        } catch (Exception $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            Log::error('Console backup command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
    
    /**
     * Display backup results in a formatted table.
     *
     * @param array $result
     * @return void
     */
    private function displayBackupResults(array $result): void
    {
        $this->newLine();
        $this->info('Backup Results:');
        $this->line('================');
        
        $gitStatus = 'Not attempted';
        if (isset($result['git_upload'])) {
            if ($result['git_upload'] === 'skipped') {
                $gitStatus = '⊘ Skipped (no changes)';
            } elseif ($result['git_upload']) {
                $gitStatus = '✓ Success';
            } else {
                $gitStatus = '✗ Failed';
            }
        }
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Records Backed Up', $result['total_records']],
                ['Tables Processed', count($result['tables'])],
                ['Backup Time', $result['backup_time']],
                ['Last Backup Time', $result['last_backup_time'] ?? 'First backup'],
                ['Git Repository Upload', $gitStatus]
            ]
        );
        
        if (!empty($result['tables'])) {
            $this->newLine();
            $this->info('Records per Table:');
            $this->line('==================');
            
            $tableData = [];
            foreach ($result['tables'] as $table => $count) {
                $tableData[] = [$table, $count];
            }
            
            $this->table(['Table', 'Records Backed Up'], $tableData);
        }
    }
}