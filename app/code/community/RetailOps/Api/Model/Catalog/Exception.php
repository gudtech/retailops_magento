<?php
/**
{license_text}
 */

class RetailOps_Api_Model_Catalog_Exception
    extends RetailOps_Api_Exception
{
    /**
     * API section with exception
     */
    protected $_section = null;

    /**
     * sku of product caused the excpetion
     */
    protected $_sku     = null;

    public function __construct($message, $code = 0, $sku = null, $section = null)
    {
        parent::__construct($message, $code);
        $this->_section = $section;
        $this->_sku     = $sku;
    }

    /**
     * Area of code with exception
     *
     * @return string
     */
    public function getSku()
    {
        return $this->_sku;
    }

    /**
     * Area of code with exception
     *
     * @return string
     */
    public function getSection()
    {
        return $this->_section;
    }
}
