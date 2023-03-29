<?php

namespace app\modules\api\controllers;

use Yii;
use app\models\User;
use app\models\IpLimiter;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\ServerErrorHttpException;
use yii\web\UnauthorizedHttpException;
use yii\web\NotFoundHttpException;

class AuthController extends Controller
{
     public function behaviors()
     {
          $behaviors = parent::behaviors();

          // remove authentication filter
          $auth = $behaviors['authenticator'];
          unset($behaviors['authenticator']);

          // add CORS filter
          $behaviors['corsFilter'] = [
               'class' => \yii\filters\Cors::className(),
          ];

          // re-add authentication filter
          $behaviors['authenticator'] = $auth;
          // avoid authentication on CORS-pre-flight requests (HTTP OPTIONS method)
          $behaviors['authenticator']['except'] = ['options'];

          return $behaviors;
     }

     public function actionRegister()
     {
          $out = [
               'ok' => 0,
               'status' => 200,
          ];

          $in = \Yii::$app->request->post();

          $email = $in['email'];
          $password = $in['password'];
          $username = $in['username'];
          

          if ($email && $password && $username) {

               $user = new User();

               $user->email = $email;
               $user->password = $password;
               $user->username = $username;
               $user->created_at = time();
               $user->updated_at = time();
               
               $user->access_token = \Yii::$app->security->generateRandomString();

               $userSaved = $user->save(false);

               if (!$userSaved) {
                    $out['err']['user not saved'] = [
                         $user->getErrors(),
                         $user->errors,
                         $user->getAttributes(),
                    ];
               } else {
                    $out['user_token'] = $user->access_token;
                    $out['username'] = $user->username;
                    $out['email'] = $user->email;
               }

          } else {
               throw new BadRequestHttpException('Not all data provided');
          }

          return $out;
     }

     public function actionLogin ()
     {
          $out = [
               'ok' => 0,
               'status' => 200,
          ];

          $in = \Yii::$app->request->post();

          $email = $in['email'];
          $password = $in['password'];
          $username = $in['username'];

          $user = User::find()
               ->where(['email'=>$email])
               ->orWhere(['username'=>$username])
               ->one();

          if ($user) {
               // Блокировка учетной записи B2
               if ($user->isLoginBlocked()) {
                    throw new ForbiddenHttpException('Your account is blocked until ' . date("d.m.Y H:i:s", $user->login_locked_until));
               } 
               if (Yii::$app->security->validatePassword($password, $user->password_hash)) {
                    $user->updateFailedLoginAttempts(true);
                    $out['ok'] = 1;
                    $out['user'] = $user->getAttributes();
               } else {
                    $user->updateFailedLoginAttempts(false);
               }
          } else {
               throw new NotFoundHttpException('User not found');
          }

          return $out;
     }

     public function actionCheckUser ()
     {
          $accessToken = Yii::$app->request->headers->get('Authorization');
          $accessToken = substr($accessToken, 7);
          $user = User::findIdentityByAccessToken($accessToken);

          if ($user) {
               return [
                    'ok' => 1,
                    'status' => 200,
                    'user_token' => $user->access_token,
               ];
          } else {
               throw new \yii\web\UnauthorizedHttpException();
          }
     }

}