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
        $orderItems = Mage::getResourceModel('retailops_api/api')->getRetailopsReadyOrderItems();
        $orderItems = $this->filterOrderItems($orderItems);

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
                foreach ($orderItems as $orderItem) {
                    if ($orderItem->getSku() === $itemObj->getSku()) {
                        $qty -= $orderItem->getQtyOrdered();
                    }
                }
                $itemObj->setQty($qty);

                Mage::dispatchEvent(
                    'retailops_inventory_push_record_processed',
                    array('record' => $itemObj)
                );

                $this->update($itemObj->getSku(), $itemObj->getData());
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
     * @return Mage_Sales_Model_Resource_Order_Item_Collection
     */
    public function filterOrderItems(Mage_Sales_Model_Resource_Order_Item_Collection $collection)
    {
        foreach ($collection as $item) {
            $collection->removeItemByKey($item->getParentItemId());
        }

        return $collection;
    }

}
