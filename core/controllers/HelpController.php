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

namespace app\controllers;

use app\models\Install;
use app\models\Plugin;
use app\models\SupportBundle;
use app\models\Sysinfo;
use Yii;
use yii\web\Controller;
use app\helpers\CryptHelper;


/**
 * @package app\controllers
 */
class HelpController extends Controller
{

    /**
     * @var string
     */
    public $defaultAction = 'about';
    
    /**
     * Filter sensitive data from $_SERVER array
     * Removes passwords, tokens, API keys, and other sensitive information
     * 
     * @param array $server Original $_SERVER array
     * @return array Filtered array with sensitive data masked
     */
    private static function filterServerData($server)
    {
        $filtered = [];
        $sensitiveKeys = [
            'PASSWORD', 'PASS', 'SECRET', 'TOKEN', 'KEY', 'API_KEY', 'API_SECRET',
            'ACCESS_TOKEN', 'AUTH_TOKEN', 'SESSION_ID', 'COOKIE', 'CREDENTIAL',
            'DB_PASSWORD', 'MYSQL_PASSWORD', 'DATABASE_PASSWORD',
            'REDIS_PASSWORD', 'MEMCACHED_PASSWORD',
            'HTTP_AUTHORIZATION', 'HTTP_COOKIE', 'HTTP_X_AUTH_TOKEN',
            'PHP_AUTH_USER', 'PHP_AUTH_PW', 'PHP_AUTH_DIGEST',
            'AWS_SECRET', 'AWS_KEY',
        ];
        
        foreach ($server as $key => $value) {
            $keyUpper = strtoupper($key);
            $shouldFilter = false;
            
            // Check if key contains sensitive words
            foreach ($sensitiveKeys as $sensitive) {
                if (strpos($keyUpper, $sensitive) !== false) {
                    $shouldFilter = true;
                    break;
                }
            }
            
            if ($shouldFilter) {
                // Mask the value but keep the key
                $filtered[$key] = '***FILTERED***';
            } else {
                // Recursively filter arrays
                if (is_array($value)) {
                    $filtered[$key] = self::filterServerData($value);
                } else {
                    $filtered[$key] = $value;
                }
            }
        }
        
        return $filtered;
    }


