<?php
/**
{license_text}
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
       return Mage::getModel('retailops_api/catalog_api')->catalogPull($filters);
    }

    /**
     * Product Inventory Update
     *
     * @param array $itemData
     * @return array
     */
    public function inventoryPush($itemData = null){
        return Mage::getModel('retailops_api/inventory_api')->inventoryPush($itemData);
    }

    /**
     * Get Products
     *
     * @param mixed $filters
     * @return array
     */
    public function orderPull($filters = null){
        return Mage::getModel('retailops_api/order_api')->orderPull($filters);
    }

}
