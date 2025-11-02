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
        
        // Set short timeout
        @set_time_limit(5); // 5 seconds max - page must load quickly
        
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

        // Render page immediately - even with empty data
        return $this->render('about', [
            'phpinfo'    => $phpinfo,
            'SERVER'     => $_SERVER,
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
