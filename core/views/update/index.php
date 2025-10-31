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
 *
 * @var $database string
 */

use yii\helpers\Html;
use yii\web\View;

$this->title = Yii::t('update', 'cBackup update');
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'System' )];
$this->params['breadcrumbs'][] = ['label' => Yii::t('update', 'cBackup update')];

// Check if running in Docker
$isDocker = getenv('DOCKER_CONTAINER') === 'true' || getenv('container') === 'docker';

$this->registerJs(/** @lang JavaScript */"
    
    var path = $('#path');
    var span = $('.path');
    span.text(path.val());
    
    path.keyup(function() {
        span.text(path.val());
    });
    
", View::POS_READY);
?>
<div class="row">
    <div class="col-md-12">
        <?php if ($isDocker): ?>
            <!-- Docker Compose Update Instructions -->
            <div class="box box-primary">
                <div class="box-header with-border">
                    <i class="fa fa-docker"></i>
                    <h3 class="box-title"><?= Yii::t('update', 'Docker Compose Update') ?></h3>
                </div>
                <div class="box-body">
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <strong><?= Yii::t('update', 'Docker Installation Detected') ?></strong><br>
                        <?= Yii::t('update', 'Your cBackup installation is running in Docker containers. Use the instructions below to update.') ?>
                    </div>
                    <ol>
                        <li>
                            <strong><?= Yii::t('update', 'Backup database') ?></strong><br>
                            <code>docker-compose exec db mysqldump -u <?= Yii::$app->db->username ?> -p<?= $database ?> | gzip > backup-$(date +%Y-%m-%d).sql.gz</code>
                            <br><small class="text-muted"><?= Yii::t('update', 'Password will be prompted. Use password from your .env file.') ?></small>
                            <br><br>
                        </li>
                        <li>
                            <strong><?= Yii::t('update', 'Stop containers') ?></strong><br>
                            <code>docker-compose stop</code>
                            <br><br>
                        </li>
                        <li>
                            <strong><?= Yii::t('update', 'Pull latest code (if using git)') ?></strong><br>
                            <code>git pull</code>
                            <br><small class="text-muted"><?= Yii::t('update', 'If you are using Git, pull the latest changes. Otherwise, download and extract the update manually.') ?></small>
                            <br><br>
                        </li>
                        <li>
                            <strong><?= Yii::t('update', 'Rebuild Docker images') ?></strong><br>
                            <code>docker-compose build --no-cache</code>
                            <br><small class="text-muted"><?= Yii::t('update', 'This will rebuild all containers with updated code.') ?></small>
                            <br><br>
                        </li>
                        <li>
                            <strong><?= Yii::t('update', 'Start containers') ?></strong><br>
                            <code>docker-compose up -d</code>
                            <br><small class="text-muted"><?= Yii::t('update', 'Start all services with updated images.') ?></small>
                            <br><br>
                        </li>
                        <li>
                            <strong><?= Yii::t('update', 'Update database') ?></strong><br>
                            <code>docker-compose exec web php yii migrate</code>
                            <br><small class="text-muted"><?= Yii::t('update', 'Apply database migrations if any.') ?></small>
                            <br><br>
                        </li>
                        <li>
                            <strong><?= Yii::t('update', 'Flush cache') ?></strong><br>
                            <code>docker-compose exec web php yii cache/flush-all</code><br>
                            <code>docker-compose exec web php yii asset/flush-all</code>
                            <br><small class="text-muted"><?= Yii::t('update', 'Clear application cache and assets.') ?></small>
                            <br><br>
                        </li>
                        <li>
                            <strong><?= Yii::t('update', 'Check service status') ?></strong><br>
                            <code>docker-compose ps</code>
                            <br><small class="text-muted"><?= Yii::t('update', 'Verify all containers are running.') ?></small>
                            <br><br>
                        </li>
                    </ol>
                    <hr>
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle"></i>
                        <strong><?= Yii::t('update', 'Update Complete') ?></strong><br>
                        <?= Yii::t('update', 'Now update is finished, check if everything works as it is intended.') ?>
                        <br><?= Yii::t('update', 'It is strongly recommended to reset your browser cache by pressing CTRL+F5 or CMD+SHIFT+R') ?>
                    </div>
                    <h4><?= Yii::t('update', 'Quick Update Commands') ?></h4>
                    <p><?= Yii::t('update', 'For quick update, you can run these commands in sequence:') ?></p>
                    <pre style="background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto;"># Backup database
docker-compose exec db mysqldump -u <?= Yii::$app->db->username ?> -p<?= $database ?> | gzip > backup-$(date +%Y-%m-%d).sql.gz

# Stop, rebuild and start
docker-compose stop
docker-compose build --no-cache
docker-compose up -d

# Update database and clear cache
docker-compose exec web php yii migrate
docker-compose exec web php yii cache/flush-all
docker-compose exec web php yii asset/flush-all

# Verify
docker-compose ps</pre>
                </div>
            </div>
        <?php else: ?>
            <!-- Traditional Update Instructions -->
            <div class="box box-default">
                <div class="box-header with-border">
                    <i class="fa fa-hand-grab-o"></i>
                    <h3 class="box-title"><?= Yii::t('update', 'Manual update') ?></h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-12">
                            <form style="margin: 0 1em;">
                                <label for="path"><?= Yii::t('update', 'Path to your cBackup installation') ?></label>
                                <input id="path" type="text" class="form-control" value="<?= Yii::$app->basePath ?>">
                            </form>
                        </div>
                        <div class="col-md-12">
                            <hr>
                            <ol>
                                <li>
                                    <?= Yii::t('update', 'Backup everything') ?>, <?= Yii::t('update', 'or better make full system snapshot') ?> <br>
                                    $ <code>tar czf /opt/backup-$(date +%Y-%m-%d).tar.gz -C <span class="path">-C /opt/cbackup</span> .</code>
                                    <br>
                                    $ <code>mysqldump -u <?= Yii::$app->db->username ?> -p <?= $database ?> | gzip > /opt/backup-$(date +%Y-%m-%d).sql.gz</code>
                                    <br><br>
                                </li>
                                <li>
                                    <?= Yii::t('update', 'Download the latest update') ?><br>
                                    $ <code>wget http://cbackup.me/latest?package=update -P /opt -O update.tar.gz</code>
                                    <br><br>
                                </li>
                                <li>
                                    <?= Yii::t('update', 'Create lock file in your cBackup installation folder to enable maintentance mode, blocking access to the interface') ?><br>
                                    $ <code>touch <span class="path">/opt/cbackup</span>/update.lock</code>
                                    <br><br>
                                </li>
                                <li>
                                    <?= Yii::t('update', 'Stop the service') ?><br>
                                    <?= Yii::t('update', 'In case of systemd use') ?> <code>sudo systemctl stop cbackup</code><br>
                                    <?= Yii::t('update', 'In case of SysVinit use') ?> <code>sudo service cbackup stop</code>
                                    <br><br>
                                </li>
                                <li>
                                    <?= Yii::t('update', 'Unpack downloaded archive to your cBackup installation overriding all files') ?><br>
                                    $ <code>tar -xzf /opt/update.tar.gz -C <span class="path">/opt/cbackup</span></code>
                                    <br><br>
                                </li>
                                <li>
                                    <?= Yii::t('update', 'Remove archive') ?> <br>
                                    $ <code>rm /opt/update.tar.gz</code>
                                    <br><br>
                                </li>
                                <li>
                                    <?= Yii::t('update', 'Restore permissions') ?> <br>
                                    <?= Yii::t('update', 'If you have nginx or running web server with another user:group - adjust corresponding data') ?><br>
                                    $ <code>chown -R apache:apache <span class="path">/opt/cbackup</span></code>
                                    <br><br>
                                </li>
                                <li>
                                    <?= Yii::t('update', 'Update database') ?> <br>
                                    $ <code><span class="path">/opt/cbackup</span>/yii migrate</code>
                                    <br><br>
                                </li>
                                <li>
                                    <?= Yii::t('update', 'Flush cache and runtime resources') ?> <br>
                                    $ <code><span class="path">/opt/cbackup</span>/yii cache/flush-all</code><br>
                                    $ <code><span class="path">/opt/cbackup</span>/yii asset/flush-all</code>
                                    <br><br>
                                </li>
                                <li>
                                    <?= Yii::t('update', 'Start service') ?> <br>
                                    $ <code>sudo systemctl start cbackup</code>
                                    <br><br>
                                </li>
                                <li>
                                    <?= Yii::t('update', 'Remove lock file') ?> <br>
                                    $ <code>rm <span class="path">/opt/cbackup</span>/update.lock</code>
                                </li>
                            </ol>
                            <hr>
                            <p style="padding: 0 1em;">
                                <?= Yii::t('update', 'Now update is finished, check if everything works as it is intended.') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
