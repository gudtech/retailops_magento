<?php
/**
{license_text}
 */

class RetailOps_Api_Model_Catalog_Push_Downloadable_Validator extends Mage_Downloadable_Model_Link_Api_Validator
{
    /**
     * Remove base64 file contents validation
     *
     * @param mixed $var
     */
    public function validateFileDetails(&$var)
    {
    }
}
