<?php

function isGuest() {
    return Yii::$app->user->isGuest;
}

function currUserId() {
    return Yii::$app->user->id;
}

function param($key) {
  return Yii::$app->params[$key];
}

// debugger:
function hh($data)
{
    yii\helpers\VarDumper::dump($data, 10, true);
    Yii::$app->end();
}
