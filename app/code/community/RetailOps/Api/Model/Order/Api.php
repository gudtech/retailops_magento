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

class RetailOps_Api_Model_Order_Api extends Mage_Sales_Model_Order_Api
{
    /**
     * Initialize attributes map
     */
    public function __construct()
    {
        $this->_attributesMap = array(
            'order' => array(
                'order_id' => 'entity_id',
                'order_increment_id' => 'increment_id'
            ),
            'order_address' => array('address_id' => 'entity_id'),
            'order_payment' => array('payment_id' => 'entity_id')
        );
    }

    /**
     * Retrieve list of orders. Filtration could be applied
     *
     * @param null|object|array $filters
     * @return array
     */
    public function orderPull($filters = null)
    {
        $result = array();
        //TODO: add full name logic
        $billingAliasName = 'billing_o_a';
        $shippingAliasName = 'shipping_o_a';

        /** @var $orderCollection Mage_Sales_Model_Mysql4_Order_Collection */
        $orderCollection = Mage::getModel("sales/order")->getCollection();
        $billingFirstnameField = "$billingAliasName.firstname";
        $billingLastnameField = "$billingAliasName.lastname";
        $shippingFirstnameField = "$shippingAliasName.firstname";
        $shippingLastnameField = "$shippingAliasName.lastname";
        $orderCollection->addAttributeToSelect('*')
            ->addAddressFields()
            ->addExpressionFieldToSelect('billing_firstname', "{{billing_firstname}}",
                array('billing_firstname' => $billingFirstnameField))
            ->addExpressionFieldToSelect('billing_lastname', "{{billing_lastname}}",
                array('billing_lastname' => $billingLastnameField))
            ->addExpressionFieldToSelect('shipping_firstname', "{{shipping_firstname}}",
                array('shipping_firstname' => $shippingFirstnameField))
            ->addExpressionFieldToSelect('shipping_lastname', "{{shipping_lastname}}",
                array('shipping_lastname' => $shippingLastnameField))
            ->addExpressionFieldToSelect('billing_name', "CONCAT({{billing_firstname}}, ' ', {{billing_lastname}})",
                array('billing_firstname' => $billingFirstnameField, 'billing_lastname' => $billingLastnameField))
            ->addExpressionFieldToSelect('shipping_name', 'CONCAT({{shipping_firstname}}, " ", {{shipping_lastname}})',
                array('shipping_firstname' => $shippingFirstnameField, 'shipping_lastname' => $shippingLastnameField)
            );

        Mage::dispatchEvent(
            'retailops_order_collection_prepare',
            array('collection' => $orderCollection)
        );



        /** @var $apiHelper Retailops_Api_Helper_Data */
        $apiHelper = Mage::helper('retailops_api');
        $filters = $apiHelper->applyPager($orderCollection, $filters);
        $filters = $apiHelper->parseFilters($filters, $this->_attributesMap['order']);

        try {
            foreach ($filters as $field => $value) {
                $orderCollection->addFieldToFilter($field, $value);
            }
        } catch (Mage_Core_Exception $e) {
            $this->_fault('filters_invalid', $e->getMessage());
        }

        $result['totalCount'] = $orderCollection->getSize();
        $result['count'] = count($orderCollection);

        try {
            foreach ($orderCollection as $order) {
                $record = $this->orderInfo($order);
                $recordObj = new Varien_Object($record);

                Mage::dispatchEvent(
                    'retailops_order_pull_record',
                    array('record' => $recordObj)
                );

                $orders[] = $recordObj->getData();
            }

            $result['records'] = $orders;
        } catch (Mage_Core_Exception $e) {
            $this->_fault('order_pull_error', $e->getMessage());
        }

        return $result;
    }

    /**
    * Retrieve full order information
    *
    * @param Mage_Core_Model_Abstract $order
    * @return array
    */
    public function orderInfo($order)
    {
        if ($order->getGiftMessageId() > 0) {
            $order->setGiftMessage(
                Mage::getSingleton('giftmessage/message')->load($order->getGiftMessageId())->getMessage()
            );
        } else {
            $order->setGiftMessage(null);
        }

        $result['order_info'] = $this->_getAttributes($order, 'order');

        $result['shipping_address'] = $this->_getAttributes($order->getShippingAddress(), 'order_address');
        $result['billing_address']  = $this->_getAttributes($order->getBillingAddress(), 'order_address');
        $result['items'] = array();

        foreach ($order->getAllItems() as $item) {
            if ($item->getGiftMessageId() > 0) {
                $item->setGiftMessage(
                    Mage::getSingleton('giftmessage/message')->load($item->getGiftMessageId())->getMessage()
                );
            }

            $result['items'][] = $this->_getAttributes($item, 'order_item');
        }

        $result['payment'] = $this->_getAttributes($order->getPayment(), 'order_payment');

        $result['status_history'] = array();

        foreach ($order->getAllStatusHistory() as $history) {
            $result['status_history'][] = $this->_getAttributes($history, 'order_status_history');
        }

        $retailops_status_history = Mage::getModel('retailops_api/order_status_history')->getRetailOpsStatusHistory($order);
        $result['retailops_status_history'] = array();

        foreach ($retailops_status_history  as $history) {
            $result['retailops_status_history'][] = $this->_getAttributes($history, 'retailops_order_status_history');
        }

        return $result;
    }

    /**
     * @param array $ordersData
     * @return array
     */
    public function orderStatusUpdate($ordersData)
    {
        if (isset($ordersData['records'])) {
            $ordersData = $ordersData['records'];
        }
        $fullResult = array();
        $fullResult['records'] = array();
        foreach ($ordersData as $orderData) {
            if (isset($orderData['order_increment_id'])) {
                try {
                    $result = array(
                        'order_increment_id' => $orderData['order_increment_id']
                    );
                     /** @var Mage_Sales_Model_Order $order */
                    $order = Mage::getModel('sales/order');
                    $order->loadByIncrementId($orderData['order_increment_id']);
                    if (!$order->getId()) {
                        throw new Exception('Order is not found');
                    }
                    $order->setRetailopsStatus($orderData['retailops_status']);
                    $order->save();
                    /** @var RetailOps_Api_Model_Order_Status_History $history */
                    $history = Mage::getModel('retailops_api/order_status_history');
                    $history->setOrder($order);
                    $history->setStatus($orderData['retailops_status']);
                    if (isset($orderData['comment'])) {
                        $history->setComment($orderData['comment']);
                    }
                    $history->save();
                    $result['status'] = RetailOps_Api_Helper_Data::API_STATUS_SUCCESS;
                } catch (Exception $e) {
                    $result['status'] = RetailOps_Api_Helper_Data::API_STATUS_FAIL;
                    $result['message'] = $e->getMessage();
                }
                $fullResult['records'][] = $result;
            }
        }

        return $fullResult;
    }
}
