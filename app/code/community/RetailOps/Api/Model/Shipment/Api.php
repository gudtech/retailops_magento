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

        $helper = Mage::helper('retailops_api');
        $shipmentCollection = $helper->getVarienDataCollection($shipments);

        Mage::dispatchEvent(
            'retailops_shipment_push_before',
            array('shipments' => $shipmentCollection)
        );

        $result = array();
        $count = 0;
        foreach ($shipmentCollection as $shipment) {

            $result[$count]['order_increment_id'] = $shipment->getOrderIncrementId();

            try{
                $orderIncrementId = $shipment->getOrderIncrementId();
                $shipmentInfo = $shipment->getShipment();
                $trackInfo = $shipment->getTrack();
                $invoiceInfo = $shipment->getInvoice();

                // create shipment
                $shipmentResult = $this->createShipment($orderIncrementId,
                    $shipmentInfo['items_qty'],
                    $shipmentInfo['comment'],
                    $shipmentInfo['email'],
                    $shipmentInfo['include_comment']
                );
                $result[$count]['shipment_result'] = $shipmentResult;

                // add shipment track
                $trackResult = array();
                if (isset($shipmentResult['shipment_increment_id'])) {
                    $trackResult = $this->addTrack($shipmentResult['shipment_increment_id'],
                        $trackInfo['carrier'],
                        $trackInfo['title'],
                        $trackInfo['track_number']
                    );
                }
                $result[$count]['track_result'] = $trackResult;

                // create invoice
                $invoiceResult = $this->createInvoice(
                    $orderIncrementId,
                    $shipmentInfo['items_qty'],    // invoice the items to be shipped
                    $invoiceInfo['comment'],
                    $invoiceInfo['email'],
                    $invoiceInfo['include_comment']
                );
                $result[$count]['invoice_result'] = $invoiceResult;

                // capture invoice
                $invoiceCaptureResult = $this->captureInvoice($invoiceResult['invoice_increment_id']);
                $result[$count]['invoice_capture_result'] = $invoiceCaptureResult;

            } catch (Mage_Core_Exception $e) {
                $result[$count]['status'] = 'failed';
                $result[$count]['message'] = Mage::helper('retailops_api')->__('');
            }
            $count++;
        }

        $resultCollection = $helper->getVarienDataCollection($result);
        Mage::dispatchEvent(
            'retailops_shipment_push_after',
            array('shipments' => $shipmentCollection, 'results' => $resultCollection)
        );

        return $result;
    }

    /**
     * Create new shipment for order
     *
     * @param string $orderIncrementId
     * @param array $itemsQty
     * @param string $comment
     * @param boolean $email
     * @param boolean $includeComment
     * @return array
     */
    public function createShipment($orderIncrementId, $itemsQty = array(), $comment = null, $email = false,
                           $includeComment = false
    ) {
        $result = array();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);

        /**
         * Check order existing
         */
        if (!$order->getId()) {
            $result['status'] = 'failed';
            $result['message'] = 'order_not_exists';

            return $result;
        }

        /**
         * Check shipment create availability
         */
        if (!$order->canShip()) {
            $result['status'] = 'failed';
            $result['message'] = Mage::helper('sales')->__('Cannot do shipment for order.');

            return $result;
        }

        /* @var $shipment Mage_Sales_Model_Order_Shipment */
        $shipment = $order->prepareShipment($itemsQty);
        if ($shipment) {
            $shipment->register();
            $shipment->addComment($comment, $email && $includeComment);
            if ($email) {
                $shipment->setEmailSent(true);
            }
            $shipment->getOrder()->setIsInProcess(true);
            try {
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();
                $shipment->sendEmail($email, ($includeComment ? $comment : ''));
            } catch (Mage_Core_Exception $e) {
                $result['status'] = 'failed';
                $result['message'] = $e->getMessage();
            }

            $result['status'] = 'success';
            $result['shipment_increment_id'] = $shipment->getIncrementId();
        } else {
            $result['status'] = 'failed';
            $result['message'] = Mage::helper('retailops_api')->__('Can not create shipment');
        }

        return $result;
    }

    /**
     * Add tracking number to order
     *
     * @param string $shipmentIncrementId
     * @param string $carrier
     * @param string $title
     * @param string $trackNumber
     * @return array
     */
    public function addTrack($shipmentIncrementId, $carrier, $title, $trackNumber)
    {
        $result = array();
        $shipment = Mage::getModel('sales/order_shipment')->loadByIncrementId($shipmentIncrementId);

        /* @var $shipment Mage_Sales_Model_Order_Shipment */

        if (!$shipment->getId()) {
            $result['status'] = 'failed';
            $result['message'] = 'shipment_not_exists';

            return $result;
        }

        $carriers = $this->_getCarriers($shipment);

        if (!isset($carriers[$carrier])) {
            $result['status'] = 'failed';
            $result['message'] = Mage::helper('sales')->__('Invalid carrier specified.');

            return $result;
        }

        $track = Mage::getModel('sales/order_shipment_track')
            ->setNumber($trackNumber)
            ->setCarrierCode($carrier)
            ->setTitle($title);

        $shipment->addTrack($track);

        try {
            $shipment->save();
            $track->save();
            $result['status'] = 'success';
            $result['track_id'] = $track->getId();
        } catch (Mage_Core_Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Create new invoice for order
     *
     * @param string $orderIncrementId
     * @param array $itemsQty
     * @param string $comment
     * @param boolean $email
     * @param boolean $includeComment
     * @return array
     */
    public function createInvoice($orderIncrementId, $itemsQty, $comment = null, $email = false, $includeComment = false)
    {
        $result = array();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);

        /* @var $order Mage_Sales_Model_Order */
        /**
         * Check order existing
         */
        if (!$order->getId()) {
            $result['status'] = 'failed';
            $result['message'] = 'order_not_exists';

            return $result;
        }

        /**
         * Check invoice create availability
         */
        if (!$order->canInvoice()) {
            $result['status'] = 'failed';
            $result['message'] = Mage::helper('sales')->__('Cannot do invoice for order.');

            return $result;
        }

        $invoice = $order->prepareInvoice($itemsQty);

        $invoice->register();

        if ($comment !== null) {
            $invoice->addComment($comment, $email);
        }

        if ($email) {
            $invoice->setEmailSent(true);
        }

        $invoice->getOrder()->setIsInProcess(true);

        try {
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();

            $invoice->sendEmail($email, ($includeComment ? $comment : ''));

            $result['status'] = 'success';
            $result['invoice_increment_id'] = $invoice->getIncrementId();

        } catch (Mage_Core_Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Capture invoice
     *
     * @param string $invoiceIncrementId
     * @return array
     */
    public function captureInvoice($invoiceIncrementId)
    {
        $result = array();
        $invoice = Mage::getModel('sales/order_invoice')->loadByIncrementId($invoiceIncrementId);

        /* @var $invoice Mage_Sales_Model_Order_Invoice */

        if (!$invoice->getId()) {
            $result['status'] = 'failed';
            $result['message'] = 'invoice_not_exists';

            return $result;
        }

        if (!$invoice->canCapture()) {
            $result['status'] = 'failed';
            $result['message'] = Mage::helper('sales')->__('Invoice cannot be captured.');

            return $result;
        }

        try {
            $invoice->capture();
            $invoice->getOrder()->setIsInProcess(true);
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();

            $result['status'] = 'success';
            $result['message'] = Mage::helper('retailops_api')->__('Invoice is captured');

        } catch (Mage_Core_Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = $e->getMessage();
        } catch (Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = Mage::helper('sales')->__('Invoice capturing problem.');
        }

        return $result;
    }
}
