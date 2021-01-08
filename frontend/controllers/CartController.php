<?php

namespace frontend\controllers;

use Yii;
// use yii\web\Controller;
use frontend\base\Controller;
use common\models\CartItem;
use common\models\Product;
use yii\web\Response;


class CartController extends Controller {
    
    
    public function behaviors() {
        
        return [
            [
            
                // Posto metoda actionAdd() vraca array app.js funckciji a treba JSON, neophodno je:
                'class' => \yii\filters\ContentNegotiator::class,
                'only' => ['add'], // metoda actionAdd()
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                ],
            ]
        ];
        
    }
    
    public function actionIndex() {
        
        if(Yii::$app->user->isGuest) {
            // get the items from session
        } else {
            $cartItems = CartItem::findBySql("
                                select 
                                c.product_id as id,
                                p.image,
                                p.name,
                                p.price,
                                c.quantity,
                                p.price * c.quantity as total_price
                                from cart_items c
                                left join products p on p.id = c.product_id
                                where c.created_by = :userId", ['userId' => Yii::$app->user->id])
                    ->asArray()
                    ->all();
        }
        
        return $this->render('index', [
            
            'items' => $cartItems
            
        ]);
        
    }
    
    public function actionAdd() {
        
        // id iz app.js, ili ti iz button-a 'add to cart' iz _product_item (ajax varijanta):
        $id = Yii::$app->request->post('id');
        $product = Product::find()->id($id)->published()->one();
        if(!$product) {
            throw new \yii\web\NotFoundHttpException("Product does not exist");
        }
        
        if(Yii::$app->user->isGuest) {
            // Save in session
        } else {
            
            $userId = Yii::$app->user->id;
            $cartItem = CartItem::find()->userId($userId)->productId($id)->one();
            
            if($cartItem) {
                $cartItem->quantity++;
            } else {
                $cartItem = new CartItem();
                $cartItem->product_id = $id;
                $cartItem->created_by = Yii::$app->user->id;
                $cartItem->quantity = 1;
            }
                        
            if($cartItem->save()) {
                return [
                    'success' => true
                ];
            } else {
                return [
                    'success' => false,
                    'errors' => $cartItem->errors
                ];
            }
        }
        
    }
    
}

