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

namespace app\modules\cds\models;

use Yii;
use yii\base\Model;
use yii\data\ArrayDataProvider;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use GitWrapper\GitWrapper;

/**
 * @package app\modules\cds\models
 */
class Cds extends Model
{

    /**
     * @var array
     */
    public $dataset = [];

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $vendor;

    /**
     * @var string
     */
    public $protocol;

    /**
     * @var string
     */
    public $class;

    /**
     * @var string
     */
    private $content_dir;

    /**
     * @var string
     */
    private static $git_url = "https://github.com/cBackup/content.git";

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->content_dir = \Yii::getAlias('@app'). DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'cds' . DIRECTORY_SEPARATOR . 'content';
        $this->dataset = $this->getDataset();
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'vendor', 'protocol', 'class'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'name'     => Yii::t('network', 'Device'),
            'vendor'   => Yii::t('network', 'Vendor'),
            'protocol' => Yii::t('network', 'Protocol'),
            'class'    => Yii::t('app', 'Content class'),
        ];
    }

    /**
     *
     * @param  string $type
     * @param  null $params
     * @return ArrayDataProvider
     */
    public function dataProvider($type, $params = null)
    {

        $data = $this->dataset[$type];

        $this->load($params);

        $attributes = $this->getAttributes(['name', 'vendor', 'protocol', 'class']);

        foreach ($attributes as $attribute => $value) {
            if (!empty($value)) {
                $input = preg_quote($value, '~');
                $data  = array_filter($data, function ($content) use ($input, $attribute) {
                    return preg_grep("~{$input}~i", [$content[$attribute]]);
                });
            }
        }

        /** Create dataprovider */
        $dataProvider = new ArrayDataProvider([
            'allModels' => $data,
            'sort' => [
                'defaultOrder' => ['name' => SORT_ASC],
                'attributes' => [
                    'name' => [
                        'asc'  => ['name' => SORT_ASC],
                        'desc' => ['name' => SORT_DESC],
                    ],
                    'protocol' => [
                        'asc'  => ['protocol' => SORT_ASC],
                        'desc' => ['protocol' => SORT_DESC],
                    ],
                    'class' => [
                        'asc'  => ['class' => SORT_ASC],
                        'desc' => ['class' => SORT_DESC],
                    ]
                ]
            ],
        ]);

        return $dataProvider;

    }

    /**
     * Get content from git repo
     *
     * @return bool
     * @throws \Exception
     */
    public function updateContent()
    {
        try {

            /** Init Git repo if not exists */
            if (!$this->isGitRepo()) {
                return $this->initGitRepo();
            }

            /** Get git path - use default if empty */
            $gitPath = \Y::param('gitPath');
            if (empty($gitPath)) {
                // Try common git locations
                $possiblePaths = ['/usr/bin/git', '/usr/local/bin/git'];
                $gitPath = 'git'; // Default fallback
                foreach ($possiblePaths as $path) {
                    if (@is_executable($path)) {
                        $gitPath = $path;
                        break;
                    }
                }
            }

            /** Init Git wrapper */
            $wrapper = new GitWrapper($gitPath);

            /** Get working copy */
            $git = $wrapper->workingCopy($this->content_dir);

            /** Get files from origin */
            $git->fetch('origin');
            $git->reset("origin/master", ['hard' => true]);

            return true;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Check if directory is Git repo
     *
     * @return bool
     */
    public function isGitRepo()
    {
        $path_to_repo = $this->content_dir . DIRECTORY_SEPARATOR . '.git';
        try {
            // Use @ to suppress warnings if directory is not accessible
            return @file_exists($path_to_repo) || @is_dir($path_to_repo);
        } catch (\Exception $e) {
            // If we can't check, assume it's not a git repo
            return false;
        }
    }

    /**
     * Create data set based on content directory content
     *
     * @return array
     */
    private function getDataset()
    {

        /** Default variables */
        $result  = [];
        $content = '';
        $vendor  = '';

        /** DirectoryIterator init */
        // Use SKIP_DOTS and catch exceptions for permission errors
        try {
            $rdi = new \RecursiveDirectoryIterator($this->content_dir, \RecursiveDirectoryIterator::SKIP_DOTS | \RecursiveDirectoryIterator::CURRENT_AS_SELF);
            $it  = new \RecursiveIteratorIterator($rdi, \RecursiveIteratorIterator::SELF_FIRST);

            foreach ($it as $spl_file_info) {
                try {
                    // Skip .git directory and any permission-denied entries
                    $fileName = $spl_file_info->getFileName();
                    if ($fileName === '.git' || $fileName === '' || $fileName === null) {
                        $it->next();
                        continue;
                    }

                    // Skip if we can't access the file/directory
                    if (!$spl_file_info->isReadable()) {
                        continue;
                    }

                    /** Get content root */
                    if ($spl_file_info->isDir() && $it->getDepth() == 0) {
                        $content = $fileName;
                        $result[$content] = [];
                    }

                    /** Get content sub-directories */
                    if ($spl_file_info->isDir() && $it->getDepth() == 1) {
                        $vendor = $fileName;
                    }

                    /** Get files */
                    if ($spl_file_info->isFile() &&  $it->getDepth() == 2) {
                        $class     = preg_replace('/\.php/i', '', $fileName);
                        $file_info = explode('_', Inflector::camel2id($class, '_'));
                        if (array_key_exists(0, $file_info) && $file_info[0] == 'content') {
                            $result[$content][] = [
                                'name'      => ucfirst($vendor) . ' ' . strtoupper($file_info[1]),
                                'vendor'    => $vendor,
                                'protocol'  => (array_key_exists(2, $file_info)) ? strtoupper($file_info[2]) : null,
                                'class'     => $class,
                                'file_path' => $this->content_dir . DIRECTORY_SEPARATOR . $content . DIRECTORY_SEPARATOR . $vendor . DIRECTORY_SEPARATOR . $fileName
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    // Skip files/directories we can't access (e.g., .git with permission issues)
                    continue;
                }
            }
        } catch (\Exception $e) {
            // If content directory doesn't exist or isn't accessible, return empty result
            // Log error but don't throw exception - allow module to work with empty dataset
            error_log("CDS: Cannot read content directory: " . $e->getMessage());
        }

        return $result;

    }

    /**
     * Init Git repo
     *
     * @return  bool
     * @throws \Exception
     */
    private function initGitRepo()
    {
        try {

            /** Get git path - use default if empty */
            $gitPath = \Y::param('gitPath');
            if (empty($gitPath)) {
                // Try common git locations
                $possiblePaths = ['/usr/bin/git', '/usr/local/bin/git'];
                $gitPath = 'git'; // Default fallback
                foreach ($possiblePaths as $path) {
                    if (@is_executable($path)) {
                        $gitPath = $path;
                        break;
                    }
                }
            }

            /** Init Git wrapper */
            $wrapper = new GitWrapper($gitPath);

            /** Init Git repo */
            $git = $wrapper->init($this->content_dir);

            /** Remove originating .gitignore and any directories */
            $git->clean('-fxd');

            /** Create config file */
            $git->remote('add', 'origin', self::$git_url);
            $git->fetch('origin');
            $git->checkout('master');

            return true;

        } catch (\Exception $e) {
            if ($this->isGitRepo()) {
                FileHelper::removeDirectory($this->content_dir . DIRECTORY_SEPARATOR . '.git');
            }
            throw new \Exception($e->getMessage());
        }
    }

}
