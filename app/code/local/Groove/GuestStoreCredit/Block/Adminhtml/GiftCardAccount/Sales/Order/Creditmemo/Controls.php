<?php

/**
 * Credit memo gift card account controls block extensions.
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

class Groove_GuestStoreCredit_Block_Adminhtml_GiftCardAccount_Sales_Order_Creditmemo_Controls
    extends Enterprise_GiftCardAccount_Block_Adminhtml_Sales_Order_Creditmemo_Controls
{

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
    protected function _toHtml()
    {
        if (
            Mage::helper('gueststorecredit')->canConvertGiftCardToStoreCredit() && 
            Mage::registry('current_creditmemo')->getGiftCardsAmount()
        ) {
            return '
                <input type="hidden" name="creditmemo[refund_giftcardaccount]" value="1" />
                <p class="note">' . $this->__('Gift card amounts will automatically be refunded to store credit.') . '</p>
            ';
        }

        return parent::_toHtml();
    }

}