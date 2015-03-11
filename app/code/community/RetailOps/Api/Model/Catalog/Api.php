<?php
/**
{license_text}
 */

class RetailOps_Api_Model_Catalog_Api extends Mage_Catalog_Model_Product_Api
{
    /** @var  array */
    protected $_dataAdapters;

    /**
    * Constructor. Initializes default values.
    */
    public function __construct()
    {
        $attributeAdapter = new RetailOps_Api_Model_Catalog_Adapter_Attribute();
        $this->_dataAdapters = array(
            $attributeAdapter,
            new RetailOps_Api_Model_Catalog_Adapter_Category(),
            new RetailOps_Api_Model_Catalog_Adapter_Media(),
            new RetailOps_Api_Model_Catalog_Adapter_Option(),
            new RetailOps_Api_Model_Catalog_Adapter_Configurable(array('attributes' => $attributeAdapter)),
            new RetailOps_Api_Model_Catalog_Adapter_Tag(),
            new RetailOps_Api_Model_Catalog_Adapter_Default(),
            new RetailOps_Api_Model_Catalog_Adapter_Link(),
        );
    }

    /**
     * @return RetailOps_Api_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('retailops_api');
    }

    /**
     * @return RetailOps_Api_Model_Resource_Api
     */
    protected function _getResource()
    {
        return Mage::getResourceModel('retailops_api/api');
    }
}
