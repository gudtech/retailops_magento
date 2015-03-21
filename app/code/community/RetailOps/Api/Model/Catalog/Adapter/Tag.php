<?php
/**
{license_text}
 */

class RetailOps_Api_Model_Catalog_Adapter_Tag extends RetailOps_Api_Model_Catalog_Adapter_Abstract
{
    protected $_section  = 'tags';

    /**
     * @param array $productData
     * @param Mage_Catalog_Model_Product $product
     * @return mixed|void
     */
    public function processData(array &$productData, $product)
    {
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function outputData($product)
    {
        $data = array();
        $data['tags'] = $this->_getTagsData($product->getId());

        return $data;
    }

     /**
     * Get products tags data
     *
     * @param $productId
     * @return array
     */
    protected function _getTagsData($productId)
    {
        $data = array();
        $tags = Mage::getModel('tag/tag')->getCollection()->addProductFilter($productId)->addPopularity();
        /** @var $tag Mage_Tag_Model_Tag */
        foreach ($tags as $tag) {
            $result = array();
            $result['status'] = $tag->getStatus();
            $result['name'] = $tag->getName();
            $result['base_popularity'] = (is_numeric($tag->getBasePopularity())) ? $tag->getBasePopularity() : 0;
            $data[] = $result;
        }

        return $data;
    }
}
