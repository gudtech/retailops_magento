<?php
/**
{license_text}
 */

class RetailOps_Api_Model_Catalog_Push_Api extends RetailOps_Api_Model_Catalog_Api
{
    /** @var  array */
    protected $_skuToIdMap;

    /** @var  Mage_Index_Model_Resource_Process_Collection */
    protected $_indexers;

    protected $_errors = array();

    public function __construct()
    {
        //$this->_indexers = Mage::getSingleton('index/indexer')->getProcessesCollection();
        parent::__construct();
    }

    /**
     * @return $this
     */
    public function beforeDataPrepare()
    {
        try {
            foreach ($this->_dataAdapters as $adapter) {
                /** @var $adapter RetailOps_Api_Model_Catalog_Adapter_Abstract */
                $adapter->beforeDataPrepare();
            }
        } catch (RetailOps_Api_Model_Catalog_Exception $e) {
            $this->_addError($e);
        }

        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function prepareData(array &$data)
    {
        $this->_validateData($data);
        foreach ($this->_dataAdapters as $adapter) {
            /** @var $adapter RetailOps_Api_Model_Catalog_Adapter_Abstract */
            $adapter->prepareData($data);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function afterDataPrepare()
    {
        try {
            foreach ($this->_dataAdapters as $adapter) {
                /** @var $adapter RetailOps_Api_Model_Catalog_Adapter_Abstract */
                $adapter->afterDataPrepare();
            }
        } catch (RetailOps_Api_Model_Catalog_Exception $e) {
            $this->_addError($e);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function beforeDataProcess()
    {
        try {
            foreach ($this->_dataAdapters as $adapter) {
                /** @var $adapter RetailOps_Api_Model_Catalog_Adapter_Abstract */
                $adapter->beforeDataProcess();
            }
        } catch (RetailOps_Api_Model_Catalog_Exception $e) {
            $this->_addError($e);
        }
        $this->_skuToIdMap = $this->_getResource()->getIdsByProductSkus();

        return $this;
    }

    /**
     * @param array $data
     * @return bool
     * @throws Exception
     */
    public function processData(array &$data)
    {
        $product = Mage::getModel('catalog/product');
        if (isset($this->_skuToIdMap[$data['sku']])) {
            $data['product_id'] = $this->_skuToIdMap[$data['sku']];
            $product->load($data['product_id']);
        }

        foreach ($this->_dataAdapters as $adapter) {
            /** @var $adapter RetailOps_Api_Model_Catalog_Adapter_Abstract */
            $adapter->processData($data, $product);
        }
        $product->clearInstance();
        $this->_skuToIdMap[$data['sku']] = $data['product_id'];

        return true;
    }

    /**
     * @return $this
     */
    public function afterDataProcess()
    {
        foreach ($this->_dataAdapters as $adapter) {
            try {
                /** @var $adapter RetailOps_Api_Model_Catalog_Adapter_Abstract */
                $adapter->afterDataProcess($this->_skuToIdMap);
            } catch (RetailOps_Api_Model_Catalog_Exception $e) {
                $this->_addError($e);
            }
        }

        return $this;
    }

    /**
     * Create products
     *
     * @param $productsData
     * @return array
     */
    public function catalogPush($productsData)
    {
        if (isset($productsData['records'])) {
            $productsData = $productsData['records'];
        }
        $result = array();
        $processedSkus = array();
        try {
            //$this->_stopReindex();
            $this->beforeDataPrepare();
            foreach ($productsData as $key => $data) {
                try {
                    $dataObj = new Varien_Object($data);
                    Mage::dispatchEvent('retailops_catalog_push_data_prepare_before', array('data' => $dataObj));
                    $data = $dataObj->getData();
                    $processedSkus[] = $data['sku'];
                    $this->prepareData($data);
                } catch (RetailOps_Api_Model_Catalog_Exception $e) {
                    unset($productsData[$key]);
                    $this->_addError($e);
                }
            }
            $this->afterDataPrepare();

            $this->beforeDataProcess();
            foreach ($productsData as $data) {
                try {
                    $dataObj = new Varien_Object($data);
                    Mage::dispatchEvent('retailops_catalog_push_data_process_before', array('data' => $dataObj));
                    $data = $dataObj->getData();
                    $this->processData($data);
                } catch (RetailOps_Api_Model_Catalog_Exception $e) {
                    $this->_addError($e);
                }
            }
            $this->afterDataProcess();
            //$this->_startReindex();
        } catch (Exception $e) {
            $this->_addError(new RetailOps_Api_Model_Catalog_Exception($e->getMessage()));
        }
        foreach ($processedSkus as $sku) {
            $r['sku'] = $sku;
            $r['status'] = 'success';
            if (!empty($this->_errors[$sku])) {
                $r['status'] = 'fail';
                $r['errors'] = $this->_errors[$sku];
            }
            $result['records'][] = $r;
        }
        if (isset($this->_errors['global'])) {
            $result['global_errors'] = $this->_errors['global'];
        }

        return $result;
    }

    /**
     * @param array $data
     * @throws RetailOps_Api_Model_Catalog_Exception
     */
    protected function _validateData(array &$data)
    {
        if (empty($data['sku'])) {
           throw new RetailOps_Api_Model_Catalog_Exception('Sku is missing', 100, null, 'general');
        }
    }

    /**
     * Add error message for api results
     *
     * @param RetailOps_Api_Model_Catalog_Exception $e
     */
    protected function _addError(RetailOps_Api_Model_Catalog_Exception $e)
    {
        if ($e->getSku()) {
            $errorKey = $e->getSku();
        } else {
            $errorKey = 'global';
        }
        if (!isset($this->_errors[$errorKey])) {
            $this->_errors[$errorKey] = array();
        }
        $this->_errors[$errorKey][] = array(
            'message'   => $e->getMessage(),
            'code'      => $e->getCode(),
            'section'   => $e->getSection(),
        );
    }

//    protected function _stopReindex()
//    {
//        $this->_indexers->walk('setMode', array(Mage_Index_Model_Process::MODE_MANUAL));
//        $this->_indexers->walk('save');
//    }
//
//    protected function _startReindex()
//    {
//        $this->_indexers->walk('reindexAll');
//        $this->_indexers->walk('setMode', array(Mage_Index_Model_Process::MODE_REAL_TIME));
//        $this->_indexers->walk('save');
//    }
}
