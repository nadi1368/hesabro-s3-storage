Yii2 S3 Storage
=====================
Yii2 S3 Storage is a Yii2 component that provides an easy way to store files on Amazon S3.
[![Latest Stable Version](https://img.shields.io/packagist/v/mamadali/yii2-s3-storage.svg)](https://packagist.org/packages/mamadali/yii2-s3-storage)
[![Total Downloads](https://img.shields.io/packagist/dt/mamadali/yii2-s3-storage.svg)](https://packagist.org/packages/mamadali/yii2-s3-storage)


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require --prefer-dist mamadali/yii2-s3-storage "*"
```

or add

```
"mamadali/yii2-s3-storage": "*"
```

to the require section of your `composer.json` file.
and run `composer update`

then run migrations

```
php yii migrate/up --migrationPath=@vendor/mamadali/yii2-s3-storage/src/migrations
```

## Basic usage

add s3 component to `components` section of config file
```php
    'components' => [
        ...
        's3storage' => [
            'class' => 'mamadali\S3Storage\components\S3Storage',
            'key' => // your access key
            'secret' => // your secret key
            'endpoint' => // your endpoint
            'default_bucket_name' => // your bucket name
            'bucket_domain' => // Optional: your bucket domain
        ],
        ...
    ];
```

add behavior to your model
```php
    public function behaviors()
    {
        return [
            ...
            [
                'class' => StorageUploadBehavior::class,
                'attributes' => ['file'],
                'scenarios' => [self::SCENARIO_UPLOAD],
                'path' => 'path/model_class/{id}'
            ],
            ...
        ];
    }
```
then just add file input to your form and save model in controller
```php
    <?= $form->field($model, 'file')->fileInput() ?>
```
```php
    $model->save();
```