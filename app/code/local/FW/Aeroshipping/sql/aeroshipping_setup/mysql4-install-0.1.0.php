<?php

$installer = $this;
$installer->startSetup();

//Creates the shipment history table to store all the shipment history.
$installer->run("
	DROP TABLE IF EXISTS `{$installer->getTable('aeroshipping/history')}`;
    CREATE TABLE `{$installer->getTable('aeroshipping/history')}` (
      `history_id` int(11) NOT NULL auto_increment,
      `magento_order_number` varchar(100) NOT NULL,
      `external_order_number` varchar(100) NOT NULL,
      `external_shipment_id` varchar(100) NOT NULL,
      `magento_shipment_id` varchar(100) NOT NULL,
      `magento_shipping_charges` decimal(10,2) not null,
      `external_shipping_charges` decimal(10,2) not null default 0.00,
      `shipment_create_date` TIMESTAMP NOT NULL DEFAULT NOW(),
      PRIMARY KEY  (`history_id`),
      UNIQUE KEY `uniq_external_shipment_id` (`external_shipment_id`),
      INDEX `idx_external_shipment_id` (`external_shipment_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup();