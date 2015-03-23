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
