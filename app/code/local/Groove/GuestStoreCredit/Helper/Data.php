<?php

/**
 * Base module helper.
 * 
 * PHP Version 5
 * 
 * @category  Class
 * @package   Groove_GuestStoreCredit
 * @author    Groove Commerce
 * @copyright 2016 Groove Commerce, LLC. All Rights Reserved.
 */

/**
 * Class declaration
 *
 * @category Class_Type_Helper
 * @package  Groove_GuestStoreCredit
 * @author   Groove Commerce
 */

class Groove_GuestStoreCredit_Helper_Data
    extends Mage_Core_Helper_Abstract
{

    const XML_CONFIG_PATH_GUEST_CONVERSION = 'customer/enterprise_customerbalance/guest_conversion';
    const XML_CONFIG_PATH_CARD_CONVERSION = 'customer/giftcardaccount_general/storecredit_conversion';
    static $ISCUSTOMERREGISTER = true;

    /**
     * Prepare a customer address for import from an order address.
     * 
     * @param Mage_Sales_Model_Order         $order   The order model.
     * @param Mage_Sales_Model_Order_Address $address The order address.
     * 
     * @return Mage_Customer_Model_Address
     */
    protected function _prepareCustomerAddress(Mage_Sales_Model_Order $order, Mage_Sales_Model_Order_Address $address)
    {
        $address = Mage::getModel('customer/address');

        Mage::helper('core')->copyFieldset('sales_convert_order_address', 'to_customer_address', $order, $address);

        return $address;
    }

    /**
     * Determine whether guests may be converted to customers to receive store credit.
     * 
     * @return boolean
     */
    public function canConvertGuest()
    {
        return Mage::getStoreConfigFlag(self::XML_CONFIG_PATH_GUEST_CONVERSION);
    }

    /**
     * Determine whether gift card balances may be converted to store credit.
     * 
     * @return boolean
     */
    public function canConvertGiftCardToStoreCredit()
    {
        return Mage::getStoreConfigFlag(self::XML_CONFIG_PATH_GUEST_CONVERSION);
    }

    /**
     * Create a new customer account from the given order.
     * 
     * @param Mage_Sales_Model_Order $order The order model.
     * 
     * @return Mage_Customer_Model_Customer
     */
    public function createNewCustomerFromOrder(Mage_Sales_Model_Order $order)
    {
        if ($order->getCustomerIsGuest()) {
            /* @var $customer Mage_Customer_Model_Customer */
            $customer = Mage::getModel('customer/customer')
                ->setWebsiteId($order->getStore()->getWebsiteId())
                ->loadByEmail($order->getCustomerEmail());

            // Just in case guest flag is no longer relevant, ensure customer is new post-load
            if (!$customer->getId()) {
				self::$ISCUSTOMERREGISTER = false;
                $billing    = $order->getBillingAddress();
                $shipping   = $order->getIsVirtual() ? null : $order->getShippingAddress();

                $customerBilling = $this->_prepareCustomerAddress($order, $billing);

                $customer->addAddress($customerBilling);
                $billing->setCustomerAddress($customerBilling);
                $customerBilling->setIsDefaultBilling(true);

                if ($shipping && !$shipping->getSameAsBilling()) {
                    $customerShipping = $this->_prepareCustomerAddress($order, $shipping);

                    $customer->addAddress($customerShipping);
                    $shipping->setCustomerAddress($customerShipping);
                    $customerShipping->setIsDefaultShipping(true);
                } else {
                    $customerBilling->setIsDefaultShipping(true);
                }

                Mage::helper('core')->copyFieldset('checkout_onepage_quote', 'to_customer', $order, $customer);

                $password = $customer->generatePassword(16);

                $customer
                    ->setPassword($password)
                    ->setTempPassword($password)
                    ->setStoreId($order->getStoreId())
                    ->setWebsiteId($order->getStore()->getWebsiteId())
                    ->save();

                $order->setCustomer($customer)
                    ->setCustomerId($customer->getId())
                    ->setCustomerIsGuest(0);
            }
        } else {
            $customer = $order->getCustomer();
        }

        return $customer;
    }

}
