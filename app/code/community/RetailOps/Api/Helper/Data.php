<?php
/**
{license_text}
 */

class RetailOps_Api_Helper_Data extends Mage_Core_Helper_Abstract
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
}
