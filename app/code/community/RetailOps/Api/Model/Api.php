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
     * creates Credit Memo
     *
     * @param mixed $returns
     * @return array
     */
    public function returnPush($returns = null){
        return Mage::getModel('retailops_api/return_api')->returnPush($returns);
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
