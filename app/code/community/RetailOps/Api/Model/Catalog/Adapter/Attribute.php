<?php
/**
{license_text}
 */

class RetailOps_Api_Model_Catalog_Adapter_Attribute extends RetailOps_Api_Model_Catalog_Adapter_Abstract
{
    protected $_section = 'attribute';

    protected $_attributeSets;
    protected $_attributes;
    protected $_simpleAttributes;
    protected $_sourceAttributes;
    protected $_attributeOptionCache;
    protected $_entityTypeId;
    protected $_newOptions = array();

    protected function _construct()
    {
        $this->_entityTypeId = Mage::getModel('eav/entity')->setType(Mage_Catalog_Model_Product::ENTITY)->getTypeId();
        $this->_attributeSets = $this->_getAttributeSets();
        $this->_initAttributes();
        $this->_errorCodes = array(
            'cant_create_attribute_set'      => 101,
            'missing_attribute_set'          => 102,
            'default_attribute_set_not_set'  => 103,
            'error_processing_attribute'     => 104,
            'error_adding_attribute_options' => 105
        );

        parent::_construct();
    }

    /**
     * @param array $data
     * @return $this
     */
    public function prepareData(array &$data)
    {
        $this->_prepareAttributeSet($data);
        $this->_prepareAttributes($data);
        $this->_processAttributes($data, true);

        return $this;
    }

    /**
     * @return $this
     */
    public function afterDataPrepare()
    {
        $this->_addAttributeOptions($this->_newOptions);

        return $this;
    }

    /**
     * @param array $productData
     * @param Mage_Catalog_Model_Product $product
     * @return mixed|void
     */
    public function processData(array &$productData, $product)
    {
        $productData['attribute_set_id'] = $this->_getAttributeSetIdByName($productData['attribute_set']);
        $this->_processStaticAttributes($productData);
        $this->_processAttributes($productData);
    }

    /**
     * @return mixed
     */
    public function getSourceAttributes()
    {
        return $this->_sourceAttributes;
    }

