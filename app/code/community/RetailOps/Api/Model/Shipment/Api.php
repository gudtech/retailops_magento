<?php
/**
{license_text}
 */

class RetailOps_Api_Model_Shipment_Api extends Mage_Sales_Model_Order_Shipment_Api
{
    /**
     * Creates shipment, adds track numbers, creates new invoices
     *
     * @param $shipments array
     * @return array
     */
    public function shipmentPush($shipments)
    {
        $result = array();
        $count = 0;
        foreach ($shipments as $shipmentData) {
            try{
                $shipment = new Varien_Object($shipmentData);

                Mage::dispatchEvent(
                    'retailops_shipment_process_before',
                    array('record' => $shipment)
                );

                $result[$count]['order_increment_id'] = $shipment->getOrderIncrementId();

                $orderIncrementId = $shipment->getOrderIncrementId();
                $shipmentInfo = $shipment->getShipment();
                $trackInfo    = isset($shipmentInfo['track']) ? $shipmentInfo['track'] : array();
                $invoiceInfo  = isset($shipmentInfo['invoice']) ? $shipmentInfo['invoice'] : array();
                $shipmentIncrementId = null;
                // create shipment
                try {
                    $shipmentResult = array();
                    $shipmentIncrementId = $this->create($orderIncrementId,
                        $shipmentInfo['qtys'],
                        $shipmentInfo['comment'],
                        $shipmentInfo['email'],
                        $shipmentInfo['include_comment']
                    );

                    if ($shipmentIncrementId) {
                        $shipmentResult['status'] = RetailOps_Api_Helper_Data::API_STATUS_SUCCESS;
                        $shipmentResult['shipment_increment_id'] = $shipmentIncrementId;
                    } else {
                        $shipmentResult['status'] = RetailOps_Api_Helper_Data::API_STATUS_FAIL;
                        $shipmentResult['message'] = Mage::helper('retailops_api')->__('Can not create shipment');
                    }
                } catch (Mage_Api_Exception $e) {
                    $shipmentResult['status'] = RetailOps_Api_Helper_Data::API_STATUS_FAIL;
                    $shipmentResult['message'] = $e->getCustomMessage();
                } catch (Mage_Core_Exception $e) {
                    $shipmentResult['status'] = RetailOps_Api_Helper_Data::API_STATUS_FAIL;
                    $shipmentResult['message'] = $e->getMessage();
                }

                $result[$count]['shipment_result'] = $shipmentResult;
                if ($shipmentIncrementId) {
                    if ($trackInfo) {
                        $result[$count]['track_result'] = array();
                        foreach ($trackInfo as $track) {
                            // add shipment track
                            try {
                                $trackResult = array();
                                $track = new Varien_Object($track);

                                Mage::dispatchEvent(
                                    'retailops_shipment_add_track_before',
                                    array('record' => $track)
                                );
                                $trackResult['track_number'] = $track->getData('track_number');

                                $trackId = $this->addTrack($shipmentIncrementId,
                                    $track->getData('carrier'),
                                    $track->getData('title'),
                                    $track->getData('track_number')
                                );

                                $trackResult['status'] = RetailOps_Api_Helper_Data::API_STATUS_SUCCESS;
                                $trackResult['track_id'] = $trackId;
                            } catch (Mage_Core_Exception $e) {
                                $trackResult['status'] = RetailOps_Api_Helper_Data::API_STATUS_FAIL;
                                $trackResult['message'] = $e->getMessage();
                            }
                            $result[$count]['track_result'][] = $trackResult;
                        }
                    }

                    // create invoice
                    /** @var Mage_Sales_Model_Order $order */
                    $order = Mage::getModel('sales/order');
                    $order->loadByIncrementId($orderIncrementId);
                    $isFullyShipped = $this->_checkAllItemsShipped($order);
                    $invoices = $order->getInvoiceCollection();
                    $invoiceResult = array();
                    if ($order->canInvoice()) {
                        $itemsToInvoice = array();
                        if ($order->getPayment()->canCapturePartial()) {
                            /**
                             * If payment allows partial capture, trying to create invoice with shipped items only and capture it
                             */
                            $itemsToInvoice = $shipmentInfo['qtys'];
                        }
                        if (($itemsToInvoice || $isFullyShipped) && $invoiceInfo) {

                            $invoice = new Varien_Object($invoiceInfo);
                            $invoice->setData('items_to_invoice', $itemsToInvoice);

                            Mage::dispatchEvent(
                                'retailops_shipment_invoice_before',
                                array('record' => $invoice)
                            );

                            try {
                                $invoiceIncrementId = $this->_createInvoiceAndCapture(
                                    $order,
                                    $invoice->getItemsToInvoice(),
                                    $invoice->getComment(),
                                    $invoice->getEmail(),
                                    $invoice->getIncludeComment()
                                );
                                $invoiceResult['invoice_increment_id'] = $invoiceIncrementId;
                                $invoiceResult['status'] = RetailOps_Api_Helper_Data::API_STATUS_SUCCESS;
                            } catch (Mage_Core_Exception $e) {
                                $invoiceResult['status'] = RetailOps_Api_Helper_Data::API_STATUS_FAIL;
                                $invoiceResult['message'] = $e->getMessage();
                            }
                            $invoiceResult = array($invoiceResult);
                        }
                    } else {
                        if ($isFullyShipped) {
                            /**
                             * Capturing all available invoices if all order items are shipped
                             */
                            $invoiceResult = $this->_captureInvoices($invoices);
                        }
                    }
                    $result[$count]['invoice_result'] = $invoiceResult;
                }
            } catch (Exception $e) {
                $result[$count]['status'] = RetailOps_Api_Helper_Data::API_STATUS_FAIL;
                $result[$count]['message'] = $e->getMessage();
            }

            $count++;
        }

        return $result;
    }

