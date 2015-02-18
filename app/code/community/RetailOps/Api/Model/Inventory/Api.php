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
     * @param array $itemData
     * @return array
     */
    public function inventoryPush($itemData)
    {
        $response = array();
        $orderItems = $this->getRetailopsReadyOrderItems();

        foreach ($itemData as $item) {

            $recordObj = new Varien_Object($item);

            Mage::dispatchEvent(
                'retailops_inventory_push_record',
                array('record' => $recordObj)
            );

            $result = array();
            $result['sku'] = $item['sku'];
            try {
                $item['qty'] = $item['quantity'];
                $qty = $item['qty'];
                foreach ($orderItems as $orderItem) {
                    if ($orderItem->getSku() === $orderItem->getSku()) {
                        $qty -= $orderItem->getQtyOrdered();
                    }
                }
                $item['qty'] = $qty;
                $this->update($item['sku'], $item);
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

        return $response;
    }

    /**
     * Gets Order Items For Orders with retailops_ready status
     *
     * @return Mage_Sales_Order_Item_Collection
     */
    public function getRetailopsReadyOrderItems()
    {
        $collection = Mage::getModel('sales/order_item')->getCollection()
            ->addFieldToFilter('product_type', array(array('eq' => Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)));

        $collection->getSelect()
            ->join(
                array('orders' => 'sales_flat_order'),
                'orders.entity_id = main_table.order_id',
                array('orders.retailops_status')
            );
         $collection->addFieldToFilter('orders.retailops_status', array(array('eq' => self::RETAILOPS_ORDER_STATUS_READY)));
        return $collection;
    }

}
