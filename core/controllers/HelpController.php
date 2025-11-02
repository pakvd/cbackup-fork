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
     * @return string
     */
    public function actionAbout()
    {
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
                return $this->render('about', [
                    'phpinfo'      => [],
                    'SERVER'       => $_SERVER,
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
                
                // CRITICAL: Disable layout to prevent DB queries in sidebar/header
                // Save original layout and restore after render
                $originalLayout = $this->layout;
                $this->layout = false; // No layout = no sidebar/header = no DB queries
                error_log("Step 19.3: Layout disabled");
                
                // Also disable DB connection completely during render
                $originalDbEnabled = isset($app->db) ? $app->db->getIsActive() : false;
                if (isset($app->db)) {
                    try {
                        $app->db->close();
                        error_log("Step 19.3.1: DB connection closed");
                    } catch (\Throwable $e) {
                        error_log("Step 19.3.1: DB close error (ignored): " . $e->getMessage());
                    }
                }
                
                error_log("Step 19.4: Starting renderPartial");
                $result = $this->renderPartial('about', [
                    'phpinfo'      => $phpinfo,
                    'SERVER'       => $_SERVER,
                    'perms'        => $perms,
                    'plugins'      => $safePlugins,
                    'extensions'   => $extensions,
                    'dbVersion'    => $dbVersion,
                    'dbDriverName' => $dbDriverName,
                ], false); // false = don't use layout
                error_log("Step 19.5: renderPartial completed");
                
                // Restore DB connection if needed
                if (isset($app->db) && $originalDbEnabled) {
                    try {
                        $app->db->open();
                        error_log("Step 19.5.1: DB connection reopened");
                    } catch (\Throwable $e) {
                        error_log("Step 19.5.1: DB open error (ignored): " . $e->getMessage());
                    }
                }
                
                // Restore layout
                $this->layout = $originalLayout;
                error_log("Step 19.6: Layout restored");
                
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
                // Return minimal page on render error
                return $this->render('about', [
                    'phpinfo'      => [],
                    'SERVER'       => $_SERVER,
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
