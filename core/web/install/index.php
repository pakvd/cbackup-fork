<?php
/**
 * cBackup Installation Wizard
 * Full installer that creates database schema and initializes system
 */

// Load config without Yii
$configPath = __DIR__ . '/../../config/web.php';
$basePath = dirname(dirname(__DIR__));

// Check if already installed - check multiple locations
$installLockPath = $basePath . '/install.lock';
$runtimeLockPath = $basePath . '/runtime/install.lock';
if (file_exists($installLockPath) || file_exists($runtimeLockPath)) {
    header("Location: /");
    exit();
}

// Load database config
$dbConfig = require(__DIR__ . '/../../config/db.php');

// Extract DB credentials
$dsn = $dbConfig['dsn'];
preg_match('/host=([^;]+)/', $dsn, $hostMatch);
preg_match('/dbname=([^;]+)/', $dsn, $dbMatch);
preg_match('/port=([^;]+)/', $dsn, $portMatch);

$dbHost = $hostMatch[1] ?? 'localhost';
$dbName = $dbMatch[1] ?? 'cbackup';
$dbPort = $portMatch[1] ?? '3306';
$dbUser = $dbConfig['username'] ?? 'cbackup';
$dbPass = $dbConfig['password'] ?? '';

$errors = [];
$success = false;
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$maxSteps = 3;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo = new PDO("mysql:host={$dbHost};port={$dbPort}", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $action = $_POST['action'];
        
        if ($action === 'run_migrations') {
            // Create database tables directly via SQL
            try {
                $pdo->exec("USE `{$dbName}`");
                
                // Create Yii2 RBAC tables first (before other tables)
                $pdo->exec("CREATE TABLE IF NOT EXISTS `auth_rule` (
                    `name` VARCHAR(64) NOT NULL PRIMARY KEY,
                    `data` BLOB DEFAULT NULL,
                    `created_at` INT(11) DEFAULT NULL,
                    `updated_at` INT(11) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                $pdo->exec("CREATE TABLE IF NOT EXISTS `auth_item` (
                    `name` VARCHAR(64) NOT NULL PRIMARY KEY,
                    `type` SMALLINT(6) NOT NULL,
                    `description` TEXT DEFAULT NULL,
                    `rule_name` VARCHAR(64) DEFAULT NULL,
                    `data` BLOB DEFAULT NULL,
                    `created_at` INT(11) DEFAULT NULL,
                    `updated_at` INT(11) DEFAULT NULL,
                    KEY `idx-auth_item-type` (`type`),
                    KEY `fk_auth_item_rule_name` (`rule_name`),
                    CONSTRAINT `fk_auth_item_rule_name` FOREIGN KEY (`rule_name`) REFERENCES `auth_rule` (`name`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                $pdo->exec("CREATE TABLE IF NOT EXISTS `auth_item_child` (
                    `parent` VARCHAR(64) NOT NULL,
                    `child` VARCHAR(64) NOT NULL,
                    PRIMARY KEY (`parent`, `child`),
                    KEY `fk_auth_item_child_parent` (`parent`),
                    KEY `fk_auth_item_child_child` (`child`),
                    CONSTRAINT `fk_auth_item_child_parent` FOREIGN KEY (`parent`) REFERENCES `auth_item` (`name`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `fk_auth_item_child_child` FOREIGN KEY (`child`) REFERENCES `auth_item` (`name`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create user table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `user` (
                    `userid` VARCHAR(128) NOT NULL PRIMARY KEY,
                    `auth_key` VARCHAR(32) DEFAULT NULL,
                    `password_hash` VARCHAR(255) NOT NULL,
                    `access_token` VARCHAR(128) DEFAULT NULL,
                    `fullname` VARCHAR(128) NOT NULL,
                    `email` VARCHAR(128) DEFAULT NULL,
                    `enabled` INT(11) NOT NULL DEFAULT 1,
                    UNIQUE KEY `email` (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create other essential tables
                $pdo->exec("CREATE TABLE IF NOT EXISTS `config` (
                    `key` VARCHAR(128) NOT NULL PRIMARY KEY,
                    `value` TEXT DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                $pdo->exec("CREATE TABLE IF NOT EXISTS `setting` (
                    `key` VARCHAR(128) NOT NULL PRIMARY KEY,
                    `value` TEXT DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                $pdo->exec("CREATE TABLE IF NOT EXISTS `setting_override` (
                    `key` VARCHAR(128) NOT NULL,
                    `userid` VARCHAR(128) NOT NULL,
                    `value` TEXT DEFAULT NULL,
                    PRIMARY KEY (`key`, `userid`),
                    KEY `fk_setting_override_user` (`userid`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create vendor table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `vendor` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(128) NOT NULL UNIQUE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create device table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `device` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `vendor_id` INT(11) NOT NULL,
                    `name` VARCHAR(128) NOT NULL,
                    `auth_template_name` VARCHAR(64) DEFAULT NULL,
                    `description` VARCHAR(255) DEFAULT NULL,
                    UNIQUE KEY `vendor_device` (`vendor_id`, `name`),
                    CONSTRAINT `fk_device_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendor` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create credential table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `credential` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(128) NOT NULL UNIQUE,
                    `telnet_login` VARCHAR(128) DEFAULT NULL,
                    `telnet_password` VARCHAR(128) DEFAULT NULL,
                    `ssh_login` VARCHAR(128) DEFAULT NULL,
                    `ssh_password` VARCHAR(128) DEFAULT NULL,
                    `snmp_read` VARCHAR(128) DEFAULT NULL,
                    `snmp_set` VARCHAR(128) DEFAULT NULL,
                    `snmp_version` INT(11) DEFAULT NULL,
                    `snmp_encryption` VARCHAR(128) DEFAULT NULL,
                    `enable_password` VARCHAR(128) DEFAULT NULL,
                    `port_telnet` INT(11) DEFAULT NULL,
                    `port_ssh` INT(11) DEFAULT NULL,
                    `port_snmp` INT(11) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create network table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `network` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `credential_id` INT(11) NOT NULL,
                    `network` VARCHAR(18) NOT NULL UNIQUE,
                    `discoverable` INT(11) DEFAULT 1,
                    `description` VARCHAR(255) DEFAULT NULL,
                    CONSTRAINT `fk_network_credential` FOREIGN KEY (`credential_id`) REFERENCES `credential` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create node table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `node` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `ip` VARCHAR(15) NOT NULL UNIQUE,
                    `network_id` INT(11) DEFAULT NULL,
                    `credential_id` INT(11) DEFAULT NULL,
                    `device_id` INT(11) NOT NULL,
                    `auth_template_name` VARCHAR(64) DEFAULT NULL,
                    `mac` VARCHAR(12) DEFAULT NULL,
                    `created` DATETIME DEFAULT NULL,
                    `modified` DATETIME DEFAULT NULL,
                    `last_seen` DATETIME DEFAULT NULL,
                    `manual` INT(11) DEFAULT 0,
                    `hostname` VARCHAR(255) DEFAULT NULL,
                    `serial` VARCHAR(45) DEFAULT NULL,
                    `prepend_location` VARCHAR(255) DEFAULT NULL,
                    `location` VARCHAR(255) DEFAULT NULL,
                    `contact` VARCHAR(255) DEFAULT NULL,
                    `sys_description` VARCHAR(1024) DEFAULT NULL,
                    `protected` INT(11) DEFAULT 0,
                    CONSTRAINT `fk_node_network` FOREIGN KEY (`network_id`) REFERENCES `network` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                    CONSTRAINT `fk_node_credential` FOREIGN KEY (`credential_id`) REFERENCES `credential` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                    CONSTRAINT `fk_node_device` FOREIGN KEY (`device_id`) REFERENCES `device` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create auth_assignment table (after user table is created)
                $pdo->exec("CREATE TABLE IF NOT EXISTS `auth_assignment` (
                    `item_name` VARCHAR(64) NOT NULL,
                    `user_id` VARCHAR(128) NOT NULL,
                    `created_at` INT(11) DEFAULT NULL,
                    PRIMARY KEY (`item_name`, `user_id`),
                    KEY `fk_auth_assignment_item_name` (`item_name`),
                    KEY `fk_auth_assignment_user_id` (`user_id`),
                    CONSTRAINT `fk_auth_assignment_item_name` FOREIGN KEY (`item_name`) REFERENCES `auth_item` (`name`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Try to add foreign key to user table if it exists
                try {
                    $stmt = $pdo->query("SHOW TABLES LIKE 'user'");
                    if ($stmt->rowCount() > 0) {
                        try {
                            $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = 'auth_assignment' AND CONSTRAINT_NAME = 'fk_auth_assignment_user_id'");
                            if ($stmt->rowCount() === 0) {
                                $pdo->exec("ALTER TABLE `auth_assignment` ADD CONSTRAINT `fk_auth_assignment_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`userid`) ON DELETE CASCADE ON UPDATE CASCADE");
                            }
                        } catch (Exception $e) {
                            // Foreign key might already exist or user table structure is different
                        }
                    }
                } catch (Exception $e) {
                    // User table might not exist yet
                }
                
                // Create severity table (BEFORE inserting data!)
                $pdo->exec("CREATE TABLE IF NOT EXISTS `severity` (
                    `name` VARCHAR(32) NOT NULL PRIMARY KEY
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create schedule_type table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `schedule_type` (
                    `name` VARCHAR(32) NOT NULL PRIMARY KEY
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create task_type table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `task_type` (
                    `name` VARCHAR(32) NOT NULL PRIMARY KEY
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create task_destination table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `task_destination` (
                    `name` VARCHAR(16) NOT NULL PRIMARY KEY,
                    `description` VARCHAR(255) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create worker_protocol table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `worker_protocol` (
                    `name` VARCHAR(16) NOT NULL PRIMARY KEY
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create mailer_events_tasks_statuses table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `mailer_events_tasks_statuses` (
                    `name` VARCHAR(64) NOT NULL PRIMARY KEY
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Messages table will be created later with foreign key to user
                
                // Insert default vendor and device
                $pdo->exec("INSERT IGNORE INTO `vendor` (`id`, `name`) VALUES (1, 'Generic')");
                $pdo->exec("INSERT IGNORE INTO `device` (`id`, `vendor_id`, `name`) VALUES (1, 1, 'Generic / Default')");
                
                // Insert default severity levels
                $pdo->exec("INSERT IGNORE INTO `severity` (`name`) VALUES ('error'), ('warning'), ('info'), ('debug')");
                
                // Insert default schedule types
                $pdo->exec("INSERT IGNORE INTO `schedule_type` (`name`) VALUES ('discovery'), ('backup'), ('custom')");
                
                // Insert default task types
                $pdo->exec("INSERT IGNORE INTO `task_type` (`name`) VALUES ('backup'), ('discovery'), ('custom')");
                
                // Insert default task destinations
                $pdo->exec("INSERT IGNORE INTO `task_destination` (`name`, `description`) VALUES 
                    ('database', 'Store in database'),
                    ('file', 'Store in file system'),
                    ('git', 'Store in Git repository')");
                
                // Insert default worker protocols
                $pdo->exec("INSERT IGNORE INTO `worker_protocol` (`name`) VALUES ('telnet'), ('ssh'), ('snmp')");
                
                // Insert default mailer event task statuses
                $pdo->exec("INSERT IGNORE INTO `mailer_events_tasks_statuses` (`name`) VALUES 
                    ('pending'), ('sent'), ('failed'), ('cancelled')");
                
                // Insert default settings
                $pdo->exec("INSERT IGNORE INTO `setting` (`key`, `value`) VALUES 
                    ('language', 'en-US'),
                    ('sidebar_collapsed', '0'),
                    ('datetime', 'Y-m-d H:i:s')");
                
                // Insert default config values
                $pdo->exec("INSERT IGNORE INTO `config` (`key`, `value`) VALUES
                    ('isolated', '0'),
                    ('adminEmail', 'admin@localhost'),
                    ('dataPath', '/var/www/html/data'),
                    ('logLifetime', '30'),
                    ('nodeLifetime', '365'),
                    ('snmpRetries', '3'),
                    ('snmpTimeout', '5000'),
                    ('threadCount', '5'),
                    ('git', '0'),
                    ('gitRemote', ''),
                    ('gitUsername', ''),
                    ('gitEmail', ''),
                    ('gitDays', '30'),
                    ('gitRepo', ''),
                    ('gitLogin', ''),
                    ('gitPassword', ''),
                    ('gitPath', ''),
                    ('mailer', 'php'),
                    ('mailerType', 'php'),
                    ('mailerFromEmail', ''),
                    ('mailerFromName', ''),
                    ('mailerSendMailPath', '/usr/sbin/sendmail'),
                    ('mailerSmtpSslVerify', '1'),
                    ('mailerSmtpHost', ''),
                    ('mailerSmtpPort', '25'),
                    ('mailerSmtpSecurity', 'none'),
                    ('mailerSmtpAuth', '0'),
                    ('mailerSmtpUsername', ''),
                    ('mailerSmtpPassword', ''),
                    ('telnetTimeout', '30'),
                    ('telnetBeforeSendDelay', '100'),
                    ('sshTimeout', '30'),
                    ('sshBeforeSendDelay', '100'),
                    ('systemLogLevel', 'info'),
                    ('defaultPrependLocation', ''),
                    ('javaServerUsername', ''),
                    ('javaServerPort', '8080'),
                    ('javaServerPassword', ''),
                    ('javaSchedulerUsername', ''),
                    ('javaSchedulerPort', '8081'),
                    ('javaSchedulerPassword', '')");
                
                // Create out_backup table for storing backup configurations
                $pdo->exec("CREATE TABLE IF NOT EXISTS `out_backup` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `node_id` INT(11) NOT NULL UNIQUE,
                    `hash` VARCHAR(255) NOT NULL,
                    `time` DATETIME NOT NULL,
                    `config` TEXT DEFAULT NULL,
                    CONSTRAINT `fk_out_backup_node` FOREIGN KEY (`node_id`) REFERENCES `node` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    KEY `idx_out_backup_time` (`time`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create out_stp table for STP information
                $pdo->exec("CREATE TABLE IF NOT EXISTS `out_stp` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `node_id` INT(11) NOT NULL UNIQUE,
                    `hash` VARCHAR(255) NOT NULL,
                    `time` DATETIME NOT NULL,
                    `node_mac` VARCHAR(12) DEFAULT NULL,
                    `root_port` VARCHAR(45) DEFAULT NULL,
                    `root_mac` VARCHAR(12) DEFAULT NULL,
                    `bridge_mac` VARCHAR(12) DEFAULT NULL,
                    CONSTRAINT `fk_out_stp_node` FOREIGN KEY (`node_id`) REFERENCES `node` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    KEY `idx_out_stp_time` (`time`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create log_scheduler table for scheduler logs
                $pdo->exec("CREATE TABLE IF NOT EXISTS `log_scheduler` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `userid` VARCHAR(128) DEFAULT NULL,
                    `time` DATETIME NOT NULL,
                    `severity` VARCHAR(32) DEFAULT NULL,
                    `schedule_type` VARCHAR(32) DEFAULT NULL,
                    `schedule_id` INT(11) DEFAULT NULL,
                    `node_id` INT(11) DEFAULT NULL,
                    `action` VARCHAR(45) DEFAULT NULL,
                    `message` TEXT NOT NULL,
                    CONSTRAINT `fk_log_scheduler_node` FOREIGN KEY (`node_id`) REFERENCES `node` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                    KEY `idx_log_scheduler_time` (`time`),
                    KEY `idx_log_scheduler_schedule_id` (`schedule_id`),
                    KEY `idx_log_scheduler_node_id` (`node_id`),
                    KEY `idx_log_scheduler_action` (`action`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create log_node table for node logs
                $pdo->exec("CREATE TABLE IF NOT EXISTS `log_node` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `userid` VARCHAR(128) DEFAULT NULL,
                    `time` DATETIME NOT NULL,
                    `severity` VARCHAR(32) DEFAULT NULL,
                    `node_id` INT(11) DEFAULT NULL,
                    `action` VARCHAR(45) DEFAULT NULL,
                    `message` TEXT NOT NULL,
                    CONSTRAINT `fk_log_node_node` FOREIGN KEY (`node_id`) REFERENCES `node` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                    KEY `idx_log_node_time` (`time`),
                    KEY `idx_log_node_node_id` (`node_id`),
                    KEY `idx_log_node_action` (`action`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create log_system table for system logs
                $pdo->exec("CREATE TABLE IF NOT EXISTS `log_system` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `userid` VARCHAR(128) DEFAULT NULL,
                    `time` DATETIME NOT NULL,
                    `severity` VARCHAR(32) DEFAULT NULL,
                    `category` VARCHAR(255) DEFAULT NULL,
                    `message` TEXT NOT NULL,
                    KEY `idx_log_system_time` (`time`),
                    KEY `idx_log_system_category` (`category`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create log_mailer table for mailer logs
                $pdo->exec("CREATE TABLE IF NOT EXISTS `log_mailer` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `userid` VARCHAR(128) DEFAULT NULL,
                    `time` DATETIME NOT NULL,
                    `severity` VARCHAR(32) DEFAULT NULL,
                    `action` VARCHAR(45) DEFAULT NULL,
                    `event_task_id` INT(11) DEFAULT NULL,
                    `message` TEXT NOT NULL,
                    KEY `idx_log_mailer_time` (`time`),
                    KEY `idx_log_mailer_action` (`action`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create or fix task table (ensure correct structure with 'table' column instead of 'table_name')
                $pdo->exec("CREATE TABLE IF NOT EXISTS `task` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(64) NOT NULL UNIQUE,
                    `task_type` VARCHAR(32) DEFAULT NULL,
                    `put` VARCHAR(32) DEFAULT NULL,
                    `table` VARCHAR(255) DEFAULT NULL,
                    `yii_command` VARCHAR(255) DEFAULT NULL,
                    `protected` INT(11) DEFAULT 0,
                    `description` VARCHAR(255) DEFAULT NULL,
                    KEY `idx_task_type` (`task_type`),
                    KEY `idx_task_put` (`put`),
                    CONSTRAINT `fk_task_type` FOREIGN KEY (`task_type`) REFERENCES `task_type` (`name`) ON DELETE SET NULL ON UPDATE CASCADE,
                    CONSTRAINT `fk_task_destination` FOREIGN KEY (`put`) REFERENCES `task_destination` (`name`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Fix existing task table if it has table_name instead of table
                try {
                    $stmt = $pdo->query("SHOW COLUMNS FROM `task` LIKE 'table_name'");
                    if ($stmt->rowCount() > 0) {
                        $pdo->exec("ALTER TABLE `task` CHANGE COLUMN `table_name` `table` VARCHAR(255) DEFAULT NULL");
                    }
                } catch (Exception $e) {
                    // Column might not exist, continue
                }
                
                // Create schedule table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `schedule` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `task_name` VARCHAR(255) NOT NULL UNIQUE,
                    `schedule_cron` VARCHAR(255) NOT NULL,
                    CONSTRAINT `fk_schedule_task` FOREIGN KEY (`task_name`) REFERENCES `task` (`name`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Insert default tasks (system tasks that should exist by default)
                // These tasks are referenced in documentation and may be used by the system
                try {
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
                    foreach ($defaultTasks as $task) {
                        $stmt->execute($task);
                    }
                } catch (Exception $e) {
                    // Log error but continue installation
                    error_log("Failed to insert default tasks: " . $e->getMessage());
                }
                
                // Create worker table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `worker` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(255) NOT NULL UNIQUE,
                    `task_name` VARCHAR(255) NOT NULL,
                    `get` VARCHAR(16) NOT NULL,
                    `description` VARCHAR(255) DEFAULT NULL,
                    CONSTRAINT `fk_worker_task` FOREIGN KEY (`task_name`) REFERENCES `task` (`name`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `fk_worker_protocol` FOREIGN KEY (`get`) REFERENCES `worker_protocol` (`name`) ON DELETE RESTRICT ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create job table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `job` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(255) NOT NULL,
                    `worker_id` INT(11) NOT NULL,
                    `sequence_id` INT(11) DEFAULT NULL,
                    `command_value` VARCHAR(255) NOT NULL,
                    `command_var` VARCHAR(255) DEFAULT NULL,
                    `cli_custom_prompt` VARCHAR(255) DEFAULT NULL,
                    `snmp_request_type` VARCHAR(32) DEFAULT NULL,
                    `snmp_set_value` VARCHAR(255) DEFAULT NULL,
                    `snmp_set_value_type` VARCHAR(32) DEFAULT NULL,
                    `timeout` INT(11) DEFAULT NULL,
                    `table_field` VARCHAR(255) DEFAULT NULL,
                    `enabled` INT(11) DEFAULT 1,
                    `description` VARCHAR(255) DEFAULT NULL,
                    CONSTRAINT `fk_job_worker` FOREIGN KEY (`worker_id`) REFERENCES `worker` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    KEY `idx_job_worker` (`worker_id`),
                    KEY `idx_job_sequence` (`sequence_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create tasks_has_devices table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `tasks_has_devices` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `task_name` VARCHAR(255) NOT NULL,
                    `device_id` INT(11) NOT NULL,
                    `worker_id` INT(11) NOT NULL,
                    UNIQUE KEY `task_device` (`task_name`, `device_id`),
                    CONSTRAINT `fk_tasks_has_devices_task` FOREIGN KEY (`task_name`) REFERENCES `task` (`name`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `fk_tasks_has_devices_device` FOREIGN KEY (`device_id`) REFERENCES `device` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `fk_tasks_has_devices_worker` FOREIGN KEY (`worker_id`) REFERENCES `worker` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create tasks_has_nodes table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `tasks_has_nodes` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `node_id` INT(11) NOT NULL,
                    `task_name` VARCHAR(255) NOT NULL,
                    `worker_id` INT(11) NOT NULL,
                    UNIQUE KEY `node_task` (`node_id`, `task_name`),
                    CONSTRAINT `fk_tasks_has_nodes_node` FOREIGN KEY (`node_id`) REFERENCES `node` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `fk_tasks_has_nodes_task` FOREIGN KEY (`task_name`) REFERENCES `task` (`name`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `fk_tasks_has_nodes_worker` FOREIGN KEY (`worker_id`) REFERENCES `worker` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create messages table (after user table for foreign key)
                $pdo->exec("CREATE TABLE IF NOT EXISTS `messages` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `message` TEXT NOT NULL,
                    `created` DATETIME DEFAULT NULL,
                    `approved` DATETIME DEFAULT NULL,
                    `approved_by` VARCHAR(128) DEFAULT NULL,
                    KEY `ix_time` (`created`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Add foreign key for messages after user table exists
                try {
                    $stmt = $pdo->query("SHOW TABLES LIKE 'user'");
                    if ($stmt->rowCount() > 0) {
                        try {
                            $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = 'messages' AND CONSTRAINT_NAME = 'fk_messages_user'");
                            if ($stmt->rowCount() === 0) {
                                $pdo->exec("ALTER TABLE `messages` ADD CONSTRAINT `fk_messages_user` FOREIGN KEY (`approved_by`) REFERENCES `user` (`userid`) ON DELETE SET NULL ON UPDATE CASCADE");
                            }
                        } catch (Exception $e) {
                            // Foreign key might already exist
                        }
                    }
                } catch (Exception $e) {
                    // User table might not exist yet
                }
                
                // Create alt_interface table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `alt_interface` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `node_id` INT(11) NOT NULL,
                    `ip` VARCHAR(15) NOT NULL,
                    CONSTRAINT `fk_alt_interface_node` FOREIGN KEY (`node_id`) REFERENCES `node` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create exclusion table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `exclusion` (
                    `ip` VARCHAR(15) NOT NULL PRIMARY KEY,
                    `description` VARCHAR(255) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create device_auth_template table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `device_auth_template` (
                    `name` VARCHAR(64) NOT NULL PRIMARY KEY,
                    `auth_sequence` TEXT NOT NULL,
                    `description` VARCHAR(255) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create device_attributes_unknown table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `device_attributes_unknown` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `ip` VARCHAR(15) DEFAULT NULL,
                    `sysobject_id` VARCHAR(255) DEFAULT NULL,
                    `hw` VARCHAR(255) DEFAULT NULL,
                    `sys_description` VARCHAR(1024) DEFAULT NULL,
                    `created` DATETIME DEFAULT NULL,
                    KEY `idx_sysobject_hw_sysdescr` (`sysobject_id`(191), `hw`(191), `sys_description`(191))
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create device_attributes table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `device_attributes` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `device_id` INT(11) NOT NULL,
                    `sysobject_id` VARCHAR(255) DEFAULT NULL,
                    `hw` VARCHAR(255) DEFAULT NULL,
                    `sys_description` VARCHAR(1024) DEFAULT NULL,
                    CONSTRAINT `fk_device_attributes_device` FOREIGN KEY (`device_id`) REFERENCES `device` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    KEY `idx_device_attributes_sysobject_hw_sysdescr` (`sysobject_id`(191), `hw`(191), `sys_description`(191))
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create plugin table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `plugin` (
                    `name` VARCHAR(64) NOT NULL PRIMARY KEY,
                    `author` VARCHAR(255) NOT NULL,
                    `version` VARCHAR(32) NOT NULL,
                    `access` VARCHAR(64) DEFAULT NULL,
                    `enabled` INT(11) DEFAULT 0,
                    `widget` VARCHAR(255) DEFAULT NULL,
                    `metadata` TEXT NOT NULL,
                    `params` TEXT DEFAULT NULL,
                    `description` VARCHAR(255) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create mailer_events table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `mailer_events` (
                    `name` VARCHAR(128) NOT NULL PRIMARY KEY,
                    `subject` VARCHAR(255) DEFAULT NULL,
                    `template` TEXT DEFAULT NULL,
                    `recipients` TEXT DEFAULT NULL,
                    `description` VARCHAR(255) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create mailer_events_tasks_statuses table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `mailer_events_tasks_statuses` (
                    `name` VARCHAR(64) NOT NULL PRIMARY KEY
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create mailer_events_tasks table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `mailer_events_tasks` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `event_name` VARCHAR(128) NOT NULL,
                    `status` VARCHAR(64) DEFAULT NULL,
                    `subject` VARCHAR(255) NOT NULL,
                    `body` TEXT NOT NULL,
                    `created` DATETIME DEFAULT NULL,
                    CONSTRAINT `fk_mailer_events_tasks_event` FOREIGN KEY (`event_name`) REFERENCES `mailer_events` (`name`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `fk_mailer_events_tasks_status` FOREIGN KEY (`status`) REFERENCES `mailer_events_tasks_statuses` (`name`) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create schedule_mail table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `schedule_mail` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `event_name` VARCHAR(128) NOT NULL UNIQUE,
                    `schedule_cron` VARCHAR(255) NOT NULL,
                    CONSTRAINT `fk_schedule_mail_event` FOREIGN KEY (`event_name`) REFERENCES `mailer_events` (`name`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create job_global_variable table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `job_global_variable` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `var_name` VARCHAR(128) NOT NULL UNIQUE,
                    `var_value` TEXT DEFAULT NULL,
                    `protected` INT(11) DEFAULT 0,
                    `description` VARCHAR(255) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create job_snmp_types table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `job_snmp_types` (
                    `name` VARCHAR(32) NOT NULL PRIMARY KEY,
                    `description` VARCHAR(255) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Create job_snmp_request_types table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `job_snmp_request_types` (
                    `name` VARCHAR(32) NOT NULL PRIMARY KEY
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Add foreign keys for log tables if user table exists
                try {
                    $stmt = $pdo->query("SHOW TABLES LIKE 'user'");
                    if ($stmt->rowCount() > 0) {
                        // Add foreign key for log_scheduler
                        try {
                            $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = 'log_scheduler' AND CONSTRAINT_NAME = 'fk_log_scheduler_schedule'");
                            if ($stmt->rowCount() === 0) {
                                $stmt = $pdo->query("SHOW TABLES LIKE 'schedule'");
                                if ($stmt->rowCount() > 0) {
                                    $pdo->exec("ALTER TABLE `log_scheduler` ADD CONSTRAINT `fk_log_scheduler_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedule` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
                                }
                            }
                        } catch (Exception $e) {
                            // Foreign key creation failed, continue anyway
                        }
                        
                        // Add foreign key for log_scheduler user
                        try {
                            $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = 'log_scheduler' AND CONSTRAINT_NAME = 'fk_log_scheduler_user'");
                            if ($stmt->rowCount() === 0) {
                                $pdo->exec("ALTER TABLE `log_scheduler` ADD CONSTRAINT `fk_log_scheduler_user` FOREIGN KEY (`userid`) REFERENCES `user` (`userid`) ON DELETE SET NULL ON UPDATE CASCADE");
                            }
                        } catch (Exception $e) {
                            // Foreign key creation failed, continue anyway
                        }
                        
                        // Add foreign key for log_node user
                        try {
                            $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = 'log_node' AND CONSTRAINT_NAME = 'fk_log_node_user'");
                            if ($stmt && $stmt->rowCount() === 0) {
                                $pdo->exec("ALTER TABLE `log_node` ADD CONSTRAINT `fk_log_node_user` FOREIGN KEY (`userid`) REFERENCES `user` (`userid`) ON DELETE SET NULL ON UPDATE CASCADE");
                            }
                        } catch (Exception $e) {
                            // Foreign key creation failed, continue anyway
                        }
                        
                        // Add foreign key for log_system user
                        try {
                            $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = 'log_system' AND CONSTRAINT_NAME = 'fk_log_system_user'");
                            if ($stmt->rowCount() === 0) {
                                $pdo->exec("ALTER TABLE `log_system` ADD CONSTRAINT `fk_log_system_user` FOREIGN KEY (`userid`) REFERENCES `user` (`userid`) ON DELETE SET NULL ON UPDATE CASCADE");
                            }
                        } catch (Exception $e) {
                            // Foreign key creation failed, continue anyway
                        }
                        
                        // Add foreign key for log_mailer user
                        try {
                            $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = 'log_mailer' AND CONSTRAINT_NAME = 'fk_log_mailer_user'");
                            if ($stmt && $stmt->rowCount() === 0) {
                                $pdo->exec("ALTER TABLE `log_mailer` ADD CONSTRAINT `fk_log_mailer_user` FOREIGN KEY (`userid`) REFERENCES `user` (`userid`) ON DELETE SET NULL ON UPDATE CASCADE");
                            }
                        } catch (Exception $e) {
                            // Foreign key creation failed, continue anyway
                        }
                        
                        // Add foreign key for log_mailer event_task
                        try {
                            $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = 'log_mailer' AND CONSTRAINT_NAME = 'fk_log_mailer_event_task'");
                            if ($stmt->rowCount() === 0) {
                                $stmt = $pdo->query("SHOW TABLES LIKE 'mailer_events_tasks'");
                                if ($stmt->rowCount() > 0) {
                                    $pdo->exec("ALTER TABLE `log_mailer` ADD CONSTRAINT `fk_log_mailer_event_task` FOREIGN KEY (`event_task_id`) REFERENCES `mailer_events_tasks` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
                                }
                            }
                        } catch (Exception $e) {
                            // Foreign key creation failed, continue anyway
                        }
                        
                        // Add foreign key for setting_override user
                        try {
                            $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = 'setting_override' AND CONSTRAINT_NAME = 'fk_setting_override_user'");
                            if ($stmt->rowCount() === 0) {
                                $pdo->exec("ALTER TABLE `setting_override` ADD CONSTRAINT `fk_setting_override_user` FOREIGN KEY (`userid`) REFERENCES `user` (`userid`) ON DELETE CASCADE ON UPDATE CASCADE");
                            }
                        } catch (Exception $e) {
                            // Foreign key creation failed, continue anyway
                        }
                    }
                } catch (Exception $e) {
                    // Foreign key creation failed, continue anyway
                }
                
                $success = true;
                $step = 2; // Move to next step
            } catch (Exception $e) {
                $errors[] = 'Database creation error: ' . htmlspecialchars($e->getMessage());
            }
        } elseif ($action === 'create_admin') {
            // Create admin user
            $adminUsername = $_POST['admin_username'] ?? 'admin';
            $adminPassword = $_POST['admin_password'] ?? 'admin123';
            $adminEmail = $_POST['admin_email'] ?? 'admin@localhost';
            
            $pdo->exec("USE `{$dbName}`");
            
            // Check if user table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'user'");
            if ($stmt->rowCount() === 0) {
                $errors[] = 'User table does not exist. Please run migrations first.';
            } else {
                // Hash password (Yii2 uses bcrypt)
                $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT);
                if (!$hashedPassword) {
                    $hashedPassword = md5($adminPassword); // Fallback
                }
                
                // Insert admin user (using correct column names: userid and password_hash)
                $stmt = $pdo->prepare("INSERT INTO `user` (userid, password_hash, email, fullname, enabled, auth_key) VALUES (?, ?, ?, ?, 1, ?)");
                $authKey = bin2hex(random_bytes(16));
                $stmt->execute([$adminUsername, $hashedPassword, $adminEmail, 'Administrator', $authKey]);
                
                // Assign admin role (if RBAC tables exist)
                try {
                    $stmt = $pdo->query("SHOW TABLES LIKE 'auth_item'");
                    if ($stmt->rowCount() > 0) {
                        // Create admin role if it doesn't exist
                        $stmt = $pdo->prepare("SELECT name FROM auth_item WHERE name = 'admin'");
                        $stmt->execute();
                        if ($stmt->rowCount() === 0) {
                            // Create admin role (type = 1 means role)
                            $pdo->exec("INSERT INTO auth_item (name, type, description, created_at) VALUES ('admin', 1, 'Administrator', " . time() . ")");
                        }
                        // Assign role to user using user_id column (Yii2 standard)
                        try {
                            $stmt = $pdo->query("SHOW COLUMNS FROM auth_assignment LIKE 'user_id'");
                            if ($stmt->rowCount() > 0) {
                                $stmt = $pdo->prepare("INSERT IGNORE INTO auth_assignment (item_name, user_id, created_at) VALUES ('admin', ?, ?)");
                                $stmt->execute([$adminUsername, time()]);
                            } else {
                                // Try userid instead (if custom column name)
                                $stmt = $pdo->prepare("INSERT IGNORE INTO auth_assignment (item_name, userid, created_at) VALUES ('admin', ?, ?)");
                                $stmt->execute([$adminUsername, time()]);
                            }
                        } catch (Exception $e) {
                            // RBAC assignment failed, but continue
                        }
                    }
                } catch (Exception $e) {
                    // RBAC tables might not exist yet, continue anyway
                }
                
                $success = true;
                $step = 3;
            }
        } elseif ($action === 'finalize') {
            // Create install.lock - try multiple locations due to volume mount permission issues
            $installLockPath = $basePath . '/install.lock';
            $runtimeLockPath = $basePath . '/runtime/install.lock';
            
            // Try to create in base path first
            $success = false;
            if (is_writable($basePath) || is_writable(dirname($installLockPath))) {
                if (@file_put_contents($installLockPath, date('Y-m-d H:i:s'))) {
                    $success = true;
                }
            }
            
            // If failed, try runtime directory (which has proper permissions)
            if (!$success) {
                $runtimeDir = $basePath . '/runtime';
                if (!is_dir($runtimeDir)) {
                    @mkdir($runtimeDir, 0775, true);
                }
                if (@file_put_contents($runtimeLockPath, date('Y-m-d H:i:s'))) {
                    // Create symlink in base path if possible, or create .install-lock-location file
                    @file_put_contents($basePath . '/runtime/.install-lock-location', 'runtime');
                    $success = true;
                }
            }
            
            if (!$success) {
                $errors[] = 'Failed to create install.lock file. Please check file permissions.';
            } else {
                // Create application.properties file automatically after installation
                try {
                    $binDir = $basePath . '/bin';
                    $propsFile = $binDir . '/application.properties';
                    
                    // Ensure bin directory exists
                    if (!is_dir($binDir)) {
                        @mkdir($binDir, 0755, true);
                    }
                    
                    // Get javaScheduler values from database
                    $javaSchedulerPort = '';
                    $javaSchedulerUsername = '';
                    $javaSchedulerPassword = '';
                    
                    try {
                        $pdo->exec("USE `{$dbName}`");
                        $stmt = $pdo->query("SELECT `key`, `value` FROM `config` WHERE `key` IN ('javaSchedulerPort', 'javaSchedulerUsername', 'javaSchedulerPassword')");
                        $configRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($configRows as $row) {
                            if ($row['key'] === 'javaSchedulerPort') {
                                $javaSchedulerPort = $row['value'] ?: '8437';
                            } elseif ($row['key'] === 'javaSchedulerUsername') {
                                $javaSchedulerUsername = $row['value'] ?: 'cbadmin';
                            } elseif ($row['key'] === 'javaSchedulerPassword') {
                                $javaSchedulerPassword = $row['value'] ?: '';
                            }
                        }
                    } catch (Exception $e) {
                        // Use defaults if DB query fails
                        $javaSchedulerPort = '8437';
                        $javaSchedulerUsername = 'cbadmin';
                        $javaSchedulerPassword = '';
                    }
                    
                    // Create application.properties file
                    $content = "# SSH Daemon Shell Configuration\n"
                        . "sshd.shell.port={$javaSchedulerPort}\n"
                        . "sshd.shell.enabled=false\n"
                        . "sshd.shell.username={$javaSchedulerUsername}\n"
                        . "sshd.shell.password={$javaSchedulerPassword}\n"
                        . "sshd.shell.host=localhost\n"
                        . "sshd.shell.auth.authType=SIMPLE\n"
                        . "sshd.shell.prompt.title=cbackup\n"
                        . "\n"
                        . "# Spring Configuration\n"
                        . "spring.main.banner-mode=off\n"
                        . "\n"
                        . "# cBackup Configuration\n"
                        . "cbackup.scheme=http\n"
                        . "cbackup.site=http://web/index.php\n"
                        . "cbackup.token=\n";
                    
                    if (@file_put_contents($propsFile, $content)) {
                        @chmod($propsFile, 0644);
                    }
                    
                    // Set proper permissions on directory
                    if (is_dir($binDir)) {
                        @chmod($binDir, 0755);
                    }
                } catch (Exception $e) {
                    // If creation fails, it's not critical - user can sync manually later
                    // Just log silently and continue
                }
                
                header("Location: /");
                exit();
            }
        }
    } catch (Exception $e) {
        $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
    }
}

// Check database connection and tables
try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName}", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $tableCount = count($tables);
} catch (Exception $e) {
    $tables = [];
    $tableCount = 0;
    if (empty($errors)) {
        $errors[] = 'Database connection failed: ' . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>cBackup Installation</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-top: 0; }
        .error { color: red; background: #fee; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .success { color: green; background: #efe; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #0066cc; background: #e6f3ff; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
        button, .btn { padding: 10px 20px; background: #007cba; color: white; border: none; cursor: pointer; border-radius: 4px; text-decoration: none; display: inline-block; }
        button:hover, .btn:hover { background: #005a87; }
        .step-indicator { margin: 20px 0; }
        .step { display: inline-block; padding: 8px 15px; margin: 0 5px; background: #ddd; border-radius: 4px; }
        .step.active { background: #007cba; color: white; }
        .step.completed { background: #28a745; color: white; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .status { margin: 10px 0; padding: 10px; background: #f9f9f9; border-left: 4px solid #007cba; }
    </style>
</head>
<body>
    <div class="container">
        <h1>cBackup Installation Wizard</h1>
        
        <div class="step-indicator">
            <span class="step <?= $step >= 1 ? 'active' : '' ?> <?= $step > 1 ? 'completed' : '' ?>">1. Database</span>
            <span class="step <?= $step >= 2 ? 'active' : '' ?> <?= $step > 2 ? 'completed' : '' ?>">2. Admin User</span>
            <span class="step <?= $step >= 3 ? 'active' : '' ?> <?= $step > 3 ? 'completed' : '' ?>">3. Finish</span>
        </div>
        
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">Operation completed successfully!</div>
        <?php endif; ?>
        
        <?php if ($step === 1): ?>
            <h2>Step 1: Database Setup</h2>
            
            <div class="status">
                <strong>Database Connection:</strong> <?= $tableCount > 0 ? '✓ Connected' : '✗ Failed' ?><br>
                <strong>Tables Found:</strong> <?= $tableCount ?><br>
                <?php if ($tableCount > 0): ?>
                    <small>Tables: <?= implode(', ', array_slice($tables, 0, 10)) ?><?= count($tables) > 10 ? '...' : '' ?></small>
                <?php endif; ?>
            </div>
            
            <?php if ($tableCount === 0 || $tableCount < 10): ?>
                <p>Database tables are missing or incomplete. Click the button below to create all necessary tables using Yii migrations.</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="run_migrations">
                    <div class="form-group">
                        <button type="submit">Run Database Migrations</button>
                    </div>
                </form>
                
                <div class="info">
                    <strong>What this will do:</strong>
                    <ul>
                        <li>Create all required database tables</li>
                        <li>Set up Yii2 RBAC (Role-Based Access Control) tables</li>
                        <li>Initialize database structure</li>
                    </ul>
                </div>
            <?php else: ?>
                <div class="success">
                    Database tables are already created! You can proceed to the next step.
                </div>
                <a href="?step=2" class="btn">Next: Create Admin User</a>
            <?php endif; ?>
            
        <?php elseif ($step === 2): ?>
            <h2>Step 2: Create Administrator Account</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_admin">
                
                <div class="form-group">
                    <label for="admin_username">Username:</label>
                    <input type="text" id="admin_username" name="admin_username" value="admin" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_password">Password:</label>
                    <input type="password" id="admin_password" name="admin_password" value="admin123" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_email">Email:</label>
                    <input type="email" id="admin_email" name="admin_email" value="admin@localhost" required>
                </div>
                
                <div class="form-group">
                    <button type="submit">Create Admin User</button>
                </div>
            </form>
            
        <?php elseif ($step === 3): ?>
            <h2>Step 3: Finalize Installation</h2>
            
            <div class="success">
                <strong>Installation almost complete!</strong>
            </div>
            
            <p>Click the button below to finalize the installation. This will create the <code>install.lock</code> file and redirect you to the login page.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="finalize">
                <div class="form-group">
                    <button type="submit">Complete Installation</button>
                </div>
            </form>
            
        <?php endif; ?>
        
        <hr style="margin: 30px 0;">
        <div class="info">
            <strong>Manual Installation (if needed):</strong><br>
            You can also run migrations manually via command line:<br>
            <pre>docker-compose exec web php /var/www/html/yii migrate --interactive=0</pre>
        </div>
    </div>
</body>
</html>