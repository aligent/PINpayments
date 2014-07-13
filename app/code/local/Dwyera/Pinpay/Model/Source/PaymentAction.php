<?php
/**
 * Payment Action Dropdown source
 *
 * @category  Aligent
 * @package   Dwyera_Pinpay
 * @author    Andrew Dwyer <andrew@aligent.com.au>
 * @copyright 2014 Aligent Consulting.
 * @link      http://www.aligent.com.au/
 */

class Dwyera_Pinpay_Model_Source_PaymentAction
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE,
                'label' => Mage::helper('pinpay')->__('Authorize Only')
            ),
            array(
                'value' => Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE_CAPTURE,
                'label' => Mage::helper('pinpay')->__('Authorize and Capture')
            ),
        );
    }
}
