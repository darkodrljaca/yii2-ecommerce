<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%order_items}}`.
 */
class m210104_102304_create_order_items_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%order_items}}', [
            'id' => $this->primaryKey(),
            'product_name' => $this->string(255)->notNull(),
            'product_id' => $this->integer(11)->notNull(),
            'unit_price' => $this->decimal(10,2)->notNull(),
            'order_id' => $this->integer(11)->notNull(),
            'quantity' => $this->integer(2)->notNull(),
        ]);
        
        $this->createIndex(
                '{{%idx-order_items-product_id}}', 
                '{{%order_items}}', 
                'product_id'
                );
        
        $this->addForeignKey('{{%fk-order_items-product_id}}', 
                '{{%order_items}}', 
                'product_id', 
                '{{%products}}', 
                'id',
                'CASCADE');
        
        $this->createIndex(
                '{{%idx-order_items-order_id}}', 
                '{{%order_items}}', 
                'order_id'
                );
        
        $this->addForeignKey('{{%fk-order_items-order_id}}', 
                '{{%order_items}}', 
                'order_id', 
                '{{%orders}}', 
                'id',
                'CASCADE');
        
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        
        $this->dropForeignKey(
            '{{%fk-order_items-product_id}}',                
            '{{%order_items}}'
        );

        
        $this->dropIndex(
            '{{%idx-order_items-product_id}}',
            '{{%order_items}}'
        );
        
        $this->dropForeignKey(
                '{{%fk-order_items-order_id}}', 
                '{{%order_items}}'
                );
        
        $this->dropIndex(
                '{{%idx-order_items-order_id}}', 
                '{{%order_items}}'
                );
        
        
        $this->dropTable('{{%order_items}}');
                
    }
}
