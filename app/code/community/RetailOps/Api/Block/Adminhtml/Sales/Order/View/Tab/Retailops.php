<?php
/**
{license_text}
 */

class RetailOps_Api_Block_Adminhtml_Sales_Order_View_Tab_Retailops
    extends Mage_Adminhtml_Block_Sales_Order_Abstract
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('retailops/order/view/tab/retailops.phtml');
    }

    protected function _prepareLayout()
    {
        $onclick = "submitAndReloadArea($('retops-info').parentNode, '" . $this->getSubmitUrl() . "')";
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'label'   => Mage::helper('sales')->__('Submit'),
                'class'   => 'save',
                'onclick' => $onclick
            ));
        $this->setChild('submit_button', $button);
        return parent::_prepareLayout();
    }

    /**
     * Gets submit url for retail ops order status
     *
     * @return string
     */
    public function getSubmitUrl()
    {
        return $this->getUrl('*/*/saveRetailOpsInfo', array('order_id' => $this->getOrder()->getId()));
    }

    /**
     * Gets Retail Ops order statuses
     *
     * @return mixed
     */
    public function getStatuses()
    {
        return Mage::helper('retailops_api')->getRetOpsStatuses();
    }

    /**
     * Gets Current Order
     *
     * @return Mage_Sales_Model_Order|mixed
     */
    public function getOrder()
    {
        return Mage::registry('current_order');
    }

    /**
     * Gets Retail Ops Order Status History
     *
     * @return mixed
     */
    public function getRetailOpsStatusHistory()
    {
        return Mage::getModel('retailops_api/order_status_history')->getRetailOpsStatusHistory($this->getOrder());
    }

    /**
     * ######################## TAB settings #################################
     */
    public function getTabLabel()
    {
        return Mage::helper('retailops_api')->__('RetailOps');
    }

    public function getTabTitle()
    {
        return Mage::helper('retailops_api')->__('RetailOps');
    }

    public function canShowTab()
    {
        return true;
    }

    public function isHidden()
    {
        return false;
    }
}
