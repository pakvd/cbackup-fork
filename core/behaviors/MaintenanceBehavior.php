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

namespace app\behaviors;

use Yii;
use yii\base\Behavior;


/**
 * @package app\behaviors
 */
class MaintenanceBehavior extends Behavior
{

    /**
     * @var string Yii route format string
     */
    protected $redirectUrl;

    /**
     * @var array Routes which are ignored by maintenance mode
     */
    protected $ignoredRoutes = [];

    /**
     * @param string $url
     */
    public function setRedirectUri($url)
    {
        $this->redirectUrl = $url;
    }

    /**
     * Sets ignoredRoutes param
     *
     * @param array $routes
     */
    public function setIgnoredRoutes(array $routes)
    {
        $this->ignoredRoutes = $routes;
    }

    /**
     * Redirect all requests to maintenance page if update.lock exists
     */
    public function init()
    {
        $lock = Yii::$app->basePath . DIRECTORY_SEPARATOR . 'update.lock';
        if (file_exists($lock)) {
            // Try to get URL, but handle errors gracefully
            try {
                $url = Yii::$app->getRequest()->url;
            } catch (\Throwable $e) {
                // If URL cannot be determined yet, skip ignored routes check
                $url = '';
            }
            
            foreach ($this->ignoredRoutes as $ignoredRoute) {
                if ($url && preg_match($ignoredRoute, $url)) {
                    return;
                }
            }
            Yii::$app->catchAll = [$this->redirectUrl];
        } else {
            // Try to get URL, but handle errors gracefully
            try {
                $url = Yii::$app->getRequest()->url;
                if (preg_match('/' . urlencode($this->redirectUrl) . '/im', urlencode($url))) {
                    Yii::$app->getResponse()->redirect(Yii::$app->homeUrl)->send();
                }
            } catch (\Throwable $e) {
                // If URL cannot be determined, skip redirect check
            }
        }
    }

}
