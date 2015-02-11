<?php
/**
{license_text}
 */

class RetailOps_Api_Model_Inventory_Api extends Mage_CatalogInventory_Model_Stock_Item_Api
{
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
        $idBySku = $product->getIdBySku($productId);
        $productId = $idBySku ? $idBySku : $productId;

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
     * Update stock data of multiple products at once
     *
     * @param array $productData
     * @return array
     */
    public function inventoryPush($productData)
    {
        Mage::dispatchEvent(
            'retailops_inventory_push_before',
            array('product_data' => $productData)
        );

        $response = array();
        $productData = (array)$productData;
        $orderItems = Mage::helper('retailops_api')->getRetailopsReadyOrderItems();

        foreach ($productData as $data) {
            try {
                $result = array();
                $data['qty'] = $data['quantity'];
                $result['sku'] = $data['sku'];
                foreach ($orderItems as $item) {
                    if ($data['sku'] === $item->getSku()) {
                        $data['qty'] -= $item->getQtyOrdered();
                    }
                }
                $this->update($data['sku'], $data);
                $result[]['status'] = 'success';
            } catch (Mage_Core_Exception $e) {
                $result['status'] = 'failed';
                $result['error'] = array(
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                );
            }
            $response[] = $result;
        }

        Mage::dispatchEvent(
            'retailops_inventory_push_after',
            array('product_data' => $productData, 'response' => $response)
        );

        return $response ;
    }
}
