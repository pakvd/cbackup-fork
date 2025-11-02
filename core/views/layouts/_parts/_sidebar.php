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

use yii\helpers\Inflector;
use app\models\Plugin;
?>
<aside class="main-sidebar">

    <section class="sidebar">

        <?php

            // CRITICAL: For about page, disable ALL RBAC checks to prevent DB queries
            // Check if we're on about page first - BEFORE any Yii component access
            $isAboutPage = false;
            $user = null;
            $userCanAdmin = false;
            
            try {
                $currentRoute = isset($this->context->action) ? $this->context->action->uniqueId : '';
                $isAboutPage = (strpos($currentRoute, 'help/about') !== false);
                
                // Only access user component if NOT on about page
                if (!$isAboutPage) {
                    $user = Yii::$app->getUser();
                    // Cache admin check once to avoid repeated DB queries
                    try {
                        $userCanAdmin = $user->can('admin');
                    } catch (\Throwable $e) {
                        $userCanAdmin = false;
                    }
                } else {
                    // For about page: skip ALL RBAC - set everything to false
                    $userCanAdmin = false;
                    $user = Yii::$app->getUser(); // Still need user object for other checks
                }
            } catch (\Throwable $e) {
                // On any error, use safe defaults
                $isAboutPage = false;
                $userCanAdmin = false;
                try {
                    $user = Yii::$app->getUser();
                } catch (\Throwable $e2) {
                    $user = null;
                }
            }
            
            // Helper function to safely check admin access without DB queries on about page
            $checkAdmin = function() use ($isAboutPage, $userCanAdmin, $user) {
                if ($isAboutPage) {
                    return false; // Always deny on about page to prevent DB queries
                }
                return $userCanAdmin || (isset($user) && $user->can('admin'));
            };

            /**
             * Check if current request route is identical to param to
             * highlight corresponding menu item as 'active'
             *
             * @param  array $routes
             * @return bool
             */
            $checkRoute = function ($routes) {

                $retval = false;
                $check  = $this->context->action->uniqueId;

                if (in_array($check, array_map('trim', $routes, [" \t\n\r\0\x0B/"]))) {
                    $retval = true;
                }

                return $retval;

            };

            /**
             * Render plugin menu based on installed plugins
             *
             * @return array
             */
            $renderPluginMenu = function () {

                $menu    = [];
                // CRITICAL: Only load from cache - never query database during page render
                // This prevents schema loading issues and hanging
                $plugins = [];
                try {
                    $cachedData = Yii::$app->cache->get('pluginmenu');
                    if ($cachedData !== false && $cachedData !== null) {
                        $plugins = $cachedData;
                    }
                } catch (\Throwable $e) {
                    // If cache fails, use empty array - don't query database
                    $plugins = [];
                }

                /** Display menu only if plugins were found */
                if (!empty($plugins)) {
                    $menu = [
                        'label'   => '<i class="fa fa-plug"></i> <span>' . Yii::t('app', 'Plugins') . '</span> <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>',
                        'url'     => ['/'],
                        'options' => ['class' => 'treeview'],
                        'items'   => []
                    ];

                    /** Get unique module id */
                    $module_id   = $this->context->module->uniqueid;
                    $plugins_dir = \Yii::getAlias('@app'). DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'plugins';

                    /** Generate plugin menu items */
                    foreach ($plugins as $entry) {
                        $plugin_name = strtolower(Inflector::camelize($entry->name));
                        $base_file   = $plugins_dir . DIRECTORY_SEPARATOR . $plugin_name . DIRECTORY_SEPARATOR . Inflector::camelize($entry->name) . ".php";

                        /** Check if base file exists */
                        if (file_exists($base_file)) {
                            $module_route = '/' . $module_id . '/' . $entry->plugin->defaultRoute;
                            $plugin_url   = '/plugins/' .  $plugin_name . '/' . $entry->plugin->defaultRoute;
                            $menu['items'][] = [
                                'label'   => $entry->plugin_params['translated_name'],
                                'url'     => [$plugin_url],
                                'active'  => ($plugin_url == $module_route) ? true : false,
                                'visible' => $isAboutPage ? false : (isset($user) && $user->can($entry->access))
                            ];
                        }
                    }
                }

                return $menu;

            };

            /** @noinspection PhpUnhandledExceptionInspection */
            echo \yii\widgets\Menu::widget([
                'encodeLabels'    => false,
                'activateParents' => true,
                'options'         => [ 'class' => 'sidebar-menu' ],
                'submenuTemplate' => "\n<ul class='treeview-menu'>\n{items}\n</ul>\n",
                'items'           => [
                    [
                        'label'   => '<i class="fa fa-dashboard"></i> <span>'.Yii::t('app', 'Dashboard').'</span>',
                        'url'     => Yii::$app->homeUrl,
                        'active'  => $checkRoute(['site/index']),
                    ],
                    [
                        'label'           => '<i class="fa fa-tasks"></i> <span>'.Yii::t('node', 'Nodes').'</span> <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>',
                        'url'             => ['/node/list'],
                        'options'         => ['class' => 'treeview'],
                        'active'          => $checkRoute(['node/view', 'node/list', 'node/add', 'node/edit', 'network/exclusion/list', 'network/exclusion/list', 'network/exclusion/add', 'network/exclusion/edit']),
                        'items' => [
                            [
                                'label' => Yii::t('app', 'List'),
                                'url'   => ['/node/list'],
                                'active'  => $checkRoute(['node/list']),
                            ],
                            [
                                'label'   => '',
                                'options' => ['class' => 'divider'],
                            ],
                            [
                                'label'   => Yii::t('app', 'Add manually'),
                                'url'     => ['/node/add'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute(['node/add']),
                            ],
                            [
                                'label'   => Yii::t('app', 'Exclusions'),
                                'url'     => ['/network/exclusion/list'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute(['network/exclusion/list', 'network/exclusion/add', 'network/exclusion/edit']),
                            ],
                        ]
                    ],
                    [
                        'label'           => '<i class="fa fa-list"></i> <span>'.Yii::t('app', 'Logs').'</span> <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>',
                        'url'             => ['/log'],
                        'options'         => ['class' => 'treeview'],
                        'items' => [
                            [
                                'label'   => Yii::t('app', 'System'),
                                'url'     => ['/log/system/list'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute(['log/system/list']),
                            ],
                            [
                                'label'   => Yii::t('node', 'Nodes'),
                                'url'     => ['/log/nodelog/list'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute(['log/nodelog/list']),
                            ],
                            [
                                'label'   => Yii::t('app', 'Scheduler'),
                                'url'     => ['/log/scheduler/list'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute(['log/scheduler/list']),
                            ],
                            [
                                'label'   => Yii::t('app', 'Mail'),
                                'url'     => ['/log/mailerlog/list'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute(['log/mailerlog/list']),
                            ],
                        ]
                    ],
                    [
                        'label'    => '<i class="fa fa-wrench"></i> <span>'.Yii::t('app', 'Inventory').'</span> <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>',
                        'url'      => ['/network/subnet/list'],
                        'options'  => ['class' => 'treeview'],
                        'items'    => [
                            [
                                'label'   => Yii::t('app', 'Subnets'),
                                'url'     => ['/network/subnet/list'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute(['network/subnet/list', 'network/subnet/add', 'network/subnet/edit']),
                            ],
                            [
                                'label'   => Yii::t('app', 'Devices'),
                                'url'     => ['/network/device/list'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute([
                                    'network/device/list',
                                    'network/device/add',
                                    'network/device/edit',
                                    'network/device/unknown-list',
                                    'network/device/add-unknown-device',
                                    'network/device/change-device',
                                    'network/vendor/add',
                                    'network/vendor/edit'
                                ]),
                            ],
                            [
                                'label'   => '',
                                'options' => ['class' => 'divider'],
                            ],
                            [
                                'label'   => Yii::t('app', 'Credentials'),
                                'url'     => ['/network/credential/list'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute(['network/credential/list', 'network/credential/add', 'network/credential/edit']),
                            ],
                            [
                                'label'   => Yii::t('app', 'Device auth templates'),
                                'url'     => ['/network/authtemplate/list'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute(['network/authtemplate/list', 'network/authtemplate/add', 'network/authtemplate/edit']),
                            ],
                        ],
                    ],
                    [
                        'label'    => '<i class="fa fa-refresh"></i> <span>'.Yii::t('app', 'Processes').'</span> <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>',
                        'url'      => ['/'],
                        'options'  => ['class' => 'treeview'],
                        'items'    => [
                            [
                                'label'   => Yii::t('app', 'Tasks'),
                                'url'     => ['/network/task/list'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute(['network/task/list', 'network/task/add', 'network/task/edit']),
                            ],
                            [
                                'label'   => Yii::t('app', 'Workers & Jobs'),
                                'url'     => ['/network/worker/list'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute(['network/worker/list']),
                            ],
                            [
                                'label'   => Yii::t('app', 'Task assignments'),
                                'url'     => ['/network/assigntask/list'],
                                'options' => ['class' => 'set-active-tab', 'data-target' => 'node_tasks'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute([
                                        'network/assigntask/list',
                                        'network/assigntask/assign-device-task',
                                        'network/assigntask/edit-device-task',
                                        'network/assigntask/assign-node-task',
                                        'network/assigntask/edit-node-task',
                                        'network/assigntask/adv-node-assign',
                                        'network/assigntask/adv-device-assign'
                                ]),
                            ],
                            [
                                'label'   => Yii::t('app', 'Schedules'),
                                'url'     => ['/network/schedule/list'],
                                'options' => ['class' => 'set-active-tab', 'data-target' => 'tasks_schedule'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute(['network/schedule/list', 'network/schedule/add', 'network/schedule/edit']),
                            ],
                            [
                                'label'   => '',
                                'options' => ['class' => 'divider'],
                            ],
                            [
                                'label'   => Yii::t('app', 'Global worker variables'),
                                'url'     => ['/network/workervariables'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute(['network/workervariables/list', 'network/workervariables/add', 'network/workervariables/edit']),
                            ],
                            [
                                'label'   => Yii::t('app', 'Mailer') . '<span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>',
                                'url'     => ['/'],
                                'visible' => $checkAdmin(),
                                'options' => ['class' => 'treeview'],
                                'items' => [
                                    [
                                        'label'  => Yii::t('app', 'Events'),
                                        'url'    => ['/mail/events/list'],
                                        'active' => $checkRoute([
                                            'mail/events/list',
                                            'mail/events/add-event',
                                            'mail/events/edit-event',
                                            'mail/events/edit-event-recipients',
                                            'mail/events/edit-event-template',
                                        ]),
                                    ],
                                    [
                                        'label'  => Yii::t('app', 'Messages'),
                                        'url'    => ['/mail/events/messages'],
                                        'active' => $checkRoute(['mail/events/messages']),
                                    ]
                                ]
                            ],
                            [
                                'label'   => Yii::t('app', 'Task output tables'),
                                'url'     => ['/network/outcustom/list'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute(['network/outcustom/list']),
                            ],
                        ]
                    ],
                    [
                        'label'           => '<i class="fa fa-database"></i> <span>'.Yii::t('app', 'System').'</span> <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>',
                        'url'             => ['/'],
                        'options'         => ['class' => 'treeview'],
                        'items' => [
                            [
                                'label'  => Yii::t('app', 'System settings'),
                                'url'    => ['/config'],
                                'active' => $checkRoute(['config/index']),
                            ],
                            [
                                'label'  => Yii::t('app', 'System messages'),
                                'url'    => ['/message'],
                                'active' => $checkRoute(['message/list']),
                            ],
                            [
                                'label'   => Yii::t('app', 'Plugin manager'),
                                'url'     => ['/plugin/index'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute(['plugin/index', 'plugin/edit-plugin']),
                            ],
                            [
                                'label'   => Yii::t('app', 'Content delivery system'),
                                'url'     => ['/cds'],
                                'visible' => $checkAdmin(),
                                'active'  => $checkRoute(['cds/cds/index']),
                            ],
                            [
                                'label'   => '',
                                'options' => ['class' => 'divider'],
                            ],
                            [
                                'label'   => Yii::t('app', 'User management') . '<span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>',
                                'url'     => ['/'],
                                'visible' => $checkAdmin(),
                                'options' => ['class' => 'treeview'],
                                'items' => [
                                    [
                                        'label'  => Yii::t('app', 'Users'),
                                        'url'    => ['/user/list'],
                                        'active' => $checkRoute(['user/list', 'user/edit', 'user/add']),
                                    ],
                                    [
                                        'label'  => Yii::t('app', 'Rights assignment'),
                                        'url'    => ['/rbac/assign/list'],
                                        'active' => $checkRoute(['rbac/assign/list', 'rbac/assign/add', 'rbac/assign/edit']),
                                    ],
                                    [
                                        'label'   => Yii::t('app', 'Access rights'),
                                        'url'     => ['/rbac/access/list'],
                                        'active'  => $checkRoute(['rbac/access/list', 'rbac/access/add', 'rbac/access/edit']),
                                    ],
                                ]
                            ],
                            [
                                'label'  => Yii::t('help', 'Support bundle'),
                                'url'    => ['/help/support'],
                                'active' => $checkRoute(['help/support']),
                            ],
                            [
                                'label'    => Yii::t('app', 'Documentation'),
                                'url'      => 'http://cbackup.rtfd.io',
                                'template' => /** @lang text */ '<a href="{url}" target="_blank">{label} <i class="fa fa-fw fa-external-link"></i></a>',
                            ],
                            [
                                'label'   => '',
                                'options' => ['class' => 'divider'],
                            ],
                            [
                                'label'  => Yii::t('app', 'Update'),
                                'url'    => ['/update/index'],
                                'active' => $checkRoute(['update/index']),
                            ],
                            [
                                'label'  => Yii::t('app', 'About'),
                                'url'    => ['/help/about'],
                                'active' => $checkRoute(['help/about']),
                            ],
                        ]
                    ],
                    $renderPluginMenu()
                ]
            ]);
        ?>

    </section>

</aside>
