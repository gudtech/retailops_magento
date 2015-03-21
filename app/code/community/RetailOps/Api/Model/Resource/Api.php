<?php
/**
{license_text}
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
            $where = sprintf("sku IN ('%s')", implode("','", $productSkus));
            $select->where($where);
        }

        return $this->_getReadAdapter()->fetchPairs($select);
    }

    /**
     * Gets Order Items For Orders with retailops_ready status
     *
     * @return Mage_Sales_Order_Item_Collection
     */
    public function getRetailopsReadyOrderItems()
    {
        $collection = Mage::getModel('sales/order_item')->getCollection();

        $collection->getSelect()
            ->join(
                array('orders' => $this->getTable('sales/order')),
                'orders.entity_id = main_table.order_id',
                array('orders.retailops_status')
            )
            ->where('orders.retailops_status = (?)', RetailOps_Api_Helper_Data::RETAILOPS_ORDER_READY);

        return $collection;
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
}
