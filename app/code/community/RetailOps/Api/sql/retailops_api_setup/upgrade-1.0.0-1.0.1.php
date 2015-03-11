<?php
/**
{license_text}
 */

$installer = $this;

$installer->startSetup();

$installer->getConnection()
    ->addColumn($installer->getTable('catalog/product_attribute_media_gallery'),
        'retailops_mediakey',
        array(
            'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
            'length'    => 255,
            'comment'   => 'RetailOps Mediakey'
            ));
$installer->getConnection()
    ->addIndex($installer->getTable('catalog/product_attribute_media_gallery'),
        $installer->getIdxName('catalog/product_attribute_media_gallery', array('retailops_mediakey')),
        array('retailops_mediakey')
    );

$installer->endSetup();
