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

class RetailOps_Api_Model_Resource_Api extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_setResource('catalog');
    }

    /**
     * Get product link types
     *
     * @return array
     */
    public function getLinkTypes()
    {
        $select = $this->_getReadAdapter()->select()->from($this->getTable('catalog/product_link_type'));

        return $this->_getReadAdapter()->fetchPairs($select);
    }

    /**
     * Get products skus
     *
     * @param array $produtIds
     * @return array
     */
    public function getSkuByProductIds($produtIds)
    {
        if (!$produtIds) {
            return array();
        }
        $select = $this->_getReadAdapter()->select()->from($this->getTable('catalog/product'), array('sku'));
        $where = sprintf('entity_id IN (%s)', implode(',', $produtIds));
        $select->where($where);

        return $this->_getReadAdapter()->fetchCol($select);
    }

    /**
     * Get products ids
     *
     * @param array $productSkus
     * @return array
     */
    public function getIdsByProductSkus($productSkus = null)
    {
        $select = $this->_getReadAdapter()->select()->from($this->getTable('catalog/product'), array('sku', 'entity_id'));
        if ($productSkus) {
            $select->where("sku IN (?)", $productSkus);
        }

        return $this->_getReadAdapter()->fetchPairs($select);
    }

    /**
     * Gets Order Items For Orders with retailops_ready status
     *
     * @return Mage_Sales_Order_Item_Collection
     */
    public function getRetailopsNonretrievedOrderItems()
    {
        $collection = Mage::getModel('sales/order_item')->getCollection();

        $collection->getSelect()
            ->join(
                array('orders' => $this->getTable('sales/order')),
                'orders.entity_id = main_table.order_id',
                array('orders.retailops_status')
            )
            ->where('orders.retailops_status IN (?)', array( RetailOps_Api_Helper_Data::RETAILOPS_ORDER_HOLD, RetailOps_Api_Helper_Data::RETAILOPS_ORDER_READY ));

        return $collection;
    }

    /**
     * Get hash of SKUs with non-retrieved qtys
     *
     * @param $collection Mage_Sales_Model_Resource_Order_Item_Collection
     * @return array
     */
    public function getRetailopsNonretrievedQtys()
    {
        $result = array();

        $collection = $this->getRetailopsNonretrievedOrderItems();

        /* remove parent items */
        foreach ($collection as $item) {
            $collection->removeItemByKey($item->getParentItemId());
        }

        /* calculate total ordered quantity per item */
        foreach ($collection as $item) {
            if (isset($result[$item->getSku()])){
                $result[$item->getSku()] += $item->getQtyOrdered();
            } else {
                $result[$item->getSku()] = $item->getQtyOrdered();
            }
        }

        return $result;
    }

    /**
     * Get product's media gallery records
     *
     * @param $productId
     * @return array
     */
    public function getProductMedia($productId)
    {
        $select = $this->_getReadAdapter()->select()->from($this->getTable('catalog/product_attribute_media_gallery'))
            ->where('entity_id = ?', $productId);

        return $this->_getReadAdapter()->fetchAll($select);
    }

    /**
     * Update gallery table with media keys
     *
     * @param $data
     */
    public function updateMediaKeys($data)
    {
        $adapter = $this->_getWriteAdapter();
        $adapter->insertOnDuplicate($this->getTable('catalog/product_attribute_media_gallery'), $data);
    }

    /**
     * Subtract non-retreived qtys from stock data
     *
     * @param $nonretrievedQtys
     * @param $stockData
     * @return Varien_Object
     */
    public function subtractNonretrievedQtys($nonretrievedQtys, $stockData) {
        $stockObj = new Varien_Object($stockData);

        $result = array();
        $result['sku'] = $stockObj->getSku();

        $stockObj->setQty($stockObj->getQuantity()); // api update accepts qty not quantity parameter

        if (isset($nonretrievedQtys[$stockObj->getSku()])) {
            $qty = $stockObj->getQty() - $nonretrievedQtys[$stockObj->getSku()];
            $stockObj->setQty($qty);
        }

        return $stockObj;
    }
}
