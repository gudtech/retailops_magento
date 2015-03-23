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

class RetailOps_Api_Model_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Get Products
     *
     * @param mixed $filters
     * @return array
     */
    public function catalogPull($filters = null){
       return Mage::getModel('retailops_api/catalog_pull_api')->catalogPull($filters);
    }

    /**
     * Create/update Products
     *
     * @param mixed $productsData
     * @return array
     */
    public function catalogPush($productsData){
       return Mage::getModel('retailops_api/catalog_push_api')->catalogPush($productsData);
    }

    /**
     * Product Inventory Update
     *
     * @param array $itemData
     * @return array
     */
    public function inventoryPush($itemData){
        return Mage::getModel('retailops_api/inventory_api')->inventoryPush($itemData);
    }

    /**
     * create Credit Memo
     *
     * @param mixed $returns
     * @return array
     */
    public function returnPush($returns){
        return Mage::getModel('retailops_api/return_api')->returnPush($returns);
    }

    /**
     * Get orders
     *
     * @param mixed $filters
     * @return array
     */
    public function orderPull($filters = null){
        return Mage::getModel('retailops_api/order_api')->orderPull($filters);
    }

    /**
     * Update retailops order status
     *
     * @param $ordersData
     * @return mixed
     */
    public function orderStatusUpdate($ordersData){
        return Mage::getModel('retailops_api/order_api')->orderStatusUpdate($ordersData);
    }

    /**
     * Create shipments
     *
     * @param mixed $shipments
     * @return array
     */
    public function shipmentPush($shipments){
        return Mage::getModel('retailops_api/shipment_api')->shipmentPush($shipments);
    }

    /**
     * Close order
     *
     * @param $ordersData
     * @return mixed
     */
    public function orderClose($ordersData){
        return Mage::getModel('retailops_api/shipment_api')->orderClose($ordersData);
    }
}
