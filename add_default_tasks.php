<?php
/**
 * Script to add default tasks to existing cBackup installation
 * Run this script if default tasks are missing after installation
 * 
 * Usage: docker compose exec web php /var/www/html/add_default_tasks.php
 */

require __DIR__ . '/core/config/db.php';

try {
    $pdo = new PDO($db['dsn'], $db['username'], $db['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $defaultTasks = [
        ['backup', 'backup', 'database', 'out_backup', 1, 'Backup task - stores configuration backups in database'],
        ['discovery', 'discovery', null, null, 1, 'Discovery task - automatically discovers network devices'],
        ['stp', 'custom', 'database', 'out_stp', 1, 'STP information collection task'],
        ['save', 'custom', 'file', null, 1, 'Save task - saves configurations to file system'],
        ['git_commit', 'custom', 'git', null, 1, 'Git commit task - commits changes to Git repository'],
        ['log_processing', 'custom', null, null, 1, 'Log processing task'],
        ['node_processing', 'custom', null, null, 1, 'Node processing task']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO `task` (`name`, `task_type`, `put`, `table`, `protected`, `description`) VALUES (?, ?, ?, ?, ?, ?)");
    
    $added = 0;
    $skipped = 0;
    
    foreach ($defaultTasks as $task) {
        try {
            $stmt->execute($task);
            if ($stmt->rowCount() > 0) {
                echo "✓ Added task: {$task[0]}\n";
                $added++;
            } else {
                echo "- Task already exists: {$task[0]}\n";
                $skipped++;
            }
        } catch (Exception $e) {
            echo "✗ Error adding task {$task[0]}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nSummary: Added {$added} tasks, skipped {$skipped} existing tasks.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

