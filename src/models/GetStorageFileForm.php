<?php

namespace mamadali\S3Storage\models;

class GetStorageFileForm extends \yii\base\Model
{
    public string $token = '';
    public ?StorageFiles $storageFile = null;

    public function rules(): array
    {
        return [
            [['token'], 'match', 'pattern' => MgStorageFileTokens::TOKEN_REGEX],
            [['token'], 'validateToken'],
        ];
    }

    public function validateToken()
    {
        if(!$this->hasErrors()){
            if(!$token = MgStorageFileTokens::find()
                ->byToken($this->token)
                ->byUserId(\Yii::$app->user->id)
                ->byIp(\Yii::$app->request->userIP)
                ->notExpired()
                ->limit(1)->one()
            ){
                $this->addError('token', \Yii::t('app', 'invalid token'));
                return;
            }
            $this->storageFile = $token->storageFile;
        }
    }
}