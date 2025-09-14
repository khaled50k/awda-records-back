# Git Backup Setup Guide

This guide explains how to configure and use the simplified Git backup integration for the AWDA Records backup system.

## Overview

The Git backup feature automatically uploads your full database backups to a GitHub repository, providing:
- **Remote Storage**: Secure off-site backup storage
- **Monthly Organization**: Backups organized by year and month
- **Accessibility**: Access backups from anywhere
- **Redundancy**: Additional backup layer for critical data

## Repository Information

- **Repository URL**: https://github.com/khaled50k/awda-records-db-backup
- **Local Clone Path**: `storage/app/backup-repo`
- **Upload Frequency**: After each backup (every 3 hours)

## Quick Setup

### Step 1: Configure Git Backup

Run the configuration command:

```bash
php artisan backup:configure-git
```

Or with parameters:

```bash
php artisan backup:configure-git --name="AWDA Backup System" --email="backup@awda-records.com"
```

## What Gets Uploaded

### Backup Files
- **SQL Dump Files**: Complete database structure and all data (full backup)
- **Monthly Organization**: Files organized in YYYY/M folder structure

### File Structure
```
backup-repo/
├── 2025/
│   ├── 8/
│   │   ├── full_backup_2025-08-15_14-30-15.sql
│   │   └── full_backup_2025-08-20_18-45-22.sql
│   └── 9/
│       ├── full_backup_2025-09-01_09-15-30.sql
│       └── full_backup_2025-09-13_14-29-14.sql
└── ...
```

### Commit Messages

Automatic commits use descriptive messages:
```
Full Database Backup - 2025-09-13_14-29-14
```

## Testing the Setup

### Test Git Configuration

```bash
# Test the configuration command
php artisan backup:configure-git

# Verify Git settings
cd storage/app/backup-repo
git config user.name
git config user.email
```

### Test Backup with Git Upload

```bash
# Run a test backup
php artisan backup:incremental --sync
```

Look for the "Git Repository Upload" status in the results table.

### Manual Git Operations

```bash
# Navigate to backup repository
cd storage/app/backup-repo

# Check status
git status

# View recent commits
git log --oneline -5

# Test push
git push origin main
```

## Monitoring and Logs

### Laravel Logs

Check `storage/logs/laravel.log` for:

```
[2025-01-10 15:00:00] local.INFO: Backup successfully uploaded to Git repository
[2025-01-10 15:00:00] local.ERROR: Git upload failed: {"error":"..."}
```

### Git Repository

Monitor the GitHub repository:
- **Commits**: Check for regular backup commits
- **Files**: Verify backup files are being created
- **Issues**: Check for any push failures

## Troubleshooting

### Common Issues

1. **Repository Not Found**
   ```
   Solution: Ensure repository exists and you have access
   ```

2. **Git Not Installed**
   ```
   Solution: Install Git and ensure it's in system PATH
   ```

3. **Permission Denied**
   ```
   Solution: Check file permissions on storage/app directory
   ```

### Debug Commands

```bash
# Check Git installation
git --version

# Test repository access
git ls-remote https://github.com/khaled50k/awda-records-db-backup.git

# Check local repository status
cd storage/app/backup-repo && git status

# View Git configuration
git config --list
```

### Reset Repository

If you need to start fresh:

```bash
# Remove local repository
rm -rf storage/app/backup-repo

# Reconfigure
php artisan backup:configure-git
```

## Security Considerations

- **Never commit sensitive data**: Only metadata is uploaded, not actual database content
- **Monitor repository access**: Check who has access to the backup repository
- **Use private repository**: Ensure the backup repository is private

## Automation

The Git upload is fully automated:

1. **Scheduler runs every 3 hours**
2. **Backup executes successfully**
3. **Git service automatically uploads metadata**
4. **Commit and push to GitHub**
5. **Status logged and displayed**

No manual intervention required once configured!