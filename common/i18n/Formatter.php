<?php

namespace common\i18n;

use common\models\Order;

class Formatter extends \yii\i18n\Formatter {
    
    
    public function asOrderStatus($status) {
        
        if($status === Order::STATUS_COMPLETED) {
            return \yii\bootstrap4\Html::tag('span', 'Completed', ['class' => 'badge badge-success']);
        } else if($status === Order::STATUS_PAID) {
            return \yii\bootstrap4\Html::tag('span', 'Paid', ['class' => 'badge badge-primary']);
        } else if($status === Order::STATUS_DRAFT) {
            return \yii\bootstrap4\Html::tag('span', 'Unpaid', ['class' => 'badge badge-secundary']);
        } else {
            return \yii\bootstrap4\Html::tag('span', 'Failured', ['class' => 'badge badge-danger']);
        }
        
        
        
    }
    
    
}
