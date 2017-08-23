<?php
class FW_Aeroshipping_Model_Mysql4_History_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {
        //parent::__construct();
        $this->_init('aeroshipping/history');
    }
}
