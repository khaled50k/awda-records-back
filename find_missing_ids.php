<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Finding Missing Record IDs ===\n\n";

$tables = ['medical_records', 'record_transfers'];

foreach ($tables as $table) {
    echo "Checking table: {$table}\n";
    
    try {
        // Get all IDs from backup database
        $backupIds = DB::connection('backup')->table($table)->pluck('record_id')->toArray();
        
        // Get all IDs from current database
        $currentIds = DB::connection('mysql')->table($table)->pluck('record_id')->toArray();
        
        // Find IDs that exist in backup but not in current
        $missingIds = array_diff($backupIds, $currentIds);
        
        echo "  Backup DB has: " . count($backupIds) . " records\n";
        echo "  Current DB has: " . count($currentIds) . " records\n";
        echo "  Missing from current: " . count($missingIds) . " records\n";
        
        if (!empty($missingIds)) {
            echo "  Missing IDs: " . implode(', ', $missingIds) . "\n";
            
            // Show sample of missing records
            $sampleRecords = DB::connection('backup')
                ->table($table)
                ->whereIn('record_id', array_slice($missingIds, 0, 3))
                ->get(['record_id', 'created_at', 'updated_at']);
                
            echo "  Sample missing records:\n";
            foreach ($sampleRecords as $record) {
                echo "    ID: {$record->record_id}, Created: {$record->created_at}, Updated: {$record->updated_at}\n";
            }
        }
        
        echo "\n";
        
    } catch (Exception $e) {
        echo "  Error: {$e->getMessage()}\n\n";
    }
}

echo "=== End of Analysis ===\n";