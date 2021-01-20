<?php

namespace frontend\controllers;

use Yii;
// use yii\web\Controller;
use frontend\base\Controller;
use common\models\CartItem;
use common\models\Product;
use yii\web\Response;
use yii\filters\VerbFilter;
use common\models\OrderAddress;
use common\models\Order;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Payments\AuthorizationsGetRequest;
use yii\web\BadRequestHttpException;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use yii\web\NotFoundHttpException;
use yii\helpers\VarDumper;



class CartController extends Controller {


    public function behaviors() {

        return [
            [

                // Posto metode actionAdd(), actionCreateOrder(), actionSubmitPayment(), actionChangeQuantity()
                //  vracaju array app.js funckciji a treba JSON, neophodno je:
                'class' => \yii\filters\ContentNegotiator::class,
                'only' => ['add', 'create-order', 'submit-payment', 'change-quantity'], // metoda actionAdd() i metoda actionCreateOrder
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                ],
            ],
            [
                // delete je moguc samo uz POST metod:
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST', 'DELETE'],
                    'create-order' => ['POST'],
                ]
            ]
        ];

    }

    public function actionIndex() {
        
        $cartItems = CartItem::getItemsForUser(currUserId());
        
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
            // $found_key moze da bude tipa boolean i tipa integer. Napr. ako je tipa integer i vrednost je nula to znaci
            // da je nasao podatak u array index = 0 sto je ok. Ako nije nasao nista vraca podatak tipa booelan vrednosti
            // false:
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

    public function actionChangeQuantity() {

        $id = Yii::$app->request->post('id');
        $product = Product::find()->id($id)->published()->one();
        if(!$product) {
            throw new \yii\web\NotFoundHttpException("Product does not exist");
        }

        $quantity = Yii::$app->request->post('quantity');
        
        if($quantity >= 1) {
            if(isGuest()) {
                $cartItems = Yii::$app->session->get(CartItem::SESSION_KEY, []);
                foreach($cartItems as &$cartItem) {
                  if($cartItem['id'] === $id) {
                    $cartItem['quantity'] = $quantity;
                    break;
                  }
                }
                Yii::$app->session->set(CartItem::SESSION_KEY, $cartItems);
            } else {
                $cartItem = CartItem::find()->userId(currUserId())->productId($id)->one();
                if($cartItem) {
                  $cartItem->quantity = $quantity;
                  $cartItem->save();
                }
            }
            
        }                
        
        return [
            'quantity' => CartItem::getTotalQuantityForUser(currUserId()),
            'price' => Yii::$app->formatter->asCurrency(CartItem::getTotalPriceForItemForUser($id, currUserId()))
        ];
        
    }
    
    public function actionCheckout() {
        
        $cartItems = CartItem::getItemsForUser(currUserId());
        $productQuantity = CartItem::getTotalQuantityForUser(currUserId());
        $totalPrice = CartItem::getTotalPriceForUser(currUserId());
        
        if(empty($cartItems)) {
            return $this->redirect([Yii::$app->homeUrl]);
        }
        
        $order = new Order();
        $order->total_price = $totalPrice;
        $order->status = Order::STATUS_DRAFT;
        $order->created_at = time();
        $order->created_by = currUserId(); // moze biti i null ako kupuje neko ko se nije registrovao  
        $transaction = Yii::$app->db->beginTransaction();
        
        if($order->load(Yii::$app->request->post()) 
                && $order->save() 
                && $order->saveAddress(Yii::$app->request->post())        
                && $order->saveOrderItems()) {
            $transaction->commit();                         
            
            CartItem::clearCartItems(currUserId());
                                    
            return $this->render('pay-now',[
                'order' => $order,            
            ]);        
        }
        
        $orderAddress = new OrderAddress();
        
        if(!isGuest()) {
            
            /** @var \common\models\User $user */
            $user = Yii::$app->user->identity;
            $userAddress = $user->getAddress();
            
            $order->firstname = $user->firstname;
            $order->lastname = $user->lastname;
            $order->email = $user->email;
            $order->status = Order::STATUS_DRAFT;
            
            
            $orderAddress->address = $userAddress->address;
            $orderAddress->city = $userAddress->city;
            $orderAddress->state = $userAddress->state;
            $orderAddress->country = $userAddress->country;
            $orderAddress->zipcode = $userAddress->zipcode;
            
        } 
        
        return $this->render('checkout', [
            'order' => $order,
            'orderAddress' => $orderAddress,
            'cartItems' => $cartItems,
            'productQuantity' => $productQuantity,
            'totalPrice' => $totalPrice    
        ]);
        
        
    }
    /*
    public function actionSubmitOrder() {
        
        // $transactionId = Yii::$app->request->post('transactionId');
        $status = Yii::$app->request->post('status');
        
        $totalPrice = CartItem::getTotalPriceForUser(currUserId());
        if($totalPrice === null) {
            throw new \yii\web\BadRequestHttpException("Cart is empty");
        }
        
        $order = new Order();
        
        $order->total_price = $totalPrice;
        $order->status = Order::STATUS_DRAFT;
        $order->created_at = time();
        $order->created_by = currUserId(); // moze biti i null ako kupuje neko ko se nije registrovao  
        $transaction = Yii::$app->db->beginTransaction();
        if($order->load(Yii::$app->request->post()) 
                && $order->save() 
                && $order->saveAddress(Yii::$app->request->post())        
                && $order->saveOrderItems()) {
            $transaction->commit(); 
            
            CartItem::clearCartItems(currUserId());
            
            $cartItems = CartItem::getItemsForUser(currUserId());
            $productQuantity = CartItem::getTotalQuantityForUser(currUserId());
            $totalPrice = CartItem::getTotalPriceForUser(currUserId());
            
            return $this->render('pay-now',[
                'order' => $order,
                'orderAddress' => $order->orderAddress,
                'cartItems' => $cartItems,
                'productQuantity' => $productQuantity,
                'totalPrice' => $totalPrice 
            ]);
            
        } else {
            $transaction->rollBack();
            return [
                'success' => false,
                'errors' => $order->errors
            ];
        }
        
    }
    */
    
    public function actionSubmitPayment($orderId) {
        
        
        $where = ['id' => $orderId, 'status' => Order::STATUS_DRAFT];
        if(!isGuest()) {
            $where['created_by'] = currUserId();
        }
        $order = Order::findOne($where);
        if(!$order) {
            throw new NotFoundHttpException();
        }
                
        $req = Yii::$app->request;
        $paypalOrderId = $req->post('orderId');        
        $exists = Order::find()->andWhere(['paypal_order_id' => $paypalOrderId])->exists();
        if($exists) {
            throw new BadRequestHttpException();
        }       
        
        $environment = new SandboxEnvironment(Yii::$app->params['paypalClientId'], Yii::$app->params['paypalSecret']);
        $client = new PayPalHttpClient($environment);
        $response = $client->execute(new OrdersGetRequest($paypalOrderId));
        
        if($response->statusCode === 200) {
            $order->paypal_order_id = $paypalOrderId;
            $payedAmount = 0;
            foreach($response->result->purchase_units as $purchase_unit) {
                if($purchase_unit->amount->currency_code === 'USD') {
                    $payedAmount += $purchase_unit->amount->value;
                }
            }
            if($payedAmount === (float)$order->total_price && $response->result->status === 'COMPLETED') {
                $order->status = Order::STATUS_COMPLETED;
            }
            $order->transaction_id = $response->result->purchase_units[0]->payments->captures[0]->id;
            if($order->save()) {
                if(!$order->sendEmailToVendor()) {
                    Yii::error("The email was not sent to the vendor");
                }  
                if(!$order->sendEmailToCustomer()) {
                    Yii::error("The email was not sent to the customer");
                }
                                
                return [
                    'success' => true
                ];
            } else {
                Yii::error("Order was not saved. Data: " . 
                        VarDumper::dumpAsString($order->toArray()) . 
                        ". Errors: " . VarDumper::dumpAsString($order->errors));
            }
        }
        
        throw new BadRequestHttpException();                        
        
    }
    
    
    

}
