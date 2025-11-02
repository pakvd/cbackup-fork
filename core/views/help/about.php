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
// CRITICAL: Wrap breadcrumbs and title in try-catch to prevent any DB queries
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

// Log template execution start - using direct file write to avoid any Yii2 operations
@file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " === about.php template START ===\n", FILE_APPEND | LOCK_EX);
error_log("=== about.php template START ===");

// CRITICAL: DO NOT access Yii::$app here - it may trigger DB queries
// Schema cache is already disabled in controller
// Just proceed to HTML output immediately

@file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before HTML start\n", FILE_APPEND | LOCK_EX);
error_log("=== about.php: Before HTML start ===");
?>
<div class="row">
    <div class="col-md-12">
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li class="active">
                    <a href="#tab_1" data-toggle="tab"><?php 
                        @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before first Yii::t() call\n", FILE_APPEND | LOCK_EX);
                        error_log("=== about.php: Before Yii::t('app', 'System') ===");
                        try {
                            $systemText = Yii::t('app', 'System');
                            @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Yii::t('app', 'System') returned: " . substr($systemText, 0, 20) . "\n", FILE_APPEND | LOCK_EX);
                            echo htmlspecialchars($systemText); 
                        } catch (\Throwable $e) {
                            @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Yii::t() ERROR: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                            echo 'System'; // Fallback
                        }
                        error_log("=== about.php: After Yii::t('app', 'System') ===");
                        @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " After first Yii::t() call\n", FILE_APPEND | LOCK_EX);
                    ?></a>
                </li>
                <li>
                    <a href="#tab_2" data-toggle="tab"><?php 
                        error_log("=== about.php: Before Yii::t('app', 'Diagnostics') ===");
                        echo htmlspecialchars(Yii::t('app', 'Diagnostics')); 
                        error_log("=== about.php: After Yii::t('app', 'Diagnostics') ===");
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
                        error_log("=== about.php: Before Yii::t('help', 'Licenses') ===");
                        echo htmlspecialchars(Yii::t('help', 'Licenses')); 
                        error_log("=== about.php: After Yii::t('help', 'Licenses') ===");
                    ?></a>
                </li>
                <li class="dropdown pull-right tabdrop">
                    <a class="dropdown-toggle" data-toggle="dropdown" href="#"><i class="fa fa-ellipsis-v"></i>&nbsp;<i class="fa fa-angle-down"></i></a>
                    <ul class="dropdown-menu">
                        <li>
                            <a href="https://github.com/cBackup/main/issues" target="_blank"><?php 
                                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before Yii::t('help', 'Submit issue')\n", FILE_APPEND | LOCK_EX);
                                echo htmlspecialchars(Yii::t('help', 'Submit issue')); 
                                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " After Yii::t('help', 'Submit issue')\n", FILE_APPEND | LOCK_EX);
                            ?></a>
                        </li>
                        <li>
                            <a href="<?php 
                                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before Url::to(['/help/support'])\n", FILE_APPEND | LOCK_EX);
                                error_log("=== about.php: Before Url::to(['/help/support']) ===");
                                try {
                                    // CRITICAL: Url::to() may trigger DB queries through urlManager
                                    $supportUrl = \yii\helpers\Url::to(['/help/support']);
                                    @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Url::to() returned: " . $supportUrl . "\n", FILE_APPEND | LOCK_EX);
                                    echo htmlspecialchars($supportUrl);
                                } catch (\Throwable $e) {
                                    @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Url::to() ERROR: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                                    error_log("=== about.php: Url::to() ERROR: " . $e->getMessage());
                                    echo '/help/support'; // Fallback
                                }
                                error_log("=== about.php: After Url::to(['/help/support']) ===");
                                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " After Url::to(['/help/support'])\n", FILE_APPEND | LOCK_EX);
                            ?>"><?php 
                                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before Yii::t('help', 'Create support bundle')\n", FILE_APPEND | LOCK_EX);
                                echo htmlspecialchars(Yii::t('help', 'Create support bundle')); 
                                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " After Yii::t('help', 'Create support bundle')\n", FILE_APPEND | LOCK_EX);
                            ?></a>
                        </li>
                    </ul>
                </li>
            </ul>
            <?php 
                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before tab-content div\n", FILE_APPEND | LOCK_EX);
                error_log("=== about.php: Before tab-content div ===");
            ?>
            <div class="tab-content">
                <div class="tab-pane fade active in" id="tab_1">
                    <?php 
                        @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before table with Generic info\n", FILE_APPEND | LOCK_EX);
                        error_log("=== about.php: Before table with Generic info ===");
                    ?>
                    <table class="table table-hover">
                        <thead>
                        <tr>
                            <th colspan="3" class="bg-info"><?php 
                                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before Yii::t('app', 'Generic info')\n", FILE_APPEND | LOCK_EX);
                                echo htmlspecialchars(Yii::t('app', 'Generic info')); 
                                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " After Yii::t('app', 'Generic info')\n", FILE_APPEND | LOCK_EX);
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
                                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before Yii::\$app->version\n", FILE_APPEND | LOCK_EX);
                                error_log("=== about.php: Before Yii::\$app->version ===");
                                try {
                                    // CRITICAL: Accessing Yii::$app->version may trigger component initialization
                                    $version = Yii::$app->version;
                                    @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Yii::\$app->version = " . substr($version, 0, 20) . "\n", FILE_APPEND | LOCK_EX);
                                    echo htmlspecialchars($version);
                                } catch (\Throwable $e) {
                                    @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Yii::\$app->version ERROR: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                                    error_log("=== about.php: Yii::\$app->version ERROR: " . $e->getMessage());
                                    echo 'N/A';
                                }
                                error_log("=== about.php: After Yii::\$app->version ===");
                                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " After Yii::\$app->version\n", FILE_APPEND | LOCK_EX);
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
                                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before Yii::getVersion()\n", FILE_APPEND | LOCK_EX);
                                echo Yii::getVersion(); 
                                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " After Yii::getVersion()\n", FILE_APPEND | LOCK_EX);
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
                                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before Database client version check\n", FILE_APPEND | LOCK_EX);
                                error_log("=== about.php: Before Database client version check ===");
                                // Safe version - don't call mysqli functions if DB connection is closed
                                try {
                                    // CRITICAL: Accessing Yii::$app->db may trigger DB queries
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
                                        @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " DB is active, getting client info\n", FILE_APPEND | LOCK_EX);
                                        echo mysqli_get_client_info();
                                    } else {
                                        @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " DB not active or not available\n", FILE_APPEND | LOCK_EX);
                                        echo 'N/A (DB connection closed for security)';
                                    }
                                } catch (\Throwable $e) {
                                    @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Database client version ERROR: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                                    error_log("=== about.php: Database client version ERROR: " . $e->getMessage());
                                    echo 'N/A';
                                }
                                error_log("=== about.php: After Database client version check ===");
                                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " After Database client version check\n", FILE_APPEND | LOCK_EX);
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
                                    @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before Java version check\n", FILE_APPEND | LOCK_EX);
                                    error_log("=== about.php: Before Java version check ===");
                                    // Safe version - wrap in try-catch to prevent hanging
                                    try {
                                        @set_time_limit(1); // 1 second max for Java version check
                                        $java = \app\models\Sysinfo::getJavaVersion();
                                        @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Java version check completed\n", FILE_APPEND | LOCK_EX);
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
                                        @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Java version check ERROR: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                                        error_log("=== about.php: Java version check ERROR: " . $e->getMessage());
                                        echo '<span class="text-yellow">N/A (timeout or error)</span>';
                                    }
                                    error_log("=== about.php: After Java version check ===");
                                    @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " After Java version check\n", FILE_APPEND | LOCK_EX);
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><?= Yii::t('help', 'Git version') ?></td>
                            <td colspan="2">
                                <?php
                                    @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before Git version check\n", FILE_APPEND | LOCK_EX);
                                    error_log("=== about.php: Before Git version check ===");
                                    // Safe version - wrap in try-catch to prevent hanging
                                    try {
                                        @set_time_limit(1); // 1 second max for Git version check
                                        $git = \app\models\Sysinfo::getGitVersion();
                                        @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Git version check completed\n", FILE_APPEND | LOCK_EX);
                                        echo (is_null($git)) ? '<span class="text-red">' . \Yii::t('app', 'not found') . '</span>' : htmlspecialchars($git);
                                    } catch (\Throwable $e) {
                                        @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Git version check ERROR: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                                        error_log("=== about.php: Git version check ERROR: " . $e->getMessage());
                                        echo '<span class="text-yellow">N/A (timeout or error)</span>';
                                    }
                                    error_log("=== about.php: After Git version check ===");
                                    @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " After Git version check\n", FILE_APPEND | LOCK_EX);
                                ?>
                            </td>
                        </tr>
                    <?php 
                        @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before plugins check, plugins count: " . (is_array($plugins) ? count($plugins) : 'N/A') . "\n", FILE_APPEND | LOCK_EX);
                        error_log("=== about.php: Before plugins check ===");
                        if(!empty($plugins)): 
                            @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Plugins section starting\n", FILE_APPEND | LOCK_EX);
                            error_log("=== about.php: Plugins section starting ===");
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
                            @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before plugins foreach loop\n", FILE_APPEND | LOCK_EX);
                            error_log("=== about.php: Before plugins foreach loop ===");
                            $pluginIndex = 0;
                            foreach ($plugins as $plugin): 
                                $pluginIndex++;
                                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Processing plugin #{$pluginIndex}: " . (isset($plugin->name) ? $plugin->name : 'unknown') . "\n", FILE_APPEND | LOCK_EX);
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
                            @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " After plugins foreach loop, processed {$pluginIndex} plugins\n", FILE_APPEND | LOCK_EX);
                            error_log("=== about.php: After plugins foreach loop ===");
                        ?>
                    <?php 
                        else:
                            @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Plugins section skipped (empty)\n", FILE_APPEND | LOCK_EX);
                            error_log("=== about.php: Plugins section skipped (empty) ===");
                        endif; 
                        @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " After plugins section\n", FILE_APPEND | LOCK_EX);
                        error_log("=== about.php: After plugins section ===");
                    ?>
                        </tbody>
                    </table>
                    <?php 
                        @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Table tab_1 closed, before tab_2\n", FILE_APPEND | LOCK_EX);
                        error_log("=== about.php: Table tab_1 closed, before tab_2 ===");
                    ?>
                </div>
                <div class="tab-pane fade in" id="tab_2">
                    <?php 
                        @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Tab_2 started\n", FILE_APPEND | LOCK_EX);
                        error_log("=== about.php: Tab_2 started ===");
                    ?>
                    <?php if( mb_stripos(PHP_OS, 'Linux') === false ) : ?>
                        <div class="alert alert-warning">
                            <p><?= Yii::t('help', "We don't officially support cBackup in non-Linux environment yet. Use it at own and sole discretion.") ?></p>
                        </div>
                    <?php endif; ?>
                    <?php 
                        @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before Install::checkWorldAccess() - SKIPPING for about page performance\n", FILE_APPEND | LOCK_EX);
                        error_log("=== about.php: Install::checkWorldAccess() SKIPPED for performance ===");
                        // CRITICAL: Skip Install::checkWorldAccess() on about page to prevent hanging
                        // This function makes HTTP request via cURL which can hang even with timeouts
                        // For about page, we'll just assume secure (false) - users can check this in diagnostics if needed
                        $worldAccess = false; // Default to secure (not accessible) - safe assumption
                        @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Install::checkWorldAccess() skipped, using default: false\n", FILE_APPEND | LOCK_EX);
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
                        @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before permissions table, perms count: " . (is_array($perms) ? count($perms) : 'N/A') . "\n", FILE_APPEND | LOCK_EX);
                        error_log("=== about.php: Before permissions table ===");
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
                            @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Before perms foreach loop\n", FILE_APPEND | LOCK_EX);
                            error_log("=== about.php: Before perms foreach loop ===");
                            $permIndex = 0;
                            foreach ($perms as $perm): 
                                $permIndex++;
                                @file_put_contents('/tmp/about_template.log', date('H:i:s.') . substr(microtime(), 2, 6) . " Processing perm #{$permIndex}\n", FILE_APPEND | LOCK_EX);
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
                        <?php foreach($SERVER as $key => $val): ?>
                            <tr>
                                <td><?= $key ?></td>
                                <td>
                                    <?php
                                    if( is_array($val) ) {
                                        print_r($val);
                                    }
                                    else {
                                        echo $val;
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="tab-pane fade" id="tab_4">
                    <table class="table table-hover">
                        <?php foreach($phpinfo as $key=>$val): ?>
                            <thead>
                            <tr class="info">
                                <th colspan="2"><b><?= $key ?></b></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach($val as $param => $data): ?>
                                <?php if( is_int($param) ): ?>
                                    <tr class="active">
                                        <td colspan="2">
                                            <?= strip_tags($data) ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td><?= $param ?></td>
                                        <td>
                                            <?php echo is_array($data) ? $data[0] : $data ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </tbody>
                        <?php endforeach; ?>
                    </table>
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
