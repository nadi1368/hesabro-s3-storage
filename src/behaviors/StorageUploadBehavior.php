<?php

namespace mamadali\S3Storage\behaviors;

use mamadali\S3Storage\models\StorageFiles;
use mamadali\S3Storage\models\StorageFileShared;
use mamadali\S3Storage\models\StorageFilesQuery;
use Closure;
use WebPConvert\WebPConvert;
use yii\base\Behavior;
use yii\base\ErrorException;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use yii\web\UploadedFile;

/**
 * @property ActiveRecord $owner
 *
 * basic usage
 *  * ```php
 * use mamadali\S3Storage\behaviors\StorageUploadBehavior;
 *
 * function behaviors()
 * {
 *     return [
 *          [
 *              'class' => StorageUploadBehavior::class,
 *              'modelType' => StorageFiles::MODEL_TYPE_CLIENT_SETTINGS,
 *              'attributes' => ['photo'],
 *              'scenarios' => [self::SCENARIO_CHANGE_VALUE],
 *          ],
 *     ];
 * }
 * ```
 *
 * @property StorageFiles $storageFile
 * @property StorageFiles[] $storageFiles
 */
class StorageUploadBehavior extends Behavior
{
    /**
     * @var array list of model attributes for upload file
     */
    public array $attributes = [];

    /**
     * @var array list of scenarios for upload files
     */
    public array $scenarios = [];

    /**
     * @var Closure|null
     * 'fileName' => function (self $model, string $attribute, UploadedFile $file) {
     *        return $model->generateFileName($attribute, $file);
     * }
     */
    public ?Closure $fileName = null;

    /**
     * @var int one of model types const in StorageFiles Model
     */
    public int $modelType;

    public string $primaryKey = 'id';

    /**
     * @var int one of access const in StorageFiles Model
     */
    public int $accessFile = StorageFiles::ACCESS_PUBLIC_READ;

    /**
     * @var bool delete previous uploaded file on attribute with single file (this not work for multiple file in attribute)
     */
    public bool $deletePreviousFilesOnAttribute = true;

    public bool $convertImageToWebp = false;
    public array $memeTypeForConvertToWebp = ['image/png', 'image/jpg', 'image/jpeg'];

    /**
     * @var array|Closure list of client who can see file OR ['*'] for see in all clients
     */
    public array|Closure $sharedWith = [];

    protected array $_files = [];

    /**
     * @var StorageFiles[]
     */
    protected array $_storageFiles = [];

    /**
     * @var StorageFiles[]
     */
    protected array $_deletedStorageFiles = [];

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        if (empty($this->modelType) || !array_key_exists($this->modelType, StorageFiles::itemAlias('ModelType'))) {
            throw new InvalidConfigException('The "modelType" property must be set.');
        }

        if (!array_key_exists($this->accessFile, StorageFiles::itemAlias('S3Acl'))) {
            throw new InvalidConfigException('The "accessFile" property must be set.');
        }

        if ($this->attributes === []) {
            throw new InvalidConfigException('The "attributes" property must be set.');
        }

