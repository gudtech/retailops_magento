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

class RetailOps_Api_Model_Catalog_Adapter_Configurable extends RetailOps_Api_Model_Catalog_Adapter_Abstract
{
    protected $_section             = 'configurable';

    protected $_associations        = array();
    protected $_configurableOptions = array();

    protected function _construct()
    {
        $this->_errorCodes = array(
            'cant_save_configurable_data' => 301,
        );
        parent::_construct();
    }

    /**
     * @param array $productData
     * @param Mage_Catalog_Model_Product $product
     * @return mixed|void
     */
    public function processData(array &$productData, $product)
    {
        $sku = $productData['sku'];
        if (isset($productData['configurable_sku'])) {
            $this->_associations[$sku] = $productData['configurable_sku'];
        }

        if (isset($productData['price_changes'])) {
            $this->_configurableOptions[$sku] = $productData['price_changes'];
        }
    }

    /**
     * @param array $skuToIdMap
     * @return $this|void
     */
    public function afterDataProcess(array &$skuToIdMap)
    {
        $failedSkus = array();
        $idsToReindex = array();
        if (!empty($this->_associations)) {
            $parentChildIds = array();
            $disassociateIds = array();
            foreach ((array) $this->_associations as $sku => $parentSkus) {
                $childProductId = $skuToIdMap[$sku];
                if ($parentSkus) {
                    foreach ($parentSkus as $parentSku) {
                        if (isset($skuToIdMap[$parentSku])) {
                            $parentProductId = $skuToIdMap[$parentSku];
                            if (!isset($parentChildIds[$parentProductId])) {
                                $parentChildIds[$parentProductId]['add'] = array();
                            }
                            $parentChildIds[$parentProductId]['add'][] = $childProductId;
                        } else {
                            $failedSkus[$sku] = sprintf('Parent product "%s" not found', $parentSku);
                        }
                    }
                } else {
                    $disassociateIds[] = $childProductId;
                }
            }
            foreach ($disassociateIds as $disassociateId) {
                $parents = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($disassociateId);
                foreach ($parents as $parentId) {
                    if (!isset($parentChildIds[$parentId])) {
                        $parentChildIds[$parentId]['remove'] = array();
                    }
                    $parentChildIds[$parentId]['remove'][] = $disassociateId;
                }
            }
            foreach ($parentChildIds as $parentId => $childIds) {
                try {
                    /** @var Mage_Catalog_Model_Product $configurable */
                    $configurable = Mage::getModel('catalog/product')->load($parentId);
                    if ($configurable->getTypeId() !== Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE) {
                        Mage::throwException('Product is not configurable');
                    }
                    $assignedProducts = $configurable->getTypeInstance()->getUsedProductIds($configurable);
                    if (!empty($childIds['add'])) {
                        $assignedProducts = array_merge($assignedProducts, $childIds['add']);
                    }
                    if (!empty($childIds['remove'])) {
                        $assignedProducts = array_diff($assignedProducts, $childIds['remove']);
                    }
                    Mage::getResourceModel('catalog/product_type_configurable')
                                            ->saveProducts($configurable, $assignedProducts);
                    $idsToReindex[] = $parentId;
                } catch (Exception $e) {
                    $failedSkus[$configurable->getSku()] = $e->getMessage();
                }
            }
        }
        if (isset($this->_configurableOptions)) {
            /** @var RetailOps_Api_Model_Catalog_Adapter_Attribute $attributesAdapter */
            $attributesAdapter = $this->_api->getAdapter('attributes');
            $allOptions =  $attributesAdapter->getAttributeOptions();
            foreach ($this->_configurableOptions as $sku => $configurableAttributes) {
                try {
                    $productId = $skuToIdMap[$sku];
                    $configurable = Mage::getModel('catalog/product')->load($productId);
                    if ($configurable->getTypeId() !== Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE) {
                        Mage::throwException('Product is not configurable');
                    }
                    $productType = $configurable->getTypeInstance();
                    $productType->setProduct($configurable);
                    $usedAttributes = array();
                    foreach ($configurableAttributes as $attributeCode => $attribute) {
                        $attributeId = $attributesAdapter->findAttribute($attributeCode);
                        if ($attributeId === false) {
                            Mage::throwException(sprintf('Attribute "%s" not found', $attributeCode));
                        }
                        /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
                        $attribute = Mage::getResourceModel('catalog/eav_attribute')->load($attributeId);
                        $isInSet = Mage::getResourceModel('eav/entity_attribute_set')->getSetInfo(array($attributeId),
                            $configurable->getAttributeSetId());
                        if (!$isInSet[$attributeId]
                            || !$productType->canUseAttribute($attribute)) {
                            Mage::throwException(sprintf('Attribute "%s" is not assigned to attribute set or cannot be used for configurable products', $attributeCode));
                        }
                        $usedAttributes[] = $attributeId;
                    }
                    $configurableAttributesData = $productType->getConfigurableAttributesAsArray();
                    if (!$configurableAttributesData) {
                        $productType->setUsedProductAttributeIds($usedAttributes);
                        $configurableAttributesData = $productType->getConfigurableAttributesAsArray();
                    } else {
                        foreach ($configurableAttributesData as $key => $attributeData) {
                            if (!in_array($attributeData['attribute_id'], $usedAttributes)) {
                                unset($configurableAttributesData[$key]);
                            }
                        }
                    }
                    foreach ($configurableAttributesData as &$attributeData) {
                        if (isset($configurableAttributes[$attributeData['attribute_code']])) {
                            $attribute = $configurableAttributes[$attributeData['attribute_code']];
                        } else {
                            $attribute = array();
                        }
                        $attributeData['label'] = isset($attribute['label']) ? $attribute['label'] : $attributeData['frontend_label'];
                        $attributeData['position'] = isset($attribute['position']) ? $attribute['position'] : 0;
                        if (isset($attribute['options'])) {
                            foreach ($attribute['options'] as $option => $priceChange) {
                                if (isset($allOptions[$attributeCode][$option])) {
                                    $optionId = $allOptions[$attributeCode][$option];
                                    $isPercent = 0;
                                    if (false !== strpos($priceChange, '%')) {
                                        $isPercent = 1;
                                    }
                                    $priceChange = preg_replace('/[^0-9\.,-]/', '', $priceChange);
                                    $priceChange = (float) str_replace(',', '.', $priceChange);
                                    $attributeData['values'][$optionId] = array(
                                        'value_index'   => $optionId,
                                        'is_percent'    => $isPercent,
                                        'pricing_value' => $priceChange,
                                    );
                                }
                            }
                        }
                    }
                    $configurable->setConfigurableAttributesData($configurableAttributesData);
                    $configurable->save();
                    $idsToReindex[] = $configurable->getId();
                    $configurable->clearInstance();
                } catch (Exception $e) {
                    $failedSkus[$configurable->getSku()] = $e->getMessage();
                }
            }
        }
        if ($idsToReindex) {
            $this->getHelper()->reindexProducts(array_unique($idsToReindex),
                Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE);
        }
        if ($failedSkus) {
            $finalMessage = array();
            foreach ($failedSkus as $sku => $message) {
                $finalMessage[] = sprintf('sku: "%s", error: "%s"', $sku, $message);
            }
            $finalMessage = implode('; ', $finalMessage);
            $this->_throwException('Configurable data is not saved for: ' . $finalMessage,
                'cant_save_configurable_data');
        }
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function outputData($product)
    {
        $data = array();

        if ($product->getTypeId() !== Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE) {
            return array();
        }
        $productType = $product->getTypeInstance(true);
        $productType->setProduct($product);
        $children = $productType->getChildrenIds($product->getId());
        if ($children) {
            $children = $children[0];
        }
        $associations = $this->_getResource()->getSkuByProductIds($children);

        $data['associations'] = $associations;

        $attributesData = $productType->getConfigurableAttributesAsArray();
        $priceChanges = array();
        foreach ($attributesData as $attributeData) {
            $options = array();
            foreach ($attributeData['values']  as $value) {
                $options[$value['label']] = floatval($value['pricing_value']) . ($value['is_percent'] ? '%' : '');
            }
            $priceChanges[$attributeData['attribute_code']] = array(
                'label'    => $attributeData['label'],
                'position' => $attributeData['position'],
                'options'  => $options
            );
        }

        $data['price_changes'] = $priceChanges;

        return $data;
    }
}