    /**
     * @param array $ordersData
     * @return array
     */
    public function orderClose($ordersData)
    {
        $fullResult = array();
        foreach ($ordersData as $orderData) {
            try {
                if (!empty($orderData['order_increment_id'])) {
                    $result = array(
                        'order_increment_id' => $orderData['order_increment_id']
                    );
                    /** @var Mage_Sales_Model_Order $order */
                    $order = Mage::getModel('sales/order');
                    $order->loadByIncrementId($orderData['order_increment_id']);
                    if (!$order->getId()) {
                        throw new Exception('Order is not found');
                    }
                    Mage::dispatchEvent(
                        'retailops_order_close_before',
                        array('order' => $order)
                    );
                    $items = $order->getAllItems();
                    $itemsToReturn = array();
                    $itemsToCancel = array();
                    $itemsToCapture = array();
                    /** @var $item Mage_Sales_Model_Order_Item  */
                    foreach ($items as $item) {
                        $qtyToShip = $item->getQtyToShip();
                        $qtyToInvoice = $item->getQtyToInvoice();
                        if ($qtyToShip + $qtyToInvoice > 0) {
                           if ($qtyToShip < $qtyToInvoice) {
                                /**
                                 * If we have shipped more items than invoiced, capture the difference
                                 */
                                $itemsToCapture[$item->getId()] = $qtyToInvoice - $qtyToShip;
                           } elseif ($qtyToShip > $qtyToInvoice) {
                                /**
                                 * If we have invoiced more items than shipped, return the difference
                                 */
                                $itemsToReturn[$item->getId()] = $qtyToShip - $qtyToInvoice;
                           }
                        }
                    }
                    if ($itemsToCapture) {
                        $result['invoice_increment_id'] = $this->_createInvoiceAndCapture($order, $itemsToCapture);
                    }
                    if ($itemsToReturn) {
                        $result['creditmemo'] = $this->_getCreditMemoApi()->create($order, array('qtys' => $itemsToReturn));
                    }
                    /**
                     * Cancel rest items if any
                     */
                    $order->registerCancellation(Mage::helper('retailops_api')->__('No more items will be shipped'));
                    $order->save();

                    $result['status'] = RetailOps_Api_Helper_Data::API_STATUS_SUCCESS;
                }
            } catch (Exception $e) {
                $result['status'] = RetailOps_Api_Helper_Data::API_STATUS_FAIL;
                $result['message'] = $e->getMessage();
            }
            $fullResult[] = $result;
        }

        return $fullResult;
    }

    /**
     * @return RetailOps_Api_Model_Return_Api
     */
    protected function _getCreditMemoApi()
    {
        return Mage::getModel('retailops_api/return_api');
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param array $itemsQty
     * @param string $comment
     * @param bool $email
     * @param bool $includeComment
     * @return string
     */
    protected function _createInvoiceAndCapture($order, $itemsQty, $comment = null, $email = false, $includeComment = false)
    {
        $itemsQtyFomratted = array();
        foreach ($order->getAllItems() as $item) {
            $itemsQtyFomratted[$item->getId()] = isset($itemsQty[$item->getId()]) ? $itemsQty[$item->getId()] : 0;
        }
        $invoice = $order->prepareInvoice($itemsQtyFomratted);
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
        $invoice->register();

        if ($comment !== null) {
            $invoice->addComment($comment, $email);
        }

        if ($email) {
            $invoice->setEmailSent(true);
        }

        $invoice->getOrder()->setIsInProcess(true);


        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder())
            ->save();

        $invoice->sendEmail($email, ($includeComment ? $comment : ''));

        return $invoice->getIncrementId();
    }

    /**
     * Check that there are no items left for shipping
     *
     * @param Mage_Sales_Model_Order $order $order
     * @return bool
     */
    protected function _checkAllItemsShipped($order)
    {
        $items = $order->getAllItems();
        /** @var $item Mage_Sales_Model_Order_Item  */
        foreach ($items as $item) {
            if ($item->getQtyToShip()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Mage_Sales_Model_Resource_Order_Invoice_Collection $invoices
     * @return array
     */
    protected function _captureInvoices($invoices)
    {
        $result = array();
        /** @var $invoice Mage_Sales_Model_Order_Invoice */
        foreach ($invoices as $invoice) {
            try {
                $invoiceResult = array();
                $invoiceResult['invoice_increment_id'] = $invoice->getIncrementId();
                if ($invoice->canCapture()) {
                    throw new Exception('Invoice cannot be captured.');
                }
                $invoice->capture();
                $invoice->getOrder()->setIsInProcess(true);
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();
                $invoiceResult['status'] = RetailOps_Api_Helper_Data::API_STATUS_SUCCESS;
            } catch (Exception $e) {
                $invoiceResult['message'] = $e->getMessage();
                $invoiceResult['status'] = RetailOps_Api_Helper_Data::API_STATUS_FAIL;
            }
            $result[] = $invoiceResult;
        }

        return $result;
    }
}
