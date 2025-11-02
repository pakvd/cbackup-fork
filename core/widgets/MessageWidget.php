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

namespace app\widgets;

use yii\base\Widget;
use yii\db\Expression;
use yii\db\Query;


/**
 * @package app\widgets
 */
class MessageWidget extends Widget
{

    /**
     * @var array
     */
    public $data = [
        'count'    => '',
        'messages' => []
    ];

    /**
     * Prepare dataset
     *
     * @return void
     */
    public function init()
    {

        parent::init();

        // CRITICAL: Use cache to prevent database queries during page render
        // This prevents schema loading issues and hanging
        try {
            $cachedData = Yii::$app->cache->get('message_widget_data');
            if ($cachedData !== false && $cachedData !== null && is_array($cachedData)) {
                $this->data = $cachedData;
                return;
            }
        } catch (\Throwable $e) {
            // If cache fails, use defaults
        }

        // If cache is empty or failed, try to query database but with error handling
        try {
            // Disable schema cache temporarily to prevent recursion
            $originalSchemaCache = \Yii::$app->db->enableSchemaCache ?? false;
            \Yii::$app->db->enableSchemaCache = false;
            
            $messages = (new Query())
                ->select(['created', 'message'])
                ->from([new Expression('{{%messages}} FORCE INDEX (ix_time)')])
                ->where(['approved' => null])
                ->orderBy(['created' => SORT_DESC])
            ;

            $this->data = [
                'count'    => $messages->count(),
                'messages' => $messages->limit(5)->all(),
            ];
            
            // Restore schema cache
            \Yii::$app->db->enableSchemaCache = $originalSchemaCache;
            
            // Cache the result for 1 minute
            try {
                \Yii::$app->cache->set('message_widget_data', $this->data, 60);
            } catch (\Throwable $e) {
                // Ignore cache errors
            }
        } catch (\Throwable $e) {
            // If query fails, use empty defaults
            $this->data = [
                'count'    => 0,
                'messages' => [],
            ];
        }

    }

    /**
     * Render messages view
     *
     * @return string
     */
    public function run()
    {
        return $this->render('message_widget', [
            'data' => $this->data
        ]);
    }

}
