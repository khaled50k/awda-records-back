# Laravel Scheduler Runner - 24/7 Service
# This script runs the Laravel scheduler continuously

Write-Host "Starting Laravel Scheduler - Running 24/7" -ForegroundColor Green
Write-Host "Press Ctrl+C to stop" -ForegroundColor Yellow
Write-Host ""

# Set location to Laravel project directory
Set-Location -Path $PSScriptRoot

try {
    while ($true) {
        $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
        Write-Host "[$timestamp] Running scheduler..." -ForegroundColor Cyan
        
        # Run the Laravel scheduler
        & php artisan schedule:run
        
        # Wait 60 seconds before next check
        Start-Sleep -Seconds 60
    }
}
catch {
    Write-Host "Scheduler stopped: $($_.Exception.Message)" -ForegroundColor Red
}
finally {
    Write-Host "Laravel Scheduler has been stopped." -ForegroundColor Yellow
}