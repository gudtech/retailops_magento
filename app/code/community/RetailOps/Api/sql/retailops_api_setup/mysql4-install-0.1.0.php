<?php

$installer = $this;

$installer->startSetup();

$installer->getConnection()->addColumn($installer->getTable('sales/order'), 'retailops_status', 'varchar(255)');

$installer->endSetup();
