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
use yii\bootstrap\ActiveForm;
use app\helpers\FormHelper;

/**
 * @var $this   yii\web\View
 * @var $model  app\models\Vendor
 * @var $form   yii\bootstrap\ActiveForm
 */

/** @noinspection PhpUndefinedFieldInspection */
$action    = $this->context->action->id;
$page_name = ($action == 'add') ? Yii::t('network', 'Add vendor') : Yii::t('network', 'Edit vendor');

$this->title = Yii::t('app', 'Vendors');
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Inventory')];
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Devices'), 'url' => ['/network/device/list']];
$this->params['breadcrumbs'][] = ['label' => $page_name];
?>

<div class="row">
    <div class="col-md-7">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fa <?= ($action == 'add') ? 'fa-plus' : 'fa-pencil-square-o' ?>"></i> <?= $page_name ?>
                </h3>
            </div>
            <?php
                $form = ActiveForm::begin([
                    'id'                     => 'vendor_form',
                    'layout'                 => 'horizontal',
                    'enableClientValidation' => false,
                    'fieldConfig' => [
                        'horizontalCssClasses' => [
                            'label'   => 'col-sm-4',
                            'wrapper' => 'col-sm-8'
                        ],
                    ],
                ]);
            ?>
                <div class="box-body">
                    <?php
                        echo $form->field($model, 'name')->textInput([
                            'class'       => 'form-control',
                            'placeholder' => FormHelper::label($model, 'name'),
                            'disabled'    => ($action == 'edit') ? true : false
                        ])->hint(Yii::t('network', 'Vendor name should start with letter and contain only a-z, 0-9 or underscore'));
                    ?>
                </div>
                <div class="box-footer text-right">
                    <?php
                        if($action == 'edit') {
                            echo Html::a(Yii::t('app', 'Delete'), ['delete', 'name' => $model->name], [
                                'class' => 'btn btn-sm btn-danger pull-left',
                                'data' => [
                                    'confirm' => Yii::t('app', 'Are you sure you want to delete this item?'),
                                    'method'  => 'post',
                                ],
                            ]);
                        }
                        echo Html::a(Yii::t('app', 'Cancel'), ['/network/device/list'], ['class' => 'btn btn-default']);
                        echo '&nbsp;';
                        echo Html::submitButton(Yii::t('app', 'Save'), ['class' => 'btn btn-primary']);
                    ?>
                </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

