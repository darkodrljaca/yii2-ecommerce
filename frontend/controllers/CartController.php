<?php

namespace frontend\controllers;

use Yii;
// use yii\web\Controller;
use frontend\base\Controller;
use common\models\CartItem;
use common\models\Product;
use yii\web\Response;
use yii\filters\VerbFilter;


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
            ],
            [
                // delete je moguc samo uz POST metod:
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST', 'DELETE'],
                ]
            ]
        ];

    }

    public function actionIndex() {

        if(Yii::$app->user->isGuest) {
            // get the items from session
            // ovo na kraju ", []", znaci da ako ne postoji daj prazan array
            $cartItems = Yii::$app->session->get(CartItem::SESSION_KEY, []);
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
            $cartItem=[
                'id' => $id,
                'name' => $product->name,
                'image' => $product->image,
                'price' => $product->price,
                'quantity' => 1,
                'total_price' => $product->price
            ];

            $cartItems = Yii::$app->session->get(CartItem::SESSION_KEY, []);            
            $id_ = array_column($cartItems, 'id');
            $found_key = array_search($id, $id_);            
            // zato sto $found_key moze da bude i integer koji je napr. nula i to onda znaci da je nasao index nula:
            if(is_bool($found_key) === true && !$found_key) {                            
                array_push($cartItems, $cartItem);
            } else {
                $cartItems[$found_key]['quantity'] = $cartItems[$found_key]['quantity'] + 1;                 
            }
            Yii::$app->session->set(CartItem::SESSION_KEY, $cartItems);
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

    public function actionDelete($id) {

        if(isGuest()) {
            $cartItems = Yii::$app->session->get(CartItem::SESSION_KEY, []);
            foreach($cartItems as $i => $cartItem) {
                if($cartItem['id'] == $id) {
                    array_splice($cartItems, $i, 1);
                    break;
                }
            }
            Yii::$app->session->set(CartItem::SESSION_KEY, $cartItems);
        } else {
            CartItem::deleteAll(['product_id' => $id, 'created_by' => currUserId()]);
        }

        return $this->redirect(['index']);
    }

}
