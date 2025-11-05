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
use \yii\db\ActiveRecord;


/**
 * This is the model class for table "{{%device}}".
 *
 * @property integer $id
 * @property string $vendor
 * @property string $model
 * @property string $auth_template_name
 *
 * @property DeviceAuthTemplate $authTemplateName
 * @property Vendor $vendorName
 * @property DeviceAttributes[] $deviceAttributes
 * @property Node[] $nodes
 * @property TasksHasDevices[] $tasksHasDevices
 *
 * @package app\models
 */
class Device extends ActiveRecord
{

    /**
     * Default page size
     *
     * @var int
     */
    public $page_size = 20;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%device}}';
    }

    /**
     * Virtual properties for compatibility
     * @var string|null
     */
    public $vendor;
    public $model;
    
    public function rules()
    {
        return [
            [['vendor_id', 'name'], 'required'],
            [['name'], 'filter', 'filter' => 'trim'],
            [['auth_template_name'], 'string', 'max' => 64],
            [['name'], 'string', 'max' => 128],
            [['vendor_id'], 'integer'],
            [['auth_template_name'], 'exist', 'skipOnError' => true, 'targetClass' => DeviceAuthTemplate::class, 'targetAttribute' => ['auth_template_name' => 'name']],
            [['vendor_id'], 'exist', 'skipOnError' => true, 'targetClass' => Vendor::class, 'targetAttribute' => ['vendor_id' => 'id']],
            [['vendor_id', 'name'], 'unique', 'targetAttribute' => ['vendor_id', 'name'], 'message' => 'The combination of Vendor and Model has already been taken.'],
            [['name'], 'match', 'pattern' => '/^[a-z](?!.*[\-_]{2,})[\w\-]*/i',
                'message' => Yii::t('network', 'Device name should start with letter, contain only a-z, 0-9 and non-repeating hyphens and/or underscores')
            ],
            [['vendor', 'model'], 'safe'], // Virtual properties
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'                 => Yii::t('app', 'ID'),
            'vendor_id'          => Yii::t('network', 'Vendor'),
            'vendor'             => Yii::t('network', 'Vendor'),
            'name'               => Yii::t('network', 'Model'),
            'model'              => Yii::t('network', 'Model'),
            'auth_template_name' => Yii::t('network', 'Auth template name'),
            'description'        => Yii::t('app', 'Description'),
            'page_size'          => Yii::t('app', 'Page size'),
        ];
    }
    
    /**
     * Override afterFind to populate virtual properties
     */
    public function afterFind()
    {
        parent::afterFind();
        $this->vendor = $this->vendorName;
        $this->model = $this->name;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAuthTemplateName()
    {
        return $this->hasOne(DeviceAuthTemplate::class, ['name' => 'auth_template_name']);
    }

    /**
     * Get vendor relation
     * @return \yii\db\ActiveQuery
     */
    public function getVendor()
    {
        return $this->hasOne(Vendor::class, ['id' => 'vendor_id']);
    }
    
    /**
     * Get vendor name (virtual property)
     * @return string|null
     */
    public function getVendorName()
    {
        return $this->vendor ? $this->vendor->name : null;
    }
    
    /**
     * Virtual property: vendor (alias for vendor name)
     * @return string|null
     */
    public function getVendorProperty()
    {
        return $this->vendorName;
    }
    
    /**
     * Virtual property: model (alias for name)
     * @return string|null
     */
    public function getModel()
    {
        return $this->name;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDeviceAttributes()
    {
        return $this->hasMany(DeviceAttributes::class, ['device_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getNodes()
    {
        return $this->hasMany(Node::class, ['device_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTasksHasDevices()
    {
        return $this->hasMany(TasksHasDevices::class, ['device_id' => 'id']);
    }
}
