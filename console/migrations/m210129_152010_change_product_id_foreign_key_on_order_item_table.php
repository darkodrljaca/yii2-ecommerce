<?php

use yii\db\Migration;

/**
 * Class m210129_152010_change_product_id_foreign_key_on_order_item_table
 */
class m210129_152010_change_product_id_foreign_key_on_order_item_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

      $this->dropForeignKey(
          '{{%fk-order_items-product_id}}',
          '{{%order_items}}'
      );

    }


    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210129_152010_change_product_id_foreign_key_on_order_item_table cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210129_152010_change_product_id_foreign_key_on_order_item_table cannot be reverted.\n";

        return false;
    }
    */
}
