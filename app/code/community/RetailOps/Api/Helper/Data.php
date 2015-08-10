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

class RetailOps_Api_Helper_Data extends Mage_Api_Helper_Data
{
    const API_STATUS_SUCCESS = 'success';
    const API_STATUS_FAIL    = 'fail';

    const RETAILOPS_ORDER_PROCESSING    = 'retailops_processing';
    const RETAILOPS_ORDER_COMPLETE      = 'retailops_complete';
    const RETAILOPS_ORDER_READY         = 'retailops_ready';
    const RETAILOPS_ORDER_HOLD          = 'retailops_hold';

    const DEFAULT_LIMIT = 10;

    const XML_CONFIG_DEFAULT_GROUP      = 'catalog';

    public function getRetOpsStatuses()
    {
        return array(
            self::RETAILOPS_ORDER_PROCESSING    => 'Processing',
            self::RETAILOPS_ORDER_COMPLETE      => 'Complete',
            self::RETAILOPS_ORDER_READY         => 'Ready',
            self::RETAILOPS_ORDER_HOLD          => 'Hold'
        );
    }

    /**
     * Apply pager to collection
     *
     * @param $collection
     * @param $filters
     * @return array
     */
    public function applyPager($collection, $filters)
    {
        $start = 0;
        if (isset($filters['start'])) {
            $start = $filters['start'];
            unset($filters['start']);
        }
        $limit = self::DEFAULT_LIMIT;
        if (isset($filters['limit'])) {
            $limit = $filters['limit'];
            unset($filters['limit']);
        }

        $collection->getSelect()->limit($limit, $start);

        return $filters;
    }

    /**
     * @param array $array
     * @param $keyField
     * @param $valueField
     * @return array
     */
    public function arrayToOptionHash(array $array, $keyField, $valueField)
    {
        $result = array();
        foreach ($array as $item) {
            /** @var $item Varien_Object */
            $result[$item[$keyField]] = $item[$valueField];
        }

        return $result;
    }

    /**
     * Get config value
     *
     * @param $path
     * @return mixed
     */
    public function getConfig($path)
    {
        if (strpos($path, '/') === false) {
            $path = self::XML_CONFIG_DEFAULT_GROUP . '/' . $path;
        }
        
        return Mage::getStoreConfig('retailops_settings/' . $path);
    }

    /**
     * Reindex stock and price data for products
     *
     * @param $idsToReindex
     * @param null $type
     */
    public function reindexProducts($idsToReindex, $type = null)
    {
         $indexerStock = Mage::getModel('cataloginventory/stock_status');
        foreach ($idsToReindex as $id) {
            $indexerStock->updateStatus($id, $type);
        }
        $indexerPrice = Mage::getResourceModel('catalog/product_indexer_price');
        $indexerPrice->reindexProductIds($idsToReindex);
    }

    /**
     * Remove objects from result array
     *
     * @param $data
     * @return array
     */
    public function removeObjectsFromResult($data)
    {
        foreach ($data as $key => $value) {
            if (is_object($value)) {
                unset($data[$key]);
            }
        }

        return $data;
    }
}
