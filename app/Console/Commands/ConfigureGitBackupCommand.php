<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GitBackupService;
use Exception;

class ConfigureGitBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:configure-git 
                            {--name= : Git user name for commits}
                            {--email= : Git user email for commits}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure Git settings for backup repository uploads';

    /**
     * Execute the console command.
     */
    public function handle(GitBackupService $gitService): int
    {
        $this->info('Configuring Git backup settings...');
        
        try {
            // Get user input
            $name = $this->option('name') ?? $this->ask('Enter Git user name', 'AWDA Backup System');
            $email = $this->option('email') ?? $this->ask('Enter Git user email', 'backup@awda-records.com');
            
            // Configure Git user
            if ($gitService->configureGitUser($name, $email)) {
                $this->info("✓ Git user configured: {$name} <{$email}>");
            } else {
                $this->error('✗ Failed to configure Git user');
                return Command::FAILURE;
            }
            
            // Initialize repository
            $this->info('Initializing backup repository...');
            if ($gitService->initializeRepository()) {
                $this->info('✓ Backup repository initialized successfully');
            } else {
                $this->error('✗ Failed to initialize backup repository');
                return Command::FAILURE;
            }
            
            // Display setup information
            $this->newLine();
            $this->info('Git backup configuration completed successfully!');
            $this->newLine();
            
            $this->line('<comment>Repository:</comment> https://github.com/khaled50k/awda-records-db-backup');
            $this->line('<comment>Local path:</comment> ' . storage_path('app/backup-repo'));
            
            $this->newLine();
            $this->info('Backups will be organized in monthly folders (YYYY/M) and uploaded automatically.');
            
            return Command::SUCCESS;
            
        } catch (Exception $e) {
            $this->error('Configuration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}