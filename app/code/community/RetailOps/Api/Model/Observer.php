<?php
/**
{license_text}
 */

class RetailOps_Api_Model_Observer
{
    /**
     * @param $observer
     */
    public function updateRetailopsStatus($observer)
    {
        $order = $observer->getEvent()->getPayment()->getOrder();
        $order->setRetailopsStatus(RetailOps_Api_Helper_Data::RETAILOPS_ORDER_READY);
    }
}
