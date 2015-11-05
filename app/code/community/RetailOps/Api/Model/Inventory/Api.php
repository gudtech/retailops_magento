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

class RetailOps_Api_Model_Inventory_Api extends Mage_CatalogInventory_Model_Stock_Item_Api
{
    /**
     * Update stock data of multiple products at once
     *
     * @param array $itemData
     * @return array
     */
    public function inventoryPush($itemData)
    {
        if (isset($itemData['records'])) {
            $itemData = $itemData['records'];
        }
        $response = array();
        $response['records'] = array();
        $resourceModel = Mage::getResourceModel('retailops_api/api');
        $orderQtys = $resourceModel->getRetailopsNonretrievedQtys();
        $productIds = $this->getProductIds($itemData);

        foreach ($itemData as $item) {
            try {
                Mage::dispatchEvent(
                    'retailops_inventory_push_record',
                    array('record' => $stockObj)
                );

                $stockObj = $resourceModel->subtractNonretrievedQtys($orderQtys, $item);

                Mage::dispatchEvent(
                    'retailops_inventory_push_record_qty_processed',
                    array('record' => $stockObj)
                );

                $this->update($productIds[$stockObj->getSku()], $stockObj->getData());

                $result['status'] = RetailOps_Api_Helper_Data::API_STATUS_SUCCESS;
            } catch (Mage_Core_Exception $e) {
                $result['status'] = RetailOps_Api_Helper_Data::API_STATUS_FAIL;
                $result['error'] = array(
                    'code'      => $e->getCode(),
                    'message'   => $e->getMessage()
                );
            }
            $response['records'][] = $result;
        }

        return $response;
    }

    /**
     * Update product stock data
     *
     * @param int   $productId
     * @param array $data
     * @return bool
     */
    public function update($productId, $data)
    {
        /** @var $product Mage_Catalog_Model_Product */
        $product = Mage::getModel('catalog/product');

        $product->setStoreId($this->_getStoreId())
            ->load($productId);

        if (!$product->getId()) {
            $this->_fault('not_exists');
        }

        /** @var $stockItem Mage_CatalogInventory_Model_Stock_Item */
        $stockItem = $product->getStockItem();
        $stockData = array_replace($stockItem->getData(), (array)$data);
        $stockItem->setData($stockData);

        try {
            $stockItem->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('not_updated', $e->getMessage());
        }

        return true;
    }

    /**
     *
     *
     * @param $data array
     * @return array
     */
    public function getProductIds($data)
    {
        $skus = array();

        foreach ($data as $item) {
            $skus[] = $item['sku'];
        }

        $result = Mage::getResourceModel('retailops_api/api')->getIdsByProductSkus($skus);

        return $result;
    }
}
