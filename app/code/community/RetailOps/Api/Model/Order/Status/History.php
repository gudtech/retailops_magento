<?php
/**
The MIT License (MIT)

Copyright (c) 2015 Gud Technologies Incorporated (RetailOps by GüdTech)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
 */

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
        parent::_beforeSave();
        if (!$this->getParentId() && $this->getOrder()) {
            $this->setParentId($this->getOrder()->getId());
            $this->setStatus($this->getOrder()->getRetailopsStatus());
        }

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     *
     * @return RetailOps_Api_Model_Resource_Order_Status_History_Collection
     */
    public function getRetailOpsStatusHistory(Mage_Sales_Model_Order $order)
    {
        $statusHistory = Mage::getResourceModel('retailops_api/order_status_history_collection')
            ->setOrderFilter($order)
            ->setOrder('created_at', 'desc')
            ->setOrder('entity_id', 'desc');

        if ($order->getId()) {
            foreach ($statusHistory as $status) {
                $status->setOrder($order);
            }
        }

        return $statusHistory;
    }
}
