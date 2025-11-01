<?php

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
	// Enable schema cache to avoid repeated information_schema queries
	'enableSchemaCache' => !defined('YII_DEBUG') || !YII_DEBUG,
	'schemaCache' => 'cache',
	'schemaCacheDuration' => 86400, // 24 hours
	// Additional MySQL 8.0 compatibility
	'schemaMap' => [
		'mysql' => [
			'class' => 'yii\db\mysql\Schema',
			// Fix for MySQL 8.0 information_schema queries
			'defaultTableOptions' => [
				'engine' => 'InnoDB',
				'charset' => 'utf8mb4',
				'collate' => 'utf8mb4_unicode_ci',
			],
		],
	],
];
