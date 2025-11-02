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

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\helpers\ArrayHelper;
use app\models\Config;

/**
 * Config console controller
 */
class ConfigController extends Controller
{
    /**
     * Sync application.properties file with database values
     * 
     * Usage: php yii config/sync-properties
     */
    public function actionSyncProperties()
    {
        $this->stdout("Synchronizing application.properties with database...\n", \yii\helpers\Console::FG_YELLOW);
        
        try {
            // Get all config data from database
            $data = ArrayHelper::map(Config::find()->asArray()->all(), 'key', 'value');
            
            if (empty($data)) {
                $this->stdout("No configuration data found in database.\n", \yii\helpers\Console::FG_RED);
                return self::EXIT_CODE_ERROR;
            }
            
            // Sync application.properties
            $result = Config::syncApplicationProperties($data);
            
            if ($result) {
                $this->stdout("✓ application.properties synchronized successfully!\n", \yii\helpers\Console::FG_GREEN);
                
                // Show synced values
                $this->stdout("\nSynced values:\n", \yii\helpers\Console::FG_CYAN);
                foreach ($data as $key => $value) {
                    if (preg_match('/^javaScheduler(\w+)$/', $key, $match)) {
                        $match = array_filter($match);
                        $match = array_values($match);
                        $match = array_key_exists(1, $match) ? mb_strtolower($match[1]) : '';
                        $pkey  = "sshd.shell.$match";
                        $this->stdout("  {$pkey} = {$value}\n", \yii\helpers\Console::FG_CYAN);
                    }
                }
                
                return self::EXIT_CODE_NORMAL;
            } else {
                $this->stdout("✗ Failed to synchronize application.properties. Check file permissions.\n", \yii\helpers\Console::FG_RED);
                return self::EXIT_CODE_ERROR;
            }
            
        } catch (\Exception $e) {
            $this->stdout("✗ Error: " . $e->getMessage() . "\n", \yii\helpers\Console::FG_RED);
            return self::EXIT_CODE_ERROR;
        }
    }
}

