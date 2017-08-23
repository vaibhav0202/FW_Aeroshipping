<?php
chdir(dirname(__FILE__));  // Change working directory to script location
require_once '../../../../../Mage.php';  // Include Mage
Mage::app('admin');

$aeroShipping = new FW_Aeroshipping_Model_Observer();
$aeroShipping->getAeroShippingStatusExport();
