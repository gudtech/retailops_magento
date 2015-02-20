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
                    'retailops_shipment_push_record',
                    array('record' => $shipment)
                );

                $result[$count]['order_increment_id'] = $shipment->getOrderIncrementId();

                $orderIncrementId = $shipment->getOrderIncrementId();
                $shipmentInfo = $shipment->getShipment();
                $trackInfo = $shipment->getTrack();
                $invoiceInfo = $shipment->getInvoice();

                // create shipment
                try {
                    $shipmentResult = array();
                    $shipmentIncrementId = $this->create($orderIncrementId,
                        $shipmentInfo['items_qty'],
                        $shipmentInfo['comment'],
                        $shipmentInfo['email'],
                        $shipmentInfo['include_comment']
                    );

                    if ($shipmentIncrementId) {
                        $shipmentResult['status'] = 'success';
                        $shipmentResult['shipment_increment_id'] = $shipmentIncrementId;
                    } else {
                        $shipmentResult['status'] = 'failed';
                        $shipmentResult['message'] = Mage::helper('retailops_api')->__('Can not create shipment');
                    }
                } catch (Mage_Core_Exception $e) {
                    $shipmentResult['status'] = 'failed';
                    $shipmentResult['message'] = $e->getMessage();
                }

                $result[$count]['shipment_result'] = $shipmentResult;

                // add shipment track
                try {
                    $trackResult = array();

                    if (isset($shipmentResult['shipment_increment_id'])) {
                        $trackId = $this->addTrack($shipmentResult['shipment_increment_id'],
                            $trackInfo['carrier'],
                            $trackInfo['title'],
                            $trackInfo['track_number']
                        );

                        $trackResult['status'] = 'success';
                        $trackResult['track_id'] = $trackId;

                    } else {
                        $trackResult['status'] = 'failed';
                        $trackResult['message'] = Mage::helper('retailops_api')->__('Can not add track to the shipment');
                    }
                } catch (Mage_Core_Exception $e) {
                    $trackResult['status'] = 'failed';
                    $trackResult['message'] = $e->getMessage();
                }

                $result[$count]['track_result'] = $trackResult;

                // create invoice
                try {
                    $invoiceIncrementId = Mage::getModel('sales/order_invoice_api')->create(
                        $orderIncrementId,
                        $shipmentInfo['items_qty'],    // invoice the items to be shipped
                        $invoiceInfo['comment'],
                        $invoiceInfo['email'],
                        $invoiceInfo['include_comment']
                    );

                    $invoiceResult['status'] = 'success';
                    $invoiceResult['invoice_increment_id'] = $invoiceIncrementId;

                } catch (Mage_Core_Exception $e) {
                    $invoiceResult['status'] = 'failed';
                    $invoiceResult['message'] = $e->getMessage();
                }

                $result[$count]['invoice_result'] = $invoiceResult;

                // capture invoice
                try {
                    $invoiceCaptured = Mage::getModel('sales/order_invoice_api')
                        ->capture($invoiceResult['invoice_increment_id']);
                    if ($invoiceCaptured) {
                        $invoiceCaptureResult['status'] = 'success';
                        $invoiceCaptureResult['message'] = Mage::helper('retailops_api')->__('Invoice is captured');
                    } else {
                        $invoiceCaptureResult['status'] = 'failed';
                        $invoiceCaptureResult['message'] = Mage::helper('retailops_api')->__('Invoice cannot be captured');
                    }
                } catch (Mage_Core_Exception $e) {
                    $invoiceCaptureResult['status'] = 'failed';
                    $invoiceCaptureResult['message'] = $e->getMessage();
                }
                $result[$count]['invoice_capture_result'] = $invoiceCaptureResult;

            } catch (Mage_Core_Exception $e) {
                $result[$count]['status'] = 'failed';
                $result[$count]['message'] = Mage::helper('retailops_api')->__('Cannot Create Shipment');
            }

            $count++;
        }

        return $result;
    }
}
