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

class RetailOps_Api_Model_Shipment_Api extends Mage_Sales_Model_Order_Shipment_Api
{
    protected $_requestParams;

    /**
     * Create new shipment for order
     *
     * @param string $orderIncrementId
     * @param array $itemsQty
     * @param string $comment
     * @param booleam $email
     * @param boolean $includeComment
     * @param int $retailopsShipmentId
     * @return string
     */
    public function create($orderIncrementId, $itemsQty = array(), $comment = null, $email = false,
        $includeComment = false, $retailopsShipmentId = null
    ) {
        $shipmentIncrementId = parent::create($orderIncrementId, $itemsQty, $comment, $email, $includeComment);

        if ($retailopsShipmentId && $shipmentIncrementId) {
            $shipment = Mage::getModel('sales/order_shipment')->loadByIncrementId($shipmentIncrementId);

            if (!$shipment->getId()) {
                $this->_fault('shipment_not_exists');
            }

            $shipment->setRetailopsShipmentId($retailopsShipmentId);
            $shipment->save();
        }

        return $shipmentIncrementId;
    }

    /**
     * Creates shipment, adds track numbers, creates new invoices
     *
     * @param $shipments array
     * @return array
     */
    public function shipmentPush($shipments)
    {
        $this->_requestParams = $shipments;

        $fullResult = array();
        $fullResult['records'] = array();
        if (isset($shipments['records'])) {
            $shipments = $shipments['records'];
        }
        foreach ($shipments as $shipmentData) {
            $result = array();
            try{
                $shipment = new Varien_Object($shipmentData);

                Mage::dispatchEvent(
                    'retailops_shipment_process_before',
                    array('record' => $shipment)
                );

                $result['order_increment_id'] = $shipment->getOrderIncrementId();

                $orderIncrementId = $shipment->getOrderIncrementId();
                $shipmentInfo = $shipment->getShipment();
                $trackInfo    = isset($shipmentInfo['track']) ? $shipmentInfo['track'] : array();
                $invoiceInfo  = isset($shipmentInfo['invoice']) ? $shipmentInfo['invoice'] : array();
                $shipmentResult = array();
                $shipmentIncrementId = null;

                /** @var Mage_Sales_Model_Order $order */
                $order = Mage::getModel('sales/order');
                $order->loadByIncrementId($orderIncrementId);
                if (!$order->getId()) {
                    throw new Exception('Order is not found');
                }

                // create shipment
                try {
                    // Try to locate existing shipment
                    $orderShipments = $order->getShipmentsCollection();
                    if (count($orderShipments)) {
                        foreach ($orderShipments as $orderShipment) {
                            $retailopsShipmentId = $orderShipment->getRetailopsShipmentId();
                            if ($retailopsShipmentId && $shipmentInfo['retailops_shipment_id']
                                && $retailopsShipmentId == $shipmentInfo['retailops_shipment_id']) {
                                $shipmentIncrementId = $orderShipment->getIncrementId();

                                break;
                            }

                            $shipmentComments = $orderShipment->getCommentsCollection();
                            foreach ($shipmentComments as $shipmentComment) {
                                if ($shipmentComment->getComment() == $shipmentInfo['comment']) {
                                    $shipmentIncrementId = $orderShipment->getIncrementId();

                                    break;
                                }
                            }

                            if ($shipmentIncrementId) {
                                break;
                            }
                        }
                    }

                    // Create a new shipment if we didn't find an existing one
                    // Only adding shipment to result if it was created
                    if (!$shipmentIncrementId && $order->canShip()) {
                        $shipmentIncrementId = $this->create($orderIncrementId,
                            $shipmentInfo['qtys'],
                            $shipmentInfo['comment'],
                            $shipmentInfo['email'],
                            $shipmentInfo['include_comment'],
                            $shipmentInfo['retailops_shipment_id']
                        );

                        if ($shipmentIncrementId) {
                            $shipmentResult['status'] = RetailOps_Api_Helper_Data::API_STATUS_SUCCESS;
                            $shipmentResult['shipment_increment_id'] = $shipmentIncrementId;
                        } else {
                            $shipmentResult['status'] = RetailOps_Api_Helper_Data::API_STATUS_FAIL;
                            $shipmentResult['message'] = Mage::helper('retailops_api')->__('Can not create shipment');
                        }
                    }

                    // We should have found or created a shipment by now
                    if (!$shipmentIncrementId) {
                        $shipmentResult['status'] = RetailOps_Api_Helper_Data::API_STATUS_FAIL;
                        $shipmentResult['message'] = Mage::helper('retailops_api')->__('Shipment not found');
                    }
                } catch (Mage_Core_Exception $e) {
                    $shipmentResult['status'] = RetailOps_Api_Helper_Data::API_STATUS_FAIL;
                    $shipmentResult['message'] = $e->getCustomMessage() ? $e->getCustomMessage() : $e->getMessage();
                    $shipmentResult['stack_trace'] = $e->getTraceAsString();
                    $shipmentResult['request_params'] = $this->_requestParams;
                }
                $result['shipment_result'] = $shipmentResult ? array($shipmentResult) : array();

                if ($shipmentIncrementId) {
                    if ($trackInfo) {
                        $existingShipmentInfo = Mage::getModel('sales/order_shipment_api')->info($shipmentIncrementId);

                        $result['track_result'] = array();
                        foreach ($trackInfo as $track) {
                            // add shipment track
                            try {
                                $trackResult = array();
                                $track = new Varien_Object($track);

                                $trackId = null;
                                foreach ($existingShipmentInfo['tracks'] as $existingTrack) {
                                    if ($existingTrack['track_number'] == $track->getData('track_number')) {
                                        $trackId = $existingTrack['track_id'];

                                        break;
                                    }
                                }

                                if ($trackId) {
                                    continue;
                                }

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
                                $trackResult['stack_trace'] = $e->getTraceAsString();
                                $trackResult['request_params'] = $this->_requestParams;
                            }
                            $result['track_result'][] = $trackResult;
                        }
                    }

                    // create invoice
                    /** @var Mage_Sales_Model_Order $order */
                    // Note that this does need to be loaded again.  The item data may be stale if a shipment
                    // was submitted above.
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

                            $invoiceResult = $this->_createInvoiceAndCapture(
                                    $order,
                                    $invoice->getItemsToInvoice(),
                                    $invoice->getCapturedOffline(),
                                    $invoice->getComment(),
                                    $invoice->getEmail(),
                                    $invoice->getIncludeComment()
                                );

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
                    $result['invoice_result'] = $invoiceResult;
                }
            } catch (Exception $e) {
                $result['status'] = RetailOps_Api_Helper_Data::API_STATUS_FAIL;
                $result['message'] = $e->getMessage();
                $result['stack_trace'] = $e->getTraceAsString();
                $result['request_params'] = $this->_requestParams;
            }
            $fullResult['records'][] = $result;
        }

        return $fullResult;
    }

