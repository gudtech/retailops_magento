<?php
/**
{license_text}
 */

class RetailOps_Api_Helper_Data extends Mage_Core_Helper_Abstract
{
    const RETAILOPS_ORDER_STATUS_READY = 'retailops_ready';

    /**
     * Gets Order Items For Orders with retailops_ready status
     *
     * @return array
     */
    public function getRetailopsReadyOrderItems()
    {
        $items = array();
        $orderCollection = Mage::getModel('sales/order')->getCollection()
            ->addFieldToFilter('retailops_status', self::RETAILOPS_ORDER_STATUS_READY);
        foreach ($orderCollection as $order) {
            foreach ($order->getAllItems() as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }
}
