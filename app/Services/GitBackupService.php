<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class GitBackupService
{
    private string $repoPath;
    private string $repoUrl;
    
    public function __construct()
    {
        $this->repoPath = storage_path('app/backup-repo');
        $this->repoUrl = 'https://github.com/khaled50k/awda-records-db-backup.git';
    }
    
    /**
     * Initialize or update the Git repository
     */
    public function initializeRepository(): bool
    {
        try {
            if (!is_dir($this->repoPath)) {
                // Clone the repository if it doesn't exist
                $cloneCommand = "git clone {$this->repoUrl} \"" . $this->repoPath . "\"";
                exec($cloneCommand, $output, $returnCode);
                
                if ($returnCode !== 0) {
                    Log::error('Failed to clone backup repository', [
                        'command' => $cloneCommand,
                        'output' => $output,
                        'return_code' => $returnCode
                    ]);
                    return false;
                }
                
                Log::info('Backup repository cloned successfully');
            } else {
                // Pull latest changes if repository exists
                $this->executeGitCommand('git pull origin main');
                Log::info('Backup repository updated');
            }
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to initialize backup repository', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Upload backup to Git repository
     */
    public function uploadBackup(array $backupResult): bool
    {
        try {
            if (!$this->initializeRepository()) {
                return false;
            }
            
            // Create monthly folder structure (e.g., 2025/8)
            $year = date('Y');
            $month = date('n'); // Month without leading zeros
            $monthFolder = "{$year}/{$month}";
            $monthPath = $this->repoPath . '/' . $monthFolder;
            
            // Create directory if it doesn't exist
            if (!is_dir($monthPath)) {
                mkdir($monthPath, 0755, true);
            }
            
            $timestamp = date('Y-m-d_H-i-s');
            $sqlFileName = "full_backup_{$timestamp}.sql";
            
            // Create SQL backup file in monthly folder
            $sqlFilePath = $monthPath . '/' . $sqlFileName;
            $this->createFullSqlBackupFile($sqlFilePath);
            
            // Add files to git
            $this->executeGitCommand('git add .');
            
            // Commit changes
            $commitMessage = "Full Database Backup - {$timestamp}";
            $this->executeGitCommand("git commit -m \"{$commitMessage}\"");
            
            // Push to remote
            $this->executeGitCommand('git push origin main');
            
            Log::info('Backup uploaded successfully', [
                'sql_file' => "{$monthFolder}/{$sqlFileName}",
                'timestamp' => $timestamp
            ]);
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to upload backup to Git', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
 
    
    /**
     * Create full SQL backup file with all database data
     */
    private function createFullSqlBackupFile(string $filePath): void
    {
        $sql = "-- AWDA Records Full Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Backup Type: Full Database\n\n";
        
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        
        // Get database connection
        $backupConnection = config('database.connections.backup');
        $pdo = new \PDO(
            "mysql:host={$backupConnection['host']};port={$backupConnection['port']};dbname={$backupConnection['database']}",
            $backupConnection['username'],
            $backupConnection['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        
        // Get all tables from the database
        $tablesQuery = "SHOW TABLES";
        $tablesStmt = $pdo->query($tablesQuery);
        $tables = $tablesStmt->fetchAll(\PDO::FETCH_COLUMN);
        
        foreach ($tables as $tableName) {
            // Skip Laravel system tables
            if (in_array($tableName, ['migrations', 'failed_jobs', 'password_reset_tokens', 'personal_access_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches'])) {
                continue;
            }
            
            $sql .= $this->generateFullTableSql($pdo, $tableName);
        }
        
        $sql .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
        
        file_put_contents($filePath, $sql);
    }
    
    /**
     * Generate full SQL dump for a specific table
     */
    private function generateFullTableSql(\PDO $pdo, string $tableName): string
    {
        $sql = "\n-- Table: {$tableName}\n";
        
        try {
            // Get table structure
            $createTableStmt = $pdo->query("SHOW CREATE TABLE `{$tableName}`");
            $createTableResult = $createTableStmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($createTableResult) {
                $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sql .= $createTableResult['Create Table'] . ";\n\n";
            }
            
            // Get all data from the table
            $dataQuery = "SELECT * FROM `{$tableName}`";
            $stmt = $pdo->query($dataQuery);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                // Get column names
                $columns = array_keys($rows[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';
                
                $sql .= "INSERT INTO `{$tableName}` ({$columnList}) VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $rowValues = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $rowValues[] = 'NULL';
                        } else {
                            $rowValues[] = $pdo->quote($value);
                        }
                    }
                    $values[] = '(' . implode(', ', $rowValues) . ')';
                }
                
                $sql .= implode(",\n", $values) . ";\n\n";
            } else {
                $sql .= "-- No data in table {$tableName}\n\n";
            }
            
        } catch (\Exception $e) {
            $sql .= "-- Error generating SQL for table {$tableName}: " . $e->getMessage() . "\n\n";
        }
        
        return $sql;
    }
    
    /**
     * Execute a Git command in the repository directory
     */
    private function executeGitCommand(string $command): array
    {
        $fullCommand = "cd \"" . $this->repoPath . "\" && " . $command;
        exec($fullCommand, $output, $returnCode);
        
        if ($returnCode !== 0) {
            Log::warning('Git command failed', [
                'command' => $command,
                'output' => $output,
                'return_code' => $returnCode
            ]);
        }
        
        return $output;
    }
    
    /**
     * Configure Git user for commits (call this once)
     */
    public function configureGitUser(string $name = 'AWDA Backup System', string $email = 'backup@awda-records.com'): bool
    {
        try {
            $this->executeGitCommand("git config user.name \"$name\"");
            $this->executeGitCommand("git config user.email \"$email\"");
            
            Log::info('Git user configured', ['name' => $name, 'email' => $email]);
            return true;
        } catch (Exception $e) {
            Log::error('Failed to configure Git user', ['error' => $e->getMessage()]);
            return false;
        }
    }
}