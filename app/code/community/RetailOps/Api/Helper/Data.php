<?php
/**
{license_text}
 */

class RetailOps_Api_Helper_Data extends Mage_Api_Helper_Data
{
    /**
     * @param $items
     * @return Varien_Data_Collection
     */
    public function getVarienDataCollection($items) {
        $collection = new Varien_Data_Collection();
        foreach ($items as $item) {
            $varienObject = new Varien_Object();
            $varienObject->setData($item);
            $collection->addItem($varienObject);
        }
        return $collection;
    }
    const DEFAULT_LIMIT = 10;

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
}
