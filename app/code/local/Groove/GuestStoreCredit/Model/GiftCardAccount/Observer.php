<?php

/**
 * Gift card account observer extensions.
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

class Groove_GuestStoreCredit_Model_GiftCardAccount_Observer
    extends Enterprise_GiftCardAccount_Model_Observer
{

    /**
     * Convert gift card balances to store credit on a credit memo refund request.
     * 
     * @param Mage_Sales_Model_Order_Creditmemo $creditMemo The credit memo model.
     * 
     * @return Groove_GuestStoreCredit_Model_GiftCardAccount_Observer
     */
    protected function _applyAsStoreCredit(Mage_Sales_Model_Order_Creditmemo $creditMemo)
    {
        /* @var Mage_Sales_Model_Order $order */
        $order = $creditMemo->getOrder();
        $cards = $this->_getHelper('enterprise_giftcardaccount')->getCards($order);
        
        if (is_array($cards)) {
            if ($order->getCustomerId() || Mage::helper('gueststorecredit')->canConvertGuest()) {
                $balance = 0;

                foreach ($cards as $card) {
                    $balance += $card['ba'];
                }

                if ($balance > 0) {
                    $baseAmount = $order->getBaseGiftCardsAmount();
                    $amount     = $order->getGiftCardsAmount();

                    //
                    // When auto-refund is enabled, the balance adjustment above will be processed 
                    // via Enterprise_CustomerBalance_Model_Observer::creditmemoSaveAfter
                    // 
                    if (
                        !$creditMemo->getAutomaticallyCreated() && 
                        !Mage::helper('enterprise_customerbalance')->isAutoRefundEnabled()
                    ) {
                        $creditMemo->setBsCustomerBalTotalRefunded($creditMemo->getBsCustomerBalTotalRefunded() + $baseAmount);
                        $creditMemo->setCustomerBalTotalRefunded($creditMemo->getCustomerBalTotalRefunded() + $amount);

                        /* @var $balanceModel Enterprise_CustomerBalance_Model_Balance */
                        $balanceModel = Mage::getModel('enterprise_customerbalance/balance');
                        $balanceModel->setCustomerId($order->getCustomerId())
                            ->setWebsiteId($this->_getApp()->getStore($order->getStoreId())->getWebsiteId())
                            ->setAmountDelta($balance)
                            ->setHistoryAction(Enterprise_CustomerBalance_Model_Balance_History::ACTION_REFUNDED)
                            ->setOrder($order)
                            ->setCreditMemo($creditMemo)
                            ->save();
                    } else {
                        // Still allow gift cards to flow back to store credit for other scenarios
                        $creditMemo
                            ->setBaseCustomerBalanceAmount($creditMemo->getBaseCustomerBalanceAmount() + $baseAmount)
                            ->setCustomerBalanceAmount($creditMemo->getCustomerBalanceAmount() + $amount)
                            ->setBsCustomerBalTotalRefunded($creditMemo->getBaseCustomerBalanceAmount())
                            ->setCustomerBalTotalRefunded($creditMemo->getCustomerBalanceAmount());
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Set refund amount to credit memo.
     *
     * @param Varien_Event_Observer $observer
     * 
     * @return Enterprise_GiftCardAccount_Model_Observer
     *
     * @event sales_order_creditmemo_refund
     */
    public function refund(Varien_Event_Observer $observer)
    {
        if (!Mage::helper('gueststorecredit')->canConvertGiftCardToStoreCredit()) {
            return parent::refund($observer);
        }

        $this->_applyAsStoreCredit($observer->getEvent()->getCreditmemo());

        return $this;
    }

}