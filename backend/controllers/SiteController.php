<?php
namespace backend\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use backend\models\LoginForm;
use common\models\Order;
use common\models\OrderItem;
use common\models\User;

/**
 * Site controller
 */
class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        // allow for everybody:
                        'actions' => ['login', 'forgot-password', 'error'],
                        'allow' => true,
                    ],
                    [
                      // only autorised user can access:
                        'actions' => ['logout', 'index'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {

        $totalEarnings = Order::find()->paid()->sum('total_price');
        $totalOrders = Order::find()->paid()->count();
        $totalProducts = OrderItem::find()
                ->alias('oi')
                ->innerJoin(Order::tableName(). ' o',  ' o.id = oi.order_id')
                ->andWhere(['o.status' => [Order::STATUS_PAID, Order::STATUS_COMPLETED]])
                ->sum('quantity');
        $totalUsers = User::find()->andWhere(['status' => User::STATUS_ACTIVE])->count();

        $orders = Order::findBySql("
                                SELECT
                                    DATE_FORMAT(FROM_UNIXTIME(o.created_at), '%Y-%m-%d') as date,
                                    SUM(o.total_price) AS total_price
                                FROM orders o
                                WHERE status IN (".Order::STATUS_PAID.", ".Order::STATUS_COMPLETED.")
                                GROUP BY DATE_FORMAT(FROM_UNIXTIME(o.created_at), '%Y-%m-%d')
                                ORDER BY DATE_FORMAT(FROM_UNIXTIME(o.created_at), '%Y-%m-%d')
                                    ")
                ->asArray()->all();

        // Line Chart
        $earningsData = [];
        $labels = [];
        if(!empty($orders)) {
            $minDate = $orders[0]['date'];
            $ordersByPriceMap = \yii\helpers\ArrayHelper::map($orders, 'date', 'total_price');
            $d = new \DateTime($minDate);
//            echo '<pre>';
//            var_dump($d);
//            echo '</pre>';
//            exit;
            $nowDate = new \DateTime();
            while($d->getTimestamp() < $nowDate->getTimestamp()) {
                $label = $d->format('d/m/Y');
                $labels[] = $label;
                $earningsData[] = (float)($ordersByPriceMap[$d->format('Y-m-d')] ?? 0);
                // 86400 is number of seconds per day
                $d->setTimestamp($d->getTimestamp() + 86400);
            }
        }

        // Pie Chart
        $countriesData = Order::findBySql("
            SELECT
                country,
                    SUM(total_price) AS total_price
                FROM orders o
                INNER JOIN order_addresses oa ON o.id=oa.order_id
                WHERE o.status IN (".Order::STATUS_PAID.", ".Order::STATUS_COMPLETED.")
                GROUP BY country
                ")
                ->asArray()
                ->all();

        $countryLabels = \yii\helpers\ArrayHelper::getColumn($countriesData, 'country');

        $colorOptions = ['#4e73df', '#1cc88a', '#36b9cc'];
        $bgColors = [];
        foreach($countryLabels as $i => $country) {
            // ovo je varijanta random boja za svaku drzavu
            // mana je ta da ruzno izgleda
            // $color = "rgb(". rand(0, 255).", ". rand(0, 255).",". rand(0, 255).")";
            // $bgColors[] = $color;

            // $i % count($colorOptions): index podeli sa brojem elemenata u $colorOptions.
            // mana je ta da imacemo boje koje se ponavljaju - svaka cetvrta drzava ce imati istu boju
            $bgColors[] = $colorOptions[$i % count($colorOptions)];
        }

        return $this->render('index', [
            'totalEarnings' => $totalEarnings,
            'totalOrders' => $totalOrders,
            'totalProducts' => $totalProducts,
            'totalUsers' => $totalUsers,
            'data' => $earningsData,
            'labels' => $labels,
            'countries' => $countryLabels,
            'bgColors' => $bgColors,
            'countriesData' => \yii\helpers\ArrayHelper::getColumn($countriesData, 'total_price')
        ]);
    }

    /**
     * Login action.
     *
     * @return string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $this->layout = 'blank';

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        } else {
            $model->password = '';

            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Logout action.
     *
     * @return string
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    public function actionForgotPassword() {

        return "Forgot password";

    }
}
