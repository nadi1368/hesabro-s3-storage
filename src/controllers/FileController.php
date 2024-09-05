<?php

namespace mamadali\S3Storage\controllers;

use mamadali\S3Storage\models\GetStorageFileForm;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\RangeNotSatisfiableHttpException;

/**
 * Default controller for the `storage` module
 */
class FileController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' =>
                    [
                        [
                            'allow' => true,
                            'roles' => ['@'],
                        ],
                    ]
            ]
        ];
    }

    /**
     * Renders the index view for the module
     * @return void
     * @throws NotFoundHttpException|RangeNotSatisfiableHttpException
     */
    public function actionIndex($token, $inline = false)
    {
        $model = new GetStorageFileForm([
            'token' => $token,
        ]);
        if($model->validate()){
            $S3 = Yii::$app->s3;
            $object = $S3->loadObject($model->storageFile->fullFilePath);
            $response = Yii::$app->response;
            $headers = $response->getHeaders();
            $headers->set('Content-Type', $model->storageFile->meme_type);
            if ($object !== false) {
                Yii::$app->response->sendContentAsFile($object->getContents(), $model->storageFile->file_name, ['inline' => $inline])->send();
            } else {
                throw new NotFoundHttpException("Can't find requested file!");
            }
        } else {
            throw new NotFoundHttpException('token invalid');
        }
    }
}
