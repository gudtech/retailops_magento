<?php

/**
 * Order status history comments
 *
 * @method RetailOps_Api_Model_Resource_Order_Status_History _getResource()
 * @method RetailOps_Api_Model_Resource_Order_Status_History getResource()
 * @method int getParentId()
 * @method RetailOps_Api_Model_Order_Status_History setParentId(int $value)
 * @method string getComment()
 * @method RetailOps_Api_Model_Order_Status_History setComment(string $value)
 * @method string getStatus()
 * @method RetailOps_Api_Model_Order_Status_History setStatus(string $value)
 * @method string getCreatedAt()
 * @method RetailOps_Api_Model_Order_Status_History setCreatedAt(string $value)
 *
 * @category    RetailOps
 * @package     RetailOps_Api
 */
class RetailOps_Api_Model_Order_Status_History extends Mage_Core_Model_Abstract
{

    /**
     * Order instance
     *
     * @var Mage_Sales_Model_Order
     */
    protected $_order;

    protected $_eventPrefix = 'retailops_order_status_history';
    protected $_eventObject = 'retailops_status_history';

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('retailops_api/order_status_history');
    }

    /**
     * Set order object and grab some metadata from it
     *
     * @param   Mage_Sales_Model_Order $order
     * @return  RetailOps_Api_Model_Order_Status_History
     */
    public function setOrder(Mage_Sales_Model_Order $order)
    {
        $this->_order = $order;
        return $this;
    }

    /**
     * Retrieve order instance
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return $this->_order;
    }

    /**
     * Set order again if required
     *
     * @return RetailOps_Api_Model_Order_Status_History
     */
    protected function _beforeSave()
    {
        Mage::log('dfgd');
        parent::_beforeSave();
        if (!$this->getParentId() && $this->getOrder()) {
            $this->setParentId($this->getOrder()->getId());
            $this->setStatus($this->getOrder()->getRetailopsStatus());
        }

        return $this;
    }
}
