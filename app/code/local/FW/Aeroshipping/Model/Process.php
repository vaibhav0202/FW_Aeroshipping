<?php

class FW_Aeroshipping_Model_Process {

    private $_helper;

    public function initiateHelper() {
        $this->_helper = Mage::helper('fw_aeroshipping');
    }

    public function getHelper() {
        $this->initiateHelper();
        return $this->_helper;
    }

    public function process($queue) {

        $queueItemData = $queue->getQueueData();
        $file = $queueItemData['file'];
        $this->processShippingStatusExport($file);
    }

    public function processShippingStatusExport($file) {
        $date = date('Y-m-d');
        if(!file_exists($file)) {
            Mage::log("File {$file} does not exist while trying to process aero shipments on {$date}.", null, 'aeroshipping_missing_file.log');
            return;
        }

        $xml = simplexml_load_file($file);

        if($xml === false) {
            Mage::log("{$file} has invalid XML when processing on {$date}", null, 'aeroshipping_xml_error.log');
            return;
        }

        Mage::log("Processing {$file} on " . date("Y-m-d"), null, 'aeroshipping_processing.log');

        $aeroshipOrderCount = count($xml->Order);
        $createdShipmentCount = 0;
        $skippedOrderCount = 0;

        foreach($xml->Order as $orderElement) {
            $aeroId = $orderElement->aeroid;

            $orderNumber = $this->getHelper()->getParsedOrderNumber($orderElement->ponumber);
            $orderModel = Mage::getModel('sales/order')->load($orderNumber, 'increment_id');

            //Try loading shipment history based on aero ID to determine if it's been processed already.
            $shipmentProcessed = Mage::getModel('aeroshipping/history')->load($aeroId, 'external_shipment_id')->getHistoryId();

            $orderSkipped = true;

            $logMessage = "Skipped Aeroship Order {$aeroId} with Order Number {$orderNumber}. REASON: ";

            //Only create order shipments if the order exists.
            if($orderModel->getId() && !$shipmentProcessed) {
                $shipmentId = $this->createOrderShipments($orderElement, $orderModel);
                if($shipmentId) {
                    //Shipment was created, so set orderSkipped to false and increment the createdShipmentCount
                    $orderSkipped = false;
                    ++$createdShipmentCount;
                    Mage::log("{$orderNumber} created shipment", null, 'aeroshipping_success.log');
                } else {
                    $logMessage .= " ShipmentID returned null";
                }

            } else {

                if(!$orderModel->getId()) {
                    $logMessage .= "{$orderNumber} NOT FOUND.";
                }
                if($shipmentProcessed) {
                    $logMessage .= "${orderNumber} ALREADY PROCESSED.";
                }
            }
            if($orderSkipped) {
                ++$skippedOrderCount;
                Mage::Log($logMessage, null, 'aeroshipping_skipped_shipments.log');
            }

        }

        //Log statistics from export file
        $skippedPercentage = ($skippedOrderCount / $aeroshipOrderCount) * 100;
        $loggingStats = "File {$file} processed on {$date} with the following info:\n";
        $loggingStats .= "\tShipments Created: {$createdShipmentCount}\n";
        $loggingStats .= "\tOrders Skipped: {$skippedOrderCount}\n";
        $loggingStats .= "\tTotal Orders: {$aeroshipOrderCount}\n";
        $loggingStats .= "\tSkipped Percentage: {$skippedPercentage}%";

        Mage::log($loggingStats, null, $this->_logDir . '/stats.log');

        //If Order counts don't match up, something went wrong. Log filename for further analysis
        if($aeroshipOrderCount != ($skippedOrderCount + $createdShipmentCount)) {
            Mage::log("Order totals mismatch for {$file} on {$date}", null, 'aeroshipping_mismatch.log');
        }
    }

