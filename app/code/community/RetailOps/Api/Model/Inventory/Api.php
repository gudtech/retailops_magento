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

                Mage::getModel('cataloginventory/stock_item_api_v2')->update($itemObj->getSku(), $itemObj->getData());
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
            $result[$item->getSku()] += $item->getQtyOrdered();
        }

        return $result;
    }

}
