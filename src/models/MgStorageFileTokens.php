<?php

namespace mamadali\S3Storage\models;

use common\components\Helper;
use Yii;
use yii\base\Exception;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveQueryInterface;
use yii\mongodb\ActiveRecord;

/**
 * This is the model class for table "{{%user_verify}}".
 *
 * @property string $token
 * @property int $storage_file_id
 * @property int $user_id
 * @property string $ip
 * @property int $expire_at
 * @property int $created_at
 * @property int $created_by
 * @property int $updated_at
 * @property int $updated_by
 * @property int $slave_id
 *
 * @property StorageFiles $storageFile
 */
class MgStorageFileTokens extends ActiveRecord
{

    const TOKEN_REGEX = '/^[a-z0-9A-Z]+$/';

    public static function collectionName(): string
    {
        return 'storage_file_tokens';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
            ],
            [
                'class' => BlameableBehavior::class,
            ],
        ];
    }

    /**
     * @return array list of attribute names.
     */
    public function attributes(): array
    {
        return [
            '_id',
            'token',
            'storage_file_id',
            'user_id',
            'ip',
            'expire_at',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
            'slave_id',
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['token', 'user_id', 'ip', 'expire_at', 'storage_file_id'], 'required'],
            [['user_id', 'expire_at', 'created_at', 'created_by', 'updated_at', 'updated_by', 'slave_id', 'storage_file_id'], 'integer'],
            [['token'], 'string', 'max' => 256],
            [['token'], 'unique'],
            [['token'], 'match', 'pattern' => self::TOKEN_REGEX],
            [['ip'], 'ip'],
            //[['storage_file_id'], 'exist', 'targetClass' => StorageFileShared::class, 'targetAttribute' => ['storage_file_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     * @return MgStorageFileTokensQuery the active query used by this AR class.
     */
    public static function find(): MgStorageFileTokensQuery
    {
        $query = new MgStorageFileTokensQuery(get_called_class());
        return $query->byClient();
    }

    public function getStorageFile(): ActiveQueryInterface|ActiveQuery
    {
        return $this->hasOne(StorageFileShared::class, ['id' => 'storage_file_id']);
    }

    /**
     * @throws Exception
     */
    public static function createNewToken(StorageFiles $storageFile, int $expireMinutes = 30): string|bool
    {
        if(!$tokenModel = self::find()
            ->byStorageFileId($storageFile->id)
            ->byUserId(Yii::$app->user->id)
            ->byIp(Yii::$app->request->userIP)
            ->limit(1)->one()
        ) {
            $token = self::generateRandomString();
            $tokenModel = new self([
                'token' => $token,
                'storage_file_id' => $storageFile->id,
            ]);
        }
        $tokenModel->expire_at = time() + ($expireMinutes * 60);
        self::deleteExpired();
        if($tokenModel->save()){
            return $tokenModel->token;
        }
        return false;
    }

    public static function deleteExpired(): int
    {
        return self::deleteAll(['<', 'expire_at', time()]);
    }

    protected static function generateRandomString($length = 32): string
    {
        $random = Helper::generateRandomString($length);
        if (self::find()->byToken($random)->limit(1)->exists()) {
            return self::generateRandomString($length);
        }
        return $random;
    }

    public function beforeValidate(): bool
    {
        $this->ip = Yii::$app->request->getUserIP();
        $this->user_id = Yii::$app->user->id;
        return parent::beforeValidate();
    }

    public function beforeSave($insert): bool
    {
        $this->slave_id = Yii::$app->client->id;
        return parent::beforeSave($insert);
    }
}