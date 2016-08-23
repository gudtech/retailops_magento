<?php

/**
 * Customer model extensions.
 *
 * Used to enhance available registration e-mail scenarios.
 * 
 * PHP Version 5
 * 
 * @category  Class
 * @package   Groove_GuestStoreCredit
 * @author    Groove Commerce
 * @copyright 2016 Groove Commerce, LLC. All Rights Reserved.
 */

/**
 * Class declaration
 *
 * @category Class_Type_Block
 * @package  Groove_GuestStoreCredit
 * @author   Groove Commerce
 */

class Groove_GuestStoreCredit_Model_Customer
    extends DollsKill_Customer_Model_Customer
    //extends Mage_Customer_Model_Customer
{

    const XML_PATH_FROM_STORE_CREDIT_EMAIL_TEMPLATE = 'customer/create_account/from_store_credit_template';
    const XML_PATH_FROM_STORE_CREDIT_CUSTOMER_EMAIL_TEMPLATE = 'customer/create_account/from_store_credit_customer_template';

    /**
     * Send email with new account related information.
     *
     * @param string $type    The type of e-mail to send.
     * @param string $backUrl An option return URL to include with the e-mail.
     * @param string $storeId An optional store ID to include.
     * @param array  $data    Optional additional data to pass to the template.
     * 
     * @throws Mage_Core_Exception
     * 
     * @return Mage_Customer_Model_Customer
     */
    public function sendNewAccountEmail($type = 'registered', $backUrl = '', $storeId = '0', array $data = array())
    {
        if (!Mage::getStoreConfig('customer/create_account/send_welcome_email') && $type == 'registered') {
            return $this;
        }

        $types = array(
            'from_store_credit' => self::XML_PATH_FROM_STORE_CREDIT_EMAIL_TEMPLATE,
            'from_store_credit_customer' => self::XML_PATH_FROM_STORE_CREDIT_CUSTOMER_EMAIL_TEMPLATE,
            'registered'        => Mage_Customer_Model_Customer::XML_PATH_REGISTER_EMAIL_TEMPLATE,
            'confirmed'         => Mage_Customer_Model_Customer::XML_PATH_CONFIRMED_EMAIL_TEMPLATE,
            'confirmation'      => Mage_Customer_Model_Customer::XML_PATH_CONFIRM_EMAIL_TEMPLATE,
        );

        if (!isset($types[$type])) {
            Mage::throwException(Mage::helper('customer')->__('Wrong transactional account email type'));
        }

        if (!$storeId) {
            $storeId = $this->_getWebsiteStoreId($this->getSendemailStoreId());
        }

        $this->_sendEmailTemplate(
            $types[$type],
            self::XML_PATH_REGISTER_EMAIL_IDENTITY,
            array_merge(array('customer' => $this, 'back_url' => $backUrl), $data),
            $storeId
        );

        return $this;
    }

}
