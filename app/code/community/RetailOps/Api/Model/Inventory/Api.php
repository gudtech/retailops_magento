<?php
/**
{license_text}
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
        $response = array();
        $orderItemsCollection = Mage::getResourceModel('retailops_api/api')->getRetailopsReadyOrderItems();
        $orderItems = $this->filterOrderItems($orderItemsCollection);
        $productIds = $this->getProductIds($itemData);

        foreach ($itemData as $item) {
            try {
                $itemObj = new Varien_Object($item);

                Mage::dispatchEvent(
                    'retailops_inventory_push_record',
                    array('record' => $itemObj)
                );

                $result = array();
                $result['sku'] = $itemObj->getSku();

                $itemObj->setQty($itemObj->getQuantity()); // api update accepts qty not quantity parameter

                $qty = $itemObj->getQty();
                if (isset($orderItems[$itemObj->getSku()])) {
                    $qty = $itemObj->getQty() - $orderItems[$itemObj->getSku()];
                }
                $itemObj->setQty($qty);

                Mage::dispatchEvent(
                    'retailops_inventory_push_record_qty_processed',
                    array('record' => $itemObj)
                );

                $this->update($productIds[$itemObj->getSku()], $itemObj->getData());
                $result['status'] = 'success';
            } catch (Mage_Core_Exception $e) {
                $result['status'] = 'failed';
                $result['error'] = array(
                    'code'      => $e->getCode(),
                    'message'   => $e->getMessage()
                );
            }
            $response[] = $result;
        }

        return $response;
    }

    /**
     * Removes parent order items from collection
     *
     * @param $collection Mage_Sales_Model_Resource_Order_Item_Collection
     * @return array
     */
    public function filterOrderItems(Mage_Sales_Model_Resource_Order_Item_Collection $collection)
    {
        $result = array();

        /* remove parent items */
        foreach ($collection as $item) {
            $collection->removeItemByKey($item->getParentItemId());
        }

        /* calculate total ordered quantity per item */
        foreach ($collection as $item) {
            if (isset($result[$item->getSku()])){
                $result[$item->getSku()] += $item->getQtyOrdered();
            } else {
                $result[$item->getSku()] = $item->getQtyOrdered();
            }
        }

        return $result;
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
     * Removes parent order items from collection
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

        $records = Mage::getResourceModel('retailops_api/api')->getIdsByProductSkus($skus);

        foreach ($records as $record) {
            $result[$record['sku']] = $record['entity_id'];
        }

        return $result;
    }

}
