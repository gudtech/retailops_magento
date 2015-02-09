<?php

/**
 * Flat retailops order status history collection
 *
 * @category    RetailOps
 * @package     RetailOps_Api
 */
class RetailOps_Api_Model_Resource_Order_Status_History_Collection
    extends Mage_Sales_Model_Resource_Order_Collection_Abstract
{
    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix    = 'retailops_order_status_history_collection';

    /**
     * Event object
     *
     * @var string
     */
    protected $_eventObject    = 'order_retailops_status_history_collection';

    /**
     * Model initialization
     *
     */
    protected function _construct()
    {
        $this->_init('retailops_api/order_status_history');
    }
}
