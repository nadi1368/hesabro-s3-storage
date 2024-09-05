<?php

namespace mamadali\S3Storage;

/**
 * storage module definition class
 */
class StorageModule extends \yii\base\Module
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'mamadali\S3Storage\controllers';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        // custom initialization code goes here
    }
}
