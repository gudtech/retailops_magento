<?php
/**
{license_text}
 */

class RetailOps_Api_Adminhtml_Sales_OrderController extends Mage_Adminhtml_Sales_OrderController
{

    /**
     * Add order comment action
     */
    public function saveRetailOpsInfoAction()
    {
        if ($order = $this->_initOrder()) {
            try {
                $response = false;
                $data = $this->getRequest()->getPost('retops');

                $order->setRetailopsStatus($data['status']);
                $order->save();

                $history = Mage::getModel('retailops_api/order_status_history')
                    ->setOrder($order)
                    ->setComment($data['comment'])
                    ->save();

                $this->loadLayout('empty');
                $this->renderLayout();
            }
            catch (Mage_Core_Exception $e) {
                $response = array(
                    'error'     => true,
                    'message'   => $e->getMessage(),
                );
            }
            catch (Exception $e) {
                $response = array(
                    'error'     => true,
                    'message'   => $this->__('Cannot add order history.')
                );
            }
            if (is_array($response)) {
                $response = Mage::helper('core')->jsonEncode($response);
                $this->getResponse()->setBody($response);
            }
        }
    }
}