        if(count($this->attributes) !== count(array_unique($this->attributes))){
            throw new InvalidConfigException('The "attributes" property must be unique.');
        }
    }

    /**
     * @inheritdoc
     */
    public function events(): array
    {
        return [
            Model::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            BaseActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
        ];
    }

    /**
     * This method is invoked before validation starts.
     */
    public function beforeValidate(): bool
    {
        if ($this->shouldProcess()) {
            foreach ($this->getSafeAttributes() as $attribute) {
                $this->processAttribute($attribute);
            }
        }
        return true;
    }

    /**
     * @throws ErrorException
     */
    public function afterSave(): void
    {
        if ($this->shouldProcess()) {
            foreach ($this->attributes as $attribute) {
                if ($this->owner->$attribute instanceof UploadedFile) {
                    $this->processSingleFile($this->owner->$attribute, $attribute);
                } elseif (is_array($this->owner->$attribute)) {
                    $this->processFileArray($this->owner->$attribute, $attribute);
                }
            }
            if($this->_storageFiles){
                $this->processStorageFiles();
                $this->deletePreviousStorageFiles();
            }
        }
    }

    protected function getSafeAttributes(): array
    {
        $scenario = $this->owner->getScenario();
        $scenarioAttributes = $this->owner->scenarios()[$scenario];
        $attributes = [];
        foreach ($this->attributes as $attribute) {
            if (in_array($attribute, $scenarioAttributes)) {
                $attributes[] = $attribute;
            }
        }
        return $attributes;
    }

    protected function processAttribute(string $attribute): void
    {
        $file = $this->getUploadedFile($attribute);
        if($file instanceof UploadedFile){
            $file = $this->processFile($file, $attribute);
        } else {
            foreach (($file ?: []) as $f) {
                if ($f instanceof UploadedFile) {
                    $this->processFile($f, $attribute);
                }
            }
        }
        if($file) $this->owner->$attribute = $file;
    }

    protected function processFile(UploadedFile $file, string $attribute): UploadedFile
    {
        if(!filter_var($file->tempName, FILTER_VALIDATE_URL) && $this->convertImageToWebp && in_array($file->type, $this->memeTypeForConvertToWebp)){
            $source = $file->tempName;
            $destination = $source . '.webp';
            WebPConvert::convert($source, $destination);
            $file->name = $file->name . '.webp';
            $file->tempName = $destination;
            $file->type = 'image/webp';
            $file->size = filesize($file->tempName);
        }
        $file->name = $this->getFileName($attribute, $file);
        return $file;
    }

    protected function getUploadedFile(string $attribute): array|UploadedFile
    {
        $value = $this->owner->$attribute;

        if ($value instanceof UploadedFile) {
            return $value;
        }

        /** for upload file from url */
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return UploadFromUrl::getUploadedFile($value) ?: [];
        } elseif(is_array($value)){
            $file = [];
            foreach ($value as $item) {
                if(is_string($item) && filter_var($item, FILTER_VALIDATE_URL)){
                    $fileFromUrl = UploadFromUrl::getUploadedFile($item);
                    if($fileFromUrl) $file[] = $fileFromUrl;
                }
            }
            return $file;
        }

        $file = UploadedFile::getInstance($this->owner, $attribute);
        if (!$file) {
            return UploadedFile::getInstances($this->owner, $attribute);
        }

        return $file ?: [];
    }

    protected function shouldProcess(): bool
    {
        return !$this->scenarios || in_array($this->owner->scenario, $this->scenarios);
    }

    protected function processSingleFile(UploadedFile $file, string $attribute): void
    {
        $primaryKey = $this->primaryKey;

        if ($this->deletePreviousFilesOnAttribute) {
            $this->processDeletedStorageFiles($attribute, $primaryKey);
        }

        $this->processStorageFile($file, $attribute, $primaryKey);
    }

    protected function processFileArray(array $files, string $attribute): void
    {
        $this->owner->$attribute = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $primaryKey = $this->primaryKey;
                $this->processStorageFile($file, $attribute, $primaryKey, true);
            }
        }
    }

    protected function processDeletedStorageFiles(string $attribute, string $primaryKey): void
    {
        $oldStorageFiles = StorageFiles::find()
            ->byModel($this->modelType, $this->owner->$primaryKey, $attribute)
            ->all();

        $this->_deletedStorageFiles = array_merge($this->_deletedStorageFiles, $oldStorageFiles);
    }

    protected function processStorageFile(UploadedFile $file, string $attribute, string $primaryKey, bool $arrayFile = false): void
    {
        $storageFile = StorageFiles::saveNewFile(
            file: $file, 
            modelType: $this->modelType, 
            modelId: $this->owner->$primaryKey, 
            attribute: $attribute, 
            access: $this->accessFile, 
            sharedWith: $this->getSharedWithClients());

        if ($storageFile) {
            $this->_storageFiles[] = $storageFile;
            if(!$arrayFile){
                $this->owner->$attribute = $storageFile->id;
            } else {
                $this->owner->$attribute = null;
            }
        }
    }

    /**
     * @throws ErrorException
     */
    protected function processStorageFiles(): void
    {
        foreach ($this->_storageFiles as $storageFile) {
            $this->uploadStorageFile($storageFile);
        }
    }

    /**
     * @throws ErrorException
     */
    protected function uploadStorageFile(StorageFiles $storageFile): void
    {
        if (!$storageFile->uploadFile()) {
            throw new ErrorException('خطا در آپلود فایل');
        }
    }

    /**
     * @throws ErrorException
     */
    protected function deletePreviousStorageFiles(): void
    {
        foreach ($this->_deletedStorageFiles as $deletedStorageFile) {
            if (!$deletedStorageFile->softDelete()) {
                throw new ErrorException('خطا در حذف فایل های قبلی');
            }
        }
    }

    /**
     * @param string|null $attribute
     * @return string|null
     */
    public function getFileUrl(string $attribute = null): ?string
    {
        $storageFile = $this->getStorageFile($attribute)->one();
        return $storageFile?->getFileUrl();
    }

    /**
     * @param string|null $attribute
     * @return string|null
     */
    public function getFileStorageName(string $attribute = null): ?string
    {
        $storageFile = $this->getStorageFile($attribute)->one();
        return $storageFile?->file_name;
    }

    protected function getFileName(string $attribute, UploadedFile $file): string
    {
        if($this->fileName instanceof Closure){
            return call_user_func($this->fileName, $this->owner, $attribute, $file);
        }
        return $this->generateFileName($file);
    }

    protected function generateFileName(UploadedFile $file): string
    {
        return md5(uniqid(uniqid())) . '.' . $file->extension;
    }

    protected function getSharedWithClients(): array
    {
        $sharedWith = $this->sharedWith;
        return $sharedWith instanceof Closure ? $sharedWith($this->owner) : $sharedWith;
    }

    public function getStorageFile(string $attribute = null): StorageFilesQuery|ActiveQuery
    {
        $modelClass = $this->getSharedWithClients() ? StorageFileShared::class : StorageFiles::class;
        $primaryKey = $this->primaryKey;
        return $this->owner->hasOne($modelClass, ['model_id' => $primaryKey])
            ->andOnCondition(['model_type' => $this->modelType, 'attribute' => $attribute ?: $this->attributes[array_key_first($this->attributes)]]);
    }

    /**
     * @param string|null $attribute
     * @return ActiveQuery|StorageFilesQuery
     */
    public function getStorageFiles(string $attribute = null): ActiveQuery|StorageFilesQuery
    {
        $modelClass = $this->getSharedWithClients() ? StorageFileShared::class : StorageFiles::class;
        $primaryKey = $this->primaryKey;
        return $this->owner->hasMany($modelClass, ['model_id' => $primaryKey])
            ->andOnCondition(['model_type' => $this->modelType, 'attribute' => $attribute ?: $this->attributes[array_key_first($this->attributes)]]);
    }
}