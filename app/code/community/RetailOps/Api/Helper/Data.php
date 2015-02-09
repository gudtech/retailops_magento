<?php
/**
{license_text}
 */

class RetailOps_Api_Helper_Data extends Mage_Core_Helper_Abstract
{
    const RETAILOPS_ORDER_PROCESSING = 'retailops_processing';
    const RETAILOPS_ORDER_COMPLETE = 'retailops_complete';
    const RETAILOPS_ORDER_CANCELED = 'retailops_canceled';

    public function getRetOpsStatuses()
    {
        return array(
            self::RETAILOPS_ORDER_PROCESSING => 'Processing',
            self::RETAILOPS_ORDER_COMPLETE => 'Complete',
            self::RETAILOPS_ORDER_CANCELED => 'Canceled'
        );
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
