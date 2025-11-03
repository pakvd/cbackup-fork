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

$params = require(__DIR__ . '/params.php');
$db     = require(__DIR__ . '/db.php');

if (file_exists(__DIR__ . '/settings.ini')) {
    $ini = parse_ini_file(__DIR__ . '/settings.ini');
}
else {
    $ini = ['cookieValidationKey' => 'gdy82VYeW2-uPceUhWbGfej1bQA2OnYPswpoNLwsY', 'defaultTimeZone' => 'UTC'];
}

$config = [

    'name'           => 'cBackup',
    'id'             => 'cBackup',
    'basePath'       => dirname(__DIR__),
    'sourceLanguage' => 'en-US',
    'version'        => require_once('version.php'),
    'bootstrap'      => ['log', 'app\helpers\ConfigHelper'],
	'aliases'        => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@webroot' => dirname(__DIR__) . '/web',
        '@web' => '', // URL path - empty string means root of the site
    ],

    'components' => [
        'request' => [
            'cookieValidationKey' => $ini['cookieValidationKey'],
            'parsers' => [
                'application/json'                => 'yii\web\JsonParser',
                'application/json; charset=UTF-8' => 'yii\web\JsonParser',
            ]
        ],
        'assetManager' => [
            'appendTimestamp' => true
        ],
        'formatter' => [
            'defaultTimeZone' => $ini['defaultTimeZone'],
        ],
        'urlManager' => [
            'enablePrettyUrl'     => false,
            'enableStrictParsing' => false,
            'showScriptName'      => true,
            'rules'               => [
                [
                    'class'      => 'yii\rest\UrlRule',
                    'controller' => ['v1/core', 'v2/node'],
                    'pluralize'  => false,
                ],
            ],
        ],
        // Redis connection component (only if Redis extension and package are available)
        // Must be defined before 'cache' component if Redis cache is used
        'redis' => (extension_loaded('redis') && getenv('REDIS_HOST') && class_exists('yii\redis\Connection')) ? [
            'class' => 'yii\redis\Connection',
            'hostname' => getenv('REDIS_HOST') ?: 'redis',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'database' => 0,
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'retries' => 1,
            // Note: socketTimeout is not a valid property in yii2-redis
            // Use connectionTimeout and dataTimeout instead
            'connectionTimeout' => 2,
            'dataTimeout' => 2,
        ] : null,
        'cache' => [
            // Use Redis if extension, package and connection are available, fallback to FileCache
            'class' => (extension_loaded('redis') && getenv('REDIS_HOST') && class_exists('yii\redis\Cache')) 
                ? 'yii\redis\Cache' 
                : 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass'   => 'app\models\User',
            'enableAutoLogin' => true,
            'loginUrl'        => ['/user/login'],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
            'discardExistingOutput' => false,
        ],
        'mailer' => [
            'class'            => 'yii\swiftmailer\Mailer',
            'useFileTransport' => false,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets'    => [
                [
                    'class'   => 'app\logger\CDbTarget',
                    'levels'  => ['info', 'error', 'warning'],
                    'except'  => ['yii*'],
                    'logVars' => []
                ],
                [
                    'class'   => 'yii\log\FileTarget',
                    'levels'  => ['error', 'warning', 'info'],
                    'logVars' => ['_GET', '_POST', '_FILES', '_COOKIE'],
                    'except'  => [], // Log all errors including deprecated warnings
                ],
            ],
        ],
        'i18n' => [
            'translations' => [
                '*' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@app/messages',
                    'sourceLanguage' => 'en-US',
                    'fileMap' => [
                        'app' => 'app.php',
                        'user' => 'user.php',
                        'network' => 'network.php',
                        'log' => 'log.php',
                        'mail' => 'mail.php',
                        'config' => 'config.php',
                        'rbac' => 'rbac.php',
                    ],
                ],
            ],
        ],
        'authManager' => [
            'cache' => YII_DEBUG ? null : 'cache',
            'class' => 'yii\rbac\DbManager',
        ],
        'db' => $db,
    ],
    'as maintenanceMode' => [
        'class'         => 'app\behaviors\MaintenanceBehavior',
        'redirectUri'   => 'site/maintenance',
        'ignoredRoutes' => [
            '/\/index\.php\?r=debug.+/im',
            '/\/index\.php\?r=update.+/im'
        ],
    ],
    'as beforeRequest' => [
        'class' => 'app\behaviors\LanguageBehavior',
    ],
    'as AccessBehavior' => [
        'class'         => 'app\behaviors\AccessBehavior',
        'allowedRoutes' => [
            '/\/index\.php\?r=debug.+/im',
            '/\/index\.php\?r=v1.+/im',
            '/\/index\.php\?r=v2.+/im',
        ],
    ],
    'params' => $params,
    'modules' => [

        /** RESTful JavaCore API module */
        'v1' => [
            'class' => 'app\modules\v1\v1Module',
            'components' => [
                'output' => [
                    'class' => 'app\modules\v1\components\OutputProcessing',
                ],
            ]
        ],

        /** RESTful API module */
        'v2' => [
            'class' => 'app\modules\v2\v2Module',
        ],

        /** Access control module */
        'rbac' => [
            'class'        => 'app\modules\rbac\Rbac',
            'defaultRoute' => 'access'
        ],

        /** Network module */
        'network' => [
            'class'        => 'app\modules\network\Network',
            'defaultRoute' => 'subnet'
        ],

        /** Log module */
        'log' => [
            'class' => 'app\modules\log\Log',
            'defaultRoute' => 'system/list',
        ],

        /** Mailer UI module */
        'mail' => [
            'class'        => 'app\modules\mail\Mail',
            'defaultRoute' => 'events'
        ],

        /** Custom plugins module */
        'plugins' => [
            'class' => 'app\modules\plugins\Plugins',
        ],

        /** Content delivery system */
        'cds' => [
            'class' => 'app\modules\cds\Cds',
        ],
    ],
];

// Debug toolbar - only enable in development mode (explicit YII_DEBUG=true and YII_ENV=dev)
// Disabled in production for security and performance
if( defined('YII_DEBUG') && YII_DEBUG === true && defined('YII_ENV') && YII_ENV === 'dev' ) {
    if (class_exists('yii\debug\Module')) {
        $config['bootstrap'][]      = 'debug';
        $config['modules']['debug'] = [
            'class'      => 'yii\debug\Module',
            'allowedIPs' => ['127.0.0.1', '::1'], // Only localhost for security
            'panels'     => [
                'user' => [
                    'class' => 'yii\debug\panels\UserPanel',
                    'ruleUserSwitch' => [
                        'allow' => true,
                        'roles' => ['admin'],
                    ]
                ]
            ]
        ];
    }
}

// Gii generator - only enable in development mode
if (defined('YII_ENV') && YII_ENV === 'dev' && defined('YII_DEBUG') && YII_DEBUG === true) {
    if (class_exists('yii\gii\Module')) {
        $config['bootstrap'][]    = 'gii';
        $config['modules']['gii'] = [
            'class'      => 'yii\gii\Module',
            'allowedIPs' => ['127.0.0.1', '::1'] // Only localhost for security
        ];
    }
}

return $config;
