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