    /**
     * @return string
     */
    public function actionAbout()
    {
        // CRITICAL: Prevent recursion - if already rendering, return immediately
        static $rendering = false;
        if ($rendering) {
            error_log("=== HelpController::actionAbout() RECURSION DETECTED - returning empty ===");
            return '<div>About page is loading...</div>';
        }
        $rendering = true;
        
        // CRITICAL: Ultra-fast page with zero database queries
        // This page MUST load instantly without any DB operations
        
        error_log("=== HelpController::actionAbout() START ===");
        $startTime = microtime(true);
        
        // Set very aggressive timeout
        @set_time_limit(2); // 2 seconds max
        
        error_log("Step 1: Timeout set");
        
        // Initialize ALL data with empty defaults IMMEDIATELY - BEFORE any Yii component access
        $phpinfo = [];
        $plugins = [];
        $perms = [];
        $extensions = [];
        $dbVersion = 'N/A';
        $dbDriverName = 'mysql';
        
        error_log("Step 2: Defaults initialized");
        
        // Try to access Yii::$app safely
        try {
            error_log("Step 3: Trying to access Yii::\$app");
            $app = \Yii::$app;
            error_log("Step 4: Yii::\$app accessed");
            
            // Try to disable schema cache BEFORE accessing cache
            if (isset($app->db)) {
                error_log("Step 5: Accessing db component");
                $originalDb = $app->db;
                $originalSchemaCache = $originalDb->enableSchemaCache ?? false;
                $originalDb->enableSchemaCache = false;
                error_log("Step 6: Schema cache disabled");
            } else {
                error_log("Step 5: db component not found");
                $originalDb = null;
                $originalSchemaCache = false;
            }
            
            // ONLY try to load from cache - with strict timeout and error handling
            error_log("Step 7: Starting cache operations");
            $cacheStart = microtime(true);
            
            if (isset($app->cache)) {
                error_log("Step 8: Cache component found");
                try {
                    @set_time_limit(1); // 1 second for cache operations
                    error_log("Step 9: Trying cache get operations");
                    
                    $phpinfo = @$app->cache->get('help_about_phpinfo') ?: [];
                    error_log("Step 10: phpinfo loaded");
                    
                    $plugins = @$app->cache->get('help_about_plugins') ?: [];
                    error_log("Step 11: plugins loaded");
                    
                    $perms = @$app->cache->get('help_about_permissions') ?: [];
                    error_log("Step 12: perms loaded");
                    
                    $extensions = @$app->cache->get('help_about_extensions') ?: [];
                    error_log("Step 13: extensions loaded");
                    
                    $dbInfo = @$app->cache->get('help_about_db_info');
                    error_log("Step 14: dbInfo loaded");
                    if ($dbInfo && is_array($dbInfo)) {
                        $dbVersion = $dbInfo['version'] ?? 'N/A';
                        $dbDriverName = $dbInfo['driverName'] ?? 'mysql';
                    }
                    
                    // Generate missing data if cache is empty (but only if we have time)
                    // This ensures data is available even on first load
                    if (empty($phpinfo) || empty($perms) || empty($extensions)) {
                        @set_time_limit(3); // Give more time for generation
                        try {
                            if (empty($phpinfo)) {
                                error_log("Generating phpinfo...");
                                $sysinfo = new \app\models\Sysinfo();
                                $phpinfo = $sysinfo->getPhpInfo();
                                if (!empty($phpinfo)) {
                                    $app->cache->set('help_about_phpinfo', $phpinfo, 3600); // Cache for 1 hour
                                }
                            }
                            
                            if (empty($perms)) {
                                error_log("Generating permissions...");
                                $perms = Install::checkPermissions();
                                if (!empty($perms)) {
                                    $app->cache->set('help_about_permissions', $perms, 3600); // Cache for 1 hour
                                }
                            }
                            
                            if (empty($extensions)) {
                                error_log("Generating extensions...");
                                $extensions = Install::getPhpExtensions();
                                if (!empty($extensions)) {
                                    $app->cache->set('help_about_extensions', $extensions, 3600); // Cache for 1 hour
                                }
                            }
                        } catch (\Throwable $genEx) {
                            error_log("Error generating data (ignored): " . $genEx->getMessage());
                            // Continue with empty data
                        }
                    }
                } catch (\Throwable $e) {
                    error_log("Cache read error (ignored): " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                    // Use defaults
                }
            } else {
                error_log("Step 8: Cache component not found, using defaults");
            }
            
            $cacheElapsed = microtime(true) - $cacheStart;
            error_log("Step 15: Cache read time: {$cacheElapsed}s");
            
        } catch (\Throwable $e) {
            error_log("CRITICAL ERROR accessing Yii::\$app: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            error_log("Stack trace: " . $e->getTraceAsString());
            // Continue with defaults
            $originalDb = null;
            $originalSchemaCache = false;
        }
        
        try {
            
            // Ensure plugins are simple objects
            error_log("Step 16: Processing plugins");
            $safePlugins = [];
            if (!empty($plugins)) {
                foreach ($plugins as $plugin) {
                    try {
                        if (is_object($plugin)) {
                            $safePlugins[] = (object)[
                                'name' => $plugin->name ?? ($plugin->{'name'} ?? ''),
                                'description' => $plugin->description ?? ($plugin->{'description'} ?? ''),
                                'enabled' => $plugin->enabled ?? ($plugin->{'enabled'} ?? 0),
                            ];
                        } elseif (is_array($plugin)) {
                            $safePlugins[] = (object)[
                                'name' => $plugin['name'] ?? '',
                                'description' => $plugin['description'] ?? '',
                                'enabled' => $plugin['enabled'] ?? 0,
                            ];
                        }
                    } catch (\Throwable $e) {
                        // Skip invalid plugin entries
                        continue;
                    }
                }
            }
            error_log("Step 17: Plugins processed");

            // Check time before render
            $beforeRender = microtime(true);
            $elapsedBeforeRender = $beforeRender - $startTime;
            error_log("Step 18: Time before render: {$elapsedBeforeRender}s");
            
            if ($elapsedBeforeRender > 1.5) {
                error_log("TOO SLOW before render ({$elapsedBeforeRender}s), returning minimal page");
                // Filter sensitive data from $_SERVER
                $filteredServer = self::filterServerData($_SERVER);
                
                return $this->render('about', [
                    'phpinfo'      => [],
                    'SERVER'       => $filteredServer,
                    'perms'        => [],
                    'plugins'      => [],
                    'extensions'   => [],
                    'dbVersion'    => 'N/A',
                    'dbDriverName' => 'mysql',
                ]);
            }
            
            // Render with output buffering and timeout
            error_log("Step 19: Starting render");
            $renderStart = microtime(true);
            
            // CRITICAL: Disable schema cache BEFORE render to prevent any DB queries
            if (isset($app->db)) {
                $app->db->enableSchemaCache = false;
                error_log("Step 19.1: Schema cache disabled before render");
            }
            
            // Use register_shutdown_function to detect if render hangs
            $renderCompleted = false;
            register_shutdown_function(function() use (&$renderCompleted, $renderStart) {
                if (!$renderCompleted) {
                    $hangTime = microtime(true) - $renderStart;
                    error_log("RENDER HANG DETECTED: Render did not complete after {$hangTime}s");
                }
            });
            
            ob_start();
            try {
                error_log("Step 19.2: Calling render() method");
                
                // CRITICAL: Use direct file include instead of renderPartial to avoid Yii2 rendering system
                // This completely bypasses any potential DB queries in Yii's rendering
                error_log("Step 19.3: Rendering content directly from file");
                
                // Ensure schema cache is still disabled during render
                if (isset($app->db)) {
                    $app->db->enableSchemaCache = false;
                }
                
                // Render content by directly including the view file
                // This bypasses Yii2's renderPartial which might trigger DB queries
                // Try multiple possible paths
                $viewPath = $this->getViewPath();
                $possiblePaths = [
                    $viewPath . DIRECTORY_SEPARATOR . 'about.php',
                    dirname($viewPath) . DIRECTORY_SEPARATOR . 'help' . DIRECTORY_SEPARATOR . 'about.php',
                    \Yii::getAlias('@app') . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'help' . DIRECTORY_SEPARATOR . 'about.php',
                ];
                
                $viewFile = null;
                foreach ($possiblePaths as $path) {
                    if (file_exists($path)) {
                        $viewFile = $path;
                        break;
                    }
                }
                
                error_log("Step 19.3.1: View path from getViewPath(): " . $viewPath);
                error_log("Step 19.3.1: Found view file: " . ($viewFile ?: 'NOT FOUND'));
                
                if ($viewFile && file_exists($viewFile)) {
                    error_log("Step 19.3.2: View file exists, starting output buffering");
                    
                    // Set very short timeout for template execution
                    @set_time_limit(1);
                    
                    // Filter sensitive data from $_SERVER before passing to template
                    $filteredServerForExtract = self::filterServerData($_SERVER);
                    
                    ob_start();
                    try {
                        // Extract variables for the view
                        extract([
                            'phpinfo'      => $phpinfo,
                            'SERVER'       => $filteredServerForExtract,
                            'perms'        => $perms,
                            'plugins'      => $safePlugins,
                            'extensions'   => $extensions,
                            'dbVersion'    => $dbVersion,
                            'dbDriverName' => $dbDriverName,
                        ], EXTR_SKIP);
                        
                        // Wrap $this to prevent any DB access
                        $originalThis = isset($this) ? $this : null;
                        
                        error_log("Step 19.3.3: Variables extracted, including file");
                        
                        // Use register_shutdown_function to detect if include hangs
                        $includeCompleted = false;
                        $includeStart = microtime(true);
                        register_shutdown_function(function() use (&$includeCompleted, $includeStart) {
                            if (!$includeCompleted) {
                                $hangTime = microtime(true) - $includeStart;
                                error_log("INCLUDE HANG DETECTED: File did not complete after {$hangTime}s");
                            }
                        });
                        
                        // CRITICAL: Try to include with very strict timeout
                        // Use pcntl_alarm if available, otherwise rely on set_time_limit
                        if (function_exists('pcntl_alarm')) {
                            pcntl_alarm(1); // 1 second alarm
                        }
                        
                        // Filter sensitive data from $_SERVER before passing to template
                        $filteredServer = self::filterServerData($SERVER ?? $_SERVER);
                        
                        // Create isolated scope for include to prevent variable pollution
                        $includeContent = function() use ($viewFile, $phpinfo, $filteredServer, $perms, $safePlugins, $extensions, $dbVersion, $dbDriverName) {
                            // Re-extract in isolated scope
                            extract([
                                'phpinfo'      => $phpinfo,
                                'SERVER'       => $filteredServer,
                                'perms'        => $perms,
                                'plugins'      => $safePlugins,
                                'extensions'   => $extensions,
                                'dbVersion'    => $dbVersion,
                                'dbDriverName' => $dbDriverName,
                            ], EXTR_SKIP);
                            
                            // Include with output buffering in isolated scope
                            ob_start();
                            include $viewFile;
                            return ob_get_clean();
                        };
                        
                        error_log("Step 19.3.4: Calling include function");
                        $content = $includeContent();
                        error_log("Step 19.3.5: Include function completed");
                        
                        $includeCompleted = true;
                        error_log("Step 19.4: Content rendered directly from file, length: " . strlen($content));
                    } catch (\Throwable $includeError) {
                        $includeCompleted = true;
                        ob_end_clean();
                        error_log("Step 19.4 ERROR: " . $includeError->getMessage() . " in " . $includeError->getFile() . ":" . $includeError->getLine());
                        error_log("Stack trace: " . $includeError->getTraceAsString());
                        // Return minimal content on error instead of throwing
                        $content = '<div class="container"><h1>About cBackup</h1><p>Error rendering page: ' . htmlspecialchars($includeError->getMessage()) . '</p></div>';
                    }
                } else {
                    error_log("Step 19.3.2: View file NOT found, using renderPartial fallback");
                    // Filter sensitive data from $_SERVER
                    $filteredServer = self::filterServerData($_SERVER);
                    
                    $content = $this->renderPartial('about', [
                        'phpinfo'      => $phpinfo,
                        'SERVER'       => $filteredServer,
                        'perms'        => $perms,
                        'plugins'      => $safePlugins,
                        'extensions'   => $extensions,
                        'dbVersion'    => $dbVersion,
                        'dbDriverName' => $dbDriverName,
                    ], true);
                }
                
                // Now wrap in layout using renderContent (which uses layout but doesn't call action)
                error_log("Step 19.5: Wrapping content in layout");
                $result = $this->renderContent($content);
                error_log("Step 19.6: Layout wrap completed");
                
                $renderCompleted = true;
                $renderElapsed = microtime(true) - $renderStart;
                error_log("Step 20: Render completed, time: {$renderElapsed}s");
                
                $totalElapsed = microtime(true) - $startTime;
                error_log("Step 21: Total time: {$totalElapsed}s");
                error_log("=== HelpController::actionAbout() END ===");
                
                return $result;
            } catch (\Throwable $renderError) {
                $renderCompleted = true;
                ob_end_clean();
                error_log("RENDER ERROR: " . $renderError->getMessage() . " in " . $renderError->getFile() . ":" . $renderError->getLine());
                error_log("Stack trace: " . $renderError->getTraceAsString());
                // Filter sensitive data from $_SERVER
                $filteredServer = self::filterServerData($_SERVER);
                
                // Return minimal page on render error
                return $this->render('about', [
                    'phpinfo'      => [],
                    'SERVER'       => $filteredServer,
                    'perms'        => [],
                    'plugins'      => [],
                    'extensions'   => [],
                    'dbVersion'    => 'N/A',
                    'dbDriverName' => 'mysql',
                ]);
            }
            
        } finally {
            // Restore schema cache
            if ($originalDb !== null) {
                try {
                    $originalDb->enableSchemaCache = $originalSchemaCache;
                } catch (\Throwable $e) {
                    // Ignore
                }
            }
            
            // Clear recursion guard
            $rendering = false;
            error_log("=== HelpController::actionAbout() FINALLY ===");
        }

    }


    /**
     * Create and download support bundle
     *
     * @return string
     * @throws \yii\web\RangeNotSatisfiableHttpException
     */
    public function actionSupport()
    {

        if( Yii::$app->request->isPost ) {

            $file = preg_replace('/\W+/', '', gethostname()).'-support.bin';
            $data = SupportBundle::getData();

            if( Yii::$app->request->post('encryption') ) {
                $key  = file_get_contents(Yii::$app->getBasePath().DIRECTORY_SEPARATOR.'cbackup.public.key');
                $data = CryptHelper::encrypt($data, $key);
            }

            return Yii::$app->response->sendContentAsFile($data, $file);

        }

        return $this->render('support');

    }

}
