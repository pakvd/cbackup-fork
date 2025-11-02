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
        // CRITICAL: Only load from cache - never generate data on page load
        // This prevents infinite loops and hanging
        // Data generation should happen via separate background job or console command
        
        // Set aggressive timeout and track execution time
        $startTime = microtime(true);
        @set_time_limit(3); // 3 seconds max - page must load very quickly
        
        // Disable schema cache temporarily to prevent recursion issues
        $originalSchemaCache = Yii::$app->db->enableSchemaCache;
        Yii::$app->db->enableSchemaCache = false;
        
        try {
            // Initialize with empty defaults
        $phpinfo = [];
        $plugins = [];
        $perms = [];
        $extensions = [];
        
        // ONLY load from cache - never generate
        try {
            $phpinfo = Yii::$app->cache->get('help_about_phpinfo') ?: [];
        } catch (\Throwable $e) {
            // Ignore
        }
        
        try {
            $plugins = Yii::$app->cache->get('help_about_plugins') ?: [];
        } catch (\Throwable $e) {
            // Ignore
        }
        
        try {
            $perms = Yii::$app->cache->get('help_about_permissions') ?: [];
        } catch (\Throwable $e) {
            // Ignore
        }
        
        try {
            $extensions = Yii::$app->cache->get('help_about_extensions') ?: [];
        } catch (\Throwable $e) {
            // Ignore
        }

        // Cache database info to avoid schema loading during render
        // CRITICAL: Only load from cache, never query database
        $dbInfo = Yii::$app->cache->get('help_about_db_info') ?: null;
        if ($dbInfo === null) {
            // If cache is empty, use defaults - do NOT query database
            // Database info will be cached by a background process or on first successful access
            $dbInfo = ['version' => 'N/A', 'driverName' => 'mysql'];
        }
        
        $dbVersion = $dbInfo['version'] ?? 'N/A';
        $dbDriverName = $dbInfo['driverName'] ?? 'mysql';
        
        // Ensure plugins are simple objects/arrays, not ActiveRecord (to avoid schema loading)
        $safePlugins = [];
        if (!empty($plugins)) {
            foreach ($plugins as $plugin) {
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
            }
        }

            // Check execution time - if taking too long, return early with minimal data
            $elapsed = microtime(true) - $startTime;
            if ($elapsed > 2.5) {
                // Taking too long - return minimal page immediately
                error_log("HelpController::actionAbout() taking too long: {$elapsed}s, returning minimal page");
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
            
            // Render page immediately - even with empty data
            $result = $this->render('about', [
                'phpinfo'      => $phpinfo,
                'SERVER'       => $_SERVER,
                'perms'        => $perms,
                'plugins'      => $safePlugins,
                'extensions'   => $extensions,
                'dbVersion'    => $dbVersion,    // Pass cached version to avoid query in template
                'dbDriverName' => $dbDriverName, // Pass cached driver name to avoid schema loading
            ]);
            
            return $result;
        } finally {
            // Always restore schema cache setting
            Yii::$app->db->enableSchemaCache = $originalSchemaCache;
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
