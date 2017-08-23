<?php
chdir(dirname(__FILE__));  // Change working directory to script location
require_once '../../../../../Mage.php';  // Include Mage
Mage::app('admin');

if(!isset($argv[1])) {
    echo "Please provide a file for processing\n";
    exit;
}

$fileName = $argv[1];

$aeroShipping = new FW_Aeroshipping_Model_Process();
$aeroShipping->processShippingStatusExport($fileName);