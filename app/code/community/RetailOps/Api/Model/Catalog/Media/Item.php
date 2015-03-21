<?php
/**
{license_text}
 */

/**
 * RetailOps media update item
 *
 * @method RetailOps_Api_Model_Resource_Catalog_Media_Item _getResource()
 * @method RetailOps_Api_Model_Resource_Catalog_Media_Item getResource()
 * @method int getProductId()
 * @method RetailOps_Api_Model_Catalog_Media_Item setProductId()
 * @method int getUnsetOtherMedia()
 * @method RetailOps_Api_Model_Catalog_Media_Item setUnsetOtherMedia()
 * @method string getMediaData()
 * @method RetailOps_Api_Model_Catalog_Media_Item setMediaData()
 *
 * @category    RetailOps
 * @package     RetailOps_Api
 */
class RetailOps_Api_Model_Catalog_Media_Item extends Mage_Core_Model_Abstract
{
    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('retailops_api/catalog_media_item');
    }
}
