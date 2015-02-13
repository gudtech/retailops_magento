<?php
/**
{license_text}
 */

class RetailOps_Api_Helper_Data extends Mage_Core_Helper_Abstract
{
    const RETAILOPS_ORDER_PROCESSING = 'retailops_processing';
    const RETAILOPS_ORDER_COMPLETE = 'retailops_complete';
    const RETAILOPS_ORDER_READY = 'retailops_ready';

    public function getRetOpsStatuses()
    {
        return array(
            self::RETAILOPS_ORDER_PROCESSING => 'Processing',
            self::RETAILOPS_ORDER_COMPLETE => 'Complete',
            self::RETAILOPS_ORDER_CANCELED => 'Ready'
        );
    }

}
