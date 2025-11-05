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
use yii\helpers\Url;
use yii\grid\GridView;
use yii\widgets\Pjax;

/**
 * @var $this           yii\web\View
 * @var $searchModel    app\models\search\DeviceSearch
 * @var $dataProvider   yii\data\ActiveDataProvider
 * @var $vendors        array
 * @var $unkn_count     integer
 */

// Register Select2Asset for select2 dropdowns
app\assets\Select2Asset::register($this);
// Register LaddaAsset for button spinners
app\assets\LaddaAsset::register($this);

$this->title = Yii::t('app', 'Devices');
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Inventory' )];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="row">
    <div class="col-md-9">
        <div class="box">
            <div class="box-header">
                <i class="fa fa-list"></i><h3 class="box-title box-title-align"><?= Yii::t('network', 'Device list') ?></h3>
                <div class="pull-right">
                    <div class="btn-group margin-r-5">
                        <?= Html::a(Yii::t('network', 'View unknown devices'), ['unknown-list'], ['class' => 'btn btn-sm btn-default'])?>
                        <span class="btn btn-sm <?= ($unkn_count > 0) ? 'btn-warning' : 'bg-light-black' ?>" style="cursor: default"><?= $unkn_count ?></span>
                    </div>
                    <?= Html::a(Yii::t('network', 'Add device'), ['ajax-add-device'], [
                        'class'         => 'btn btn-sm bg-light-blue',
                        'data-toggle'   => 'modal',
                        'data-target'   => '#form_modal',
                        'data-backdrop' => 'static'
                    ]) ?>
                </div>
            </div>
            <div class="box-body no-padding">
                <?php Pjax::begin(['id' => 'device-pjax']); ?>
                <?=
                    /** @noinspection PhpUnhandledExceptionInspection */
                    GridView::widget([
                        'id'           => 'device-grid',
                        'tableOptions' => ['class' => 'table table-bordered'],
                        'dataProvider' => $dataProvider,
                        'filterModel'  => $searchModel,
                        'afterRow'     => function($model) { /** @var $model \app\models\Device */
                            $id = "info_{$model->id}";
                            return '<tr><td class="grid-expand-row" colspan="5"><div class="grid-expand-div" id="'.$id.'"></div></td></tr>';
                        },
                        'layout'       => '{items}<div class="row"><div class="col-sm-4"><div class="gridview-summary">{summary}</div></div><div class="col-sm-8"><div class="gridview-pager">{pager}</div></div></div>',
                        'columns' => [
                            [
                                'format'         => 'raw',
                                'options'        => ['style' => 'width:3%'],
                                'contentOptions' => ['class' => 'text-center'],
                                'value'          => function($model) { /** @var $model \app\models\Device */
                                    return Html::a('<i class="fa fa-caret-square-o-down"></i>', 'javascript:;', [
                                        'class'         => 'ajaxGridExpand',
                                        'title'         => Yii::t('app', 'Device attributes'),
                                        'data-ajax-url' => Url::to(['device/ajax-get-device-attributes', 'device_id' => $model->id]),
                                        'data-div-id'   => "#info_{$model->id}",
                                        'data-multiple' => 'false'
                                    ]);
                                },
                            ],
                            [
                                'attribute' => 'vendor',
                                'value' => function($model) { /** @var $model \app\models\Device */
                                    return $model->vendorName ?: 'Unknown';
                                },
                            ],
                            [
                                'attribute' => 'model',
                                'value' => function($model) { /** @var $model \app\models\Device */
                                    return $model->name;
                                },
                            ],
                            [
                                'attribute'     => 'auth_template_name',
                            ],
                            [
                                'class'    => \yii\grid\ActionColumn::class,
                                'template' =>'{edit} {delete}',
                                'buttons'  => [
                                    'edit' => function (/** @noinspection PhpUnusedParameterInspection */$url, $model) { /** @var $model \app\models\Device */
                                        return Html::a('<i class="fa fa-pencil-square-o"></i>', ['/network/device/edit', 'id' => $model->id], [
                                            'title'     => Yii::t('app', 'Edit'),
                                            'data-pjax' => '0',
                                        ]);
                                    },
                                    'delete' => function (/** @noinspection PhpUnusedParameterInspection */$url, $model) { /** @var $model \app\models\Device */
                                        return Html::a('<i class="fa fa-trash-o"></i>', ['/network/device/delete', 'id' => $model->id], [
                                            'title' => Yii::t('app', 'Delete'),
                                            'style' => 'color: #D65C4F',
                                            'data'  =>[
                                                'pjax'      => '0',
                                                'method'    => 'post',
                                                'confirm'   => Yii::t('network', 'Are you sure you want to delete device {0} {1}?', [$model->vendor, $model->model]),
                                                'params'    => ['id' => $model->id],
                                            ]
                                        ]);
                                    },
                                ],
                                'contentOptions' => [
                                    'class' => 'narrow'
                                ]
                            ],
                        ],
                    ]);
                ?>
                <?php Pjax::end(); ?>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="box box-default">
            <div class="box-header with-border">
                <i class="fa fa-list"></i><h3 class="box-title box-title-align"><?= Yii::t('network', 'Vendors') ?></h3>
                <div class="box-tools pull-right">
                    <?php
                        echo Html::a('<i class="fa fa-plus"></i>', ['/network/vendor/add'], [
                            'class' => 'btn btn-box-tool',
                            'style' => 'margin-top: 7px;',
                            'title' => Yii::t('app', 'Add')
                        ]);
                    ?>
                </div>
            </div>
            <div class="box-body no-padding">
                <table class="table">
                    <?php foreach ($vendors as $vendor): ?>
                        <tr>
                            <td><?= $vendor['name'] ?></td>
                            <td class="narrow">
                                <?php
                                    echo Html::a('<i class="fa fa-pencil-square-o"></i>', ['/network/vendor/edit', 'name' => $vendor['name']], [
                                        'title' => Yii::t('app', 'Edit'),
                                        'data-pjax' => '0'
                                    ]);
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Modal form container -->
<div class="modal fade" id="form_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div id="form_modal_content"></div>
    </div>
