<?php
/**
{license_text}
 */

$installer = $this;

$installer->startSetup();

$installer->getConnection()
    ->addColumn($installer->getTable('sales/order'),
        'retailops_status',
        array(
            'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
            'length'    => 255,
            'comment'   => 'RetailOps Order Status'
            ));
$installer->getConnection()
    ->addIndex($installer->getTable('sales/order'),
        $installer->getIdxName('sales/order', array('retailops_status')),
        array('retailops_status')
    );

/**
 * Create table 'retail_ops/order_status_history'
 */
$table = $installer->getConnection()
    ->newTable($installer->getTable('retailops_api/order_status_history'))
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'Entity Id')
    ->addColumn('parent_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned'  => true,
        'nullable'  => false,
    ), 'Parent Id')
    ->addColumn('comment', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', array(
    ), 'Comment')
    ->addColumn('status', Varien_Db_Ddl_Table::TYPE_TEXT, 32, array(
    ), 'Status')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
    ), 'Created At')
    ->addIndex($installer->getIdxName('retailops_api/order_status_history', array('parent_id')),
        array('parent_id'))
    ->addIndex($installer->getIdxName('retailops_api/order_status_history', array('created_at')),
        array('created_at'))
    ->addIndex($installer->getIdxName('retailops_api/order_status_history', array('status')),
        array('status'))
    ->addForeignKey($installer->getFkName('retailops_api/order_status_history', 'parent_id', 'sales/order', 'entity_id'),
        'parent_id', $installer->getTable('sales/order'), 'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE)
    ->setComment('Sales Flat Order Status History');

$installer->getConnection()->createTable($table);

$installer->endSetup();
