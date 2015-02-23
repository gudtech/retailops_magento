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
            $returnObj = new Varien_Object($return);
            Mage::dispatchEvent(
                'retailops_return_push_record',
                array('record' => $returnObj)
            );

            $result[] = $this->create($returnObj->getOrderIncrementId(), $returnObj->getCreditmemoData(),
                $returnObj->getComment(), $returnObj->getNotifyCustomer(), $returnObj->getIncludeComment(),
                $returnObj->getRefundToStoreCredit());
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
            $creditmemoData['order'] = $order;
            $creditmemoData = $this->_prepareCreateData($creditmemoData);

            /** @var $service Mage_Sales_Model_Service_Order */
            $service = Mage::getModel('sales/service_order', $order);
            /** @var $creditmemo Mage_Sales_Model_Order_Creditmemo */
            $creditmemo = $service->prepareCreditmemo($creditmemoData, $order);

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
     * Set shipping amount to refund to 0 in case it's not passed
     * Set qtys to refund to 0 by default if they are not passed
     *
     * @param  array $data
     * @return array
     */
    protected function _prepareCreateData($data)
    {
        $data = parent::_prepareCreateData($data);
        $qtys = array();
        if (isset($data['qtys'])) {
            $qtys = $data['qtys'];
        }
        $order = $data['order'];
        $qtysArray = array();
        foreach ($order->getAllItems() as $orderItem) {
            if (!isset($qtys[$orderItem->getId()])) {
                $qtysArray[$orderItem->getId()] = 0;
            }
        }
        $data['qtys'] = $qtysArray;

        if (empty($data['shipping_amount'])) {
            $data['shipping_amount'] = 0;
        }

        return $data;
    }
}
