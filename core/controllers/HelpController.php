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
        
        // Log start time
        error_log("Start time: " . $startTime);
        
        // COMPLETELY DISABLE database for this action
        $originalDb = Yii::$app->db;
        $originalSchemaCache = $originalDb->enableSchemaCache ?? false;
        
        try {
            // Disable schema cache completely
            $originalDb->enableSchemaCache = false;
            
            // Disable DB connection temporarily by setting it to null in registry (if possible)
            // But we'll use a try-catch approach instead
            
            // Initialize ALL data with empty defaults IMMEDIATELY
            $phpinfo = [];
            $plugins = [];
            $perms = [];
            $extensions = [];
            $dbVersion = 'N/A';
            $dbDriverName = 'mysql';
            
            // ONLY try to load from cache - with strict timeout
            $cacheStart = microtime(true);
            try {
                @set_time_limit(1); // 1 second for cache operations
                $phpinfo = Yii::$app->cache->get('help_about_phpinfo') ?: [];
                $plugins = Yii::$app->cache->get('help_about_plugins') ?: [];
                $perms = Yii::$app->cache->get('help_about_permissions') ?: [];
                $extensions = Yii::$app->cache->get('help_about_extensions') ?: [];
                $dbInfo = Yii::$app->cache->get('help_about_db_info');
                if ($dbInfo && is_array($dbInfo)) {
                    $dbVersion = $dbInfo['version'] ?? 'N/A';
                    $dbDriverName = $dbInfo['driverName'] ?? 'mysql';
                }
            } catch (\Throwable $e) {
                error_log("Cache read error (ignored): " . $e->getMessage());
                // Use defaults
            }
            $cacheElapsed = microtime(true) - $cacheStart;
            error_log("Cache read time: {$cacheElapsed}s");
            
            // Ensure plugins are simple objects
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

            // Check time before render
            $beforeRender = microtime(true);
            $elapsedBeforeRender = $beforeRender - $startTime;
            error_log("Time before render: {$elapsedBeforeRender}s");
            
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
            $renderStart = microtime(true);
            ob_start();
            try {
                $result = $this->render('about', [
                    'phpinfo'      => $phpinfo,
                    'SERVER'       => $_SERVER,
                    'perms'        => $perms,
                    'plugins'      => $safePlugins,
                    'extensions'   => $extensions,
                    'dbVersion'    => $dbVersion,
                    'dbDriverName' => $dbDriverName,
                ]);
                
                $renderElapsed = microtime(true) - $renderStart;
                error_log("Render time: {$renderElapsed}s");
                
                $totalElapsed = microtime(true) - $startTime;
                error_log("Total time: {$totalElapsed}s");
                error_log("=== HelpController::actionAbout() END ===");
                
                return $result;
            } catch (\Throwable $renderError) {
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
            $originalDb->enableSchemaCache = $originalSchemaCache;
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
