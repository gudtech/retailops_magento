<?php
/**
{license_text}
 */

/**
 * Flat RetailOps_Api order status history resource
 *
 * @category    RetailOps
 * @package     RetailOps_Api
 */
class RetailOps_Api_Model_Resource_Order_Status_History extends Mage_Sales_Model_Resource_Order_Abstract
{
    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix    = 'retailops_api_order_status_history_resource';

    /**
     * Model initialization
     *
     */
    protected function _construct()
    {
        $this->_init('retailops_api/order_status_history', 'entity_id');
    }
}