</div>

<?php
$this->registerJs(/** @lang JavaScript */
    '
        /** Load modal content via AJAX */
        $(document).on("click", "a[data-target=\"#form_modal\"]", function (e) {
            var modal = $("#form_modal");
            var url = $(this).attr("href");
            
            // Clear previous content
            modal.find("#form_modal_content").empty();
            
            // Load content via AJAX (renderAjax includes scripts)
            $.ajax({
                url: url,
                type: "GET",
                dataType: "html",
                success: function(data) {
                    // Insert HTML content (renderAjax includes scripts in the response)
                    modal.find("#form_modal_content").html(data);
                    modal.modal("show");
                    
                    // Init select2 after modal is shown and scripts are executed
                    // renderAjax automatically executes script tags, so select2 should be available
                    setTimeout(function() {
                        if (typeof $.fn.select2 !== "undefined") {
                            $("#form_modal .select2").select2({
                                width: "100%"
                            });
                        } else {
                            // Select2 should be loaded on main page, retry
                            setTimeout(function() {
                                if (typeof $.fn.select2 !== "undefined") {
                                    $("#form_modal .select2").select2({
                                        width: "100%"
                                    });
                                }
                            }, 500);
                        }
                    }, 300);
                },
                error: function(xhr, status, error) {
                    var errorMsg = "Error loading form: " + error;
                    modal.find("#form_modal_content").html(
                        "<div class=\"alert alert-danger\">" + errorMsg + "</div>"
                    );
                    modal.modal("show");
                }
            });
            
            return false;
        });
        
        /** Handle button click as alternative to form submit */
        $(document).on("click", "#save", function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var form = $("#device_form");
            if (form.length === 0) {
                return false;
            }
            
            // Trigger form submit
            form.trigger("submit");
            return false;
        });
        
        /** Device form AJAX submit handler - use event delegation for dynamically loaded content */
        $(document).on("submit", "#device_form", function (e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            var form = $(this);
            
            // Get fields - try different selectors
            var vendorField = form.find("select[name=\"Device[vendor]\"]");
            if (vendorField.length === 0) {
                vendorField = form.find("select[name*=\"vendor\"]").first();
            }
            
            var modelField = form.find("input[name=\"Device[model]\"]");
            if (modelField.length === 0) {
                modelField = form.find("input[name*=\"model\"]").first();
            }
            
            // Client-side validation
            var hasErrors = false;
            
            // Check vendor - for select2, need to get value properly
            var vendorValue = vendorField.length > 0 ? vendorField.val() : null;
            if (vendorField.length > 0 && typeof $.fn.select2 !== "undefined") {
                try {
                    var select2Val = vendorField.select2("val");
                    if (select2Val) {
                        vendorValue = select2Val;
                    }
                } catch(e) {
                    // Silently handle select2 errors
                }
            }
            
            if (!vendorValue || vendorValue === "" || vendorValue === "0" || vendorValue === null) {
                if (vendorField.length > 0) {
                    vendorField.closest(".form-group").addClass("has-error");
                }
                toastr.error("Vendor is required", "", {toastClass: "no-shadow", timeOut: 5000, closeButton: true});
                hasErrors = true;
            } else {
                if (vendorField.length > 0) {
                    vendorField.closest(".form-group").removeClass("has-error");
                }
            }
            
            // Check model
            var modelValue = modelField.length > 0 ? modelField.val() : "";
            if (!modelValue || modelValue.trim() === "") {
                if (modelField.length > 0) {
                    modelField.closest(".form-group").addClass("has-error");
                }
                toastr.error("Model is required", "", {toastClass: "no-shadow", timeOut: 5000, closeButton: true});
                hasErrors = true;
            } else {
                if (modelField.length > 0) {
                    modelField.closest(".form-group").removeClass("has-error");
                }
            }
            
            if (hasErrors) {
                return false;
            }
            
            // If validation passes, submit via modalFormHandler
            modalFormHandler(form, "form_modal", "save");
            return false;
        });
        
        /** Modal shown event handler - init select2 when modal is fully shown */
        $(document).on("shown.bs.modal", "#form_modal", function () {
            // Check if select2 is available and init
            if (typeof $.fn.select2 !== "undefined") {
                $("#form_modal .select2").select2({
                    width: "100%"
                });
            }
        });
        
        /** Modal hidden event handler */
        $(document).on("hidden.bs.modal", "#form_modal", function () {
            var toast = $("#toast-container");
            
            /** Reload grid after record was added */
            if (toast.find(".toast-success, .toast-warning").is(":visible")) {
                $.pjax.reload({container: "#device-pjax", timeout: 10000});
                location.reload(); // Reload page to update vendors list
            }
            
            /** Remove errors after modal close */
            toast.find(".toast-error").fadeOut(1000, function() { $(this).remove(); });
        });
        
        /** Init select2 on page load - only if select2 is available */
        if (typeof $.fn.select2 !== "undefined") {
            $(".select2").select2({
                width: "100%"
            });
        }
    '
);
?>
