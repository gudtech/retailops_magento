<?php
/**
{license_text}
 */

$installer = $this;

$installer->startSetup();

$table = $installer->getConnection()
    ->newTable($installer->getTable('retailops_api/media_import'))
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
    'identity'  => true,
    'unsigned'  => true,
    'nullable'  => false,
    'primary'   => true,
), 'Entity Id')
    ->addColumn('product_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
    'unsigned'  => true,
    'nullable'  => false,
), 'Product Id')
    ->addColumn('unset_other_media', Varien_Db_Ddl_Table::TYPE_TINYINT, null, array(
), 'Unset other media flag')
    ->addColumn('media_data', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', array(
), 'Full Media Data')
    ->addForeignKey($installer->getFkName('retailops_api/media_import', 'product_id', 'catalog/product', 'entity_id'),
    'product_id', $installer->getTable('catalog/product'), 'entity_id',
    Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE)
    ->setComment('RetailOps Media Update Table');

$installer->getConnection()->createTable($table);

$installer->endSetup();
