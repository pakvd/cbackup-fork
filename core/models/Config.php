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

namespace app\models;

use Yii;
use yii\base\DynamicModel;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use GitWrapper\GitWrapper;


/**
 * This is the model class for table "{{%config}}".
 *
 * @property string $key
 * @property string $value
 *
 * @package app\models
 */
class Config extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%config}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['key'], 'required'],
            [['key'], 'string', 'max' => 64],
            [['value'], 'string', 'max' => 255],
        ];
    }

    /**
     * Config form input validator
     *
     * @param  array $form_fields
     * @return DynamicModel
     */
    public function configFormValidator($form_fields)
    {

        $fields = new DynamicModel($form_fields);

        $fields->addRule([
            'adminEmail', 'dataPath', 'snmpTimeout', 'snmpRetries',
            'telnetTimeout', 'telnetBeforeSendDelay', 'sshTimeout', 'gitDays',
            'javaServerUsername', 'javaServerPort', 'javaSchedulerUsername', 'javaSchedulerPort',
            'sshBeforeSendDelay', 'threadCount', 'logLifetime', 'nodeLifetime'], 'required');
        $fields->addRule([
            'adminEmail', 'gitUsername', 'gitEmail', 'gitLogin', 'gitPassword', 'gitRepo', 'gitPath', 'dataPath', 'snmpTimeout', 'snmpRetries',
            'telnetTimeout', 'telnetBeforeSendDelay', 'sshTimeout', 'sshBeforeSendDelay', 'threadCount', 'logLifetime', 'nodeLifetime',
            'mailerFromEmail', 'mailerFromName', 'mailerSmtpHost', 'mailerSmtpPort', 'mailerSmtpUsername', 'mailerSmtpPassword', 'mailerSendMailPath',
            'defaultPrependLocation'
        ], 'filter', ['filter' => 'trim']);
        $fields->addRule(['adminEmail'], 'email');
        $fields->addRule(['gitUsername', 'javaServerUsername', 'javaSchedulerUsername'], 'string', ['max' => 64]);
        $fields->addRule(['adminEmail', 'gitRepo', 'gitPath', 'dataPath', 'defaultPrependLocation'], 'string', ['max' => 255]);
        $fields->addRule(['git', 'gitRemote'], 'boolean');
        $fields->addRule(['threadCount'], 'integer', ['min' => 1, 'max' => 30]);
        $fields->addRule(['snmpRetries'], 'integer', ['min' => 1, 'max' => 10]);
        $fields->addRule(['logLifetime', 'nodeLifetime'], 'integer', ['min' => 0]);
        $fields->addRule(['snmpTimeout', 'telnetTimeout', 'sshTimeout', 'telnetBeforeSendDelay', 'sshBeforeSendDelay'], 'integer', ['min' => 1, 'max' => 60000]);
        $fields->addRule(['dataPath'], function ($attribute) use ($fields) {
            if (!file_exists($fields->attributes[$attribute]) || !is_dir($fields->attributes[$attribute])) {
                $fields->addError($attribute, Yii::t('config', "Path folder doesn't exist"));
            }
        });

        /** Git settings validation */
        $fields->addRule(['gitRepo'], 'url');
        $fields->addRule(['gitDays'], 'integer', ['min' => 1]);
        $fields->addRule(['gitUsername', 'gitEmail'], 'required', ['when' => function() use ($fields) {
            return $fields->attributes['git'] == 1;
        }]);
        $fields->addRule(['gitEmail'], 'email', ['when' => function() use ($fields) {
            return $fields->attributes['git'] == 1;
        }]);
        $fields->addRule(['gitRepo', 'gitLogin', 'gitPassword'], 'required', ['when' => function() use ($fields) {
            return ($fields->attributes['git'] == 1 && $fields->attributes['gitRemote'] == 1);
        }]);
        $fields->addRule(['gitLogin', 'gitPassword'], 'string', ['max' => 64, 'when' => function() use ($fields) {
            return ($fields->attributes['git'] == 1 && $fields->attributes['gitRemote'] == 1);
        }]);
        $fields->addRule(['gitLogin'], 'required', ['when' => function() use ($fields) {
            return ($fields->attributes['gitRemote'] == 1 && !empty($fields->attributes['gitPassword']));
        }]);
        $fields->addRule(['gitPassword'], 'required', ['when' => function() use ($fields) {
            return ($fields->attributes['gitRemote'] == 1 && !empty($fields->attributes['gitLogin']));
        }]);
        $fields->addRule(['gitPath'], function ($attribute) use ($fields) {
            // Use command execution instead of file_exists to avoid open_basedir restrictions
            $gitPath = $fields->attributes[$attribute];
            if (empty($gitPath)) {
                $fields->addError($attribute, Yii::t('config', 'Git path is required'));
                return;
            }
            
            // Try to execute git command to verify it's valid
            // This works even with open_basedir restrictions
            // If exec() is disabled, skip validation but allow path if file exists
            $exitCode = -1;
            if (function_exists('exec')) {
                $testCmd = escapeshellarg($gitPath) . ' --version 2>&1';
                @exec($testCmd, $output, $exitCode);
            } else {
                // exec() disabled: just check if file exists and is executable
                if (@is_file($gitPath) && @is_executable($gitPath)) {
                    $exitCode = 0; // Assume valid if file exists and is executable
                }
            }
            
            if ($exitCode !== 0) {
                // Fallback: try just 'git' command if full path fails
                $exitCode2 = -1;
                if (function_exists('exec')) {
                    $testCmd2 = 'git --version 2>&1';
                    @exec($testCmd2, $output2, $exitCode2);
                } else {
                    // exec() disabled: skip validation
                    $exitCode2 = 0; // Allow if exec is disabled
                }
                if ($exitCode2 === 0) {
                    // Git is available, update path
                    $whichCmd = (mb_stripos(PHP_OS, 'WIN') !== false) ? 'where git' : 'which git';
                    $whichResult = SystemHelper::exec($whichCmd);
                    if (!$whichResult->exitcode && !empty($whichResult->stdout)) {
                        $paths = explode("\n", $whichResult->stdout);
                        $fields->attributes[$attribute] = trim($paths[0]);
                        return; // Valid git found
                    }
                }
                $fields->addError($attribute, Yii::t('config', 'Git executable cannot be found in specified location'));
            }
        },['when' => function() use ($fields) { return $fields->attributes['git'] == 1; }]);
        $fields->addRule(['gitUsername', 'gitEmail', 'gitLogin', 'gitPassword', 'gitRepo', 'gitPath', 'defaultPrependLocation'], 'default', ['value' => null]);

        /** SMTP settings validation */
        $fields->addRule(['mailerFromEmail', 'mailerFromName'], 'required', ['when' => function() use ($fields) {
            return $fields->attributes['mailer'] == 1;
        }]);

        $fields->addRule(['mailerFromEmail'], 'email', ['when' => function() use ($fields) {
            return $fields->attributes['mailer'] == 1;
        }]);

        $fields->addRule(['mailerSmtpPort'], 'integer', ['min' => 1, 'max' => 65535,  'when' => function() use ($fields) {
            return $fields->attributes['mailer'] == 1;
        }]);

        $fields->addRule(['mailerSmtpHost', 'mailerSmtpPort'], 'required', ['when' => function() use ($fields) {
            return ($fields->attributes['mailer'] == 1 && $fields->attributes['mailerType'] == 'smtp');
        }]);

        $fields->addRule(['mailerSendMailPath'], 'required', ['when' => function() use ($fields) {
            return ($fields->attributes['mailer'] == 1 && $fields->attributes['mailerType'] == 'local');
        }]);

        $fields->addRule(['mailerSmtpUsername', 'mailerSmtpPassword'], 'required', ['when' => function() use ($fields) {
            return ($fields->attributes['mailer'] == 1 && $fields->attributes['mailerSmtpAuth'] == 1);
        }]);

        /** SSH port number validators */
        $fields->addRule(['javaSchedulerPort', 'javaServerPort'], 'integer', ['min' => 1, 'max' => 65535]);
        $fields->addRule(['javaSchedulerPort'], 'compare', ['compareAttribute' => 'javaServerPort', 'operator' => '!=', 'type' => 'number']);

        return $fields;

    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'key'     => Yii::t('app', 'Key'),
            'value'   => Yii::t('app', 'Value'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {

        parent::afterSave($insert, $changedAttributes);

        $key  = [];
        $file = Yii::$app->basePath.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'application.properties';

        if (preg_match('/^javaScheduler(\w+)$/', $this->attributes['key'], $key)) {

            $key = array_filter($key);
            $key = array_values($key);
            $key = array_key_exists(1, $key) ? mb_strtolower($key[1]) : '';

            if (!empty($key)) {
                // If file doesn't exist, sync all values to create it
                if (!file_exists($file)) {
                    $data = ArrayHelper::map(static::find()->asArray()->all(), 'key', 'value');
                    static::syncApplicationProperties($data);
                } else if (is_writable($file)) {
                    // File exists and is writable, update the specific key
                    $in  = file_get_contents($file);
                    $out = preg_replace("/^(sshd\.shell\.$key)=.*$/im", "$1={$this->attributes['value']}", $in);
                    file_put_contents($file, $out);
                }
            }

        }

    }

    /**
     * Sync database values to bin/application.properties file
     * Creates the file if it doesn't exist or updates existing values
     *
     * @param  array $data Database configuration data
     * @return bool
     */
    public static function syncApplicationProperties($data)
    {
        $file = Yii::$app->basePath.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'application.properties';
        $dir  = dirname($file);

        // Create directory if it doesn't exist
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Read existing file if it exists
        $content = '';
        if (file_exists($file)) {
            $content = file_get_contents($file);
        } else {
            // If file doesn't exist, try to copy from worker template
            $workerTemplate = Yii::$app->basePath.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'worker'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'application.properties';
            if (file_exists($workerTemplate)) {
                $content = file_get_contents($workerTemplate);
            } else {
                // Create a default template if neither exists
                $content = "# SSH Daemon Shell Configuration\n"
                    . "sshd.shell.port=8437\n"
                    . "sshd.shell.enabled=false\n"
                    . "sshd.shell.username=cbadmin\n"
                    . "sshd.shell.password=\n"
                    . "sshd.shell.host=localhost\n"
                    . "sshd.shell.auth.authType=SIMPLE\n"
                    . "sshd.shell.prompt.title=cbackup\n"
                    . "\n"
                    . "# Spring Configuration\n"
                    . "spring.main.banner-mode=off\n"
                    . "\n"
                    . "# cBackup Configuration\n"
                    . "cbackup.scheme=http\n"
                    . "cbackup.site=http://web/index.php\n"
                    . "cbackup.token=\n";
            }
        }

        // Update values from database
        foreach ($data as $key => $value) {
            $match = [];

            if (preg_match('/^javaScheduler(\w+)$/', $key, $match)) {
                $match = array_filter($match);
                $match = array_values($match);
                $match = array_key_exists(1, $match) ? mb_strtolower($match[1]) : '';
                $pkey  = "sshd.shell.$match";

                if (!empty($match)) {
                    // Ensure value is a string (handle null and empty values)
                    $value = ($value === null || $value === '') ? '' : (string)$value;
                    
                    // Escape special regex characters in the property key
                    $escapedPkey = preg_quote($pkey, '/');
                    
                    // Replace or add the property
                    if (preg_match("/^{$escapedPkey}=.*$/im", $content)) {
                        // Property exists, replace it
                        $content = preg_replace("/^{$escapedPkey}=.*$/im", "{$pkey}={$value}", $content);
                    } else {
                        // Property doesn't exist, add it after SSH Daemon Shell Configuration section
                        if (preg_match("/^(# SSH Daemon Shell Configuration[^\n]*\n)/im", $content, $headerMatch)) {
                            // Add after the header line
                            $content = preg_replace("/^(# SSH Daemon Shell Configuration[^\n]*\n)/im", "$1{$pkey}={$value}\n", $content);
                        } elseif (preg_match("/^(sshd\.shell\.\w+=.*\n)/im", $content, $lastProp)) {
                            // Add after the last sshd.shell property
                            $content = preg_replace("/^(sshd\.shell\.\w+=.*\n)/im", "$1{$pkey}={$value}\n", $content, -1);
                        } else {
                            // Add at the beginning of SSH section or at the end
                            $content = preg_replace("/^(# SSH Daemon Shell Configuration)/im", "$1\n{$pkey}={$value}", $content, 1);
                            if (!preg_match("/{$pkey}={$value}/", $content)) {
                                $content .= "\n{$pkey}={$value}";
                            }
                        }
                    }
                }
            }
        }

        // Write the updated content
        // Ensure directory exists and is writable
        if (!is_dir($dir)) {
            // Try to create directory
            if (!@mkdir($dir, 0755, true)) {
                return false;
            }
        }
        
        // Check if directory is writable, if not try to chmod it
        if (!is_writable($dir)) {
            @chmod($dir, 0755);
            // Check again after chmod
            if (!is_writable($dir)) {
                return false;
            }
        }
        
        // If file exists but is not writable, try to chmod it
        if (file_exists($file) && !is_writable($file)) {
            @chmod($file, 0644);
        }
        
        // Try to write file
        $result = @file_put_contents($file, $content, LOCK_EX);
        
        if ($result !== false) {
            // Make sure file has correct permissions
            @chmod($file, 0644);
            return true;
        }

        return false;
    }

    /**
     * Checks if values in bin/application.properties and database
     * are even and matched to avoid descync between java behavior
     * and netssh scripts
     *
     * @param  array $data
     * @return void
     */
    public static function checkApplicationProperties($data)
    {

        $res  = [];
        $file = Yii::$app->basePath.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'application.properties';

        // If file doesn't exist, try to sync it
        if (!file_exists($file)) {
            if (static::syncApplicationProperties($data)) {
                // File created successfully, check again
                if (file_exists($file)) {
                    // File was created, now validate
                    $props = @parse_ini_file($file, false, INI_SCANNER_RAW);
                    if (!empty($props)) {
                        foreach ($data as $key => $value) {
                            $match = [];
                            if (preg_match('/^javaScheduler(\w+)$/', $key, $match)) {
                                $match = array_filter($match);
                                $match = array_values($match);
                                $match = array_key_exists(1, $match) ? mb_strtolower($match[1]) : '';
                                $pkey  = "sshd.shell.$match";
                                if (array_key_exists($pkey, $props) && $props[$pkey] != $value) {
                                    $res[] = $pkey;
                                }
                            }
                        }
                    }
                }
            }
            // If file still doesn't exist after sync attempt, skip check
            if (!file_exists($file)) {
                return;
            }
        }

        // Read and parse existing file
        $props = @parse_ini_file($file, false, INI_SCANNER_RAW);

        if (empty($props)) {
            // File exists but can't be parsed, try to recreate it
            if (static::syncApplicationProperties($data)) {
                $props = @parse_ini_file($file, false, INI_SCANNER_RAW);
            }
            if (empty($props)) {
                return; // Still can't parse, skip check
            }
        }

        // Check for mismatches
        foreach ($data as $key => $value) {
            $match = [];

            if (preg_match('/^javaScheduler(\w+)$/', $key, $match)) {

                $match = array_filter($match);
                $match = array_values($match);
                $match = array_key_exists(1, $match) ? mb_strtolower($match[1]) : '';
                $pkey  = "sshd.shell.$match";

                if (!empty($match)) {
                    // Normalize values for comparison (both as strings)
                    $propValue = isset($props[$pkey]) ? (string)$props[$pkey] : '';
                    $dbValue = ($value === null || $value === '') ? '' : (string)$value;
                    
                    // Check if property exists in file
                    if (array_key_exists($pkey, $props)) {
                        // Property exists, check if value matches
                        if ($propValue !== $dbValue) {
                            $res[] = $pkey;
                        }
                    } else {
                        // Property doesn't exist in file, add it to mismatch list
                        $res[] = $pkey;
                    }
                }
            }
        }

        // If there's a mismatch, try to sync
        if (!empty($res)) {
            if (static::syncApplicationProperties($data)) {
                // Re-read file after sync
                $props = @parse_ini_file($file, false, INI_SCANNER_RAW);
                if (!empty($props)) {
                    // Re-check after sync
                    $res = [];
                    foreach ($data as $key => $value) {
                        $match = [];
                        if (preg_match('/^javaScheduler(\w+)$/', $key, $match)) {
                            $match = array_filter($match);
                            $match = array_values($match);
                            $match = array_key_exists(1, $match) ? mb_strtolower($match[1]) : '';
                            $pkey  = "sshd.shell.$match";
                            if (!empty($match)) {
                                // Normalize values for comparison (both as strings)
                                $propValue = isset($props[$pkey]) ? (string)$props[$pkey] : '';
                                $dbValue = ($value === null || $value === '') ? '' : (string)$value;
                                
                                if (array_key_exists($pkey, $props)) {
                                    if ($propValue !== $dbValue) {
                                        $res[] = $pkey;
                                    }
                                } else {
                                    // Property still missing after sync
                                    $res[] = $pkey;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Only show warning if there are still mismatches after sync attempt
        if (!empty($res)) {
            // Try one more sync attempt before showing warning
            if (static::syncApplicationProperties($data)) {
                // Re-read and re-check one final time
                $props = @parse_ini_file($file, false, INI_SCANNER_RAW);
                if (!empty($props)) {
                    $res = [];
                    foreach ($data as $key => $value) {
                        $match = [];
                        if (preg_match('/^javaScheduler(\w+)$/', $key, $match)) {
                            $match = array_filter($match);
                            $match = array_values($match);
                            $match = array_key_exists(1, $match) ? mb_strtolower($match[1]) : '';
                            $pkey  = "sshd.shell.$match";
                            if (!empty($match)) {
                                $propValue = isset($props[$pkey]) ? (string)$props[$pkey] : '';
                                $dbValue = ($value === null || $value === '') ? '' : (string)$value;
                                
                                if (!array_key_exists($pkey, $props) || $propValue !== $dbValue) {
                                    $res[] = $pkey;
                                }
                            }
                        }
                    }
                }
            }
            
            // Only show warning if there are still mismatches
            if (!empty($res)) {
                \Y::flash('warning', Yii::t('config', 'Mismatched data in application.properties and database for following keys: <b>{0}</b>', join(', ', $res)));
            }
        }

    }

    /**
     * Check if directory is Git repo
     *
     * @return bool
     */
    public static function isGitRepo()
    {
        $path_to_repo = \Y::param('dataPath') . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . '.git';
        return (file_exists($path_to_repo) || is_dir($path_to_repo)) ? true : false;
    }

    /**
     * Init git repository
     *
     * @param  string $user
     * @param  string $mail
     * @param  string $gitPath
     * @param  string $dataPath
     * @return bool
     * @throws \Exception
     */
    public static function runRepositoryInit($user, $mail, $gitPath, $dataPath)
    {

        $status = false;

        try {

            /** Init repository */
            $wrapper = new GitWrapper($gitPath);
            $git     = $wrapper->init($dataPath . DIRECTORY_SEPARATOR . 'backup');

            /** Set .git settings if repo successfully cloned */
            if ($git->isCloned()) {
                static::initGitSettings($user, $mail, $gitPath, $dataPath);
                $status = true;
            }

            return $status;

        } catch (\Exception $e) {
            throw $e;
        }

    }

    /**
     * Init Git settings based on DB saved values
     *
     * @param string $user
     * @param string $mail
     * @param string $gitPath
     * @param string $dataPath
     * @return bool
     * @throws \Exception
     */
    public static function initGitSettings($user, $mail, $gitPath, $dataPath)
    {
        try {

            /** Init GitWrapper */
            $wrapper = new GitWrapper($gitPath);

            /** Get working copy */
            $git = $wrapper->workingCopy($dataPath . DIRECTORY_SEPARATOR . 'backup');

            /** Set all necessary configuration */
            $git
                ->config('user.name', $user)
                ->config('user.email', $mail);

            if (\Y::param('gitRemote') == 1) {
                $git
                    ->config('push.default', 'simple')
                    ->config('remote.origin.url', static::prepareGitRepoUrl())
                    ->config('branch.master.remote', 'origin')
                    ->config('branch.master.merge', 'refs/heads/master');
            }

            return true;

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Generate Git repository url
     *
     * @return string
     */
    private static function prepareGitRepoUrl()
    {
        $git_cred   = '';
        $parsed_url = parse_url(\Y::param('gitRepo'));
        $git_login  = \Y::param('gitLogin');
        $git_pass   = \Y::param('gitPassword');

        /** Generate git credentials */
        if (!is_null($git_login) && !is_null($git_pass)) {
            $git_cred = $git_login . ':' . $git_pass . '@';
        }

        return $parsed_url['scheme'] . '://' . $git_cred . $parsed_url['host'] . $parsed_url['path'];
    }

}
