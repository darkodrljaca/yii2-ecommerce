<?php

use yii\helpers\Html;
// use yii\widgets\ActiveForm;
use yii\bootstrap4\ActiveForm;

// instalirana malo starija verzija (2.1.0) zbog nekih zavisnosti koje su nepotrebne
// php composer.phar require 2amigos/yii2-ckeditor-widget:2.1.0
use dosamigos\ckeditor\CKEditor;


/* @var $this yii\web\View */
/* @var $model common\models\Product */
/* @var $form yii\bootstrap4\ActiveForm */
?>

<div class="product-form">

    <?php $form = ActiveForm::begin([
        'options' => ['enctype' => 'multipart/form-data']
    ]); ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'description')->widget(CKEditor::className(),[
        'options' => ['rows' => 6],
        'preset' => 'basic'
    ]) ?>

    <?= $form->field($model, 'imageFile', [
        'template' =>
          '
            <div class="custom-file">
              {input}
              {label}
              {error}
            </div>
          ',
        'inputOptions' => ['class' => 'custom-file-input'],
        'labelOptions' => ['class' => 'custom-file-label']
    ])->textInput(['type' => 'file']) ?>

    <?= $form->field($model, 'price')->textInput([
        'maxlength' => true,
        'type' => 'number',
        'step' => '0.01'
        ]) ?>

    <?= $form->field($model, 'status')->checkbox() ?>

    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
