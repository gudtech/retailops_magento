<?php
/**
{license_text}
 */

class RetailOps_Api_Model_System_Config_Source_Catalog_AttributeSet
{
    protected $_options;

    public function toOptionArray()
    {
        if (!$this->_options) {
            $entityType = Mage::getModel('eav/entity')->setType(Mage_Catalog_Model_Product::ENTITY)->getTypeId();
            $collection = Mage::getResourceModel('eav/entity_attribute_set_collection')
                ->setEntityTypeFilter($entityType);
            $this->_options = $collection->load()->toOptionArray();
        }

        return $this->_options;
    }
}
