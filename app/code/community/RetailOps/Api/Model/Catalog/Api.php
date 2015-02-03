<?php
/**
{license_text}
 */

class RetailOps_Api_Model_Catalog_Api extends Mage_Catalog_Model_Product_Api
{
    /**
     * Get Products
     *
     * @param  mixed $filters
     * @return array
     */
    public function catalogPull($filters = null)
    {
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addStoreFilter($this->_getStoreId($store))
            ->addAttributeToSelect('name');

        /** @var $apiHelper Mage_Api_Helper_Data */
        $apiHelper = Mage::helper('api');
        $filters = $apiHelper->parseFilters($filters, $this->_filtersMap);
        try {
            foreach ($filters as $field => $value) {
                $collection->addFieldToFilter($field, $value);
            }
        } catch (Mage_Core_Exception $e) {
            $this->_fault('filters_invalid', $e->getMessage());
        }
        $result = array();
        foreach ($collection as $product) {
            $result[] = array(
                'product_id' => $product->getId(),
                'sku'        => $product->getSku(),
                'name'       => $product->getName(),
                'set'        => $product->getAttributeSetId(),
                'type'       => $product->getTypeId(),
                'category_ids' => $product->getCategoryIds(),
                'website_ids'  => $product->getWebsiteIds()
            );
        }

        return $result;
    }
}
