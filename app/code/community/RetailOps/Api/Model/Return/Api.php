<?php
/**
{license_text}
 */

class RetailOps_Api_Model_Return_Api extends Mage_Sales_Model_Order_Creditmemo_Api
{
    /**
     * Creates Credit Memo
     *
     * @param  mixed $returns
     * @return array
     */
    public function returnPush($returns = null)
    {
        $result = array();
        foreach ($returns as $return) {
            $result[] = $this->create($return['order_increment_id'], $return['credit_memo_data'] = null,
                $return['comment'] = null, $return['notify_customer'] = false, $return['include_comment'] = false,
                $return['refund_to_store_credit_amount'] = null);
        }
        return $result;
    }

    /**
     * Create new credit memo for order
     *
     * @param string $orderIncrementId
     * @param array $creditmemoData array('qtys' => array('sku1' => qty1, ... , 'skuN' => qtyN),
     *      'shipping_amount' => value, 'adjustment_positive' => value, 'adjustment_negative' => value)
     * @param string|null $comment
     * @param bool $notifyCustomer
     * @param bool $includeComment
     * @param string $refundToStoreCreditAmount
     * @return string $creditmemoIncrementId
     */
    public function create($orderIncrementId, $creditmemoData = null, $comment = null, $notifyCustomer = false,
                           $includeComment = false, $refundToStoreCreditAmount = null)
    {
        try {
            $result = array();
            /** @var $order Mage_Sales_Model_Order */
            $order = Mage::getModel('sales/order')->load($orderIncrementId, 'increment_id');
            if (!$order->getId()) {
                $this->_fault('order_not_exists');
            }
            if (!$order->canCreditmemo()) {
                $this->_fault('cannot_create_creditmemo');
            }
            $creditmemoData = $this->_prepareCreateData($creditmemoData);

            /** @var $creditmemo Mage_Sales_Model_Order_Creditmemo */
            $creditmemo = $this->prepareCreditmemo($creditmemoData);

            // refund to Store Credit
            if ($refundToStoreCreditAmount) {
                // check if refund to Store Credit is available
                if ($order->getCustomerIsGuest()) {
                    $this->_fault('cannot_refund_to_storecredit');
                }
                $refundToStoreCreditAmount = max(
                    0,
                    min($creditmemo->getBaseCustomerBalanceReturnMax(), $refundToStoreCreditAmount)
                );
                if ($refundToStoreCreditAmount) {
                    $refundToStoreCreditAmount = $creditmemo->getStore()->roundPrice($refundToStoreCreditAmount);
                    $creditmemo->setBaseCustomerBalanceTotalRefunded($refundToStoreCreditAmount);
                    $refundToStoreCreditAmount = $creditmemo->getStore()->roundPrice(
                        $refundToStoreCreditAmount*$order->getStoreToOrderRate()
                    );
                    // this field can be used by customer balance observer
                    $creditmemo->setBsCustomerBalTotalRefunded($refundToStoreCreditAmount);
                    // setting flag to make actual refund to customer balance after credit memo save
                    $creditmemo->setCustomerBalanceRefundFlag(true);
                }
            }
            $creditmemo->setPaymentRefundDisallowed(true)->register();
            // add comment to creditmemo
            if (!empty($comment)) {
                $creditmemo->addComment($comment, $notifyCustomer);
            }

            Mage::getModel('core/resource_transaction')
                ->addObject($creditmemo)
                ->addObject($order)
                ->save();
            // send email notification
            $creditmemo->sendEmail($notifyCustomer, ($includeComment ? $comment : ''));
            $result['increment_id'] = $creditmemo->getIncrementId();
            $result['status'] = 'success';
        } catch (Mage_Core_Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = $e->getMessage();
            $result['code'] = $e->getCode();
        }

        return $result;
    }

    /**
     * Prepare order creditmemo based on order items and requested params
     *
     * @param array $data
     * @return Mage_Sales_Model_Order_Creditmemo
     */
    public function prepareCreditmemo($data = array(), Mage_Sales_Model_Order $order)
    {
        $totalQty = 0;
        $convertor = Mage::getModel('sales/convert_order');
        $creditmemo = $convertor->toCreditmemo($order);
        $qtys = isset($data['qtys']) ? $data['qtys'] : array();
        $this->updateLocaleNumbers($qtys);

        foreach ($order->getAllItems() as $orderItem) {
            if (!$this->_canRefundItem($orderItem, $qtys)) {
                continue;
            }

            $item = $convertor->itemToCreditmemoItem($orderItem);
            if ($orderItem->isDummy()) {
                $qty = 1;
                $orderItem->setLockedDoShip(true);
            } else {
                if (isset($qtys[$orderItem->getSku()])) {
                    $qty = (float) $qtys[$orderItem->getSku()];
                } elseif (!count($qtys)) {
                    $qty = $orderItem->getQtyToRefund();
                } else {
                    continue;
                }
            }
            $totalQty += $qty;
            $item->setQty($qty);
            $creditmemo->addItem($item);
        }
        $creditmemo->setTotalQty($totalQty);

        $this->_initCreditmemoData($creditmemo, $data);

        $creditmemo->collectTotals();
        return $creditmemo;
    }

}
