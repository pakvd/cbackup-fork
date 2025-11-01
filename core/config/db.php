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
];
