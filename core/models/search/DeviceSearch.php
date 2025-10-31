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

namespace app\models\search;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\Device;


/**
 * DeviceSearch represents the model behind the search form about `app\models\Device`.
 * @package app\models\search
 */
class DeviceSearch extends Device
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id'], 'integer'],
            [['vendor', 'model', 'auth_template_name'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = Device::find()->with('vendor');

        // Join with vendor for filtering and sorting
        $query->joinWith('vendor');
        
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort'  => [
                'defaultOrder' => [
                    'vendor' => SORT_ASC,
                    'model' => SORT_ASC
                ],
                'attributes' => [
                    'vendor' => [
                        'asc' => ['vendor.name' => SORT_ASC],
                        'desc' => ['vendor.name' => SORT_DESC],
                        'label' => 'Vendor',
                    ],
                    'model' => [
                        'asc' => ['device.name' => SORT_ASC],
                        'desc' => ['device.name' => SORT_DESC],
                        'label' => 'Model',
                    ],
                    'id',
                    'auth_template_name',
                ],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'device.id' => $this->id,
        ]);
        
        $query->andFilterWhere(['like', 'vendor.name', $this->vendor])
            ->andFilterWhere(['like', 'device.name', $this->model])
            ->andFilterWhere(['like', 'device.auth_template_name', $this->auth_template_name]);

        return $dataProvider;
    }
}
