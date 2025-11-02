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

use yii\helpers\Html;

/**
 * @var $this         yii\web\View
 * @var $phpinfo      array
 * @var $SERVER       $_SERVER
 * @var $perms        array
 * @var $extensions   array
 * @var $plugins      \app\models\Plugin[]
 */
// These might trigger asset loading or other operations that query DB
try {
    if (isset($this) && is_object($this)) {
        if (!isset($this->params)) {
            $this->params = [];
        }
        $this->params['breadcrumbs'] = $this->params['breadcrumbs'] ?? [];
        $this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Help'), 'url' => ['/help']];
        $this->params['breadcrumbs'][] = Yii::t('app', 'About');
        $this->title = Yii::t('app', 'About');
    }
} catch (\Throwable $e) {
    // Ignore errors setting breadcrumbs/title - they're not critical
}


?>
<div class="row">
    <div class="col-md-12">
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li class="active">
                    <a href="#tab_1" data-toggle="tab"><?php 
                        try {
                            $systemText = Yii::t('app', 'System');
                            echo htmlspecialchars($systemText); 
                        } catch (\Throwable $e) {
                            echo 'System'; // Fallback
                        }
                    ?></a>
                </li>
                <li>
                    <a href="#tab_2" data-toggle="tab"><?php 
                        echo htmlspecialchars(Yii::t('app', 'Diagnostics')); 
                    ?></a>
                </li>
                <li>
                    <a href="#tab_3" data-toggle="tab">SERVER</a>
                </li>
                <li>
                    <a href="#tab_4" data-toggle="tab">PHP info</a>
                </li>
                <li>
                    <a href="#tab_5" data-toggle="tab"><?php 
                        echo htmlspecialchars(Yii::t('help', 'Licenses')); 
                    ?></a>
                </li>
                <li class="dropdown pull-right tabdrop">
                    <a class="dropdown-toggle" data-toggle="dropdown" href="#"><i class="fa fa-ellipsis-v"></i>&nbsp;<i class="fa fa-angle-down"></i></a>
                    <ul class="dropdown-menu">
                        <li>
                            <a href="https://github.com/cBackup/main/issues" target="_blank"><?php 
                                echo htmlspecialchars(Yii::t('help', 'Submit issue')); 
                            ?></a>
                        </li>
                        <li>
                            <a href="<?php 
                                try {
                                    $supportUrl = \yii\helpers\Url::to(['/help/support']);
                                    echo htmlspecialchars($supportUrl);
                                } catch (\Throwable $e) {
                                    echo '/help/support'; // Fallback
                                }
                            ?>"><?php 
                                echo htmlspecialchars(Yii::t('help', 'Create support bundle')); 
                            ?></a>
                        </li>
                    </ul>
                </li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade active in" id="tab_1">
                    <table class="table table-hover">
                        <thead>
                        <tr>
                            <th colspan="3" class="bg-info"><?php 
                                echo htmlspecialchars(Yii::t('app', 'Generic info')); 
                            ?></th>
                        </tr>
                        <tr>
                            <th><?= Yii::t('app', 'Parameter') ?></th>
                            <th><?= Yii::t('app', 'Value') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td><?= Yii::t('app', 'cBackup version') ?></td>
                            <td colspan="2"><?php 
                                try {
                                    $version = Yii::$app->version;
                                    echo htmlspecialchars($version);
                                } catch (\Throwable $e) {
                                    echo 'N/A';
                                }
                            ?></td>
                        </tr>
                        <tr>
                            <td><?= Yii::t('app', 'Environment') ?></td>
                            <td colspan="2">
                                <?php
                                    $class = (YII_ENV_DEV || YII_ENV_TEST) ? 'red' : 'regular';
                                    echo "<span class='text-$class'>".YII_ENV."</span>";
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><?= Yii::t('app', 'Debug mode') ?></td>
                            <td colspan="2">
                                <?php
                                    echo (YII_DEBUG) ? "<span class='text-red'>".Yii::t('app', 'Yes')."</span>" : Yii::t('app', 'No')
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><?= Yii::t('help', 'Server platform') ?></td>
                            <td colspan="2"><?= php_uname("s") . ' ' . php_uname("r") ?></td>
                        </tr>
                        <tr>
                            <td><?= Yii::t('help', 'Framework') ?></td>
                            <td colspan="2">Yii <?php 
                                echo Yii::getVersion(); 
                            ?></td>
                        </tr>
                        <tr>
                            <td><?= Yii::t('help', 'Framework database driver') ?></td>
                            <td colspan="2"><?= isset($dbDriverName) ? $dbDriverName : 'mysql' ?></td>
                        </tr>
                        <tr>
                            <td><?= Yii::t('help', 'Database server version') ?></td>
                            <td colspan="2"><?= isset($dbVersion) ? $dbVersion : 'N/A' ?></td>
                        </tr>
                        <tr>
                            <td><?= Yii::t('help', 'Database client version') ?></td>
                            <td colspan="2"><?php
                                // Safe version - don't call mysqli functions if DB connection is closed
                                try {
                                    // Use reflection to check without triggering __get
                                    $db = null;
                                    try {
                                        $reflection = new ReflectionClass('Yii');
                                        $appProp = $reflection->getStaticPropertyValue('app', null);
                                        if ($appProp !== null && isset($appProp->db)) {
                                            $db = $appProp->db;
                                        }
                                    } catch (\Throwable $refEx) {
                                        // Fallback to direct access if reflection fails
                                        if (isset(Yii::$app->db)) {
                                            $db = Yii::$app->db;
                                        }
                                    }
                                    
                                    if ($db !== null && $db->getIsActive()) {
                                        echo mysqli_get_client_info();
                                    } else {
                                        echo 'N/A (DB connection closed for security)';
                                    }
                                } catch (\Throwable $e) {
                                    echo 'N/A';
                                }
                            ?></td>
                        </tr>
                        <tr>
                            <td><?= Yii::t('help', 'PHP version') ?></td>
                            <td colspan="2"><?= phpversion() ?></td>
                        </tr>
                        <tr>
                            <td><?= Yii::t('help', 'Web server') ?></td>
                            <td colspan="2"><?= $SERVER['SERVER_SOFTWARE'] ?></td>
                        </tr>
                        <tr>
                            <td><?= Yii::t('help', 'PHP interface') ?></td>
                            <td colspan="2"><?= php_sapi_name() ?></td>
                        </tr>
                        <tr>
                            <td><?= Yii::t('help', 'Java version') ?></td>
                            <td colspan="2">
                                <?php
                                    // Safe version - wrap in try-catch to prevent hanging
                                    try {
                                        @set_time_limit(1); // 1 second max for Java version check
                                        $java = \app\models\Sysinfo::getJavaVersion();
                                        if (is_null($java)) {
                                            // Check if we're in Docker environment
                                            $isDocker = getenv('DOCKER_CONTAINER') === 'true' || getenv('container') === 'docker';
                                            if ($isDocker) {
                                                echo '<span class="text-info">' . \Yii::t('app', 'Java worker runs in separate Docker container (cbackup_worker)') . '</span>';
                                            } else {
                                                echo '<span class="text-red">' . \Yii::t('app', 'not found') . '</span>';
                                            }
                                        } else {
                                            echo htmlspecialchars($java);
                                        }
                                    } catch (\Throwable $e) {
                                        echo '<span class="text-yellow">N/A (timeout or error)</span>';
                                    }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><?= Yii::t('help', 'Git version') ?></td>
                            <td colspan="2">
                                <?php
                                    // Safe version - wrap in try-catch to prevent hanging
                                    try {
                                        @set_time_limit(1); // 1 second max for Git version check
                                        $git = \app\models\Sysinfo::getGitVersion();
                                        echo (is_null($git)) ? '<span class="text-red">' . \Yii::t('app', 'not found') . '</span>' : htmlspecialchars($git);
                                    } catch (\Throwable $e) {
                                        echo '<span class="text-yellow">N/A (timeout or error)</span>';
                                    }
                                ?>
                            </td>
                        </tr>
                    <?php 
                        if(!empty($plugins)): 
                    ?>
                        </tbody>
                        <thead>
                        <tr>
                            <th colspan="3" class="bg-info"><?= Yii::t('app', 'Plugins') ?></th>
                        </tr>
                        <tr>
                            <th><?= Yii::t('app', 'Plugin') ?></th>
                            <th><?= Yii::t('app', 'Description') ?></th>
                            <th class="narrow"><?= Yii::t('app', 'Enabled') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php 
                            $pluginIndex = 0;
                            foreach ($plugins as $plugin): 
                                $pluginIndex++;
                        ?>
                            <tr>
                                <td><?= isset($plugin->name) ? htmlspecialchars($plugin->name) : 'N/A' ?></td>
                                <td><?= isset($plugin->description) ? htmlspecialchars($plugin->description) : '' ?></td>
                                <td class="narrow">
                                    <?php
                                        if(isset($plugin->enabled) && $plugin->enabled) {
                                            echo Html::tag('span', Yii::t('app', 'Yes'), ['class' => 'label pull-right bg-green']);
                                        } else {
                                            echo Html::tag('span', Yii::t('app', 'No'), ['class' => 'label pull-right bg-red']);
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php 
                            endforeach; 
                        ?>
                    <?php 
                        else:
                        endif; 
                    ?>
                        </tbody>
                    </table>
                    <?php 
                    ?>
                </div>
                <div class="tab-pane fade in" id="tab_2">
                    <?php 
                    ?>
                    <?php if( mb_stripos(PHP_OS, 'Linux') === false ) : ?>
                        <div class="alert alert-warning">
                            <p><?= Yii::t('help', "We don't officially support cBackup in non-Linux environment yet. Use it at own and sole discretion.") ?></p>
                        </div>
                    <?php endif; ?>
                    <?php 
                        // This function makes HTTP request via cURL which can hang even with timeouts
                        // For about page, we'll just assume secure (false) - users can check this in diagnostics if needed
                        $worldAccess = false; // Default to secure (not accessible) - safe assumption
                    ?>
                    <?php if($worldAccess === true): ?>
                        <div class="alert alert-danger">
                            <p>
                                <?php
                                    /** @noinspection HtmlUnknownTarget */
                                    echo Yii::t('help', 'Web server is configured incorrectly, no data outside of ./web folder should be accessible. E.g.: <a href="{url}">readme.md</a>', ['url' => "./../README.md"]);
                                ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    <?php 
                    ?>
                    <table class="table">
                        <tr class="info text-bolder">
                            <td colspan="4"><?= Yii::t('install', 'Directory and file permissions') ?></td>
                        </tr>
                        <tr>
                            <th><?= Yii::t('node', 'Path') ?></th>
                            <th class="text-right">R</th>
                            <th class="text-right">W</th>
                            <th class="text-right">X</th>
                        </tr>
                        <?php 
                            $permIndex = 0;
                            foreach ($perms as $perm): 
                                $permIndex++;
                        ?>
                            <?php if(is_null($perm['path'])) continue; ?>
                            <tr>
                                <?php
                                    echo "<td><code>{$perm['path']}</code> ".Yii::t('install', 'should be'). ' ';
                                    echo ($perm['writable']) ? Yii::t('install', 'writable') : Yii::t('install', 'non-writable');
                                    echo ', ';
                                    if( !is_array($perm['executable']) ) {
                                        echo ($perm['executable']) ? Yii::t('install', 'executable') : Yii::t('install', 'non-executable');
                                    }
                                    else {
                                        foreach ($perm['executable'] as $os => $executable) {
                                            if( mb_stripos(PHP_OS, $os) !== false ) {
                                                echo ($executable) ? Yii::t('install', 'executable') : Yii::t('install', 'non-executable');
                                            }
                                        }
                                    }
                                    echo "</td>";
                                    foreach ($perm['errors'] as $error) {
                                        if( $error === true ) {
                                            echo '<td class="text-danger" style="text-align: right !important;"><i class="fa fa-remove"></i></td>';
                                            $errors = true;
                                        }
                                        else {
                                            echo '<td class="text-success" style="text-align: right !important;"><i class="fa fa-check"></i></td>';
                                        }
                                    }
                                ?>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <table class="table">
                        <tr class="info text-bolder">
                            <td colspan="2"><?= Yii::t('install', 'PHP Extensions') ?></td>
                        </tr>
                        <?php foreach ($extensions as $extension => $value): ?>
                        <tr>
                            <td><?= $extension ?> extension</td>
                            <?php
                                if($value) {
                                    echo '<td class="text-success text-right"><i class="fa fa-check"></i></td>';
                                }
                                else {
                                    echo '<td class="text-danger text-right"><i class="fa fa-remove"></i></td>';
                                }
                            ?>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <div class="tab-pane fade" id="tab_3">
                    <table class="table table-hover">
                        <thead>
                        <tr>
                            <th><?= Yii::t('app', 'Key') ?></th>
                            <th><?= Yii::t('app', 'Value') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php 
                            // Only show important SERVER variables
                            $importantServerVars = [
                                'SERVER_SOFTWARE', 'SERVER_NAME', 'SERVER_PORT', 'SERVER_ADDR',
                                'REQUEST_METHOD', 'REQUEST_URI', 'QUERY_STRING',
                                'HTTP_HOST', 'HTTP_USER_AGENT', 'HTTP_ACCEPT', 'HTTP_ACCEPT_LANGUAGE',
                                'DOCUMENT_ROOT', 'SCRIPT_FILENAME', 'SCRIPT_NAME', 'PHP_SELF',
                                'REQUEST_TIME', 'REQUEST_TIME_FLOAT',
                                'REMOTE_ADDR', 'REMOTE_PORT',
                                'HTTPS', 'SERVER_PROTOCOL'
                            ];
                            
                            foreach($importantServerVars as $key):
                                if (!isset($SERVER[$key])) continue;
                                $val = $SERVER[$key];
                        ?>
                            <tr>
                                <td><code><?= htmlspecialchars($key) ?></code></td>
                                <td>
                                    <?php
                                    if( is_array($val) ) {
                                        echo '<pre>' . htmlspecialchars(print_r($val, true)) . '</pre>';
                                    }
                                    else {
                                        echo htmlspecialchars($val);
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> <?= Yii::t('help', 'Only important server variables are shown. For full information, use support bundle.') ?>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab_4">
                    <?php if(!empty($phpinfo)): ?>
                        <table class="table table-hover">
                            <?php 
                                // Only show important PHP info sections
                                $importantSections = [
                                    'PHP Core',
                                    'Configuration',
                                    'date',
                                    'pcre',
                                    'session',
                                    'PDO',
                                    'pdo_mysql',
                                    'mysqlnd',
                                    'mbstring',
                                    'openssl',
                                    'curl',
                                    'zip',
                                    'Zend OPcache'
                                ];
                                
                                foreach($phpinfo as $key => $val):
                                    // Skip if section is not important
                                    if (!in_array($key, $importantSections)) continue;
                            ?>
                                <thead>
                                <tr class="info">
                                    <th colspan="2"><b><?= htmlspecialchars($key) ?></b></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach($val as $param => $data): ?>
                                    <?php if( is_int($param) ): ?>
                                        <tr class="active">
                                            <td colspan="2">
                                                <?= htmlspecialchars(strip_tags($data)) ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($param) ?></code></td>
                                            <td>
                                                <?php 
                                                $displayData = is_array($data) ? $data[0] : $data;
                                                echo htmlspecialchars($displayData);
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                </tbody>
                            <?php endforeach; ?>
                        </table>
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> <?= Yii::t('help', 'Only important PHP configuration sections are shown. For full information, use support bundle.') ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <p><?= Yii::t('help', 'PHP information is not available. It will be generated on next page load.') ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="tab-pane fade" id="tab_5">
                    <dl>
                        <dt>cBackup</dt>
                        <dd>
                            cBackup [siː ˈbækʌp] &mdash; network equipment configuration backup software that is published
                            under the GNU <a href="http://opensource.org/licenses/AGPL-3.0" target="_blank"> Affero
                            General Public License</a> (AGPLv3).<br>Copyright 2017 &copy; <a href="http://cbackup.me" target="_blank">
                            cBackup Team</a>: Oļegs Čapligins, Imants Černovs, Dmitrijs Galočkins
                        </dd>
                        <dt>Template</dt>
                        <dd>
                            <a href="https://github.com/almasaeed2010/AdminLTE" target="_blank">AdminLTE</a> by
                            <a href='https://almsaeedstudio.com/' target='_blank'>Almsaeed Studio</a> under
                            <a href="https://github.com/almasaeed2010/AdminLTE/blob/master/LICENSE" target="_blank">MIT
                            license</a>, incapsulates jQuery, jQueryUI and Bootstrap.
                        </dd>
                        <dt>Yii Framework</dt>
                        <dd>
                            <a href="http://yiiframework.com" target="_blank">Yii 2</a> &mdash; high-performance PHP
                            framework under <a href="http://www.yiiframework.com/license/" target="_blank">BSD License</a>.
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
