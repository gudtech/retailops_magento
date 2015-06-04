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

class RetailOps_Api_Model_Catalog_Adapter_Bundle extends RetailOps_Api_Model_Catalog_Adapter_Abstract
{
    protected $_section             = 'bundle';

    protected $_bundleOptions = array();

    protected function _construct()
    {
        $this->_errorCodes = array(
            'cant_save_bundle_data' => '801'
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

        if (isset($productData['bundle_options'])) {
            $this->_bundleOptions[$sku] = $productData['bundle_options'];
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
        if (isset($this->_bundleOptions)) {

            foreach ($this->_bundleOptions as $sku => $bundleOptions) {
                try {
                    $productId = $skuToIdMap[$sku];
                    /** @var Mage_Catalog_Model_Product $bundle */
                    $bundle = Mage::getModel('catalog/product')->load($productId);
                    $options = array();
                    $selections = array();
                    foreach ($bundleOptions as $optionKey => $bundleOption) {
                        $option = array(
                            'type'     => $bundleOption['type'],
                            'title'    => $bundleOption['title'],
                            'required' => isset($bundleOption['required']) ? $bundleOption['required'] : 0,
                            'position' => isset($bundleOption['position']) ? $bundleOption['position'] : 0,
                            'delete'   => 0,
                        );
                        $options[] = $option;
                        if (!empty($bundleOption['bundle_selections'])) {
                            $selections[$optionKey] = array();
                            foreach ($bundleOption['bundle_selections'] as $selectionData) {
                                if (isset($skuToIdMap[$selectionData['sku']])) {
                                    $productId = $skuToIdMap[$selectionData['sku']];
                                    $priceType = 0;
                                    $price = isset($selectionData['price']) ? $selectionData['price'] : 0;
                                    if (false !== strpos($price, '%')) {
                                        $priceType = 1;
                                    }
                                    $selection = array(
                                        'product_id'               => $productId,
                                        'delete'                   => 0,
                                        'selection_price_value'    => $price,
                                        'selection_price_type'     => $priceType,
                                        'selection_qty'            => isset($selectionData['default_qty']) ? $selectionData['default_qty'] : 1,
                                        'selection_can_change_qty' => isset($selectionData['can_change_qty']) ? $selectionData['can_change_qty'] : 1,
                                        'is_default'               => isset($selectionData['is_default']) ? $selectionData['is_default'] : 0,
                                        'position'                 => isset($selectionData['position']) ? $selectionData['position'] : 0,
                                    );
                                    $selections[$optionKey][] = $selection;
                                }
                            }

                        }
                    }
                    Mage::register('product', $bundle); //required by selection object
                    $currentOptions = $bundle->getTypeInstance()->getOptionsCollection();
                    foreach ($currentOptions as $option) {
                        $option->delete();
                    }
                    $bundle->setBundleOptionsData($options);
                    $bundle->setBundleSelectionsData($selections);
                    $bundle->setCanSaveCustomOptions(true);
                    $bundle->setCanSaveBundleSelections(true);
                    $bundle->save();
                    $idsToReindex[] = $bundle->getId();
                    $bundle->clearInstance();
                    Mage::unregister('product');
                } catch (Exception $e) {
                    $failedSkus[] = $sku;
                }
            }
        }
        if ($idsToReindex) {
            $this->getHelper()->reindexProducts(array_unique($idsToReindex), 'bundle');
        }
        if ($failedSkus) {
            $this->_throwException('Bundle data is not saved for ' . implode(',', array_unique($failedSkus)),
                'cant_save_bundle_data');
        }
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function outputData($product)
    {
        $data = array();

        if ($product->getTypeId() !== 'bundle') {
            return array();
        }
        $options = $product->getTypeInstance()->getOptionsCollection();
        $selectionsCollection = $product->getTypeInstance()->getSelectionsCollection($options->getAllIds());
        $options->appendSelections($selectionsCollection, false, true);
        $optionsData = array();
        /** @var $option Mage_Bundle_Model_Option */
        foreach ($options as $option) {
            $selectionsData = array();
            $optionSelections = $option->getSelections();
            /** @var $selection Mage_Bundle_Model_Selection */
            foreach ($optionSelections as $selection) {
                $selectionsData[] = array(
                    'sku'                      => $selection->getSku(),
                    'price'                    => $selection->getSelectionPriceValue() . ($selection->getSelectionPriceType() ? '%' : ''),
                    'default_qty'              => $selection->getSelectionQty(),
                    'selection_can_change_qty' => $selection->getSelectionCanChangeQty(),
                    'is_default'               => $selection->getIsDefault(),
                    'position'                 => $selection->getPosition(),
                );
            }
            $optionsData[] = array(
                'type'              => $option->getType(),
                'title'             => $option->getDefaultTitle(),
                'required'          => $option->getRequired(),
                'position'          => $option->getPosition(),
                'bundle_selections' => $selectionsData
            );
        }

        $data['bundle_options'] = $optionsData;

        return $data;
    }
}
