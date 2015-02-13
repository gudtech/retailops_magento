<?php
/**
{license_text}
 */

class RetailOps_Api_Model_Inventory_Api extends Mage_CatalogInventory_Model_Stock_Item_Api
{

    const RETAILOPS_ORDER_STATUS_READY = 'retailops_ready';

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
        $helper = Mage::helper('retailops_api');
        $productCollection = $helper->getVarienDataCollection($productData);

        Mage::dispatchEvent(
            'retailops_inventory_push_before',
            array('products' => $productCollection)
        );

        $response = array();
        $orderItems = $this->getRetailopsReadyOrderItems();

        foreach ($productCollection as $product) {
            $result = array();
            $result['sku'] = $product->getSku();
            try {
                $product->setQty($product->getQuantity());
                $qty = $product->getQty();
                foreach ($orderItems as $item) {
                    if ($product->getSku() === $item->getSku()) {
                        $qty -= $item->getQtyOrdered();
                    }
                }
                $product->setQty($qty);
                $this->update($product->getSku(), $product->getData());
                $result['status'] = 'success';
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
            array('products' => $productCollection, 'responses' => $helper->getVarienDataCollection($response))
        );

        return $response;
    }

    /**
     * Gets Order Items For Orders with retailops_ready status
     *
     * @return array
     */
    public function getRetailopsReadyOrderItems()
    {
        $items = array();
        $orderCollection = Mage::getModel('sales/order')->getCollection()
            ->addFieldToFilter('retailops_status', self::RETAILOPS_ORDER_STATUS_READY);
        foreach ($orderCollection as $order) {
            foreach ($order->getAllItems() as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

}
