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

abstract class RetailOps_Api_Model_Catalog_Adapter_Abstract
{
    protected $_section     = 'general';
    protected $_errorCodes  = array();
    /** @var RetailOps_Api_Model_Catalog_Api */
    protected $_api;

    public function __construct($api = null)
    {
        $this->_api = $api;
        $this->_construct();
    }

    /**
     * @return RetailOps_Api_Model_Resource_Api
     */
    protected function _getResource()
    {
        return Mage::getResourceModel('retailops_api/api');
    }

    /**
     * @return RetailOps_Api_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('retailops_api');
    }

    /**
     * @return $this
     */
    protected function _construct()
    {
        return $this;
    }

    /**
     * @param $message
     * @param string $code
     * @param null $sku
     * @throws RetailOps_Api_Model_Catalog_Exception
     */
    protected function _throwException($message, $code, $sku = null)
    {
        if (isset($this->_errorCodes[$code])) {
            $code = $this->_errorCodes[$code];
        }
        throw new RetailOps_Api_Model_Catalog_Exception($message, $code, $sku, $this->_section);
    }

    /**
     * Will be called before preparing
     *
     * @return $this
     */
    public function beforeDataPrepare()
    {
        return $this;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function prepareData(array &$data)
    {
        return $this;
    }

    /**
     * Will be called when all rows prepared
     *
     * @return $this
     */
    public function afterDataPrepare()
    {
        return $this;
    }

    /**
     * @return $this
     */
    public function beforeDataProcess()
    {
        return $this;
    }

    /**
     * @param array $productData
     * @param $product
     * @return mixed
     */
    abstract public function processData(array &$productData, $product);

    /**
     * @param array $skuToIdMap
     * @return $this
     */
    public function afterDataProcess(array &$skuToIdMap)
    {
        return $this;
    }

    /**
     * Prepare data for pull api
     *
     * @param $productCollection
     * @return $this
     */
    public function prepareOutputData($productCollection)
    {
        return $this;
    }

    /**
     * Output data for pull api
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function outputData($product)
    {
        return array();
    }
}
