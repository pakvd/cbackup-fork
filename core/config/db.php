<?php

// Note: MysqlSchema class will be autoloaded by Composer when needed (after Yii2 is loaded)
// We don't require it here because install/index.php loads this file before Yii2 is loaded

// Read database configuration from environment variables (Docker) or use defaults
$dbHost = getenv('DB_HOST') ?: 'db';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: 'cbackup';
$dbUser = getenv('DB_USER') ?: getenv('MYSQL_USER') ?: 'cbackup';
$dbPassword = getenv('DB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: 'cbackup_password';

return [
	'class' => 'yii\db\Connection',
	'dsn' => "mysql:host={$dbHost};dbname={$dbName};port={$dbPort};unix_socket=null",
	'username' => $dbUser,
	'password' => $dbPassword,
	'charset' => 'utf8mb4',
	// Use custom Schema class to fix MySQL 8.0 constraint_name issues
	'schemaMap' => [
		'mysql' => 'app\components\MysqlSchema',
	],
	// Enable schema cache to improve performance and avoid repeated information_schema queries
	// Temporarily disable for debugging - can be re-enabled after fixing schema loading
	'enableSchemaCache' => false, // !defined('YII_DEBUG') || !YII_DEBUG,
	'schemaCache' => 'cache',
	'schemaCacheDuration' => 86400, // 24 hours
];
