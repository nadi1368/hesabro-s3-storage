<?php

namespace mamadali\S3Storage\models;

use common\behaviors\StatusActiveBehavior;
use common\components\Env;
use common\models\User;
use mamadali\S3Storage\components\S3Storage;
use Yii;
use yii\base\Exception;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\helpers\Url;
use yii\web\UploadedFile;

/**
 * This is the model class for table "storage_files".
 *
 * @property int $id
 * @property int $access
 * @property string $model_class
 * @property int $model_id
 * @property string $attribute
 * @property string|null $file_path
 * @property string|null $file_name
 * @property int|null $size
 * @property string|null $meme_type
 * @property int $status
 * @property int $created_at
 * @property int $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property array $additional_data
 * @property array $shared_with
 *
 * @property-read User $update
 * @property-read User $creator
 * @property string $fullFilePath
 */
class StorageFiles extends \yii\db\ActiveRecord
{
    const STATUS_ACTIVE = 1;
    const STATUS_DELETED = 0;

	const SCENARIO_CREATE = 'create';

    /**
     * @var UploadedFile|null
     */
    public ?UploadedFile $file = null;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%storage_files}}';
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
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['access', 'model_id', 'size'], 'integer'],
            [['model_class'], 'string'],
            [['file_path', 'file_name', 'meme_type', 'attribute'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'access' => Yii::t('app', 'Access'),
            'model_class' => Yii::t('app', 'Model Class'),
            'model_id' => Yii::t('app', 'Model ID'),
            'file_path' => Yii::t('app', 'File Path'),
            'file_name' => Yii::t('app', 'File Name'),
            'status' => Yii::t('app', 'Status'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
            'created_by' => Yii::t('app', 'Created By'),
            'updated_by' => Yii::t('app', 'Updated By'),
            'additional_data' => Yii::t('app', 'Additional Data'),
            'attribute' => Yii::t('app', 'Attribute'),
        ];
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();

        $scenarios[self::SCENARIO_CREATE] = ['access', 'model_class', 'model_id', 'file_path', 'file_name'];

        return $scenarios;
    }

    /**
    * @return \yii\db\ActiveQuery
    */
	public function getCreator()
	{
		return $this->hasOne(User::class, ['id' => 'created_by']);
	}

	/**
	* @return \yii\db\ActiveQuery
	*/
	public function getUpdate()
	{
		return $this->hasOne(User::class, ['id' => 'updated_by']);
	}

    public function getFullFilePath(): string
    {
        return $this->file_path . $this->file_name;
    }

    public function getS3Acl()
    {
        return S3Storage::itemAlias('S3Acl', $this->access);
    }

    /**
     * @throws Exception
     */
    public function getFileUrl(): string
    {
        return match ($this->access) {
            S3Storage::ACCESS_PRIVATE => $this->getPrivateUrlFile(),
            S3Storage::ACCESS_PUBLIC_READ => S3Storage::getPublicUrl($this->fullFilePath),
            default => ''
        };
    }

    public function getFileContent()
    {
        return Yii::$app->s3storage->loadObject($this->fullFilePath);
    }

    /**
     * @throws Exception
     */
    protected function getPrivateUrlFile(): string
    {
        $url = Yii::$app->s3storage->getPrivateObjectUrl($this->fullFilePath);
        if(Yii::$app->s3storage->bucket_domain){
            $url = str_replace(Yii::$app->s3storage->getDefaultBucket() . '.' . Yii::$app->s3storage->endpoint, Yii::$app->s3storage->bucket_domain, $url);
        }
        return $url;
    }

    public function setFile(UploadedFile $file): void
    {
        $this->file_name = $file->name;
        $this->size = $file->size;
        $this->meme_type = $file->type;
    }

    public static function saveNewFile(UploadedFile $file, string $modelClass, string $filePath, ?int $modelId = null, ?string $attribute = null, int $access = S3Storage::ACCESS_PUBLIC_READ, array $sharedWith = []): bool|StorageFiles
    {
        $storageFile = new StorageFiles();
        $storageFile->file_path = ($filePath ? $filePath . '/' : '/');
        $storageFile->setFile($file);
        $storageFile->model_class = $modelClass;
        $storageFile->model_id = $modelId;
        $storageFile->attribute = $attribute;
        $storageFile->access = $access;
        $storageFile->shared_with = $sharedWith;
        $storageFile->file = $file;
        $flag = $storageFile->save();
        return $flag ? $storageFile : false;
    }

    public static function createUploadedFileWithPath(string $file_path): ?UploadedFile
    {
        if (file_exists($file_path)) {
            $base_name = basename($file_path);
    
            $uploadedFile = new UploadedFile();
            $uploadedFile->name = $base_name;
            $uploadedFile->tempName = $file_path;
            $uploadedFile->size = filesize($file_path);
            $uploadedFile->type = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file_path);
            return $uploadedFile;
        }

        return null;
    }

    public function uploadFile(): bool
    {
        $s3 = Yii::$app->s3storage;
        if($this->file instanceof UploadedFile) {
            return $s3->uploadObject($this->fullFilePath, $this->file->tempName, acl: $this->getS3Acl(), contentType: $this->meme_type);
        }
        return true;
    }

    /**
     * {@inheritdoc}
     * @return StorageFilesQuery the active query used by this AR class.
     */
    public static function find(): StorageFilesQuery
    {
        $query = new StorageFilesQuery(get_called_class());
        return $query->active();
    }

    public function canUpdate(): bool
    {
        return false;
    }

    public function canDelete(): bool
    {
        return true;
    }

    /*
    * حذف منطقی
    */
    public function softDelete(): bool
    {
		if($this->canDelete()){
			$this->status = self::STATUS_DELETED;
            $flag = $this->save();
            $flag = $flag && $this->deleteFile();
            return $flag;
		}
		return false;
    }

    public function deleteFile(): bool
    {
        $s3 = Yii::$app->s3storage;
        return $s3->deleteObject($this->fullFilePath);
    }

    /*
    * فعال کردن
    */
    public function restore()
    {
        $this->status = self::STATUS_ACTIVE;
        if ($this->save()) {
            return true;
        } else {
            return false;
        }
    }

    public function fields()
    {
        return [
            'id',
            'src' => function(self $model) {
                return $model->getFileUrl();
            },
        ];
    }

    public static function itemAlias($type, $code = null)
	{
        $items = match ($type) {
            'Status' => [
                self::STATUS_ACTIVE => Yii::t("app", "Status Active"),
                self::STATUS_DELETED => Yii::t("app", "Status Delete"),
            ],
            default => false
        };

        $items = $items instanceof \Closure ? $items() : $items;
        if (isset($code)) {
            return $items[$code] ?? false;
        } else {
            return $items ?: false;
        }
	}

    public function beforeSave($insert)
    {
        if($this->isNewRecord){
            $this->status = self::STATUS_ACTIVE;
        }
        return parent::beforeSave($insert);
    }
}
