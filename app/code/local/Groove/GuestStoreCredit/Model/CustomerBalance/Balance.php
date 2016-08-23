<?php

/**
 * Customer balance model extensions.
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
 * @category Class_Type_Model
 * @package  Groove_GuestStoreCredit
 * @author   Groove Commerce
 */

class Groove_GuestStoreCredit_Model_CustomerBalance_Balance
    extends Enterprise_CustomerBalance_Model_Balance
{

    /**
     * Determine whether the order is from a guest.
     * 
     * @return boolean
     */
    private function _isGuestOrder()
    {
        return $this->getOrder() instanceof Mage_Sales_Model_Order &&
            $this->getOrder()->getCustomerIsGuest();
    }

    /**
     * Post-save handler.
     * 
     * @return 
     */
    protected function _afterSave()
    {
        parent::_afterSave();
        
        if ($this->getIsFromGuestFlag()) {
            $this->getCreditMemo()
                ->setBsCustomerBalTotalRefundedFormatted(
                    Mage::app()->getLocale()
                        ->currency($this->getOrder()->getStore()->getBaseCurrencyCode())
                        ->toCurrency($this->getCreditMemo()->getBsCustomerBalTotalRefunded())
                );
                
			$cust_status = Groove_GuestStoreCredit_Helper_Data::$ISCUSTOMERREGISTER;
			if($cust_status){
				$template_name = 'from_store_credit_customer';
			}else{
				$template_name = 'from_store_credit';
			}
			
            $this->getCustomer()
                ->sendNewAccountEmail(
                    $template_name, 
                    '', 
                    $this->getOrder()->getStoreId(), 
                    array('credit_memo' => $this->getCreditMemo())
                );
        }

        return $this;
    }

    /**
     * Ensure that the customer information is set.
     * 
     * @return void
     */
    protected function _ensureCustomer()
    {
        if ( $this->_isGuestOrder() && Mage::helper('gueststorecredit')->canConvertGuest() ) {
            $customer = Mage::helper('gueststorecredit')->createNewCustomerFromOrder($this->getOrder());

            $this->getCreditMemo()->setCustomerId($customer->getId());

            $this->setCustomerId($customer->getId())
                ->setCustomer($customer);

            $this->setIsFromGuestFlag(true);
        }

        parent::_ensureCustomer();
    }

}
