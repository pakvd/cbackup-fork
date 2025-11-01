<?php
/**
 * This file is part of cBackup, network equipment configuration backup tool
 * Copyright (C) 2017, Oļegs Čapligins, Imants Černovs, Dmitrijs Galočkins
 *
 * cBackup is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// Set session save path to writable directory
$sessionPath = __DIR__ . '/../runtime/sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0775, true);
}
@chmod($sessionPath, 0775);
if (is_writable($sessionPath) || is_writable(dirname($sessionPath))) {
    session_save_path($sessionPath);
} else {
    // Fallback to system temp directory if runtime is not writable
    error_log("Session save path $sessionPath is not writable, using system temp directory");
    $ssp = session_save_path();
    if( !is_writable($ssp) ) {
        session_save_path(sys_get_temp_dir());
    }
}

// Set environment from .env or default to production
// By default, production mode (no debug, no debug toolbar)
defined('YII_DEBUG') or define('YII_DEBUG', getenv('YII_DEBUG') === 'true' ? true : false);
defined('YII_ENV') or define('YII_ENV', getenv('YII_ENV') ?: 'prod');

// Suppress deprecated warnings for ReflectionParameter::getClass() in PHP 8.0+
// This is used by older Yii2 versions and doesn't affect functionality
// Set before loading Yii to ensure it applies globally
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

// Register custom error handler to suppress deprecated warnings
// Note: Fatal errors like "Array and string offset access syntax with curly braces" 
// cannot be caught by set_error_handler, they are handled by register_shutdown_function
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Suppress deprecated warnings (E_DEPRECATED = 8192)
    if ($errno === E_DEPRECATED) {
        return true; // Suppress the warning
    }
    // Log all other errors to help debug
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    // Let other errors pass through
    return false;
}, E_DEPRECATED);

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $logFile = __DIR__ . '/../runtime/logs/app.log';
        $logMessage = date('Y-m-d H:i:s') . " [FATAL] {$error['message']} in {$error['file']}:{$error['line']}\n";
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
        error_log("Fatal Error: " . $error['message'] . " in " . $error['file'] . ":" . $error['line']);
    }
});

require(__DIR__ . '/../vendor/autoload.php');
require(__DIR__ . '/../vendor/yiisoft/yii2/Yii.php');
require(__DIR__ . '/../helpers/Y.php'); // Load Y helper class

$config = require(__DIR__ . '/../config/web.php');

// Check for install.lock in multiple locations (due to volume mount permission issues)
$basePath = $config['basePath'];
$installLockPath = $basePath . DIRECTORY_SEPARATOR . 'install.lock';
$runtimeLockPath = $basePath . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'install.lock';

$isInstalled = file_exists($installLockPath) || file_exists($runtimeLockPath);

if (!$isInstalled) {
    header("Location: ./install/index.php");
    exit();
}

/** @noinspection PhpUnhandledExceptionInspection */
(new yii\web\Application($config))->run();
