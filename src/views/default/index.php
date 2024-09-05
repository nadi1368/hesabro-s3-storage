<?php

/* @var $this yii\web\View */

use mamadali\S3Storage\models\StorageFiles;

$totalStorageSize = 0;
?>
<div class="row">
    <?php foreach (StorageFiles::itemAlias('ModelTypeTitle') as $modelType => $title):
        $storageSize = StorageFiles::find()->byModelType($modelType)->sum('size');
        $totalStorageSize += $storageSize;
        ?>
    <div class="col-lg-2">
        <div class="card bg-white">
            <div class="card-body">
                <div class="d-flex no-block align-items-center">
                    <div>
                        <h6 class="yekan"><?= $title . ': ' . '<b class="font-18"><br>' . StorageFiles::formatStorageSpace($storageSize) . '</b>' ?></h6>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="col-lg-12">
        <div class="row">
            <div class="col-lg-6">
                <div class="card bg-white">
                    <div class="card-body">
                        <div class="d-flex no-block align-items-center">
                            <div>
                                <h6 class="yekan"><?= 'کل: ' . '<b class="font-18">' . StorageFiles::formatStorageSpace($totalStorageSize) . '</b>' ?></h6>
                            </div>
                            <div class="ml-auto">
                                <span class="text-secondary display-6"><i class="fal fa-database"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

