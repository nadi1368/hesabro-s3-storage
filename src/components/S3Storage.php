<?php

namespace mamadali\S3Storage\components;

use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Yii;
use yii\base\InvalidConfigException;

/**
 * @var string $defaultBucket
 */
class S3Storage extends \yii\base\Component
{
    const ACCESS_PRIVATE = 1;
    const ACCESS_PUBLIC_READ = 2;

    public $region = 'iran';
    public $error_msg;

    public string $secret;
    public string $key;
    public string $endpoint;
    public string $default_bucket_name;
    public ?string $bucket_domain = null;

    private S3Client $client;

    public array $modelMap = [];

    public string $storageFilesModelClass = 'mamadali\S3Storage\models\StorageFiles';

    public function init()
    {
        if($this->modelMap){
            if(isset($this->modelMap['StorageFiles'])){
                $this->storageFilesModelClass = $this->modelMap['StorageFiles'];
            }
        }

        if(empty($this->key)){
            throw new InvalidConfigException('The "key" property must be set.');
        }

        if(empty($this->secret)){
            throw new InvalidConfigException('The "secret" property must be set.');
        }

        if(empty($this->endpoint)){
            throw new InvalidConfigException('The "endpoint" property must be set.');
        }

        if(empty($this->default_bucket_name)){
            throw new InvalidConfigException('The "default_bucket_name" property must be set.');
        }

        $this->client = new S3Client([
            'region' => $this->region,
            'version' => '2006-03-01',
            'endpoint' => 'https://' . $this->endpoint,
            'credentials' => [
                'key' => $this->key,
                'secret' => $this->secret
            ],
            'use_path_style_endpoint' => false,
        ]);
    }

    public function getDefaultBucket(): ?string
    {
        return $this->default_bucket_name;
    }

    /**
     * @return bool
     * @var string $ACL private | public-read | public-read-write | authenticated-read
     *
     * @var string $bucket_name
     */
    public function createBucket(string $bucket_name, string $ACL = 'public-read'): bool
    {
        try {
            $result = $this->client->createBucket([
                'ACL' => $ACL,
                'Bucket' => $bucket_name,
                'CreateBucketConfiguration' => ['LocationConstraint' => $this->region],
            ]);

            return true;
        } catch (AwsException $e) {
            $this->error_msg = 'Error: ' . $e->getAwsErrorMessage();
            Yii::error('Error: ' . $e->getAwsErrorMessage(), __METHOD__ . ':' . __LINE__);
            return false;
        }
    }

    /**
     * @return bool the weather if access to the bucket or the bucket exist or not
     * @var string $bucket_name
     *
     */
    public function hasAccessToBucket(string $bucket_name): bool
    {
        try {
            $result = $this->client->headBucket([
                'Bucket' => $bucket_name,
            ]);

            return true;
        } catch (AwsException $e) {
            $this->error_msg = 'Error: ' . $e->getAwsErrorMessage();
            Yii::error('Error: ' . $e->getAwsErrorMessage(), __METHOD__ . ':' . __LINE__);
            return false;
        }
    }

    /**
     * @return array|bool
     */
    public function getListBuckets(): array|bool
    {
        try {
            $list_response = $this->client->listBuckets();
            return $list_response['Buckets'];
        } catch (AwsException $e) {
            $this->error_msg = 'Error: ' . $e->getAwsErrorMessage();
            Yii::error('Error: ' . $e->getAwsErrorMessage(), __METHOD__ . ':' . __LINE__);
            return false;
        }
    }

    /**
     * @return bool
     * @var string $bucket_name
     *
     */
    public function deleteBucket(string $bucket_name): bool
    {
        try {
            $result = $this->client->deleteBucket([
                'Bucket' => $bucket_name,
            ]);

            return true;
        } catch (AwsException $e) {
            $this->error_msg = 'Error: ' . $e->getAwsErrorMessage();
            Yii::error('Error: ' . $e->getAwsErrorMessage(), __METHOD__ . ':' . __LINE__);
            return false;
        }
    }

