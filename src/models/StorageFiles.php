<?php

namespace mamadali\S3Storage\models;

use common\behaviors\JsonAdditional;
use common\behaviors\StatusActiveBehavior;
use common\components\Env;
use common\models\User;
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
 * @property int|null $access
 * @property int|null $model_type
 * @property int|null $model_id
 * @property string|null $attribute
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
 * @property int $slave_id
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

    const ACCESS_PRIVATE = 1;
    const ACCESS_PUBLIC_READ = 2;

    const MODEL_TYPE_CLIENT_SETTINGS = 1;
    const MODEL_TYPE_CATEGORY = 2;
    const MODEL_TYPE_PRODUCT_MAIN = 3;
    const MODEL_TYPE_DOCUMENT = 4;
    const MODEL_TYPE_UPLOAD_EXCEL = 5;
    const MODEL_TYPE_INDICATOR = 6;
    const MODEL_TYPE_EDUCATION_COURSE = 7;
    const MODEL_TYPE_CHANGE_LOGS = 8;
    const MODEL_TYPE_PRODUCT_EXPORT = 9;
    const MODEL_TYPE_TICKETS = 10;
    const MODEL_TYPE_LANDING_IMAGE = 11;
    const MODEL_TYPE_USER = 12;
    const MODEL_TYPE_EMPLOYEE_BRANCH_USER = 13;
    const MODEL_TYPE_AUTOMATION_LETTER = 14;
    const MODEL_TYPE_AUTOMATION_SIGNATURE = 15;
    const MODEL_TYPE_AUTOMATION_PRINT = 16;

    const MODEL_TYPE_FAQ = 17;

    /**
     * @var UploadedFile|null
     */
    public ?UploadedFile $file = null;

    /** Additional Data Properties */
    public $old_link;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%storage_files}}';
    }

	public function behaviors()
	{
		return [
			[
				'class' => TimestampBehavior::class,
			],
			[
				'class' => BlameableBehavior::class,
			],
            [
                'class' => StatusActiveBehavior::class,
            ],
            [
                'class' => JsonAdditional::class,
                'ownerClassName' => self::class,
                'fieldAdditional' => 'additional_data',
                'AdditionalDataProperty' => [
                    'old_link' => 'String',
                ],
            ],
		];
	}

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['access', 'model_type', 'model_id', 'size'], 'integer'],
            [['additional_data'], 'safe'],
            [['file_path', 'file_name', 'meme_type', 'attribute'], 'string', 'max' => 255],
            ['model_type', 'in', 'range' => array_keys(self::itemAlias('ModelType'))],
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
            'model_type' => Yii::t('app', 'Model Type'),
            'model_id' => Yii::t('app', 'Model ID'),
            'file_path' => Yii::t('app', 'File Path'),
            'file_name' => Yii::t('app', 'File Name'),
            'status' => Yii::t('app', 'Status'),
            'created_at' => Yii::t('app', 'Created At'),
            'updated_at' => Yii::t('app', 'Updated At'),
            'created_by' => Yii::t('app', 'Created By'),
            'updated_by' => Yii::t('app', 'Updated By'),
            'additional_data' => Yii::t('app', 'Additional Data'),
            'slave_id' => Yii::t('app', 'Slave ID'),
            'attribute' => Yii::t('app', 'Attribute'),
        ];
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();

        $scenarios[self::SCENARIO_CREATE] = ['access', 'model_type', 'model_id', 'file_path', 'file_name'];

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
        return self::itemAlias('S3Acl', $this->access);
    }

    public function getFileUrl(): string
    {
        return match ($this->access) {
            self::ACCESS_PRIVATE => $this->getPrivateUrlFile(),
            self::ACCESS_PUBLIC_READ => Env::get('AWS_BUCKET_DOMAIN') ? str_replace(Yii::$app->s3->getEndpoint() . '/' . Yii::$app->s3->getDefaultBucket(), Env::get('AWS_BUCKET_DOMAIN'), Yii::$app->s3->getPublicObjectUrl($this->fullFilePath)) : Yii::$app->s3->getPublicObjectUrl($this->fullFilePath),
            default => ''
        };
    }

    public function getFileContent()
    {
        return Yii::$app->s3->loadObject($this->fullFilePath);
    }

    /**
     * @throws Exception
     */
    protected function getPrivateUrlFile(): string
    {
        $token = MgStorageFileTokens::createNewToken($this);
        return Url::to(['/storage/file', 'token' => $token], true);
    }

    public function setFile(UploadedFile $file): void
    {
        $this->file_name = $file->name;
        $this->size = $file->size;
        $this->meme_type = $file->type;
    }

    public static function saveNewFile(UploadedFile $file, int $modelType, string $filePath = '', ?int $modelId = null, ?string $attribute = null, int $access = self::ACCESS_PUBLIC_READ, array $sharedWith = []): bool|StorageFiles
    {
        $storageFile = new StorageFiles();
        $storageFile->file_path = Yii::$app->client->id . '/' . StorageFiles::itemAlias('ModelType', $modelType) . ($filePath ? $filePath . '/' : '/');
        $storageFile->setFile($file);
        $storageFile->model_type = $modelType;
        $storageFile->model_id = $modelId;
        $storageFile->attribute = $attribute;
        $storageFile->access = $access;
        $storageFile->shared_with = $sharedWith;
        $storageFile->file = $file;
        if (is_string($storageFile->file->tempName) && filter_var($storageFile->file->tempName, FILTER_VALIDATE_URL)) {
            $storageFile->old_link = $storageFile->file->tempName;
        }
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
        $s3 = Yii::$app->s3;
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

    public static function formatStorageSpace($bytes) {
        $sizes = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');

        if ($bytes == 0) {
            return '0 B';
        }

        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 3) . ' ' . $sizes[$i];
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
        $s3 = Yii::$app->s3;
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
            'ModelType' => [ // model type folder name
                self::MODEL_TYPE_CLIENT_SETTINGS => 'client-settings',
                self::MODEL_TYPE_CATEGORY => 'category',
                self::MODEL_TYPE_PRODUCT_MAIN => 'product',
                self::MODEL_TYPE_INDICATOR => 'indicator',
                self::MODEL_TYPE_DOCUMENT => 'document',
                self::MODEL_TYPE_UPLOAD_EXCEL => 'upload-excel',
                self::MODEL_TYPE_EDUCATION_COURSE => 'education-course',
                self::MODEL_TYPE_CHANGE_LOGS => 'change-logs',
                self::MODEL_TYPE_PRODUCT_EXPORT => 'product-export',
                self::MODEL_TYPE_TICKETS => 'tickets',
                self::MODEL_TYPE_LANDING_IMAGE => 'landing-images',
                self::MODEL_TYPE_USER => 'user',
                self::MODEL_TYPE_EMPLOYEE_BRANCH_USER => 'employee_branch_user',
                self::MODEL_TYPE_AUTOMATION_LETTER => 'automation_letter',
                self::MODEL_TYPE_AUTOMATION_SIGNATURE => 'automation_signature',
                self::MODEL_TYPE_AUTOMATION_PRINT => 'automation_print',
                self::MODEL_TYPE_FAQ => 'faq',
            ],
            'ModelTypeTitle' => [
                self::MODEL_TYPE_CLIENT_SETTINGS => 'تنظیمات',
                self::MODEL_TYPE_CATEGORY => 'دسته بندی محصولات',
                self::MODEL_TYPE_PRODUCT_MAIN => 'محصولات اصلی',
                self::MODEL_TYPE_INDICATOR => 'اندیکاتور',
                self::MODEL_TYPE_DOCUMENT => 'اسناد',
                self::MODEL_TYPE_UPLOAD_EXCEL => 'آپلود اکسل',
                self::MODEL_TYPE_EDUCATION_COURSE => 'ویدیو های آموزشی',
                self::MODEL_TYPE_CHANGE_LOGS => 'تغییرات اخیر',
                self::MODEL_TYPE_PRODUCT_EXPORT => 'خروجی محصولات',
                self::MODEL_TYPE_TICKETS => 'تیکت ها',
                self::MODEL_TYPE_LANDING_IMAGE => 'بنر و اسلایدشو',
            ],
            'S3Acl' => [
                self::ACCESS_PRIVATE => 'private',
                self::ACCESS_PUBLIC_READ => 'public-read',
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

    public function beforeSave($insert): bool
    {
        $this->shared_with = array_map(function ($client) {
            return (string)$client;
        }, $this->shared_with ?: []);

        return parent::beforeSave($insert);
    }
}
