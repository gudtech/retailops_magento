<?php
/**
The MIT License (MIT)

Copyright (c) 2015 Gud Technologies Incorporated (RetailOps by GüdTech)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
 */

class RetailOps_Api_Model_Catalog_Adapter_Attribute extends RetailOps_Api_Model_Catalog_Adapter_Abstract
{
    protected $_section = 'attribute';

    protected $_attributeSets;
    protected $_attributeSetGroups;
    protected $_attributes;
    protected $_simpleAttributes;
    protected $_sourceAttributes;
    protected $_multiSelectAttributes = array();
    protected $_attributeOptionCache;
    protected $_entityTypeId;
    protected $_newOptions = array();
    /**
     * Array of already processed attribute codes to avoid double save
     * @var array
     */
    protected $_wereProcessed = array();


    /*
     * Attributes to skip while unsetting missing attributes
     */
    protected $_systemAttributes = array('has_options', 'required_options', 'media_gallery');

    protected function _construct()
    {
        $this->_entityTypeId = Mage::getModel('eav/entity')->setType(Mage_Catalog_Model_Product::ENTITY)->getTypeId();
        $this->_attributeSets = $this->_getAttributeSets();
        $this->_attributeSetGroups = $this->_getAttributeSetGroups();
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
        if ($product->getId() &&
            (!isset($productData['unset_other_attribute']) || $productData['unset_other_attribute'])) {
            $this->_unsetOldData($productData, $product);
        }
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
            $values = $product->getData($code);
            if (array_search($code, $this->_multiSelectAttributes) !== false) {
                $values = explode(',', $values);
            }
            $values = (array) $values;
            $data[$code] = array();
            /**
             * Return array for multiselect options and string for select options
             */
            foreach ($values as $value) {
                $optionLabel = array_search($value, $this->_attributeOptionCache[$code]);
                if ($optionLabel) {
                    $data[$code][] = $optionLabel;
                } else {
                    $data[$code][] = $value;
                }
            }
            if (count($data[$code]) == 1) {
                $data[$code] = $data[$code][0];
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
     * @return array
     */
    protected function _getAttributeSetGroups()
    {
        /** @var $attributeSetCollection Mage_Eav_Model_Resource_Entity_Attribute_Group_Collection */
        $groupsCollection = Mage::getResourceModel('eav/entity_attribute_group_collection');
        $groups = array();
        foreach ($groupsCollection as $group) {
            if (!isset($groups[$group->getAttributeSetId()])) {
                $groups[$group->getAttributeSetId()] = array();
            }
            $groups[$group->getAttributeSetId()][$group->getAttributeGroupName()] = $group->getId();
        }

        return $groups;
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
        $this->_multiSelectAttributes = array();
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
                if ($attribute->getFrontendInput() === 'multiselect') {
                    $this->_multiSelectAttributes[] = $attribute->getAttributeCode();
                }
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
                    $attributeSetId = $this->_createAttributeSet($attributeSet, $data['sku']);
                } catch (Mage_Api_Exception $e) {
                    $this->_throwException($e->getCustomMessage(), 'cant_create_attribute_set', $data['sku']);
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
    protected function _createAttributeSet($name, $sku)
    {
        $defaultAttributeSetId = $this->getHelper()->getConfig('catalog/default_attribute_set');
        if (!$defaultAttributeSetId) {
            $this->_throwException('Default attribute set is not set', 'default_attribute_set_not_set', $sku);
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
        $tolower = function_exists('mb_strtolower') ? 'mb_strtolower' : 'strtolower';

        return array_search($tolower($set), array_map($tolower, $this->_attributeSets));
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
                    if (!in_array($attributeData['attribute_code'], $this->_wereProcessed)) {
                        if ($attributeId !== false) {
                            if ($attributeData['update_if_exists']) {
                                Mage::dispatchEvent('retailops_catalog_attribute_update_before',
                                    array('attribute_data' => $attribute));
                                $attributeApi->update($attributeData['attribute_code'], $attribute->getData());
                                Mage::dispatchEvent('retailops_catalog_attribute_update_after',
                                    array('attribute_data' => $attribute));
                            }
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
                        $this->_wereProcessed[] = $attributeData['attribute_code'];
                    }
                    $attributeGroup = $attributeData['group_name'];
                    $attributeGroupId = null;
                    if (isset($this->_attributeSetGroups[$attributeSetId][$attributeGroup])) {
                        $attributeGroupId = $this->_attributeSetGroups[$attributeSetId][$attributeGroup];
                    }
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
        return array_search($attributeCode, $this->_getAttributes());
    }

    /**
     * @return mixed
     */
    protected function _getAttributes()
    {
        return $this->_simpleAttributes + $this->_sourceAttributes;
    }

    /**
     * @param $productData
     * @param bool $collectOptions
     */
    protected function _processAttributes(&$productData, $collectOptions = false)
    {
        if (isset($productData['attributes'])) {
            foreach ($productData['attributes'] as $attributeData) {
                $code = $attributeData['attribute_code'];
                $attributeId = array_search($code, $this->_sourceAttributes);
                if ($attributeId !== false && isset($attributeData['value'])) {
                    $values = (array) $attributeData['value'];
                    if ($collectOptions) {
                        /**
                         * Collect missing options to add
                         */
                        foreach ($values as $value) {
                            if (!isset($this->_attributeOptionCache[$code][$value])) {
                                $this->_newOptions[$attributeId][] = $value;
                                $this->_attributeOptionCache[$code][$value] = true;
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
                } elseif (isset($attributeData['value'])) {
                    $productData[$code] = $attributeData['value'];
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

    /**
     * Unset attributes which are not passed in the API call
     *
     * @param array $productData
     * @param Mage_Catalog_Model_Product $product
     */
    protected function _unsetOldData(array &$productData, $product)
    {
        $usedAttributes = array();
        if (isset($data['attributes'])) {
            foreach ($data['attributes'] as $attributeData) {
                $usedAttributes[] = $attributeData['attribute_code'];
            }
        }
        $usedAttributes = array_merge(array_keys($productData), $usedAttributes);
        $origDataKeys = array_keys($product->getOrigData());
        $origDataAttributeKeys = array_intersect($origDataKeys, $this->_getAttributes());
        $keysToUnset = array_diff($origDataAttributeKeys, $usedAttributes, $this->_systemAttributes);

        foreach ($keysToUnset as $key) {
            $product->setData($key, null);
        }
    }
}
