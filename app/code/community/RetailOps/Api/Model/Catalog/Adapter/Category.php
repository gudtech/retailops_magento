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

class RetailOps_Api_Model_Catalog_Adapter_Category extends RetailOps_Api_Model_Catalog_Adapter_Abstract
{
    protected $_categories;
    /**
     * Array of already processed category indexes to avoid double save
     * @var array
     */
    protected $_wereProcessed = array();

    protected $_section = 'category';

    protected function _construct()
    {
        $this->_initCategories();
        $this->_errorCodes = array(
            'cant_save_category' => 201,
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
        $assignedCategories = array();
        $categoryApi = $this->_getCategoryApi();
        if (isset($productData['categories'])) {
            foreach ($productData['categories'] as $categoriesData) {
                $categoryPath = array();
                foreach ($categoriesData as $categoryData) {
                    try {
                        $data = new Varien_Object($categoryData);
                        $parentCategoryPath = $categoryPath;
                        $categoryPath[] = $categoryData['name'];
                        $index = $this->_getCategoryPathIndex($categoryPath);
                        if (!in_array($index, $this->_wereProcessed)) {
                            if (!isset($this->_categories[$index])) {
                                $parentIndex = $this->_getCategoryPathIndex($parentCategoryPath);
                                $parentId = isset($this->_categories[$parentIndex]) ? $this->_categories[$parentIndex] : 1;

                                Mage::dispatchEvent('retailops_catalog_category_create_before',
                                    array('category_data' => $data));

                                $categoryId = $categoryApi->create($parentId, $data->getData());
                                $this->_categories[$index] = $categoryId;

                                Mage::dispatchEvent('retailops_catalog_category_create_after',
                                    array('category_id' => $categoryId, 'category_data' => $data));

                            } else {
                                $categoryId = $this->_categories[$index];

                                Mage::dispatchEvent('retailops_catalog_category_update_before',
                                    array('category_id' => $categoryId, 'category_data' => $data));

                                $categoryApi->update($categoryId, $data->getData());

                                Mage::dispatchEvent('retailops_catalog_category_update_after',
                                    array('category_id' => $categoryId, 'category_data' => $data));
                            }
                            $this->_wereProcessed[] = $index;
                        }
                        if (!empty($categoryData['link'])) {
                            $categoryId = $this->_categories[$index];
                            $assignedCategories[] = $categoryId;
                        }
                    } catch (Mage_Api_Exception $e) {
                        $this->_throwException(
                            sprintf('Error while saving category %s, message: %s', $categoryData['name'], $e->getCustomMessage()),
                            'cant_save_category'
                        );
                    }
                }
            }
            if (empty($productData['unset_other_categories']) || !$productData['unset_other_categories']) {
                $assignedCategories = array_merge($assignedCategories, $product->getCategoryIds());
            }
            $productData['category_ids'] = $assignedCategories;
        }
    }

    /**
     * @param Mage_Catalog_Model_Resource_Product_Collection $productCollection
     * @return $this
     */
    public function prepareOutputData($productCollection)
    {
        $usedCategoryIds = array();
        /** @var $product Mage_Catalog_Model_Product */
        foreach ($productCollection as $product) {
            $usedCategoryIds = array_merge($usedCategoryIds, $product->getCategoryIds());
        }
        $usedCategoryIds = array_unique($usedCategoryIds);
        /** @var $collection Mage_Catalog_Model_Resource_Category_Collection */
        $collection = Mage::getModel('catalog/category')->getCollection();
        $collection->addAttributeToSelect('path');
        $collection->addIdFilter($usedCategoryIds);
        $categoryToLoad = array();
        /** @var $category Mage_Catalog_Model_Category */
        foreach ($collection as $category) {
            $categoryToLoad = array_merge($categoryToLoad, $category->getPathIds());
        }
        $categoryToLoad = array_unique($categoryToLoad);
        /** @var $fullCollection Mage_Catalog_Model_Resource_Category_Collection */
        $fullCollection = Mage::getModel('catalog/category')->getCollection();
        $fullCollection->addAttributeToSelect('*');
        $fullCollection->addIdFilter($categoryToLoad);
        foreach ($fullCollection as $category) {
            $this->_categories[$category->getId()] = $category;
        }

        return $this;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function outputData($product)
    {
        $data = array();
        $categoryIds = $product->getCategoryIds();
        $categoryPaths = array();
        foreach ($categoryIds as $categoryId) {
            $categoryPath = $this->_getCategoryData($categoryId);
            $categoryPaths[]['path'] = $categoryPath;
        }

        $data['categories'] = $categoryPaths;

        return $data;
    }

    /**
     * Get category data
     *
     * @param $categoryId
     * @return array
     */
    protected function _getCategoryData($categoryId)
    {
        $data = array();
        if (isset($this->_categories[$categoryId])) {
            $category = $this->_categories[$categoryId];
            $path = array_slice($category->getPathIds(), 1);
            foreach ($path as $catId) {
                if (isset($this->_categories[$catId])) {
                    $parentCategory = $this->_categories[$catId];
                    $data[] = $parentCategory->getData();
                }
            }
        }

        return $data;
    }

    /**
     * @return $this
     */
    protected function _initCategories()
    {
        $collection = Mage::getResourceModel('catalog/category_collection')->addNameToResult();
        /* @var $collection Mage_Catalog_Model_Resource_Eav_Mysql4_Category_Collection */
        foreach ($collection as $category) {
            $structure = explode('/', $category->getPath());
            $pathSize  = count($structure);
            if ($pathSize > 1) {
                $path = array();
                for ($i = 1; $i < $pathSize; $i++) {
                    $path[] = $collection->getItemById($structure[$i])->getName();
                }
                $index = $this->_getCategoryPathIndex($path);
                $this->_categories[$index] = $category->getId();
            }
        }

        return $this;
    }

    /**
     * @return Mage_Catalog_Model_Category_Api
     */
    protected function _getCategoryApi()
    {
        return Mage::getModel('catalog/category_api');
    }

    /**
     * @param $path
     * @return string
     */
    protected function _getCategoryPathIndex($path)
    {
        return implode('/', $path);
    }
}
