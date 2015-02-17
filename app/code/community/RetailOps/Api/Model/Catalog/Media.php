<?php
/**
{license_text}
 */

class RetailOps_Api_Model_Catalog_Media extends Mage_Catalog_Model_Product_Attribute_Media_Api
{
    /**
     * Get products media data
     *
     * @param $product
     * @return array
     */
    public function getMediaData($product)
    {
        $gallery = $this->_getGalleryAttribute($product);

        $gallery->getBackend()->afterLoad($product);

        $galleryData = $product->getData(self::ATTRIBUTE_CODE);

        if (!isset($galleryData['images']) || !is_array($galleryData['images'])) {
            return array();
        }

        $result = array();
        foreach ($galleryData['images'] as &$image) {
            $result[] = $this->_imageToArray($image, $product);
        }

        return $result;
    }
}
