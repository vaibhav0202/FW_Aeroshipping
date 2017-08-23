<?php
class FW_Aeroshipping_Model_Mysql4_History extends Mage_Core_Model_Mysql4_Abstract {

	function _construct() {
		$this->_init('aeroshipping/history', 'history_id');
	}
}

?>
