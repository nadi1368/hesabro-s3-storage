<?php

namespace mamadali\S3Storage\models;

use Yii;

class StorageFileShared extends \mamadali\S3Storage\models\StorageFiles
{

    public static function getDb()
    {
        return Yii::$app->get('clientDb');
    }

    /**
     * {@inheritdoc}
     * @return \mamadali\S3Storage\models\StorageFilesQuery the active query used by this AR class.
     */
    public static function find(): \mamadali\S3Storage\models\StorageFilesQuery
    {
        $query = new \mamadali\S3Storage\models\StorageFilesQuery(get_called_class());
        return $query->active()->sharedWithClient();
    }
}