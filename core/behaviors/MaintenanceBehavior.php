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
     * Attach events
     */
    public function events()
    {
        return [
            \yii\base\Application::EVENT_BEFORE_REQUEST => 'checkMaintenance',
        ];
    }

    /**
     * Redirect all requests to maintenance page if update.lock exists
     */
    public function checkMaintenance($event)
    {
        // Skip if not web application (console mode doesn't have Request with URL)
        if (!($this->owner instanceof \yii\web\Application)) {
            return;
        }
        
        $lock = Yii::$app->basePath . DIRECTORY_SEPARATOR . 'update.lock';
        if (file_exists($lock)) {
            // Try to get URL for ignored routes check, but handle errors gracefully
            $url = '';
            $skipUrlCheck = false;
            try {
                $request = Yii::$app->getRequest();
                if ($request instanceof \yii\web\Request) {
                    // Try to get URL using pathInfo instead of url property
                    // pathInfo doesn't trigger resolveRequestUri() which might fail
                    try {
                        $url = $request->pathInfo ?? $request->get('r', '');
                    } catch (\Throwable $urlEx) {
                        // If pathInfo also fails, use $_SERVER variables directly
                        $url = $_SERVER['REQUEST_URI'] ?? $_SERVER['PATH_INFO'] ?? '';
                    }
                }
            } catch (\Throwable $e) {
                // If Request cannot be obtained or any error occurs, skip URL check
                $skipUrlCheck = true;
                $url = '';
            }
            
            // Check ignored routes only if we have a valid URL
            if (!$skipUrlCheck && $url) {
                foreach ($this->ignoredRoutes as $ignoredRoute) {
                    if (preg_match($ignoredRoute, $url)) {
                        return;
                    }
                }
            }
            
            // Set catchAll to redirect to maintenance page
            Yii::$app->catchAll = [$this->redirectUrl];
        } else {
            // Try to get URL for redirect check, but handle errors gracefully
            try {
                $request = Yii::$app->getRequest();
                if ($request instanceof \yii\web\Request) {
                    // Use pathInfo instead of url property to avoid resolveRequestUri() error
                    try {
                        $url = $request->pathInfo ?? $request->get('r', '');
                    } catch (\Throwable $urlEx) {
                        // If pathInfo fails, use $_SERVER directly
                        $url = $_SERVER['REQUEST_URI'] ?? $_SERVER['PATH_INFO'] ?? '';
                    }
                    
                    if ($url && preg_match('/' . urlencode($this->redirectUrl) . '/im', urlencode($url))) {
                        Yii::$app->getResponse()->redirect(Yii::$app->homeUrl)->send();
                    }
                }
            } catch (\Throwable $e) {
                // If URL cannot be determined, skip redirect check
            }
        }
    }

}
