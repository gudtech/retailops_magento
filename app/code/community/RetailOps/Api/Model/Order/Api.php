<?php
/**
{license_text}
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

        $result['totalCount'] = $orderCollection->getSize();
        $result['count'] = count($orderCollection);

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

        try {
            foreach ($orderCollection as $order) {
                $record = $this->orderInfo($order);
                $orders[] = $record;
                $recordObj = new Varien_Object($record);

                Mage::dispatchEvent(
                    'retailops_catalog_pull_record',
                    array('record' => $recordObj)
                );
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
}
