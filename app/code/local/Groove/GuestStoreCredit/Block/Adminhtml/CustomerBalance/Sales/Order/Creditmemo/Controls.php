<?php

/**
 * Credit memo customer balance controls block extensions.
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
 * @category Class_Type_Block
 * @package  Groove_GuestStoreCredit
 * @author   Groove Commerce
 */

class Groove_GuestStoreCredit_Block_Adminhtml_CustomerBalance_Sales_Order_Creditmemo_Controls
    extends Enterprise_CustomerBalance_Block_Adminhtml_Sales_Order_Creditmemo_Controls
{

    /**
     * Determine whether the guest notice may be displayed.
     * 
     * @return boolean
     */
    public function canDisplayGuestConversionNotice()
    {
        return Mage::helper('gueststorecredit')->canConvertGuest() && 
            $this->_getCreditmemo()->getOrder()->getCustomerIsGuest();
    }

    /**
     * Determine whether refund to customer balance is allowed.
     *
     * @return boolean
     */
    public function canRefundToCustomerBalance()
    {
        if (!Mage::helper('gueststorecredit')->canConvertGiftCardToStoreCredit()) {
            return parent::canRefundToCustomerBalance();
        }

        return true;
    }

    /**
     * Post-render handler.
     * 
     * @return string
     */
    protected function _afterToHtml($html = '')
    {
        $html = parent::_afterToHtml($html);

        if ($this->canDisplayGuestConversionNotice()) {
            $html .= '<p class="note">' . $this->__('A customer will be created when applying store credit.') . '</p>';
        }

        return $html;
    }

}