    /**
     * @param array $ordersData
     * @return array
     */
    public function orderClose($ordersData)
    {
        $this->_requestParams = $ordersData;

        $fullResult = array();
        $fullResult['records'] = array();
        if (isset($ordersData['records'])) {
            $ordersData = $ordersData['records'];
        }
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
                        $result['invoice_result'] = $this->_createInvoiceAndCapture($order, $itemsToCapture, $orderData['captured_offline']);
                    }
                    if ($itemsToReturn) {
                        $result['creditmemo_result'] = $this->_getCreditMemoApi()->create($order, array('qtys' => $itemsToReturn), $orderData['captured_offline']);
                    }
                    /**
                     * Cancel the rest items if any
                     */
                    $order->registerCancellation(Mage::helper('retailops_api')->__('No more items will be shipped'));
                    if (isset($orderData['retailops_status'])) {
                        $order->setRetailopsStatus($orderData['retailops_status']);
                    }
                    $order->save();
                    $result['status'] = RetailOps_Api_Helper_Data::API_STATUS_SUCCESS;
                }
            } catch (Exception $e) {
                $result['status'] = RetailOps_Api_Helper_Data::API_STATUS_FAIL;
                $result['message'] = $e->getMessage();
                $result['stack_trace'] = $e->getTraceAsString();
                $result['request_params'] = $this->_requestParams;
            }
            $fullResult['records'][] = $result;
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
    protected function _createInvoiceAndCapture($order, $itemsQty, $capturedOffline = false, $comment = null, $email = false, $includeComment = false)
    {
        /** @var $helper RetailOps_Api_Helper_Data */
        $helper = Mage::helper('retailops_api');
        try {
            $itemsQtyFomratted = array();
            if ($itemsQty) {
                foreach ($order->getAllItems() as $item) {
                    $itemsQtyFomratted[$item->getId()] = isset($itemsQty[$item->getId()]) ? $itemsQty[$item->getId()] : 0;
                }
            }
            $invoice = $order->prepareInvoice($itemsQtyFomratted);
            $invoice->setRequestedCaptureCase($capturedOffline
                ? Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE : Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
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

            $result['invoice'] = $helper->removeObjectsFromResult($this->_getAttributes($invoice, 'invoice'));
            $result['items'] = array();
            foreach ($invoice->getAllItems() as $item) {
                $result['items'][] = $helper->removeObjectsFromResult($this->_getAttributes($item, 'invoice_item'));
            }
            $result['status'] = RetailOps_Api_Helper_Data::API_STATUS_SUCCESS;
        } catch (Exception $e) {
            $result['status'] = RetailOps_Api_Helper_Data::API_STATUS_FAIL;
            $result['message'] = $e->getMessage();
            $result['stack_trace'] = $e->getTraceAsString();
            $result['request_params'] = $this->_requestParams;
        }

        return $result;
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
                if ($invoice->getState() == Mage_Sales_Model_Order_Invoice::STATE_PAID) {
                    continue;
                }
                $invoiceResult = array();
                $invoiceResult['invoice_increment_id'] = $invoice->getIncrementId();
                if (!$invoice->canCapture()) {
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
                $invoiceResult['stack_trace'] = $e->getTraceAsString();
                $invoiceResult['request_params'] = $this->_requestParams;
            }
            $result[] = $invoiceResult;
        }

        return $result;
    }
}
