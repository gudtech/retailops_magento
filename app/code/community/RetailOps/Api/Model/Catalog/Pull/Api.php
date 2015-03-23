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

class RetailOps_Api_Model_Catalog_Pull_Api extends RetailOps_Api_Model_Catalog_Api
{
    /**
     * Get Products
     *
     * @param  mixed $filters
     * @return array
     */
    public function catalogPull($filters = null)
    {
        /** @var $collection Mage_Catalog_Model_Resource_Product_Collection */
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addStoreFilter($this->_getStoreId())
            ->addAttributeToSelect('*');

        $apiHelper = $this->getHelper();
        $filters = $apiHelper->applyPager($collection, $filters);
        $filters = $apiHelper->parseFilters($filters, $this->_filtersMap);
        try {
            foreach ($filters as $field => $value) {
                $collection->addFieldToFilter($field, $value);
            }
        } catch (Mage_Core_Exception $e) {
            $this->_fault('filters_invalid', $e->getMessage());
        }

        $result = array();
        try {

            Mage::dispatchEvent(
                'retailops_catalog_pull_collection_prepare',
                array('collection' => $collection)
            );

            $result['totalCount'] = $collection->getSize();

            foreach ($this->_dataAdapters as $adapter) {
                /** @var $adapter RetailOps_Api_Model_Catalog_Adapter_Abstract */
                $adapter->prepareOutputData($collection);
            }


            $result['count'] = count($collection);
            $records = array();
            /** @var $product Mage_Catalog_Model_Product */
            foreach ($collection as $product) {
                $record = array(
                    'product_id'   => $product->getId(),
                    'type'         => $product->getTypeId(),
                );
                foreach ($this->_dataAdapters as $adapter) {
                    /** @var $adapter RetailOps_Api_Model_Catalog_Adapter_Abstract */
                    $record = array_merge($record, $adapter->outputData($product));
                }

                $recordObj = new Varien_Object($record);

                Mage::dispatchEvent(
                    'retailops_catalog_pull_record',
                    array('record' => $recordObj)
                );

                $records[] = $recordObj->getData();
            }

            $result['records'] = $records;
        } catch (Mage_Core_Exception $e) {
            $this->_fault('catalog_pull_error', $e->getMessage());
        }

        return $result;
    }
}
