<?php

namespace mamadali\S3Storage\models;

use Yii;

/**
 * This is the ActiveQuery class for [[StorageFiles]].
 *
 * @see StorageFiles
 */
class StorageFilesQuery extends \yii\db\ActiveQuery
{

    /**
     * {@inheritdoc}
     * @return StorageFiles[]|array
     */
    public function all($db = null): array
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return StorageFiles|array|null
     */
    public function one($db = null): array|StorageFiles|null
    {
        return parent::one($db);
    }

    public function active(): StorageFilesQuery
    {
        return $this->onCondition(['<>',StorageFiles::tableName().'.status', StorageFiles::STATUS_DELETED]);
    }

    public function sharedWithClient(): StorageFilesQuery
    {
        return $this->andOnCondition([
            'OR',
            ['IS NOT', "JSON_SEARCH(" . StorageFileShared::tableName() . ".`shared_with`, 'one', '*')", null],
            ['IS NOT', "JSON_SEARCH(" . StorageFileShared::tableName() . ".`shared_with`, 'one', '" . Yii::$app->client->id . "')", null],
            [StorageFileShared::tableName() . '.slave_id' => Yii::$app->client->id],
        ]);
    }

	public function byCreatorId($id): StorageFilesQuery
    {
		return $this->andWhere([StorageFiles::tableName().'.created_by' => $id]);
	}

	public function byUpdatedId($id): StorageFilesQuery
    {
		return $this->andWhere([StorageFiles::tableName().'.updated_by' => $id]);
	}

	public function byStatus($status): StorageFilesQuery
    {
		return $this->andWhere([StorageFiles::tableName().'.status' => $status]);
	}

	public function byId($id): StorageFilesQuery
    {
		return $this->andWhere([StorageFiles::tableName().'.id' => $id]);
	}

    public function byModelType($modelType): StorageFilesQuery
    {
        return $this->andWhere([StorageFiles::tableName().'.model_type' => $modelType]);
    }

    public function byModel($modelType, $modelId = null, $attribute = null): StorageFilesQuery
    {
        return $this->andWhere([StorageFiles::tableName().'.model_type' => $modelType, StorageFiles::tableName().'.model_id' => $modelId, StorageFiles::tableName().'.attribute' => $attribute]);
    }
}
