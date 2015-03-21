<?php
/**
{license_text}
 */

/**
 * @category    RetailOps
 * @package     RetailOps_Api
 */
class RetailOps_Api_Model_Resource_Catalog_Media_Item extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Model initialization
     */
    protected function _construct()
    {
        $this->_init('retailops_api/media_import', 'entity_id');
    }
}