    public function createOrderShipments($orderElement, $orderModel) {
        //Don't continue if order isn't found
        if($orderModel == null) { return; }

        $orderItemIds = array();
        $shipmentQtys = array();
        $trackingNumbers = array();

        $orderItems = $orderModel->getAllItems();
        $aeroId = $orderElement->aeroid->__toString();

        //Build orderItemIds to productSku mapping to pass to shipment
        foreach($orderItems as $item) {
            $itemId = $item->getId();
            $sku = $item->sku;
            if ($item->getParentItemId()) {
                $orderItemIds[$sku] = $item->getParentItemId();
            } else {
                $orderItemIds[$sku] = $itemId;
            }
        }

        //Loop through items and determine if shipment is needed
        foreach($orderElement->ProductsToBeShipped->Product as $aeroProduct) {

            $productSku = $aeroProduct->productreference->__toString();
            $qtyShipped = $aeroProduct->quantityshipped;

            //Only build shipment if the sku exists in the order
            if(isset($orderItemIds[$productSku])) {
                $itemId = $orderItemIds[$productSku];
                $shipmentQtys[$itemId] = $qtyShipped;

                //If tracking nomber exists for product add it to trackingNumbers array

                $trackNo = $aeroProduct->ShippingInformation->Carton->shippingtrackno->__toString();

                if ($trackNo) {
                    //Put tracking number as key to prevent duplicates
                    $trackingNumbers[$trackNo] = $aeroProduct->ShippingInformation->Carton->shippingmethod->__toString();
                }
            }
            //We found a sku in the xml that doesn't exist in the order, so log it and skip the order
            else {
                //Reset shipmentQtys to prevent the code below to execute, essentially skipping the order
                $shipmentQtys = array();
                $logMessage = "Aero order {$aeroId} with magento order number {$orderModel->getIncrementId()} has a sku {$productSku} that doesn't exist in magento order";
                Mage::log($logMessage, null, 'aeroshipping_order_conflict.log');
            }
        }

        //If we have items in shipmentQtys array, create shipment
        if(count($shipmentQtys) > 0) {
            $shipment = Mage::getModel('sales/service_order', $orderModel)->prepareShipment($shipmentQtys);

            if ($shipment) {

                $shipmentCarrierCode = strtolower($orderElement->shippingcarrier);

                //Aero uses the code "fedx" for FedEx. Check to see if that's the carrier and switch to fedex
                $shipmentCarrierCode = ($shipmentCarrierCode == "fedx") ? "fedex" : $shipmentCarrierCode;

                //Loop through the tracking number array and add tracking to the shipment
                foreach($trackingNumbers as $trackingNumber=>$methodTitle) {

                    $track = Mage::getModel('sales/order_shipment_track');
                    $track->setTitle($methodTitle);
                    $track->setCarrierCode($shipmentCarrierCode);
                    $track->setNumber($trackingNumber);

                    $shipment->addTrack($track);
                }

                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);
                $shipment->addComment("Added shipment on " . date("m/d/Y"), false);

                try {
                    $transactionSave = Mage::getModel('core/resource_transaction')
                        ->addObject($shipment)
                        ->addObject($shipment->getOrder())
                        ->save();

                    $shipmentId = $shipment->getIncrementId();

                    //Save shipment history record
                    $shipmentHistory = Mage::getModel('aeroshipping/history');

                    $aeroShippingCharges = $orderElement->shipchg;
                    $magentoShippingCharges = $orderModel->getShippingAmount();

                    $shipmentHistory->setExternalShipmentId($aeroId);
                    $shipmentHistory->setMagentoShipmentId($shipmentId);
                    $shipmentHistory->setMagentoOrderNumber($orderModel->getIncrementId());
                    $shipmentHistory->setExternalOrderNumber($orderElement->customerreferenceorderid);
                    $shipmentHistory->setExternalShippingCharges($aeroShippingCharges);
                    $shipmentHistory->setMagentoShippingCharges($magentoShippingCharges);

                    $shipmentHistory->save();

                    return $shipmentId;

                } catch (Mage_Core_Exception $ex) {
                    Mage::log("Exception while creating shipment for order " . $orderModel->getIncrementId() . ": " . $ex->getMessage(), null, $this->_logDir . '/shipment_exception.log');
                }
            }
        }
    }
}
