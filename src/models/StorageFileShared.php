<?php

namespace mamadali\S3Storage\models;

use Yii;

class StorageFileShared extends StorageFiles
{

    public static function getDb()
    {
        return Yii::$app->get('clientDb');
    }

    /**
     * {@inheritdoc}
     * @return StorageFilesQuery the active query used by this AR class.
     */
    public static function find(): StorageFilesQuery
    {
        $query = new StorageFilesQuery(get_called_class());
        return $query->active()->sharedWithClient();
    }
}