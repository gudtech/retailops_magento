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

class RetailOps_Api_Model_Catalog_Api extends Mage_Catalog_Model_Product_Api
{
    /** @var  array */
    protected $_dataAdapters;

    /**
    * Constructor. Initializes default values.
    */
    public function __construct()
    {
        $this->_dataAdapters = array(
            'attributes'   => new RetailOps_Api_Model_Catalog_Adapter_Attribute($this),
            'category'     => new RetailOps_Api_Model_Catalog_Adapter_Category($this),
            'media'        => new RetailOps_Api_Model_Catalog_Adapter_Media($this),
            'option'       => new RetailOps_Api_Model_Catalog_Adapter_Option($this),
            'configurable' => new RetailOps_Api_Model_Catalog_Adapter_Configurable($this),
            'bundle'       => new RetailOps_Api_Model_Catalog_Adapter_Bundle($this),
            'downloadable' => new RetailOps_Api_Model_Catalog_Adapter_Downloadable($this),
            'tag'          => new RetailOps_Api_Model_Catalog_Adapter_Tag($this),
            'default'      => new RetailOps_Api_Model_Catalog_Adapter_Default($this),
            'link'         => new RetailOps_Api_Model_Catalog_Adapter_Link($this),
        );
        Mage::dispatchEvent('retailops_catalog_adapter_init_after', array('api' => $this));
    }

    /**
     * @param $code
     * @return bool
     */
    public function getAdapter($code)
    {
        if (isset($this->_dataAdapters[$code])) {
            return $this->_dataAdapters[$code];
        }

        return false;
    }

    /**
     * @param $code
     * @param $adapter
     */
    public function addAdapter($code, $adapter)
    {
        if (!($adapter instanceof RetailOps_Api_Model_Catalog_Adapter_Abstract)) {
             $this->_fault('wrong_data_adapter', 'Wrong data adapter class');
        }
        $this->_dataAdapters[$code] = $adapter;
    }

    /**
     * @return RetailOps_Api_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('retailops_api');
    }

    /**
     * @return RetailOps_Api_Model_Resource_Api
     */
    protected function _getResource()
    {
        return Mage::getResourceModel('retailops_api/api');
    }
}