    /**
     * @return mixed
     */
    public function getAttributeOptions()
    {
        return $this->_attributeOptionCache;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function outputData($product)
    {
        $data = array();
        $data['attribute_set'] = $this->_attributeSets[$product->getDefaultAttributeSetId()];
        foreach ($this->_simpleAttributes as $code) {
            $data[$code] = $product->getData($code);
        }

        foreach ($this->_sourceAttributes as $code) {
            $value = $product->getData($code);
            $optionLabel = array_search($value, $this->_attributeOptionCache[$code]);
            if ($optionLabel) {
                $data[$code] = $optionLabel;
            } else {
                $data[$code] = $value;
            }
        }

        return $data;
    }

     /**
     * @return Mage_Catalog_Model_Product_Attribute_Api
     */
    protected function _getProductAttributeApi()
    {
        return Mage::getModel('catalog/product_attribute_api');
    }

    /**
     * @return Mage_Catalog_Model_Product_Attribute_Set_Api
     */
    protected function _getProductAttributeSetApi()
    {
        return Mage::getModel('catalog/product_attribute_set_api');
    }

    /**
     * @return array
     */
    protected function _getAttributeSets()
    {
        /** @var $attributeSetCollection Mage_Eav_Model_Resource_Entity_Attribute_Set_Collection */
        $attributeSetCollection = Mage::getResourceModel('eav/entity_attribute_set_collection');
        $attributeSetCollection->setEntityTypeFilter($this->_entityTypeId);

        return $attributeSetCollection->toOptionHash();
    }

     /**
     * Init product attributes and attirubtes options
     */
    protected function _initAttributes()
    {
        /** @var $attributeCollection Mage_Catalog_Model_Resource_Product_Attribute_Collection */
        $attributeCollection = Mage::getResourceModel('catalog/product_attribute_collection');
        $attributeCollection->setItemObjectClass('catalog/resource_eav_attribute');
        $attributeCollection->addFieldToSelect('*');

        $this->_sourceAttributes = array();
        $this->_simpleAttributes = array();
        foreach ($attributeCollection as $attribute) {
            $attributeCode = $attribute->getAttributeCode();
            if ($this->_usesSource($attribute)) {
                /** @var Mage_Eav_Model_Entity_Attribute $attribute */
                $this->_attributeOptionCache[$attributeCode] =
                    $this->getHelper()->arrayToOptionHash(
                        $attribute->getSource()->getAllOptions(),
                        'label',
                        'value',
                        false
                    );
                $this->_sourceAttributes[$attribute->getId()] = $attributeCode;
            } else {
                $this->_simpleAttributes[$attribute->getId()] = $attributeCode;
            }
        }
    }

    /**
     * Check if attribute uses source
     *
     * @param $attribute
     * @return bool
     */
    protected function _usesSource($attribute)
    {
        return $attribute->getFrontendInput() === 'select' || $attribute->getFrontendInput() === 'multiselect'
            || $attribute->getData('source_model') != '';
    }

    /**
     * @param $data
     * @return mixed
     */
    protected function _prepareAttributeSet(array &$data)
    {
        if (!empty($data['attribute_set'])) {
            $attributeSet = $data['attribute_set'];
            $attributeSetId = $this->_getAttributeSetIdByName($attributeSet);
            if ($attributeSetId === false) {
                try {
                    $attributeSetId = $this->_createAttributeSet($attributeSet);
                } catch (Mage_Api_Exception $e) {
                    $this->_throwException($e->getCustomMessage(), 'cant_create_attribute_set');
                }
                $this->_attributeSets[$attributeSetId] = $attributeSet;
            }
        } else {
            $this->_throwException('Attribute set not provided', 'missing_attribute_set', $data['sku']);
        }

        $data['attribute_set_id'] = $attributeSetId;
    }

    /**
     * @param $name
     * @return mixed
     */
    protected function _createAttributeSet($name)
    {
        $defaultAttributeSetId = $this->getHelper()->getConfig('catalog/default_attribute_set');
        if (!$defaultAttributeSetId) {
            $this->_throwException('Default attribute set is not set', 'default_attribute_set_not_set');
        }

        $attributeSetId = $this->_getProductAttributeSetApi()->create($name, $defaultAttributeSetId);

        return $attributeSetId;
    }

    /**
     * @param $set
     * @return mixed
     */
    protected function _getAttributeSetIdByName($set)
    {
        return array_search($set, $this->_attributeSets);
    }

    /**
     * Create/update attributes. Assign them to the attribute set
     *
     * @param $data
     */
    protected function _prepareAttributes(array &$data)
    {
        $attributeSetId = $data['attribute_set_id'];
        $attributeApi = $this->_getProductAttributeApi();
        $attributeSetApi = $this->_getProductAttributeSetApi();
        if (isset($data['attributes'])) {
            foreach ($data['attributes'] as $attributeData) {
                try {
                    $attribute = new Varien_Object($attributeData);
                    $attributeId = $this->findAttribute($attributeData['attribute_code']);

                    if ($attributeId !== false) {
                        Mage::dispatchEvent('retailops_catalog_attribute_update_before',
                            array('attribute_data' => $attribute));
                        $attributeApi->update($attributeData['attribute_code'], $attribute->getData());
                        Mage::dispatchEvent('retailops_catalog_attribute_update_after',
                            array('attribute_data' => $attribute));
                    } else {
                        Mage::dispatchEvent('retailops_catalog_attribute_create_before',
                            array('attribute_data' => $attribute));
                        $attributeId = $attributeApi->create($attribute->getData());
                        Mage::dispatchEvent('retailops_catalog_attribute_create_after',
                            array('attribute_id' => $attributeId, 'attribute_data' => $attribute));
                        if ($this->_usesSource($attribute)) {
                            $this->_sourceAttributes[$attributeId] = $attribute->getData('attribute_code');
                        } else {
                            $this->_simpleAttributes[$attributeId] = $attribute->getData('attribute_code');
                        }
                    }
                    $attributeGroup = $attributeData['group_name'];
                    $attributeGroupId = null;
                    /**
                     * Try to add attribute set group, use the default group if failed
                     */
                    try {
                        $attributeGroupId = $attributeSetApi->groupAdd($attributeSetId, $attributeGroup);
                    } catch (Mage_Api_Exception $e) { }
                    $sortOrder = isset($attributeData['sort_order']) ? $attributeData['sort_order'] : 0;
                    try {
                        $this->_getProductAttributeSetApi()
                            ->attributeAdd($attributeId, $attributeSetId, $attributeGroupId, $sortOrder);
                    } catch (Mage_Api_Exception $e) {
                        if ($e->getMessage() !== 'attribute_is_already_in_set') {
                            throw new Mage_Api_Exception($e->getMessage(), $e->getCustomMessage());
                        }
                    }
                } catch (Mage_Api_Exception $e) {
                    $this->_throwException(
                        sprintf('Error while saving attribute %s, error message: %s', $attributeData['attribute_code'], $e->getMessage()),
                        'error_processing_attribute', $data['sku']);
                }
            }
        }
    }

    /**
     * @param $attributeCode
     * @return bool
     */
    public function findAttribute($attributeCode)
    {
        return array_search($attributeCode, $this->_simpleAttributes + $this->_sourceAttributes);
    }

    /**
     * @param $productData
     * @param bool $collectOptions
     */
    protected function _processAttributes(&$productData, $collectOptions = false)
    {
        $separator = $this->getHelper()->getConfig('multiple_select_options_separator');
        if (isset($productData['attributes'])) {
            foreach ($productData['attributes'] as $attributeData) {
                $code = $attributeData['attribute_code'];
                $attributeId = array_search($code, $this->_sourceAttributes);
                if ($attributeId !== false) {
                    if ($attributeData['frontend_input'] == 'multiselect') {
                        $values = explode($separator, $attributeData['value']);
                    } else {
                        $values = array($attributeData['value']);
                    }
                    if ($collectOptions) {
                        /**
                         * Collect missing options to add
                         */
                        foreach ($values as $value) {
                            if (!isset($this->_attributeOptionCache[$code][$value])) {
                                $this->_newOptions[$attributeId][] = $value;
                                $this->_attributeOptionCache[$code][$value] = null;
                            }
                        }
                    } else {
                        /**
                         * Collect attribute values
                         */
                        $valuesIds = array();
                        foreach ($values as $value) {
                            if (isset($this->_attributeOptionCache[$code][$value])) {
                                $valuesIds[] = $this->_attributeOptionCache[$code][$value];
                            }
                        }
                        if (count($valuesIds) == 1) {
                            $valuesIds = current($valuesIds);
                        }
                        $productData[$code] = $valuesIds;
                    }
                }
            }
        }
    }

    /**
     * @param $productData
     */
    protected function _processStaticAttributes(&$productData)
    {
        foreach ($productData as $code => $value) {
            $attributeId = array_search($code, $this->_sourceAttributes);
            if ($attributeId !== false) {
                if (isset($this->_attributeOptionCache[$code][$value])) {
                    $realValue = $this->_attributeOptionCache[$code][$value];
                    $productData[$code] = $realValue;
                }
            }
        }
    }

    /**
     * Add missing attribute options
     *
     * @param $newOptions
     */
    protected function _addAttributeOptions($newOptions)
    {
        foreach ($newOptions as $attributeId => $options) {
            $attribute = Mage::getResourceModel('catalog/eav_attribute')
                ->setEntityTypeId($this->_entityTypeId);
            $attribute->load($attributeId);
            $optionsData = array();
            foreach ($options as $key => $option) {
                $optionsData['value']['option_' . $key][0] = $option;
                $optionsData['order']['option_' . $key]    = 0;
            }
            $attribute->setData('option', $optionsData);
            try {
                $attribute->save();
                $this->_attributeOptionCache[$attribute->getAttributeCode()] =
                    $this->getHelper()->arrayToOptionHash(
                        $attribute->getSource()->getAllOptions(),
                        'label',
                        'value',
                        false
                    );
            } catch (Exception $e) {
                $this->_throwException(sprintf('Error saving attribute "%s" options, %s', $attribute->getAttributeCode(),
                    $e->getMessage()), 'error_adding_attribute_options');
            }
        }
    }
}
