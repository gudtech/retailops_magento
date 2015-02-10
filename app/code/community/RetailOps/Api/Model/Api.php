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
     * Get Products
     *
     * @param array $productData
     * @return array
     */
    public function inventoryPush($productData = null){
        return Mage::getModel('retailops_api/inventory_api')->inventoryPush($productData);
    }
}
