<?php
/**
{license_text}
 */

/**
 * @category    RetailOps
 * @package     RetailOps_Api
 */
class RetailOps_Api_Model_Resource_Catalog_Media_Item_Collection
    extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Model initialization
     */
    protected function _construct()
    {
        $this->_init('retailops_api/catalog_media_item');
    }
}
