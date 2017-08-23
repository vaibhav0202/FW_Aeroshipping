<?php

class FW_Aeroshipping_Helper_Data extends Mage_Core_Helper_Abstract  {

    const AERO_CONFIG = 'aeroshipping/settings';

    public function isActive($store = null)
    {
        return Mage::getStoreConfig(self::AERO_CONFIG . '/active', $store);
    }

    public function getOrderNumberElement($store = null)
    {
        return Mage::getStoreConfig(self::AERO_CONFIG . '/ordernumber_element', $store);
    }

    public function getFtpHost($store = null)
    {
        return Mage::getStoreConfig(self::AERO_CONFIG . '/ftp_host', $store);
    }

    public function getFtpUser($store = null)
    {
        return Mage::getStoreConfig(self::AERO_CONFIG . '/ftp_user', $store);
    }

    public function getFtpPassword($store = null)
    {
        return Mage::getStoreConfig(self::AERO_CONFIG . '/ftp_password', $store);
    }

    public function getFtpFolder($store = null)
    {
        return Mage::getStoreConfig(self::AERO_CONFIG . '/ftp_folder', $store);
    }

    public function getEmailNotice($store = null)
    {
        return Mage::getStoreConfig(self::AERO_CONFIG . '/emailnotice', $store);
    }

    public function getParsedOrderNumber($orderNumber) {
        return $orderNumber;
    }
}