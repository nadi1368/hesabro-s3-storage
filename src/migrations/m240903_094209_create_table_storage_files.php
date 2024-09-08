<?php

use yii\db\Expression;
use yii\db\Migration;

/**
 * Class m231129_094209_create_table_s3_files
 */
class m240903_094209_create_table_storage_files extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable(
            '{{%storage_files}}',
            [
                'id' => $this->primaryKey(),
                'access' => $this->integer(),
                'model_class' => $this->string(),
                'model_id' => $this->integer(),
                'attribute' => $this->string(),
                'file_path' => $this->string(),
                'file_name' => $this->string(),
                'size' => $this->integer(),
                'meme_type' => $this->string(),
                'status' => $this->boolean()->notNull(),
                'created_at' => $this->integer()->unsigned()->notNull(),
                'updated_at' => $this->integer()->unsigned()->notNull(),
                'created_by' => $this->integer()->null(),
                'updated_by' => $this->integer()->null(),
                'shared_with' => $this->json()->defaultValue(new Expression('(JSON_OBJECT())')),
            ],
        );

        $this->createIndex('access_index', '{{%storage_files}}', ['access']);
        $this->createIndex('model_index', '{{%storage_files}}', ['model_class', 'model_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%storage_files}}');
    }
}