    /**
     * @param string $file_name
     * @param string $file_path // file path or url
     * @param string $bucket_name
     * @param string $acl // private|public-read|public-read-write|authenticated-read|aws-exec-read|bucket-owner-read|bucket-owner-full-control
     * @param string|null $contentType
     * @return bool
     */
    public function uploadObject(string $file_name, string $file_path, string $bucket_name = '', string $acl = 'private', string $contentType = null): bool
    {
        $pathIsUrl = filter_var($file_path, FILTER_VALIDATE_URL);
        try {
            $this->client->putObject([
                'ACL' => $acl,
                'Bucket' => $bucket_name ?: $this->default_bucket_name,
                'Key' => $file_name,
                'Body' => $pathIsUrl ? file_get_contents($file_path) : null,
                'SourceFile' => !$pathIsUrl ? $file_path : null,
                'ContentType' => $contentType
            ]);
            return true;
        } catch (S3Exception $e) {
            $this->error_msg = 'Error: ' . $e->getAwsErrorMessage();
            Yii::error('Error: ' . $e->getAwsErrorMessage(), __METHOD__ . ':' . __LINE__);
            return false;
        }
    }

    public function getPublicObjectUrl(string $file_name, string $bucket_name = null): string
    {
        return $this->client->getObjectUrl($bucket_name ?: $this->default_bucket_name, $file_name);
    }

    public function getPrivateObjectUrl(string $file_name, string $bucket_name = '', int $expireMinutes = 30): string|bool
    {
        try {
            $object = $this->client->getCommand('GetObject', [
                'Bucket' => $bucket_name ?: $this->default_bucket_name,
                'Key' => $file_name
            ]);
            $request = $this->client->createPresignedRequest($object, $expireMinutes ? "+$expireMinutes minutes" : '');
            return (string)$request->getUri();
        } catch (S3Exception $e) {
            $this->error_msg = 'Error: ' . $e->getAwsErrorMessage();
            Yii::error('Error: ' . $e->getAwsErrorMessage(), __METHOD__ . ':' . __LINE__);
            return false;
        }
    }

    /**
     * @return mixed
     * @var string $bucket_name
     *
     * @var string $file_name
     */
    public function loadObject(string $file_name, string $bucket_name = ''): mixed
    {
        try {
            $object = $this->client->getObject([
                'Bucket' => $bucket_name ?: $this->default_bucket_name,
                'Key' => $file_name
            ]);

            return $object['Body'];
        } catch (S3Exception $e) {
            $this->error_msg = 'Error: ' . $e->getAwsErrorMessage();
            Yii::error('Error: ' . $e->getAwsErrorMessage(), __METHOD__ . ':' . __LINE__);
            return false;
        }
    }

    /**
     * @return bool
     * @var string $file_name
     *
     * @var string $bucket_name
     */
    public function deleteObject(string $file_name, string $bucket_name = null): bool
    {
        try {
            $object = $this->client->deleteObject([
                'Bucket' => $bucket_name ?: $this->default_bucket_name,
                'Key' => $file_name,
            ]);
            return true;
        } catch (S3Exception $e) {
            $this->error_msg = 'Error: ' . $e->getAwsErrorMessage();
            Yii::error('Error: ' . $e->getAwsErrorMessage(), __METHOD__ . ':' . __LINE__);
            return false;
        }
    }

    public static function getPublicUrl(string $fullFilePath): string
    {
        $url = Yii::$app->s3storage->getPublicObjectUrl($fullFilePath);
        if(Yii::$app->s3storage->bucket_domain){
            $url = str_replace(Yii::$app->s3storage->getDefaultBucket() . '.' . Yii::$app->s3storage->endpoint, Yii::$app->s3storage->bucket_domain, $url);
        }
        return $url;
    }

    public function getTotalUsage()
    {
        $modelClass = $this->storageFilesModelClass;
        return $modelClass::find()->sum('size');
    }

    public function getUsageByModelClass($model_class)
    {
        $modelClass = $this->storageFilesModelClass;
        return $modelClass::find()->andWhere(['model_class' => $model_class])->sum('size');
    }

    public function getUsageSeperatedByModelClass()
    {
        $modelClass = $this->storageFilesModelClass;
        return $modelClass::find()->select(['model_class', 'SUM(size) as size'])->groupBy('model_class')->asArray()->all();
    }

    public static function formatUsageSpace($bytes): string
    {
        $sizes = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');

        if ($bytes == 0) {
            return '0 B';
        }

        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 3) . ' ' . $sizes[$i];
    }

    public static function itemAlias($type, $code = null)
    {
        $items = match ($type) {
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
}
