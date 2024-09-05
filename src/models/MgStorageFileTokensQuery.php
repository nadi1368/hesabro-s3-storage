<?php

namespace mamadali\S3Storage\models;

use common\models\mongo\MGTarget;
use yii\mongodb\ActiveQuery;

/**
 * This is the ActiveQuery class for [[MGTarget]].
 *
 * @see MGFactor
 */
class MgStorageFileTokensQuery extends ActiveQuery
{
    /**
     * @inheritdoc
     * @return MGTarget[]|array
     */
    public function all($db = null): array
    {
        return parent::all($db);
    }

    public function one($db = null)
    {
        return parent::one($db);
    }

    public function byClient(): self
    {
        return $this->andWhere(['slave_id' => \Yii::$app->client->id]);
    }

    public function byToken(string $token): self
    {
        return $this->andWhere(['token' => $token]);
    }

    public function byIp(string $ip): self
    {
        return $this->andWhere(['ip' => $ip]);
    }

    public function byStorageFileId(int $storage_file_id): self
    {
        return $this->andWhere(['storage_file_id' => $storage_file_id]);
    }

    public function byUserId(int $user_id): self
    {
        return $this->andWhere(['user_id' => $user_id]);
    }

    public function notExpired(): self
    {
        return $this->andWhere(['>', 'expire_at', time()]);
    }

    public function expired(): self
    {
        return $this->andWhere(['<', 'expire_at', time()]);
    }
}