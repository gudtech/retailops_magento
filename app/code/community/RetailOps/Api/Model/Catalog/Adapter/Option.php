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

class RetailOps_Api_Model_Catalog_Adapter_Option extends RetailOps_Api_Model_Catalog_Adapter_Abstract
{
    protected $_section     = 'custom_options';
    protected $_optionTypes = array();

    protected function _construct()
    {
        $this->_optionTypes = $this->_getOptionTypes();
        $this->_errorCodes = array(
            'invalid_options_data' => 701,
            'invalid_options_type' => 702,
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
        if (isset($productData['custom_options'])) {
            if ($product->getId()) {
                $currentOptions = $product->getOptions();
                foreach ($currentOptions as $option) {
                    $option->delete();
                }
            }
            $optionsData = $productData['custom_options'];
            if (!empty($optionsData)) {
                foreach ($optionsData as $optionData) {
                    $this->_addOption($product, $optionData);
                }
                $product->setHasOptions(true);
            } else {
                $product->setHasOptions(false);
            }
        }
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function outputData($product)
    {
        $data = array();
        $data['custom_options'] = $this->_getCustomOptions($product);

        return $data;
    }

    /**
     * Get product custom options data
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    protected function _getCustomOptions($product)
    {
        $data = array();
        foreach ($product->getProductOptionsCollection() as $option) {
            $result = array(
                'title' => $option->getTitle(),
                'type' => $option->getType(),
                'is_require' => $option->getIsRequire(),
                'sort_order' => $option->getSortOrder(),
                // additional_fields should be two-dimensional array for all option types
                'additional_fields' => array(
                    array(
                        'price' => $option->getPrice(),
                        'price_type' => $option->getPriceType(),
                        'sku' => $option->getSku()
                    )
                )
            );
            // Set additional fields to each type group
            switch ($option->getGroupByType()) {
                case Mage_Catalog_Model_Product_Option::OPTION_GROUP_TEXT:
                    $result['additional_fields'][0]['max_characters'] = $option->getMaxCharacters();
                    break;
                case Mage_Catalog_Model_Product_Option::OPTION_GROUP_FILE:
                    $result['additional_fields'][0]['file_extension'] = $option->getFileExtension();
                    $result['additional_fields'][0]['image_size_x'] = $option->getImageSizeX();
                    $result['additional_fields'][0]['image_size_y'] = $option->getImageSizeY();
                    break;
                case Mage_Catalog_Model_Product_Option::OPTION_GROUP_SELECT:
                    $result['additional_fields'] = array();
                    foreach ($option->getValuesCollection() as $value) {
                        $result['additional_fields'][] = array(
                            'value_id' => $value->getId(),
                            'title' => $value->getTitle(),
                            'price' => $value->getPrice(),
                            'price_type' => $value->getPriceType(),
                            'sku' => $value->getSku(),
                            'sort_order' => $value->getSortOrder()
                        );
                    }
                    break;
                default:
                    break;
            }

            $data[] = $result;
        }

        return $data;
    }

    /**
     * Add custom option to product
     *
     * @param Mage_Catalog_Model_Product $product
     * @param array $data
     * @param int|string|null $store
     * @return bool $isAdded
     */
    protected function _addOption($product, $data, $store = null)
    {
        if (!(is_array($data['additional_fields']) and count($data['additional_fields']))) {
            $this->_throwException('Invalid custom options data', 'invalid_options_data');
        }
        if (!$this->_isTypeAllowed($data['type'])) {
            $this->_throwException('Invalid custom options type', 'invalid_options_type');
        }
        $this->_prepareAdditionalFields(
            $data,
            $product->getOptionInstance()->getGroupByType($data['type'])
        );
        $this->_addProductCustomOption($product, $data);

        return true;
    }

    /**
     * @return Mage_Catalog_Model_Product_Option_Api
     */
    protected function _getOptionApi()
    {
        return Mage::getModel('catalog/product_option_api');
    }

    /**
     * Add product custom option data.
     *
     * @param Mage_Catalog_Model_Product $product
     * @param array $data
     * @return void
     */
    protected function _addProductCustomOption($product, $data)
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = Mage::helper('catalog')->stripTags($value);
            }
        }

        if (!$product->getOptionsReadonly()) {
            $product
                ->getOptionInstance()
                ->addOption($data);
        }
    }

    /**
     * Prepare custom option data for saving by model.
     *
     * @param array $data
     * @param string $groupType
     * @return void
     */
    protected function _prepareAdditionalFields(&$data, $groupType)
    {
        if (is_array($data['additional_fields'])) {
            if ($groupType != Mage_Catalog_Model_Product_Option::OPTION_GROUP_SELECT) {
                // reset can be used as there should be the only
                // element in 'additional_fields' for options of all types except those from Select group
                $field = reset($data['additional_fields']);
                if (!(is_array($field) and count($field))) {
                    $this->_throwException('Invalid custom options data', 'invalid_options_data');
                } else {
                    foreach ($field as $key => $value) {
                        $data[$key] = $value;
                    }
                }
            } else {
                // convert Select rows array to appropriate format for saving in the model
                foreach ($data['additional_fields'] as $row) {
                    if (!(is_array($row) and count($row))) {
                        $this->_throwException('Invalid custom options data', 'invalid_options_data');
                    } else {
                        foreach ($row as $key => $value) {
                            $row[$key] = Mage::helper('catalog')->stripTags($value);
                        }
                        if (!empty($row['value_id'])) {
                            // map 'value_id' to 'option_type_id'
                            $row['option_type_id'] = $row['value_id'];
                            unset($row['value_id']);
                            $data['values'][$row['option_type_id']] = $row;
                        } else {
                            $data['values'][] = $row;
                        }
                    }
                }
            }
        }
        unset($data['additional_fields']);
    }

    /**
     * Get allowed option types
     *
     * @return array
     */
    protected function _getOptionTypes()
    {
        $types = $this->_getOptionApi()->types();
        $allowedTypes = array();
        foreach($types as $optionType){
            $allowedTypes[] = $optionType['value'];
        }

        return $allowedTypes;
    }

     /**
     * Check is type in allowed set
     *
     * @param string $type
     * @return bool
     */
    protected function _isTypeAllowed($type)
    {
        if (!in_array($type, $this->_optionTypes)) {
            return false;
        }

        return true;
    }
}
