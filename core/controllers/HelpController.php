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

        // Cache all expensive operations to prevent page hanging
        // These values rarely change, so cache for 5 minutes
        // Use safe wrappers to prevent timeouts on first load
        
        $sysinfo = new Sysinfo();
        
        // Helper function to safely get cached or default value
        $safeCacheGet = function($key, $callback, $default = null) {
            try {
                // Try to get from cache first
                $cached = Yii::$app->cache->get($key);
                if ($cached !== false) {
                    return $cached;
                }
                
                // If not cached, try to generate but with timeout protection
                try {
                    $result = call_user_func($callback);
                    Yii::$app->cache->set($key, $result, 300); // Cache for 5 minutes
                    return $result;
                } catch (\Throwable $e) {
                    // If generation fails, return default and cache it briefly to prevent repeated failures
                    error_log("HelpController: Failed to generate $key: " . $e->getMessage());
                    Yii::$app->cache->set($key, $default, 60); // Cache default for 1 minute
                    return $default;
                }
            } catch (\Throwable $e) {
                error_log("HelpController: Error in cache for $key: " . $e->getMessage());
                return $default;
            }
        };
        
        // Cache PHP info (phpinfo() is expensive) - use empty array as fallback
        $phpinfo = $safeCacheGet('help_about_phpinfo', function() use ($sysinfo) {
            return $sysinfo->getPhpInfo();
        }, []);
        
        // Cache plugins list - use empty array as fallback
        $plugins = $safeCacheGet('help_about_plugins', function() {
            return Plugin::find()->all();
        }, []);
        
        // Cache permissions check - use empty array as fallback to prevent timeout
        // This is the most expensive operation, so we make it optional
        $perms = $safeCacheGet('help_about_permissions', function() {
            // Set execution time limit for this operation (10 seconds)
            $oldLimit = ini_get('max_execution_time');
            @set_time_limit(10);
            try {
                $result = Install::checkPermissions();
                @set_time_limit($oldLimit);
                return $result;
            } catch (\Throwable $e) {
                @set_time_limit($oldLimit);
                throw $e;
            }
        }, []);
        
        // Cache PHP extensions list - use empty array as fallback
        $extensions = $safeCacheGet('help_about_extensions', function() {
            return Install::getPhpExtensions();
        }, []);

        return $this->render('about', [
            'phpinfo'    => $phpinfo,
            'SERVER'     => $_SERVER, // Pass as SERVER without $ prefix for display
            'perms'      => $perms,
            'plugins'    => $plugins,
            'extensions' => $extensions
        ]);

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
