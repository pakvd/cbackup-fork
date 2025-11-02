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
        // Prevent recursion
        static $rendering = false;
        if ($rendering) {
            return '<div>About page is loading...</div>';
        }
        $rendering = true;
        
        // Initialize data with empty defaults
        $phpinfo = [];
        $plugins = [];
        $perms = [];
        $extensions = [];
        $dbVersion = 'N/A';
        $dbDriverName = 'mysql';
        $originalDb = null;
        $originalSchemaCache = false;
        
        try {
            $app = \Yii::$app;
            
            // Disable schema cache to prevent DB queries during page load
            if (isset($app->db)) {
                $originalDb = $app->db;
                $originalSchemaCache = $originalDb->enableSchemaCache ?? false;
                $originalDb->enableSchemaCache = false;
            }
            
            // Load data from cache
            if (isset($app->cache)) {
                try {
                    $phpinfo = @$app->cache->get('help_about_phpinfo') ?: [];
                    $plugins = @$app->cache->get('help_about_plugins') ?: [];
                    $perms = @$app->cache->get('help_about_permissions') ?: [];
                    $extensions = @$app->cache->get('help_about_extensions') ?: [];
                    
                    $dbInfo = @$app->cache->get('help_about_db_info');
                    if ($dbInfo && is_array($dbInfo)) {
                        $dbVersion = $dbInfo['version'] ?? 'N/A';
                        $dbDriverName = $dbInfo['driverName'] ?? 'mysql';
                    }
                    
                    // Generate missing data if cache is empty
                    if (empty($phpinfo) || empty($perms) || empty($extensions)) {
                        @set_time_limit(3);
                        try {
                            if (empty($phpinfo)) {
                                $sysinfo = new \app\models\Sysinfo();
                                $phpinfo = $sysinfo->getPhpInfo();
                                if (!empty($phpinfo)) {
                                    $app->cache->set('help_about_phpinfo', $phpinfo, 3600);
                                }
                            }
                            
                            if (empty($perms)) {
                                $perms = Install::checkPermissions();
                                if (!empty($perms)) {
                                    $app->cache->set('help_about_permissions', $perms, 3600);
                                }
                            }
                            
                            if (empty($extensions)) {
                                $extensions = Install::getPhpExtensions();
                                if (!empty($extensions)) {
                                    $app->cache->set('help_about_extensions', $extensions, 3600);
                                }
                            }
                        } catch (\Throwable $genEx) {
                            // Continue with empty data
                        }
                    }
                } catch (\Throwable $e) {
                    // Use defaults
                }
            }
            
            // Process plugins to ensure safe structure
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
                        continue;
                    }
                }
            }
            
            // Filter sensitive data from $_SERVER
            $filteredServer = self::filterServerData($_SERVER);
            
            // Disable schema cache during render
            if (isset($app->db)) {
                $app->db->enableSchemaCache = false;
            }
            
            // Find view file
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
            
            if ($viewFile && file_exists($viewFile)) {
                // Render using direct file include
                @set_time_limit(2);
                
                $includeContent = function() use ($viewFile, $phpinfo, $filteredServer, $perms, $safePlugins, $extensions, $dbVersion, $dbDriverName) {
                    extract([
                        'phpinfo'      => $phpinfo,
                        'SERVER'       => $filteredServer,
                        'perms'        => $perms,
                        'plugins'      => $safePlugins,
                        'extensions'   => $extensions,
                        'dbVersion'    => $dbVersion,
                        'dbDriverName' => $dbDriverName,
                    ], EXTR_SKIP);
                    
                    ob_start();
                    include $viewFile;
                    return ob_get_clean();
                };
                
                try {
                    $content = $includeContent();
                } catch (\Throwable $includeError) {
                    $content = '<div class="container"><h1>About cBackup</h1><p>Error rendering page.</p></div>';
                }
            } else {
                // Fallback to renderPartial
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
            
            // Wrap in layout
            $result = $this->renderContent($content);
            
            return $result;
            
        } catch (\Throwable $e) {
            // Return minimal page on error
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
        } finally {
            // Restore schema cache
            if ($originalDb !== null) {
                try {
                    $originalDb->enableSchemaCache = $originalSchemaCache;
                } catch (\Throwable $e) {
                    // Ignore
                }
            }
            
            $rendering = false;
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
